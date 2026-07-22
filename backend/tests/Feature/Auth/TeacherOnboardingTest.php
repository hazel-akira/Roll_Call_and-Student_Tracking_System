<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\ResolveMicrosoftUser;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\Auth\MicrosoftTokenValidator;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SchoolAndClassSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesWithJwt;
use Tests\TestCase;

class TeacherOnboardingTest extends TestCase
{
    use AuthenticatesWithJwt;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('jwt.secret', config('app.key'));
    }

    public function test_auto_activated_teacher_without_schools_gets_onboarding_exchange_response(): void
    {
        $this->seed([RoleSeeder::class, SchoolAndClassSeeder::class]);

        config()->set('auth.sso.auto_activate', true);

        $user = app(ResolveMicrosoftUser::class)->execute([
            'sub' => 'ms-subject-onboarding',
            'email' => 'new.teacher@school.test',
            'name' => 'New Teacher',
            'tid' => 'tenant-1',
        ]);

        $this->assertSame('active', $user->status);
        $this->assertSame('teacher', $user->role?->slug);
        $this->assertFalse($user->schools()->exists());

        $this->mock(MicrosoftTokenValidator::class, function ($mock): void {
            $mock->shouldReceive('validate')
                ->once()
                ->andReturn([
                    'sub' => 'ms-subject-onboarding',
                    'email' => 'new.teacher@school.test',
                    'name' => 'New Teacher',
                    'tid' => 'tenant-1',
                ]);
        });

        $response = $this->postJson('/api/v1/auth/microsoft/exchange', [
            'id_token' => 'test-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 'school_selection_required')
            ->assertJsonPath('user.email', 'new.teacher@school.test')
            ->assertJsonStructure(['schools', 'tokens']);

        $this->assertNotEmpty($response->json('schools'));
    }

    public function test_teacher_can_assign_schools_during_onboarding(): void
    {
        $this->seed([RoleSeeder::class, SchoolAndClassSeeder::class]);

        $teacher = $this->createUserWithRole('teacher');
        $school = School::query()->where('code', 'PS')->firstOrFail();

        $response = $this->postJson('/api/v1/auth/onboarding/schools', [
            'school_ids' => [$school->id],
        ], $this->authHeaders($teacher));

        $response->assertOk()
            ->assertJsonPath('code', 'onboarding_complete')
            ->assertJsonPath('current_school_id', (string) $school->id)
            ->assertJsonStructure(['tokens', 'user']);

        $this->assertDatabaseHas('school_user', [
            'user_id' => $teacher->id,
            'school_id' => $school->id,
        ]);
    }

    public function test_dean_without_schools_still_requires_admin_assignment(): void
    {
        $this->seed(RoleSeeder::class);

        $dean = $this->createUserWithRole('dean_of_students');

        $response = $this->postJson('/api/v1/auth/onboarding/schools', [
            'school_ids' => [1],
        ], $this->authHeaders($dean));

        $response->assertStatus(403);
    }

    public function test_existing_admin_role_is_not_overwritten_on_microsoft_login(): void
    {
        $this->seed(RoleSeeder::class);

        config()->set('auth.sso.auto_activate', true);

        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();

        User::query()->create([
            'role_id' => $adminRole->id,
            'status' => 'active',
            'name' => 'Platform Admin',
            'email' => 'admin@school.test',
        ]);

        $user = app(ResolveMicrosoftUser::class)->execute([
            'sub' => 'ms-subject-admin',
            'email' => 'admin@school.test',
            'name' => 'Platform Admin',
            'tid' => 'tenant-1',
        ]);

        $this->assertSame('admin', $user->role?->slug);
        $this->assertSame('active', $user->status);
    }
}
