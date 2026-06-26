<?php

namespace App\Http\Requests\Students;

use Illuminate\Foundation\Http\FormRequest;

class StudentLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'admission_number' => ['required', 'string', 'max:255'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
        ];
    }
}
