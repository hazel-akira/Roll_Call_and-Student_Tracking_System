<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\ResolveMicrosoftUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingUserExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_user_is_created_on_first_microsoft_resolution(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $user = app(ResolveMicrosoftUser::class)->execute([
            'sub' => 'ms-subject-123',
            'email' => 'new.teacher@school.test',
            'name' => 'New Teacher',
            'tid' => 'tenant-1',
        ]);

        $this->assertSame('pending', $user->status);
        $this->assertDatabaseHas('users', [
            'email' => 'new.teacher@school.test',
            'status' => 'pending',
        ]);
    }

    public function test_user_access_service_blocks_teacher_without_schools(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $teacherRole = Role::query()->where('slug', 'teacher')->firstOrFail();
        $user = User::query()->create([
            'role_id' => $teacherRole->id,
            'status' => 'active',
            'name' => 'Test Teacher',
            'email' => 'teacher@school.test',
        ]);

        $access = app(\App\Services\Auth\UserAccessService::class);

        $this->assertFalse($access->hasSchoolAccess($user));
        $this->assertTrue($access->canSignIn($user));
    }
}
