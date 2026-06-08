<?php

namespace App\Services;

use App\Models\School;
use App\Models\SchoolClass;
use Illuminate\Support\Str;

class ClassStreamResolver
{
    public function resolveOrCreate(School $school, ?string $gradeLevel, string $streamName): SchoolClass
    {
        $streamName = trim($streamName);
        $gradeLevel = $gradeLevel !== null ? trim($gradeLevel) : '';

        if ($streamName === '') {
            throw new \InvalidArgumentException('Stream name is required to resolve a class.');
        }

        $existing = SchoolClass::query()
            ->where('school_id', $school->id)
            ->when($gradeLevel !== '', fn ($query) => $query->where('grade_level', $gradeLevel))
            ->where(function ($query) use ($streamName): void {
                $query
                    ->where('section', $streamName)
                    ->orWhere('name', $streamName)
                    ->orWhere('name', 'like', '%'.$streamName.'%');
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        $label = trim($gradeLevel !== '' ? $gradeLevel.' '.$streamName : $streamName);
        $code = strtoupper(Str::slug($school->code.'-'.$label));
        $suffix = 0;

        while (SchoolClass::query()->where('code', $code)->exists()) {
            $suffix++;
            $code = strtoupper(Str::slug($school->code.'-'.$label.'-'.$suffix));
        }

        return SchoolClass::query()->create([
            'school_id' => $school->id,
            'grade_level' => $gradeLevel !== '' ? $gradeLevel : null,
            'section' => $streamName,
            'name' => $label,
            'code' => $code,
            'academic_year' => (string) now()->year,
        ]);
    }
}
