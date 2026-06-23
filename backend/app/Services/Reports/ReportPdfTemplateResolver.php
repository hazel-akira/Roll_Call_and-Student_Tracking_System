<?php

namespace App\Services\Reports;

use App\Models\School;

class ReportPdfTemplateResolver
{
    public function resolve(?int $schoolId): string
    {
        if ($schoolId === null) {
            return 'roll_call_memo';
        }

        $school = School::query()->find($schoolId);

        if ($school?->is_junior) {
            return 'weekly_roll_call_sheet';
        }

        return 'roll_call_memo';
    }
}
