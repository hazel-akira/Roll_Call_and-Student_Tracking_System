<?php

namespace App\Exports;

use App\Exports\Concerns\AppliesReportBranding;
use App\Models\School;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class DutyRosterExport implements FromCollection, WithCustomStartCell, WithEvents, WithHeadings, WithTitle
{
    use AppliesReportBranding;

    public function __construct(
        private readonly Collection $rows,
        private readonly ?School $school = null,
        private readonly ?string $subtitle = null,
    ) {
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Section',
            'Location',
            'Time Slot',
            'Staff',
        ];
    }

    public function title(): string
    {
        return 'Duty Roster';
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $this->brandSheet(
                    $event,
                    'Weekly Duty Roster',
                    $this->school,
                    $this->subtitle,
                    columnCount: 4,
                    headerRow: 5,
                );
            },
        ];
    }
}
