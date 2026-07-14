<?php

namespace App\Services;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\RoleSlugs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class TenantService
{
    public const SESSION_KEY = 'current_school_id';

    public const SCHOOL_HEADER = 'X-School-Id';

    /**
     * Get the current tenant school for the authenticated user.
     */
    public function getCurrentSchool(): ?School
    {
        $id = $this->getCurrentSchoolId();

        return $id ? School::query()->find($id) : null;
    }

    /**
     * Get current tenant school id from session or defaults.
     */
    public function getCurrentSchoolId(): ?string
    {
        $id = Session::get(self::SESSION_KEY);

        if ($id !== null) {
            return (string) $id;
        }

        $user = Auth::user();
        if (! $user) {
            return null;
        }

        if ($this->isTenantUser($user)) {
            $schoolIds = $this->allowedSchoolIds($user);
            if (count($schoolIds) === 1) {
                $this->setCurrentSchoolId($schoolIds[0]);

                return $schoolIds[0];
            }
        }

        return null;
    }

    /**
     * School id applied to the current request (header, query, body, or session default).
     */
    public function effectiveSchoolId(Request $request): ?string
    {
        $id = $request->input('school_id');
        if ($id !== null && $id !== '') {
            return (string) $id;
        }

        return $this->getCurrentSchoolId();
    }

    /**
     * Teachers and dean staff are always scoped; admins/ICT scope only when a school is selected.
     */
    public function shouldApplySchoolScope(?User $user = null, ?Request $request = null): bool
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return false;
        }

        if ($this->isTenantUser($user)) {
            return true;
        }

        return $this->hasAccessToAllSchools($user)
            && $request !== null
            && $this->effectiveSchoolId($request) !== null;
    }

    /**
     * Restrict a query on the classes table to the active tenant school.
     */
    public function scopeSchoolClasses(Builder $query, Request $request): Builder
    {
        if (! $this->shouldApplySchoolScope(null, $request)) {
            return $query;
        }

        $schoolId = $this->effectiveSchoolId($request);
        if ($schoolId === null) {
            return $query;
        }

        return $query->where('school_id', $schoolId);
    }

    /**
     * When a teacher has class assignments, limit to those classes only.
     */
    public function scopeTeacherAssignedClasses(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? Auth::user();
        if (! $user || $user->role?->slug !== RoleSlugs::TEACHER) {
            return $query;
        }

        $classIds = app(TeacherAssignmentService::class)->assignedClassIds($user);
        if ($classIds === []) {
            return $query;
        }

        return $query->whereIn($query->getModel()->getTable().'.id', $classIds);
    }

    /**
     * Restrict a query whose records belong to a class in the active tenant school.
     */
    public function scopeViaClassSchool(Builder $query, Request $request, string $classRelation = 'classRoom'): Builder
    {
        if (! $this->shouldApplySchoolScope(null, $request)) {
            return $query;
        }

        $schoolId = $this->effectiveSchoolId($request);
        if ($schoolId === null) {
            return $query;
        }

        return $query->whereHas($classRelation, fn (Builder $classQuery) => $classQuery->where('school_id', $schoolId));
    }

    /**
     * Restrict attendance sessions to the active tenant school (via class).
     */
    public function scopeAttendanceSessions(Builder $query, Request $request): Builder
    {
        if (! $this->shouldApplySchoolScope(null, $request)) {
            return $query;
        }

        $schoolId = $this->effectiveSchoolId($request);
        if ($schoolId === null) {
            return $query;
        }

        return $query->whereHas('classRoom', fn (Builder $classQuery) => $classQuery->where('school_id', $schoolId));
    }

    public function userCanAccessSchool(User $user, string $schoolId): bool
    {
        if ($this->hasAccessToAllSchools($user)) {
            return School::query()->whereKey($schoolId)->exists();
        }

        return in_array($schoolId, $this->allowedSchoolIds($user), true);
    }

    public function classBelongsToEffectiveSchool(SchoolClass $class, Request $request): bool
    {
        if (! $this->shouldApplySchoolScope(null, $request)) {
            return true;
        }

        $schoolId = $this->effectiveSchoolId($request);

        return $schoolId !== null && (string) $class->school_id === $schoolId;
    }

    /**
     * Set the current tenant school.
     */
    public function setCurrentSchool(School $school): void
    {
        $this->setCurrentSchoolId($school->id);
    }

    /**
     * Set the current tenant school id.
     */
    public function setCurrentSchoolId(?string $schoolId): void
    {
        if ($schoolId === null) {
            Session::forget(self::SESSION_KEY);

            return;
        }
        Session::put(self::SESSION_KEY, $schoolId);
    }

    /**
     * Teachers and dean staff are restricted to assigned schools.
     */
    public function isTenantUser($user = null): bool
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return false;
        }

        return in_array($user->role?->slug, RoleSlugs::schoolScopedSlugs(), true);
    }

    /**
     * Admin and ICT can see all schools.
     */
    public function hasAccessToAllSchools($user = null): bool
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return false;
        }

        return in_array($user->role?->slug, RoleSlugs::allSchoolAccessSlugs(), true);
    }

    /**
     * School IDs the current user is allowed to access. Empty means all (admin/ICT).
     */
    public function allowedSchoolIds($user = null): array
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return [];
        }

        if ($this->hasAccessToAllSchools($user)) {
            return [];
        }

        if (! in_array($user->role?->slug, RoleSlugs::schoolScopedSlugs(), true)) {
            return [];
        }

        return array_map('strval', $this->queryUserSchoolIds($user));
    }

    private function queryUserSchoolIds(User $user): array
    {
        return School::query()
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
            ->pluck('id')
            ->all();
    }
}
