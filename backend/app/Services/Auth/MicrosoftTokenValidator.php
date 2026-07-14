<?php

namespace App\Services\Auth;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class MicrosoftTokenValidator
{
    private const JWKS_CACHE_KEY = 'microsoft.jwks';

    private const JWKS_SOURCE_CACHE_KEY = 'microsoft.jwks_source_url';

    public function validate(string $idToken, ?string $expectedNonce = null): array
    {
        try {
            $jwksUrl = trim((string) config('services.microsoft.jwks_url'));

            if ($jwksUrl === '') {
                throw new \RuntimeException('Microsoft JWKS URL is not configured.');
            }

            $jwks = Cache::remember(self::JWKS_CACHE_KEY, now()->addDay(), fn (): array => $this->fetchJwks($jwksUrl));

            JWT::$leeway = 60;

            $claims = (array) JWT::decode($idToken, JWK::parseKeySet($this->normalizeKeySet($jwks)));
        } catch (ConnectionException|RequestException $exception) {
            report($exception);
            Log::error('Microsoft JWKS fetch failed.', [
                'jwks_url' => config('services.microsoft.jwks_url'),
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'id_token' => $this->publicValidationMessage(
                    'Unable to reach Microsoft to validate the identity token. Check backend outbound HTTPS access, then run: php artisan microsoft:warm-jwks-cache',
                    $exception,
                ),
            ]);
        } catch (ExpiredException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'id_token' => $this->publicValidationMessage(
                    'The Microsoft identity token has expired. Sign in again.',
                    $exception,
                ),
            ]);
        } catch (SignatureInvalidException $exception) {
            report($exception);
            Cache::forget(self::JWKS_CACHE_KEY);
            Cache::forget(self::JWKS_SOURCE_CACHE_KEY);

            throw ValidationException::withMessages([
                'id_token' => $this->publicValidationMessage(
                    'The Microsoft identity token signature is invalid. Verify MICROSOFT_CLIENT_ID and MICROSOFT_JWKS_URL match your Entra app tenant.',
                    $exception,
                ),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            Log::error('Microsoft identity token validation failed.', [
                'jwks_url' => config('services.microsoft.jwks_url'),
                'client_id' => config('services.microsoft.client_id'),
                'class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'id_token' => $this->publicValidationMessage(
                    'Unable to validate the Microsoft identity token.',
                    $exception,
                ),
            ]);
        }

        $this->validateAudience($claims);
        $this->validateIssuer($claims);
        $this->validateTenant($claims);
        $this->validateDomain($claims);
        $this->validateNonce($claims, $expectedNonce);

        if (empty($claims['sub'])) {
            throw ValidationException::withMessages([
                'id_token' => 'The Microsoft identity token is missing the subject claim.',
            ]);
        }

        $email = strtolower((string) ($claims['preferred_username'] ?? $claims['email'] ?? $claims['upn'] ?? ''));

        if (! $email) {
            throw ValidationException::withMessages([
                'id_token' => 'The Microsoft identity token is missing a usable email address.',
            ]);
        }

        return [
            'sub' => $claims['sub'],
            'iss' => $claims['iss'] ?? null,
            'aud' => $claims['aud'] ?? null,
            'tid' => $claims['tid'] ?? null,
            'name' => $claims['name'] ?? $email,
            'preferred_username' => $claims['preferred_username'] ?? $email,
            'email' => $email,
            'roles' => Arr::wrap($claims['roles'] ?? []),
            'department' => $claims['department'] ?? null,
            'job_title' => $claims['jobTitle'] ?? null,
            'nonce' => $claims['nonce'] ?? null,
        ];
    }

    /**
     * @return array{url: string, key_count: int}
     */
    public function warmCache(): array
    {
        $jwksUrl = trim((string) config('services.microsoft.jwks_url'));

        if ($jwksUrl === '') {
            throw new \RuntimeException('Microsoft JWKS URL is not configured.');
        }

        Cache::forget(self::JWKS_CACHE_KEY);
        Cache::forget(self::JWKS_SOURCE_CACHE_KEY);

        $jwks = $this->fetchJwks($jwksUrl);

        return [
            'url' => (string) Cache::get(self::JWKS_SOURCE_CACHE_KEY, $jwksUrl),
            'key_count' => count(Arr::wrap($jwks['keys'] ?? [])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchJwks(string $primaryUrl): array
    {
        $urls = array_values(array_unique(array_filter([
            $primaryUrl,
            'https://login.microsoftonline.com/common/discovery/v2.0/keys',
        ])));

        $lastException = null;

        foreach ($urls as $url) {
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                try {
                    $response = Http::timeout(30)
                        ->acceptJson()
                        ->get($url)
                        ->throw();

                    /** @var array<string, mixed> $json */
                    $json = $response->json();

                    if (Arr::wrap($json['keys'] ?? []) === []) {
                        throw new \RuntimeException('Microsoft JWKS response did not include signing keys.');
                    }

                    Cache::put(self::JWKS_SOURCE_CACHE_KEY, $url, now()->addDay());

                    return $json;
                } catch (ConnectionException|RequestException|\RuntimeException $exception) {
                    $lastException = $exception;
                    Log::warning('Microsoft JWKS fetch attempt failed.', [
                        'url' => $url,
                        'attempt' => $attempt,
                        'message' => $exception->getMessage(),
                    ]);

                    if ($attempt < 3) {
                        usleep(500_000 * $attempt);
                    }
                }
            }
        }

        $stale = Cache::get(self::JWKS_CACHE_KEY);

        if (is_array($stale) && Arr::wrap($stale['keys'] ?? []) !== []) {
            Log::warning('Using cached Microsoft JWKS after live fetch failures.', [
                'source_url' => Cache::get(self::JWKS_SOURCE_CACHE_KEY),
            ]);

            return $stale;
        }

        throw $lastException ?? new ConnectionException('Unable to fetch Microsoft JWKS.');
    }

    private function validateAudience(array $claims): void
    {
        $expectedAudience = (string) config('services.microsoft.client_id');
        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];

        if ($expectedAudience === '' || ! in_array($expectedAudience, $audiences, true)) {
            throw ValidationException::withMessages([
                'id_token' => 'The Microsoft token audience is invalid for this application.',
            ]);
        }
    }

    private function publicValidationMessage(string $message, Throwable $exception): string
    {
        if (config('app.debug')) {
            return trim($message.' '.$exception->getMessage());
        }

        return $message;
    }

    private function normalizeKeySet(array $jwks): array
    {
        $keys = Arr::wrap($jwks['keys'] ?? []);

        return [
            'keys' => array_map(function ($key): array {
                $normalized = (array) $key;

                if (! array_key_exists('alg', $normalized)) {
                    $normalized['alg'] = 'RS256';
                }

                return $normalized;
            }, $keys),
        ];
    }

    private function validateIssuer(array $claims): void
    {
        $tenantId = $claims['tid'] ?? null;
        $issuer = (string) ($claims['iss'] ?? '');
        $expectedPrefix = rtrim((string) config('services.microsoft.issuer_prefix'), '/');

        $trustedIssuerPrefixes = array_values(array_filter([
            $tenantId ? "{$expectedPrefix}/{$tenantId}" : null,
            $tenantId ? "https://sts.windows.net/{$tenantId}" : null,
        ]));

        $issuerTrusted = $trustedIssuerPrefixes !== []
            && Arr::first($trustedIssuerPrefixes, fn (string $prefix): bool => str_starts_with($issuer, $prefix)) !== null;

        if (! $tenantId || ! $issuerTrusted) {
            throw ValidationException::withMessages([
                'id_token' => 'The Microsoft token issuer is not trusted.',
            ]);
        }
    }

    private function validateTenant(array $claims): void
    {
        $allowList = config('services.microsoft.tenant_allow_list', []);

        if ($allowList !== [] && ! in_array($claims['tid'] ?? null, $allowList, true)) {
            throw ValidationException::withMessages([
                'id_token' => 'This Microsoft tenant is not allowed to access the system.',
            ]);
        }
    }

    private function validateDomain(array $claims): void
    {
        $allowList = config('services.microsoft.domain_allow_list', []);
        $email = strtolower((string) ($claims['preferred_username'] ?? $claims['email'] ?? $claims['upn'] ?? ''));
        $domain = str_contains($email, '@') ? substr(strrchr($email, '@'), 1) : '';

        if ($allowList !== [] && ! in_array($domain, $allowList, true)) {
            throw ValidationException::withMessages([
                'id_token' => 'This email domain is not allowed to access the system.',
            ]);
        }
    }

    private function validateNonce(array $claims, ?string $expectedNonce): void
    {
        if (! $expectedNonce) {
            return;
        }

        if (($claims['nonce'] ?? null) !== $expectedNonce) {
            throw ValidationException::withMessages([
                'nonce' => 'The Microsoft token nonce did not match the expected value.',
            ]);
        }
    }
}
