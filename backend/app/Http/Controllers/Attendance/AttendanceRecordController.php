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
        $result = $this->attendanceSessionService->upsertRecords(
            $attendanceSession,
            $request->validated()['records'],
            $request->user(),
            (bool) ($request->validated()['roster_complete'] ?? false),
        );

        return response()->json([
            'message' => $result['report_sent']
                ? 'Attendance saved and roll call report sent to stakeholders.'
                : 'Attendance records saved successfully.',
            'data' => AttendanceSessionResource::make($result['session']),
            'report_sent' => $result['report_sent'],
        ]);
    }
}
