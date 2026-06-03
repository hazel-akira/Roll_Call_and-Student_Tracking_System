<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http as HttpClient;


class MicrosoftGraphMailService
{
    private string $clientId;
    private string $clientSecret;
    private string $tenantId;
    private string $mailFrom;

    public function __construct()
    {
        $this->clientId     = config('services.microsoft_graph.client_id');
        $this->clientSecret = config('services.microsoft_graph.client_secret');
        $this->tenantId     = config('services.microsoft_graph.tenant');
        $this->mailFrom     = config('services.microsoft_graph.mail_from');
    }

    /**
     * Get access token from Azure
     */
    private function getAccessToken(): string
    {
        Log::info('GRAPH: Requesting access token');

        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
            [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]
        );

        if (!$response->successful()) {
            Log::error('GRAPH TOKEN FAILED', [
                'body' => $response->body()
            ]);
            throw new \Exception('Could not get Microsoft Graph token');
        }

        $token = $response->json()['access_token'];

        // Decode JWT payload to inspect granted roles/scopes (base64url)
        try {
            $parts = explode('.', $token);
            $payload = $parts[1] ?? '';
            $payload .= str_repeat('=', (4 - (strlen($payload) % 4)) % 4);
            $decoded = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        } catch (\Throwable $e) {
            $decoded = null;
        }

        Log::info('GRAPH: Token acquired', ['aud' => $decoded['aud'] ?? null, 'appid' => $decoded['appid'] ?? $decoded['azp'] ?? null]);
        Log::info('GRAPH TOKEN CLAIMS', ['roles' => $decoded['roles'] ?? null, 'scp' => $decoded['scp'] ?? null]);

        return $token;
    }

    /**
     * Send email via Microsoft Graph
     */
    public function sendMail(string $toEmail, string $subject, string $body): void
    {
        Log::info('GRAPH SERVICE CALLED', [
            'to' => $toEmail,
            'subject' => $subject,
        ]);

        try {
            $accessToken = $this->getAccessToken();

            $message = [
                "message" => [
                    "subject" => $subject,
                    "body" => [
                        "contentType" => "HTML",
                        "content" => $body
                    ],
                    "toRecipients" => [
                        [
                            "emailAddress" => [
                                "address" => $toEmail
                            ]
                        ]
                    ]
                ],
                "saveToSentItems" => true
            ];

            Log::info('GRAPH: Sending email now (HTTP)', [
                'from' => $this->mailFrom,
                'to' => $toEmail
            ]);

            $response = HttpClient::withToken($accessToken)
                ->post("https://graph.microsoft.com/v1.0/users/{$this->mailFrom}/sendMail", $message);

            if (!$response->successful()) {
                Log::error('GRAPH SEND FAILED', ['status' => $response->status(), 'body' => $response->body()]);
                throw new \Exception('Microsoft Graph sendMail failed');
            }

            Log::info('GRAPH: EMAIL SENT SUCCESSFULLY');

        } catch (\Throwable $e) {
            Log::error('GRAPH FAILED', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
