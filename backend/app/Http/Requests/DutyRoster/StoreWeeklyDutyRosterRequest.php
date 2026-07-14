<?php

namespace App\Http\Requests\DutyRoster;

use App\Support\RoleSlugs;
use Illuminate\Foundation\Http\FormRequest;

class StoreWeeklyDutyRosterRequest extends FormRequest
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
            'week_start' => ['required', 'date'],
            'week_end' => ['nullable', 'date', 'after_or_equal:week_start'],
        ];
    }
}
