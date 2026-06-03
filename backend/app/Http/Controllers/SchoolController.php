<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $schools = School::query()
            ->when($request->boolean('active_only', true), fn ($query) => $query->where('active', true))
            ->when(! $this->tenantService->hasAccessToAllSchools($request->user()), function ($query) use ($request): void {
                $allowed = $this->tenantService->allowedSchoolIds($request->user());
                if ($allowed !== []) {
                    $query->whereIn('id', $allowed);
                }
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $schools,
            'current_school_id' => $this->tenantService->getCurrentSchoolId(),
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        $school = $this->tenantService->getCurrentSchool();

        return response()->json([
            'data' => $school,
            'current_school_id' => $school?->id,
        ]);
    }

    public function select(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
        ]);

        $schoolId = (string) $validated['school_id'];

        if (! $this->tenantService->userCanAccessSchool($request->user(), $schoolId)) {
            return response()->json([
                'message' => 'You do not have access to the selected school.',
            ], 403);
        }

        $school = School::query()->findOrFail($schoolId);
        $this->tenantService->setCurrentSchool($school);
        $request->merge(['school_id' => $schoolId]);

        return response()->json([
            'message' => 'School context updated.',
            'data' => $school,
            'current_school_id' => $schoolId,
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        if (! $this->tenantService->hasAccessToAllSchools($request->user())) {
            return response()->json([
                'message' => 'You do not have permission to clear school context.',
            ], 403);
        }

        $this->tenantService->setCurrentSchoolId(null);

        return response()->json([
            'message' => 'School context cleared. Showing all schools.',
            'data' => null,
            'current_school_id' => null,
        ]);
    }
}
