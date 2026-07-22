<?php

namespace App\Http\Requests\Reports;

use App\Support\RoleSlugs;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->slug, RoleSlugs::reportViewerSlugs(), true);
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'term' => ['nullable', 'integer', 'in:1,2,3'],
            'week_start' => ['nullable', 'date'],
            'format' => ['nullable', 'in:json,pdf,xlsx'],
        ];
    }
}
