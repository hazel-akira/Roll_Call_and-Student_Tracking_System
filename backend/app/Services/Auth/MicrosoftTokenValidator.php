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
    public function validate(string $idToken, ?string $expectedNonce = null): array
    {
        try {
            $jwksUrl = trim((string) config('services.microsoft.jwks_url'));

            if ($jwksUrl === '') {
                throw new \RuntimeException('Microsoft JWKS URL is not configured.');
            }

            $jwks = Cache::remember('microsoft.jwks', now()->addHour(), function () use ($jwksUrl): array {
                return Http::timeout(10)
                    ->acceptJson()
                    ->get($jwksUrl)
                    ->throw()
                    ->json();
            });

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
                    'Unable to reach Microsoft to validate the identity token. Check backend outbound HTTPS access and MICROSOFT_JWKS_URL.',
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
            Cache::forget('microsoft.jwks');

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
