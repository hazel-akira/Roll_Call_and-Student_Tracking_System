<?php

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UserAccessService
{
    public function canSignIn(User $user): bool
    {
        return $user->status === 'active';
    }

    public function teacherRequiresSchools(User $user): bool
    {
        return $user->role?->slug === 'teacher';
    }

    public function hasSchoolAccess(User $user): bool
    {
        if (! $this->teacherRequiresSchools($user)) {
            return true;
        }

        return $user->schools()->exists();
    }

    public function validateCanActivate(User $user): void
    {
        if ($user->status !== 'active') {
            return;
        }

        if ($this->teacherRequiresSchools($user) && ! $this->hasSchoolAccess($user)) {
            throw ValidationException::withMessages([
                'schools' => 'Teachers must be assigned to at least one school before access is granted.',
            ]);
        }
    }

    public function activate(User $user, ?int $roleId = null, array $schoolIds = []): User
    {
        if ($roleId !== null) {
            $user->role_id = $roleId;
        }

        if ($schoolIds !== []) {
            $user->schools()->sync($schoolIds);
        }

        $user->status = 'active';
        $user->load(['role', 'schools']);
        $this->validateCanActivate($user);
        $user->save();

        return $user->fresh(['role', 'schools', 'identities']);
    }

    public function deactivate(User $user): User
    {
        $user->forceFill(['status' => 'inactive'])->save();

        return $user->fresh(['role', 'schools', 'identities']);
    }

    public function signInBlockedMessage(User $user): string
    {
        return match ($user->status) {
            'pending' => 'Your account is awaiting administrator approval. An admin must assign your role and schools in the admin panel before you can sign in.',
            'inactive' => 'Your account has been deactivated. Contact your school administrator.',
            default => 'You do not have permission to sign in.',
        };
    }

    /**
     * @return array<int, string>
     */
    public function schoolOptions(): array
    {
        return \App\Models\School::query()
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function roleOptions(): array
    {
        return Role::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}
