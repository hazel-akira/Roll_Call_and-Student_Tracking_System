<?php

namespace App\Jobs;

use App\Exports\AttendanceSummaryExport;
use App\Models\School;
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
            $school = isset($this->filters['school_id'])
                ? School::query()->find((int) $this->filters['school_id'])
                : null;

            $subtitle = collect([
                isset($this->filters['from']) ? 'From '.$this->filters['from'] : null,
                isset($this->filters['to']) ? 'To '.$this->filters['to'] : null,
                isset($this->filters['academic_year']) ? 'Year '.$this->filters['academic_year'] : null,
                isset($this->filters['term']) ? 'Term '.$this->filters['term'] : null,
            ])->filter()->implode(' · ');

            $fileName = sprintf(
                'exports/attendance-report-%s.xlsx',
                now()->format('YmdHis')
            );

            Excel::store(
                new AttendanceSummaryExport(
                    $rows,
                    $school,
                    $subtitle !== '' ? $subtitle : null,
                ),
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