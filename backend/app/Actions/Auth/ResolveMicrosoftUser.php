<?php

namespace App\Actions\Auth;

use App\Models\Role;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Arr;

class ResolveMicrosoftUser
{
    public function execute(array $claims): User
    {
        $email = strtolower((string) Arr::get($claims, 'email'));
        $subject = (string) Arr::get($claims, 'sub');
        $tenantId = Arr::get($claims, 'tid');

        $identity = UserIdentity::query()
            ->with(['user.role', 'user.identities'])
            ->where('provider', 'microsoft')
            ->where('provider_subject', $subject)
            ->first();

        $user = $identity?->user;

        if (! $user && $email) {
            $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        }

        if (! $user) {
            $defaultRole = Role::query()->where('slug', env('AUTH_DEFAULT_ROLE_SLUG', 'teacher'))->first();

            $autoActivate = filter_var(env('AUTH_AUTO_ACTIVATE_SSO_USERS', false), FILTER_VALIDATE_BOOL);

            $user = User::query()->create([
                'role_id' => $defaultRole?->id,
                'name' => (string) Arr::get($claims, 'name', $email ?: 'Microsoft User'),
                'email' => $email ?: sprintf('%s@placeholder.local', $subject),
                'job_title' => Arr::get($claims, 'job_title'),
                'department' => Arr::get($claims, 'department'),
                'status' => $autoActivate ? 'active' : 'pending',
                'last_login_at' => now(),
            ]);
        } else {
            $user->forceFill([
                'name' => (string) Arr::get($claims, 'name', $user->name),
                'job_title' => Arr::get($claims, 'job_title', $user->job_title),
                'department' => Arr::get($claims, 'department', $user->department),
                'last_login_at' => now(),
            ])->save();
        }

        UserIdentity::query()->updateOrCreate(
            [
                'provider' => 'microsoft',
                'provider_subject' => $subject,
            ],
            [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'issuer' => Arr::get($claims, 'iss'),
                'provider_email' => $email,
                'meta' => [
                    'name' => Arr::get($claims, 'name'),
                    'preferred_username' => Arr::get($claims, 'preferred_username'),
                    'roles' => Arr::get($claims, 'roles', []),
                ],
                'last_login_at' => now(),
            ],
        );

        return $user->fresh(['role', 'identities', 'schools']);
    }
}
