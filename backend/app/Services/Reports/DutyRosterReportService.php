<?php

namespace App\Services\Reports;

use App\Exports\DutyRosterExport;
use App\Models\WeeklyDutyRoster;
use App\Services\DutyRoster\WeeklyDutyRosterService;
use App\Support\ReportBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DutyRosterReportService
{
    public function __construct(private readonly WeeklyDutyRosterService $rosterService)
    {
    }

    public function exportRows(WeeklyDutyRoster $roster): Collection
    {
        $roster->loadMissing('entries.staff');

        return $roster->entries->map(fn ($entry): array => [
            'section' => \App\Support\DutyRosterCategories::label($entry->category),
            'location' => $entry->location ?: 'General',
            'time_slot' => $entry->time_slot ?: 'All day',
            'staff' => $entry->staff->pluck('name')->filter()->implode(', ') ?: 'Unassigned',
        ]);
    }

    public function downloadPdf(WeeklyDutyRoster $roster): StreamedResponse
    {
        $roster->load(['entries.staff', 'publisher', 'school']);
        $payload = $this->rosterService->serialize($roster);

        $pdf = Pdf::loadView('reports.duty-roster', [
            'roster' => $payload,
            'schoolName' => strtoupper((string) ($roster->school?->name ?? 'SCHOOL')),
            'schoolLogo' => ReportBranding::logoDataUri($roster->school),
        ])
            ->setPaper('a4')
            ->setOption('isPhpEnabled', true);

        $filename = sprintf(
            'duty-roster-%s.pdf',
            $roster->week_start?->format('Y-m-d') ?? $roster->id,
        );

        return response()->streamDownload(
            static fn () => print ($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function downloadExcel(WeeklyDutyRoster $roster): BinaryFileResponse
    {
        $roster->loadMissing('school');

        $filename = sprintf(
            'duty-roster-%s.xlsx',
            $roster->week_start?->format('Y-m-d') ?? $roster->id,
        );

        $subtitle = collect([
            $roster->weekLabel(),
            $roster->status ? 'Status: '.ucfirst((string) $roster->status) : null,
        ])->filter()->implode(' · ');

        return Excel::download(
            new DutyRosterExport(
                $this->exportRows($roster),
                $roster->school,
                $subtitle !== '' ? $subtitle : null,
            ),
            $filename,
        );
    }
}
