<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class MicrosoftTokenValidator
{
    public function validate(string $idToken, ?string $expectedNonce = null): array
    {
        try {
            $jwks = Cache::remember('microsoft.jwks', now()->addHour(), function (): array {
                return Http::timeout(10)
                    ->acceptJson()
                    ->get(config('services.microsoft.jwks_url'))
                    ->throw()
                    ->json();
            });

            $claims = (array) JWT::decode($idToken, JWK::parseKeySet($jwks));
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'id_token' => 'Unable to validate the Microsoft identity token.',
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
        if (($claims['aud'] ?? null) !== config('services.microsoft.client_id')) {
            throw ValidationException::withMessages([
                'id_token' => 'The Microsoft token audience is invalid for this application.',
            ]);
        }
    }

    private function validateIssuer(array $claims): void
    {
        $tenantId = $claims['tid'] ?? null;
        $issuer = (string) ($claims['iss'] ?? '');
        $expectedPrefix = rtrim((string) config('services.microsoft.issuer_prefix'), '/');

        if (! $tenantId || ! str_starts_with($issuer, $expectedPrefix.'/'.$tenantId)) {
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
