<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AttendanceSummaryExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly Collection $rows)
    {
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Session Date',
            'Class',
            'Student',
            'Admission Number',
            'Subject',
            'Teacher',
            'Status',
            'Remark',
        ];
    }
}
