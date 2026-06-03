<?php

namespace App\Http\Requests\Attendance;

use App\Models\School;
use App\Models\SchoolClass;
use App\Services\ClassStreamResolver;
use App\Services\TeacherAssignmentService;
use App\Services\TenantService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->slug, ['teacher', 'admin', 'ict_staff'], true);
    }

    public function rules(): array
    {
        return [
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'grade_level' => ['nullable', 'string', 'max:255'],
            'stream' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'session_date' => ['required', 'date'],
            'started_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $classId = (int) $this->input('class_id', 0);
        $stream = trim((string) $this->input('stream', ''));

        if ($classId > 0 || $stream === '') {
            return;
        }

        $tenant = app(TenantService::class);
        $schoolId = $tenant->effectiveSchoolId($this);
        $school = $schoolId ? School::query()->find($schoolId) : null;

        if (! $school) {
            return;
        }

        $class = app(ClassStreamResolver::class)->resolveOrCreate(
            $school,
            $this->input('grade_level') ? (string) $this->input('grade_level') : null,
            $stream,
        );

        $this->merge(['class_id' => $class->id]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $classId = (int) $this->input('class_id', 0);
            if ($classId <= 0) {
                $validator->errors()->add(
                    'class_id',
                    'Select a form and stream, or provide a valid class.',
                );

                return;
            }

            $tenant = app(TenantService::class);
            if (! $tenant->shouldApplySchoolScope($this->user(), $this)) {
                return;
            }

            $class = SchoolClass::query()->find($classId);
            if (! $class || ! $tenant->classBelongsToEffectiveSchool($class, $this)) {
                $validator->errors()->add(
                    'class_id',
                    'The selected class does not belong to your current school.',
                );

                return;
            }

            if ($this->user()?->role?->slug !== 'teacher') {
                return;
            }

            $assignments = app(TeacherAssignmentService::class);
            if (! $assignments->teacherHasClassAssignment($this->user(), $classId)) {
                $validator->errors()->add(
                    'class_id',
                    'You are not assigned to this class or stream.',
                );
            }
        });
    }
}
