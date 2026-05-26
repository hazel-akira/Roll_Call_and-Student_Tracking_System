<?php

namespace App\Jobs;

use App\Exports\AttendanceSummaryExport;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Reports\AttendanceReportService;
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

    public function handle(AttendanceReportService $reportService, NotificationService $notificationService): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $rows = $reportService->exportRows($this->filters);
        $directory = 'private/exports';
        Storage::makeDirectory($directory);
        $timestamp = now()->format('YmdHis');
        $fileName = sprintf('attendance-report-%s.%s', $timestamp, $this->format);
        $path = $directory.'/'.$fileName;

        if ($this->format === 'pdf') {
            $content = Pdf::loadView('reports.attendance-summary', ['rows' => $rows])->output();
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
