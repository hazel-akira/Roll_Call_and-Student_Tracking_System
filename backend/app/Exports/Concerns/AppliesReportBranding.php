<?php

namespace App\Exports\Concerns;

use App\Models\School;
use App\Support\ReportBranding;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait AppliesReportBranding
{
    protected function brandSheet(
        AfterSheet $event,
        string $reportTitle,
        ?School $school = null,
        ?string $subtitle = null,
        int $columnCount = 4,
        int $headerRow = 5,
    ): void {
        $sheet = $event->sheet->getDelegate();
        $lastColumn = $this->columnLetter($columnCount);

        $schoolName = strtoupper((string) ($school?->name ?? 'SCHOOL'));
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->mergeCells("A3:{$lastColumn}3");

        $sheet->setCellValue('A1', $schoolName);
        $sheet->setCellValue('A2', strtoupper($reportTitle));
        $sheet->setCellValue('A3', $subtitle ?? '');

        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['rgb' => '0F172A'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['rgb' => '1E3A5F'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle('A3')->applyFromArray([
            'font' => [
                'size' => 9,
                'color' => ['rgb' => '475569'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->getRowDimension(3)->setRowHeight(18);
        $sheet->getRowDimension(4)->setRowHeight(8);

        $this->insertLogo($sheet, $school);

        $headerRange = "A{$headerRow}:{$lastColumn}{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E3A5F'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '94A3B8'],
                ],
            ],
        ]);

        $highestRow = max($headerRow, $sheet->getHighestRow());
        if ($highestRow > $headerRow) {
            $dataRange = 'A'.($headerRow + 1).":{$lastColumn}{$highestRow}";
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '94A3B8'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ]);

            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                if (($row - $headerRow) % 2 === 0) {
                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F8FAFC'],
                        ],
                    ]);
                }
            }
        }

        foreach (range(1, $columnCount) as $index) {
            $sheet->getColumnDimension($this->columnLetter($index))->setAutoSize(true);
        }
    }

    private function insertLogo(Worksheet $sheet, ?School $school): void
    {
        $path = ReportBranding::logoAbsolutePath($school);
        if ($path === null || ! is_file($path)) {
            return;
        }

        $drawing = new Drawing;
        $drawing->setName('School logo');
        $drawing->setDescription('School logo');
        $drawing->setPath($path);
        $drawing->setHeight(54);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(4);
        $drawing->setOffsetY(2);
        $drawing->setWorksheet($sheet);
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }
}
