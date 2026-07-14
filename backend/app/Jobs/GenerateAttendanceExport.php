<?php

namespace App\Jobs;

use App\Exports\AttendanceSummaryExport;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Reports\AttendancePdfGenerator;
use App\Services\Reports\AttendanceReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
        AttendancePdfGenerator $pdfGenerator,
        NotificationService $notificationService,
    ): void {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        if ($this->format === 'pdf') {
            $path = $pdfGenerator->generate($this->filters);
        } else {
            $rows = $reportService->exportRows($this->filters);

            $fileName = sprintf(
                'exports/attendance-report-%s.xlsx',
                now()->format('YmdHis')
            );

            Excel::store(
                new AttendanceSummaryExport($rows),
                $fileName
            );

            $path = $fileName;
        }

        $notificationService->notifyUser(
            $user,
            'Attendance export ready',
            'Your attendance export has been generated successfully.',
            [
                'path' => $path,
                'format' => $this->format,
            ],
            'report',
        );
    }
}