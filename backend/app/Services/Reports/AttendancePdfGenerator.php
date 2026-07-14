<?php

namespace App\Services\Reports;

use App\Models\AttendanceSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class AttendancePdfGenerator
{
    public function __construct(
        private readonly ReportPdfTemplateResolver $templateResolver,
        private readonly WeeklyRollCallSheetBuilder $weeklySheetBuilder,
        private readonly RollCallMemoBuilder $memoBuilder,
    ) {
    }

    /**
     * Generate an attendance report PDF.
     *
     * @param array $filters
     * @return string Storage path of the generated PDF.
     */
    public function generate(array $filters): string
    {
        $schoolId = isset($filters['school_id'])
            ? (int) $filters['school_id']
            : null;

        $template = $this->templateResolver->resolve($schoolId);

        if ($template === 'weekly_roll_call_sheet') {
            $content = Pdf::loadView(
                'reports.weekly-roll-call-sheet',
                [
                    'sheet' => $this->weeklySheetBuilder->build($filters),
                ]
            )->output();
        } else {
            $content = Pdf::loadView(
                'reports.roll-call-memo',
                [
                    'memos' => $this->memoBuilder->buildPages($filters),
                ]
            )->output();
        }

        return $this->storePdf($content, sprintf(
            'attendance-report-%s.pdf',
            now()->format('YmdHis')
        ));
    }

    /**
     * Generate a roll-call memo PDF for a single closed session.
     */
    public function generateForSession(AttendanceSession $session): string
    {
        $session->loadMissing([
            'classRoom.school',
            'subject',
            'teacher',
            'records.student',
        ]);

        $content = Pdf::loadView(
            'reports.roll-call-memo',
            [
                'memos' => [
                    $this->memoBuilder->buildMemoForSession($session),
                ],
            ]
        )->output();

        return $this->storePdf($content, sprintf(
            'rollcall-session-%d-%s.pdf',
            $session->id,
            now()->format('YmdHis')
        ));
    }

    private function storePdf(string $content, string $filename): string
    {
        Storage::makeDirectory('exports');

        $path = "exports/{$filename}";

        Storage::put($path, $content);

        return $path;
    }
}