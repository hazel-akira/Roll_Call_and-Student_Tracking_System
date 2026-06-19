<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MicrosoftOAuthService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.microsoft.enabled')
            && filled(config('services.microsoft.client_id'))
            && filled(config('services.microsoft.client_secret'))
            && filled($this->tenantId());
    }

    public function tenantId(): string
    {
        $tenantId = config('services.microsoft.tenant_id');

        if (filled($tenantId)) {
            return (string) $tenantId;
        }

        $allowList = config('services.microsoft.tenant_allow_list', []);

        if (count($allowList) === 1) {
            return (string) $allowList[0];
        }

        throw new RuntimeException('Set MICROSOFT_TENANT_ID or a single MICROSOFT_ALLOWED_TENANT_IDS value for Microsoft SSO.');
    }

    public function redirectUri(): string
    {
        return (string) config('services.microsoft.redirect_uri');
    }

    public function authorizationUrl(string $panelId): string
    {
        $state = Str::random(40);
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        Cache::put($this->cacheKey($state), [
            'panel' => $panelId,
            'verifier' => $verifier,
        ], now()->addMinutes(10));

        $query = http_build_query([
            'client_id' => config('services.microsoft.client_id'),
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri(),
            'response_mode' => 'query',
            'scope' => 'openid profile email',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ]);

        return sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/authorize?%s',
            $this->tenantId(),
            $query,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeAuthorizationCode(string $code, string $state): array
    {
        if ($state === '') {
            throw new RuntimeException('The Microsoft sign-in response was missing state.');
        }

        $context = Cache::pull($this->cacheKey($state));

        if (! is_array($context) || ! filled($context['verifier'] ?? null)) {
            throw new RuntimeException('The Microsoft sign-in session expired. Try again.');
        }

        $clientSecret = config('services.microsoft.client_secret');

        if (! filled($clientSecret)) {
            throw new RuntimeException('MICROSOFT_CLIENT_SECRET is not configured. Create a client secret in Entra and add it to backend/.env.');
        }

        $payload = [
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => $clientSecret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'code_verifier' => $context['verifier'],
        ];

        $response = Http::asForm()
            ->timeout(15)
            ->post(sprintf(
                'https://login.microsoftonline.com/%s/oauth2/v2.0/token',
                $this->tenantId(),
            ), $payload)
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new RuntimeException('Microsoft did not return a valid token response.');
        }

        $response['panel'] = $context['panel'] ?? null;

        return $response;
    }

    private function cacheKey(string $state): string
    {
        return 'microsoft_oauth:'.$state;
    }
}
