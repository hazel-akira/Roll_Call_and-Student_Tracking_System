<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $classes = $this->tenantService->scopeTeacherAssignedClasses(
            $this->tenantService->scopeSchoolClasses(SchoolClass::query(), $request),
            $request->user(),
        )
            ->with(['school:id,name,code', 'homeroomTeacher:id,name,email'])
            ->withCount('students')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $classes,
        ]);
    }
}
