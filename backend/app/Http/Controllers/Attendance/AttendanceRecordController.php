<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\UpsertAttendanceRecordsRequest;
use App\Http\Resources\AttendanceSessionResource;
use App\Models\AttendanceSession;
use App\Services\Attendance\AttendanceSessionService;
use Illuminate\Http\JsonResponse;

class AttendanceRecordController extends Controller
{
    public function __construct(private readonly AttendanceSessionService $attendanceSessionService)
    {
    }

    public function upsert(UpsertAttendanceRecordsRequest $request, AttendanceSession $attendanceSession): JsonResponse
    {
        $session = $this->attendanceSessionService->upsertRecords($attendanceSession, $request->validated()['records'], $request->user());

        return response()->json([
            'message' => 'Attendance records saved successfully.',
            'data' => AttendanceSessionResource::make($session),
        ]);
    }
}
