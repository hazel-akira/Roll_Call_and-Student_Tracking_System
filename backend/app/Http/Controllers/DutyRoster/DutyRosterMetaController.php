<?php

namespace App\Http\Controllers\DutyRoster;

use App\Http\Controllers\Controller;
use App\Services\DutyRoster\WeeklyDutyRosterService;
use App\Services\TenantService;
use App\Support\DutyRosterCategories;
use App\Support\RoleSlugs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DutyRosterMetaController extends Controller
{
    public function __construct(
        private readonly WeeklyDutyRosterService $rosterService,
        private readonly TenantService $tenantService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'categories' => DutyRosterCategories::labels(),
            'standard_template' => DutyRosterCategories::standardTemplate(),
        ]);
    }

    public function staff(Request $request): JsonResponse
    {
        abort_unless(in_array($request->user()?->role?->slug, [
            ...RoleSlugs::deanSlugs(),
            ...RoleSlugs::allSchoolAccessSlugs(),
        ], true), 403);

        $schoolId = $this->tenantService->effectiveSchoolId($request);

        abort_unless($schoolId !== null, 422, 'Select a school before loading staff.');

        abort_unless(
            $this->tenantService->userCanAccessSchool($request->user(), $schoolId),
            403,
            'You do not have access to the selected school.',
        );

        $staff = $this->rosterService
            ->schoolStaffQuery((int) $schoolId)
            ->get(['id', 'name', 'email', 'job_title'])
            ->map(fn ($user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'job_title' => $user->job_title,
            ]);

        return response()->json(['data' => $staff]);
    }
}
