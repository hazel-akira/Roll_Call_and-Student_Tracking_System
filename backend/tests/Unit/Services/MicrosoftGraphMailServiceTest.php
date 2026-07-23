<?php

namespace Tests\Unit\Services;

use App\Services\MicrosoftGraphMailService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MicrosoftGraphMailServiceTest extends TestCase
{
    public function test_send_mail_uses_explicit_mail_from_override(): void
    {
        config([
            'services.microsoft_graph.client_id' => 'client-id',
            'services.microsoft_graph.client_secret' => 'client-secret',
            'services.microsoft_graph.tenant' => 'tenant-id',
            'services.microsoft_graph.mail_from' => 'global@example.test',
        ]);

        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'access_token' => 'token',
            ], 200),
            'https://graph.microsoft.com/v1.0/users/*' => Http::response([], 202),
        ]);

        app(MicrosoftGraphMailService::class)->sendMail(
            ['teacher@example.test'],
            'Subject',
            '<p>Body</p>',
            [],
            'school@example.test',
        );

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/users/'.rawurlencode('school@example.test').'/sendMail');
        });
    }

    public function test_is_configured_accepts_per_school_from_without_global(): void
    {
        config([
            'services.microsoft_graph.client_id' => 'client-id',
            'services.microsoft_graph.client_secret' => 'client-secret',
            'services.microsoft_graph.tenant' => 'tenant-id',
            'services.microsoft_graph.mail_from' => null,
        ]);

        $service = app(MicrosoftGraphMailService::class);

        $this->assertFalse($service->isConfigured());
        $this->assertTrue($service->isConfigured('school@example.test'));
    }
}
