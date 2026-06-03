<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAttendanceSessionRequest;
use App\Http\Resources\AttendanceSessionResource;
use App\Models\AttendanceSession;
use App\Services\Attendance\AttendanceSessionService;
use App\Services\TeacherAssignmentService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceSessionController extends Controller
{
    public function __construct(
        private readonly AttendanceSessionService $attendanceSessionService,
        private readonly TenantService $tenantService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->tenantService
            ->scopeAttendanceSessions(AttendanceSession::query(), $request)
            ->with(['classRoom', 'subject', 'teacher'])
            ->latest('session_date');

        if ($request->user()?->role?->slug === 'teacher') {
            $query->where('teacher_id', $request->user()->id);

            $classIds = app(TeacherAssignmentService::class)->assignedClassIds($request->user());
            if ($classIds !== []) {
                $query->whereIn('class_id', $classIds);
            }
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->integer('class_id'));
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->integer('subject_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return response()->json([
            'data' => AttendanceSessionResource::collection($query->paginate($request->integer('per_page', 15))),
        ]);
    }

    public function store(StoreAttendanceSessionRequest $request): JsonResponse
    {
        $session = $this->attendanceSessionService->create($request->validated(), $request->user());

        return response()->json([
            'message' => 'Attendance session created successfully.',
            'data' => AttendanceSessionResource::make($session),
        ], 201);
    }

    public function show(AttendanceSession $attendanceSession): JsonResponse
    {
        return response()->json([
            'data' => AttendanceSessionResource::make($attendanceSession->load(['classRoom', 'subject', 'teacher', 'records.student'])),
        ]);
    }

    public function close(AttendanceSession $attendanceSession, Request $request): JsonResponse
    {
        $session = $this->attendanceSessionService->close($attendanceSession, $request->user());

        return response()->json([
            'message' => 'Attendance session closed successfully.',
            'data' => AttendanceSessionResource::make($session),
        ]);
    }
}
