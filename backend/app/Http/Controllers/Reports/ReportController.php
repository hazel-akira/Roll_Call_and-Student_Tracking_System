<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\AttendanceReportRequest;
use App\Jobs\GenerateAttendanceExport;
use App\Models\Notification;
use App\Models\WeeklyDutyRoster;
use App\Services\DutyRoster\WeeklyDutyRosterService;
use App\Services\Reports\AttendanceReportService;
use App\Services\Reports\DutyRosterReportService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly AttendanceReportService $reportService,
        private readonly DutyRosterReportService $dutyRosterReportService,
        private readonly WeeklyDutyRosterService $rosterService,
        private readonly TenantService $tenantService,
    ) {
    }

    public function attendanceSummary(AttendanceReportRequest $request): JsonResponse
    {
        return response()->json($this->reportService->attendanceSummary($request->validated()));
    }

    public function attendanceWeeks(AttendanceReportRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reportService->weeklyOverview($request->validated()),
        ]);
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

    public function dutyRosters(Request $request): JsonResponse
    {
        $schoolId = $this->requireSchoolId($request);
        $status = $request->query('status');

        $rosters = WeeklyDutyRoster::query()
            ->where('school_id', $schoolId)
            ->with('publisher:id,name')
            ->withCount('entries')
            ->when(in_array($status, ['draft', 'published'], true), fn ($query) => $query->where('status', $status))
            ->orderByDesc('week_start')
            ->limit(52)
            ->get()
            ->map(fn (WeeklyDutyRoster $roster): array => [
                'id' => $roster->id,
                'school_id' => $roster->school_id,
                'week_start' => $roster->week_start?->toDateString(),
                'week_end' => $roster->week_end?->toDateString(),
                'week_label' => $roster->weekLabel(),
                'status' => $roster->status ?? WeeklyDutyRoster::STATUS_DRAFT,
                'published_at' => $roster->published_at?->toDateString(),
                'published_by' => $roster->published_by,
                'published_by_name' => $roster->publisher?->name,
                'entries_count' => $roster->entries_count,
            ]);

        return response()->json(['data' => $rosters]);
    }

    public function dutyRosterShow(Request $request, WeeklyDutyRoster $dutyRoster): JsonResponse
    {
        $this->ensureRosterAccessible($request, $dutyRoster);

        return response()->json([
            'data' => $this->rosterService->serialize($dutyRoster),
        ]);
    }

    public function dutyRosterExport(Request $request, WeeklyDutyRoster $dutyRoster): BinaryFileResponse|StreamedResponse|JsonResponse
    {
        $this->ensureRosterAccessible($request, $dutyRoster);

        $format = $request->query('format', 'pdf');

        return match ($format) {
            'xlsx' => $this->dutyRosterReportService->downloadExcel($dutyRoster),
            'pdf' => $this->dutyRosterReportService->downloadPdf($dutyRoster),
            default => response()->json(['message' => 'Unsupported export format.'], 422),
        };
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

    private function requireSchoolId(Request $request): int
    {
        $schoolId = $this->tenantService->effectiveSchoolId($request);

        abort_unless($schoolId !== null, 422, 'Select a school before viewing reports.');

        abort_unless(
            $this->tenantService->userCanAccessSchool($request->user(), $schoolId),
            403,
            'You do not have access to the selected school.',
        );

        return (int) $schoolId;
    }

    private function ensureRosterAccessible(Request $request, WeeklyDutyRoster $dutyRoster): void
    {
        $schoolId = $this->requireSchoolId($request);

        abort_unless((int) $dutyRoster->school_id === $schoolId, 404);
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
