<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MicrosoftOAuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.microsoft.enabled' => true,
            'services.microsoft.client_id' => 'test-client-id',
            'services.microsoft.client_secret' => 'test-client-secret',
            'services.microsoft.tenant_id' => 'test-tenant-id',
            'services.microsoft.redirect_uri' => 'http://localhost/auth/microsoft/callback',
            'services.microsoft.jwks_url' => 'https://login.microsoftonline.com/test-tenant-id/discovery/v2.0/keys',
            'services.microsoft.issuer_prefix' => 'https://login.microsoftonline.com',
            'services.microsoft.tenant_allow_list' => ['test-tenant-id'],
        ]);
    }

    public function test_microsoft_redirect_is_available_when_sso_is_enabled(): void
    {
        $response = $this->get('/auth/microsoft/redirect?panel=admin');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.microsoftonline.com/test-tenant-id/oauth2/v2.0/authorize',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_microsoft_redirect_returns_not_found_when_disabled(): void
    {
        config(['services.microsoft.enabled' => false]);

        $this->get('/auth/microsoft/redirect?panel=admin')->assertNotFound();
    }

    public function test_redirect_stores_oauth_state_in_cache(): void
    {
        $redirect = $this->get('/auth/microsoft/redirect?panel=admin');
        parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertArrayHasKey('state', $query);
        $this->assertTrue(Cache::has('microsoft_oauth:'.$query['state']));
    }

    public function test_microsoft_callback_rejects_unknown_state(): void
    {
        $response = $this->get('/auth/microsoft/callback?code=test-code&state=unknown-state');

        $response->assertRedirect();
        $response->assertSessionHas('microsoft_sso_error');
    }
}
