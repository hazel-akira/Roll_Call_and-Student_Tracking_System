<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MicrosoftGraphMailService
{
    public function isConfigured(): bool
    {
        return filled(config('services.microsoft_graph.client_id'))
            && filled(config('services.microsoft_graph.client_secret'))
            && filled(config('services.microsoft_graph.tenant'))
            && filled(config('services.microsoft_graph.mail_from'));
    }

    /**
     * @param  list<string>  $toEmails
     * @param  list<array{path: string, name: string, mime: string}>  $attachments
     */
    public function sendMail(
        array $toEmails,
        string $subject,
        string $htmlBody,
        array $attachments = [],
    ): void {
        $toEmails = array_values(array_unique(array_filter($toEmails)));

        if ($toEmails === []) {
            return;
        }

        if (! $this->isConfigured()) {
            throw new \RuntimeException('Microsoft Graph mail is not configured.');
        }

        Log::info('GRAPH: Sending roll call report email', [
            'to' => $toEmails,
            'subject' => $subject,
            'attachment_count' => count($attachments),
        ]);

        $accessToken = $this->getAccessToken();
        $mailFrom = (string) config('services.microsoft_graph.mail_from');

        $message = [
            'message' => [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $htmlBody,
                ],
                'toRecipients' => array_map(
                    static fn (string $email): array => [
                        'emailAddress' => ['address' => $email],
                    ],
                    $toEmails,
                ),
                'attachments' => $this->buildAttachments($attachments),
            ],
            'saveToSentItems' => true,
        ];

        $response = Http::withToken($accessToken)
            ->post(
                'https://graph.microsoft.com/v1.0/users/'.rawurlencode($mailFrom).'/sendMail',
                $message,
            );

        if (! $response->successful()) {
            Log::error('GRAPH SEND FAILED', [
                'status' => $response->status(),
                'body' => $response->body(),
                'from' => $mailFrom,
            ]);

            $graphError = $response->json('error.message') ?? $response->body();

            throw new \RuntimeException(
                'Microsoft Graph sendMail failed: '.$graphError
                .'. Ensure the app has Microsoft Graph application permission Mail.Send with admin consent granted.'
            );
        }

        Log::info('GRAPH: Email sent successfully', ['to' => $toEmails]);
    }

    private function getAccessToken(): string
    {
        $tenantId = (string) config('services.microsoft_graph.tenant');

        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
            [
                'client_id' => config('services.microsoft_graph.client_id'),
                'client_secret' => config('services.microsoft_graph.client_secret'),
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]
        );

        if (! $response->successful()) {
            Log::error('GRAPH TOKEN FAILED', ['body' => $response->body()]);

            throw new \RuntimeException('Could not get Microsoft Graph token.');
        }

        $token = (string) $response->json('access_token');
        $roles = $this->decodeTokenRoles($token);

        if ($roles !== [] && ! in_array('Mail.Send', $roles, true)) {
            Log::warning('GRAPH: Token acquired but Mail.Send role is missing', ['roles' => $roles]);
        }

        if ($roles === []) {
            Log::warning(
                'GRAPH: Token has no application roles. Add Microsoft Graph Mail.Send (application) permission and grant admin consent in Azure Entra.'
            );
        }

        return $token;
    }

    /**
     * @return list<string>
     */
    private function decodeTokenRoles(string $token): array
    {
        try {
            $parts = explode('.', $token);
            $payload = $parts[1] ?? '';
            $payload .= str_repeat('=', (4 - (strlen($payload) % 4)) % 4);
            $decoded = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

            return is_array($decoded['roles'] ?? null) ? $decoded['roles'] : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  list<array{path: string, name: string, mime: string}>  $attachments
     * @return list<array<string, mixed>>
     */
    private function buildAttachments(array $attachments): array
    {
        $payload = [];

        foreach ($attachments as $attachment) {
            $path = $attachment['path'];
            $disk = Storage::disk(config('filesystems.default'));

            if (! $disk->exists($path)) {
                throw new \RuntimeException("Attachment not found: {$path}");
            }

            $payload[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $attachment['name'],
                'contentType' => $attachment['mime'],
                'contentBytes' => base64_encode($disk->get($path)),
            ];
        }

        return $payload;
    }
}
