<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\AttendanceReportRequest;
use App\Jobs\GenerateAttendanceExport;
use App\Models\Notification;
use App\Services\Reports\AttendanceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        unset($validated['format']);

        if ($format === 'json') {
            return response()->json([
                'data' => $this->reportService->exportRows($validated),
            ]);
        }

        $job = new GenerateAttendanceExport($request->user()->id, $validated, $format);

        if (config('queue.default') === 'sync') {
            dispatch_sync($job);

            return response()->json([
                'message' => 'Report export generated successfully.',
                'status' => 'completed',
            ]);
        }

        dispatch($job);

        return response()->json([
            'message' => 'Report export queued successfully.',
            'status' => 'queued',
        ], 202);
    }

    public function downloadExport(Notification $notification, Request $request): StreamedResponse|JsonResponse
    {
        $user = $request->user();

        abort_unless($notification->user_id === $user->id, 403);
        abort_unless($notification->type === 'report', 404);

        $storedPath = $notification->data['path'] ?? null;
        $format = $notification->data['format'] ?? 'xlsx';

        if (! is_string($storedPath) || $storedPath === '') {
            return response()->json(['message' => 'Export file is not available.'], 404);
        }

        $resolvedPath = $this->resolveExportStoragePath($storedPath);

        if ($resolvedPath === null || ! Storage::exists($resolvedPath)) {
            return response()->json(['message' => 'Export file was not found on the server.'], 404);
        }

        $mimeType = $format === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return Storage::download(
            $resolvedPath,
            basename($resolvedPath),
            ['Content-Type' => $mimeType],
        );
    }

    private function resolveExportStoragePath(string $path): ?string
    {
        $candidates = [$path];

        if (str_starts_with($path, 'private/')) {
            $candidates[] = substr($path, strlen('private/'));
        } else {
            $candidates[] = 'private/'.$path;
        }

        foreach (array_unique($candidates) as $candidate) {
            if (Storage::exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
