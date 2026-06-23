<?php

namespace App\Jobs;

use App\Exports\AttendanceSummaryExport;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Reports\AttendanceReportService;
use App\Services\Reports\ReportPdfTemplateResolver;
use App\Services\Reports\RollCallMemoBuilder;
use App\Services\Reports\WeeklyRollCallSheetBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class GenerateAttendanceExport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $userId,
        private readonly array $filters,
        private readonly string $format,
    ) {
    }

    public function handle(
        AttendanceReportService $reportService,
        RollCallMemoBuilder $memoBuilder,
        WeeklyRollCallSheetBuilder $weeklySheetBuilder,
        ReportPdfTemplateResolver $templateResolver,
        NotificationService $notificationService,
    ): void {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $rows = $reportService->exportRows($this->filters);
        $directory = 'exports';
        Storage::makeDirectory($directory);
        $timestamp = now()->format('YmdHis');
        $fileName = sprintf('attendance-report-%s.%s', $timestamp, $this->format);
        $path = $directory.'/'.$fileName;

        if ($this->format === 'pdf') {
            $schoolId = isset($this->filters['school_id']) ? (int) $this->filters['school_id'] : null;
            $template = $templateResolver->resolve($schoolId);

            if ($template === 'weekly_roll_call_sheet') {
                $content = Pdf::loadView('reports.weekly-roll-call-sheet', [
                    'sheet' => $weeklySheetBuilder->build($this->filters),
                ])->output();
            } else {
                $content = Pdf::loadView('reports.roll-call-memo', [
                    'memos' => $memoBuilder->buildPages($this->filters),
                ])->output();
            }

            Storage::put($path, $content);
        } else {
            Excel::store(new AttendanceSummaryExport($rows), $path);
        }

        $notificationService->notifyUser(
            $user,
            'Attendance export ready',
            'Your attendance export has been generated successfully.',
            ['path' => $path, 'format' => $this->format],
            'report',
        );
    }
}
