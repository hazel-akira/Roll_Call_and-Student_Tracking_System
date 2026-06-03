<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\Auth\JwtIssuer;
use Illuminate\Support\Str;

trait AuthenticatesWithJwt
{
    protected function createUserWithRole(string $roleSlug, array $overrides = []): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => ucfirst(str_replace('_', ' ', $roleSlug))]
        );

        return User::query()->create(array_merge([
            'role_id' => $role->id,
            'name' => ucfirst($roleSlug).' User',
            'email' => Str::uuid().'@example.test',
            'status' => 'active',
            'password' => 'password',
        ], $overrides));
    }

    protected function accessTokenFor(User $user): string
    {
        config()->set('jwt.secret', config('jwt.secret') ?: 'testing-secret');

        return app(JwtIssuer::class)->createTokenPair($user)['access_token'];
    }

    protected function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$this->accessTokenFor($user),
            'Accept' => 'application/json',
        ];
    }

    protected function createTeacherWithSchool(array $schoolOverrides = []): array
    {
        $school = School::query()->create(array_merge([
            'name' => 'Pioneer School',
            'code' => 'PIONEER-BOYS-TEST',
            'active' => true,
        ], $schoolOverrides));

        $teacher = $this->createUserWithRole('teacher');
        $teacher->schools()->attach($school->id);

        return [$teacher, $school];
    }
}
