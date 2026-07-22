<?php

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleSlugs;
use Illuminate\Validation\ValidationException;

class UserAccessService
{
    public function canSignIn(User $user): bool
    {
        return $user->status === 'active';
    }

    public function userRequiresSchools(User $user): bool
    {
        return RoleSlugs::requiresSchoolAssignment($user->role?->slug);
    }

    public function hasSchoolAccess(User $user): bool
    {
        if (! $this->userRequiresSchools($user)) {
            return true;
        }

        return $user->schools()->exists();
    }

    public function canSelfAssignSchools(User $user): bool
    {
        return $user->role?->slug === RoleSlugs::TEACHER;
    }

    public function requiresSchoolSelection(User $user): bool
    {
        return $this->canSignIn($user)
            && $this->userRequiresSchools($user)
            && ! $this->hasSchoolAccess($user)
            && $this->canSelfAssignSchools($user);
    }

    public function validateCanActivate(User $user): void
    {
        if ($user->status !== 'active') {
            return;
        }

        if ($this->userRequiresSchools($user) && ! $this->hasSchoolAccess($user)) {
            throw ValidationException::withMessages([
                'schools' => 'Teachers and dean staff must be assigned to at least one school before access is granted.',
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

    public function panelAccessDeniedMessage(User $user, string $panelId): string
    {
        $role = $user->role?->slug ?? 'unknown';

        return match ($panelId) {
            'admin' => match ($role) {
                'teacher' => 'Your account is a teacher. Use the teacher panel at /teacher to sign in.',
                'dean_of_students', 'deputy_dean' => 'Your account is dean staff. Use the dean panel at /dean to sign in.',
                default => 'Your account does not have administrator access. Ask an ICT administrator to assign the admin or ICT staff role.',
            },
            'teacher' => match ($role) {
                'dean_of_students', 'deputy_dean' => 'Your account is dean staff. Use the dean panel at /dean to sign in.',
                default => 'Your account is not set up for the teacher panel. Ask an administrator to assign the teacher role and school access.',
            },
            'dean' => 'Your account is not set up for the dean panel. Ask an administrator to assign the dean of students or deputy dean role and school access.',
            default => 'Your account does not have access to this panel.',
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
