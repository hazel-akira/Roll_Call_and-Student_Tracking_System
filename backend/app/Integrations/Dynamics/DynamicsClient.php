<?php

namespace App\Integrations\Dynamics;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class DynamicsClient
{
    public function pushAttendance(array $payload): array
    {
        $baseUrl = rtrim((string) config('services.dynamics.base_url'), '/');
        $endpoint = (string) config('services.dynamics.attendance_endpoint');

        if (! $baseUrl || ! config('services.dynamics.client_id') || ! config('services.dynamics.client_secret') || ! config('services.dynamics.scope') || ! config('services.dynamics.tenant_id')) {
            throw ValidationException::withMessages([
                'dynamics' => 'Dynamics integration credentials are incomplete.',
            ]);
        }

        $accessToken = $this->accessToken();

        return Http::timeout((int) config('services.dynamics.timeout', 15))
            ->withToken($accessToken)
            ->acceptJson()
            ->post($baseUrl.$endpoint, $payload)
            ->throw()
            ->json();
    }

    private function accessToken(): string
    {
        $tenantId = (string) config('services.dynamics.tenant_id');
        $tokenEndpoint = sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', $tenantId);

        $response = Http::asForm()
            ->timeout((int) config('services.dynamics.timeout', 15))
            ->post($tokenEndpoint, [
                'grant_type' => 'client_credentials',
                'client_id' => config('services.dynamics.client_id'),
                'client_secret' => config('services.dynamics.client_secret'),
                'scope' => config('services.dynamics.scope'),
            ])
            ->throw()
            ->json();

        return $response['access_token'] ?? throw ValidationException::withMessages([
            'dynamics' => 'Unable to obtain a Dynamics access token.',
        ]);
    }
}
