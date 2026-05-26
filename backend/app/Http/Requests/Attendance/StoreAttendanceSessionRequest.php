<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->slug, ['teacher', 'admin', 'ict_staff'], true);
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'session_date' => ['required', 'date'],
            'started_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:50'],
        ];
    }
}
