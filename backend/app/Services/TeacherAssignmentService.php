<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherAssignmentService
{
    public function sync(User $teacher, array $schoolIds, array $classIds, ?User $assignedBy = null): User
    {
        if ($teacher->role?->slug !== 'teacher') {
            throw ValidationException::withMessages([
                'teacher' => 'Assignments apply to teacher accounts only.',
            ]);
        }

        $schoolIds = array_values(array_unique(array_map('intval', $schoolIds)));
        $classIds = array_values(array_unique(array_map('intval', $classIds)));

        if ($schoolIds === []) {
            throw ValidationException::withMessages([
                'school_ids' => 'Select at least one school.',
            ]);
        }

        $this->assertClassesBelongToSchools($classIds, $schoolIds);

        DB::transaction(function () use ($teacher, $schoolIds, $classIds, $assignedBy): void {
            $teacher->schools()->sync($schoolIds);

            TeacherSubject::query()->where('teacher_id', $teacher->id)->delete();

            if ($classIds === []) {
                return;
            }

            $subjectId = $this->rollCallSubjectId();
            $assignedById = $assignedBy?->id;

            foreach ($classIds as $classId) {
                TeacherSubject::query()->create([
                    'teacher_id' => $teacher->id,
                    'class_id' => $classId,
                    'subject_id' => $subjectId,
                    'assigned_by' => $assignedById,
                ]);
            }
        });

        return $teacher->fresh([
            'role',
            'schools',
            'classAssignments.classRoom.school',
        ]);
    }

    /**
     * Class IDs the teacher may access. Empty means no class restriction (all classes in their schools).
     *
     * @return array<int>
     */
    public function assignedClassIds(User $teacher): array
    {
        return TeacherSubject::query()
            ->where('teacher_id', $teacher->id)
            ->pluck('class_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function teacherHasClassAssignment(User $teacher, int $classId): bool
    {
        $assigned = $this->assignedClassIds($teacher);

        if ($assigned === []) {
            return true;
        }

        return in_array($classId, $assigned, true);
    }

    private function assertClassesBelongToSchools(array $classIds, array $schoolIds): void
    {
        if ($classIds === []) {
            return;
        }

        $validCount = SchoolClass::query()
            ->whereIn('id', $classIds)
            ->whereIn('school_id', $schoolIds)
            ->count();

        if ($validCount !== count($classIds)) {
            throw ValidationException::withMessages([
                'class_ids' => 'One or more classes do not belong to the selected schools.',
            ]);
        }
    }

    private function rollCallSubjectId(): int
    {
        $subject = Subject::query()->firstOrCreate(
            ['code' => 'ROLL-CALL'],
            ['name' => 'General Roll Call', 'description' => 'Stream-based class roll call.'],
        );

        return $subject->id;
    }
}
