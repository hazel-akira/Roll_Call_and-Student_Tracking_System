<?php

namespace App\Http\Requests\DutyRoster;

use App\Support\DutyRosterCategories;
use App\Support\RoleSlugs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWeeklyDutyRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->slug, [
            ...RoleSlugs::deanSlugs(),
            ...RoleSlugs::allSchoolAccessSlugs(),
        ], true);
    }

    public function rules(): array
    {
        return [
            'week_start' => ['sometimes', 'date'],
            'week_end' => ['nullable', 'date', 'after_or_equal:week_start'],
            'entries' => ['sometimes', 'array'],
            'entries.*.id' => ['nullable', 'integer'],
            'entries.*.category' => ['required_with:entries', 'string', Rule::in(array_keys(DutyRosterCategories::labels()))],
            'entries.*.location' => ['nullable', 'string', 'max:255'],
            'entries.*.time_slot' => ['nullable', 'string', 'max:255'],
            'entries.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'entries.*.staff_ids' => ['nullable', 'array'],
            'entries.*.staff_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
