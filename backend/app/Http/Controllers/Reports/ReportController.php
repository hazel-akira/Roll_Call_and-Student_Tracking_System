<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\AttendanceReportRequest;
use App\Jobs\GenerateAttendanceExport;
use App\Services\Reports\AttendanceReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(private readonly AttendanceReportService $reportService)
    {
    }

    public function attendanceSummary(AttendanceReportRequest $request): JsonResponse
    {
        return response()->json($this->reportService->attendanceSummary($request->validated()));
    }

    public function classTrends(AttendanceReportRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reportService->classTrends($request->validated()),
        ]);
    }

    public function studentTrends(AttendanceReportRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reportService->studentTrends($request->validated()),
        ]);
    }

    public function export(AttendanceReportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $format = $validated['format'] ?? 'xlsx';

        if ($format === 'json') {
            return response()->json([
                'data' => $this->reportService->exportRows($validated),
            ]);
        }

        GenerateAttendanceExport::dispatch($request->user()->id, $validated, $format);

        return response()->json([
            'message' => 'Report export queued successfully.',
        ], 202);
    }
}
