<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\TeacherAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function __construct(
        private readonly TeacherAssignmentService $assignmentService,
    ) {
    }

    public function index(): JsonResponse
    {
        $teachers = User::query()
            ->with([
                'role',
                'identities',
                'schools',
                'classAssignments.classRoom.school',
            ])
            ->whereHas('role', fn ($query) => $query->where('slug', 'teacher'))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => UserResource::collection($teachers),
        ]);
    }

    public function syncSchools(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'school_ids' => ['required', 'array', 'min:1'],
            'school_ids.*' => ['integer', 'exists:schools,id'],
        ]);

        $classIds = $user->classAssignments()->pluck('class_id')->all();

        $teacher = $this->assignmentService->sync(
            $user,
            $validated['school_ids'],
            $classIds,
            $request->user(),
        );

        return response()->json([
            'message' => 'Teacher school assignments updated.',
            'data' => UserResource::make($teacher),
        ]);
    }

    public function syncAssignments(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'school_ids' => ['required', 'array', 'min:1'],
            'school_ids.*' => ['integer', 'exists:schools,id'],
            'class_ids' => ['present', 'array'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
        ]);

        $teacher = $this->assignmentService->sync(
            $user,
            $validated['school_ids'],
            $validated['class_ids'],
            $request->user(),
        );

        return response()->json([
            'message' => 'Teacher assignments updated.',
            'data' => UserResource::make($teacher),
        ]);
    }
}
