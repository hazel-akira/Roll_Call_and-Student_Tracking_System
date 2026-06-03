<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesWithJwt;
use Tests\TestCase;

class AuthMeEndpointTest extends TestCase
{
    use AuthenticatesWithJwt;
    use RefreshDatabase;

    public function test_me_endpoint_requires_bearer_token(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response
            ->assertStatus(401)
            ->assertJsonPath('message', 'Missing bearer token.');
    }

    public function test_me_endpoint_returns_authenticated_user_profile(): void
    {
        $user = $this->createUserWithRole('teacher');

        $response = $this->getJson('/api/v1/auth/me', $this->authHeaders($user));

        $response
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role.slug', 'teacher');
    }
}
