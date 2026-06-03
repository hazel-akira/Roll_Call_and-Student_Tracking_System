<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherClassAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $class = $this->classRoom;

        return [
            'id' => $this->id,
            'class_id' => $this->class_id,
            'subject_id' => $this->subject_id,
            'class' => $class ? [
                'id' => $class->id,
                'name' => $class->name,
                'code' => $class->code,
                'grade_level' => $class->grade_level,
                'section' => $class->section,
                'school_id' => $class->school_id,
                'school' => $class->relationLoaded('school') && $class->school ? [
                    'id' => $class->school->id,
                    'name' => $class->school->name,
                    'code' => $class->school->code,
                ] : null,
            ] : null,
        ];
    }
}
