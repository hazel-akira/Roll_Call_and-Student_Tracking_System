<?php

namespace App\Services\Integrations;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\ClassStreamResolver;
use App\Services\DynamicsService;

class DynamicsStudentSyncService
{
    public function __construct(
        private readonly DynamicsService $dynamicsService,
        private readonly ClassStreamResolver $classStreamResolver,
    ) {
    }

    public function syncClassStudents(SchoolClass $class): array
    {
        $class->loadMissing('school');

        $rows = $this->dynamicsService->getStudentsForClass($class);
        $synced = 0;

        foreach ($rows as $row) {
            try {
                $this->upsertStudentRow($row, $class);
                $synced++;
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return [
            'class_id' => $class->id,
            'class_name' => $class->name,
            'school_id' => $class->school_id,
            'school_name' => $class->school?->name,
            'synced_count' => $synced,
            'fetched_count' => count($rows),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function upsertStudentFromDynamics(array $row, School $school): Student
    {
        $admissionNumber = trim((string) ($row['admission_number'] ?? $row['adm_or_staff_no'] ?? ''));
        $externalReference = trim((string) ($row['external_reference'] ?? $row['id'] ?? ''));

        if ($admissionNumber === '' && $externalReference === '') {
            throw new \InvalidArgumentException('Dataverse student is missing admission number and external reference.');
        }

        $gradeLevel = trim((string) ($row['grade_level'] ?? ''));
        $stream = trim((string) ($row['class_name'] ?? ''));
        if ($stream === '') {
            $stream = 'General';
        }

        $class = $this->classStreamResolver->resolveOrCreate(
            $school,
            $gradeLevel !== '' ? $gradeLevel : null,
            $stream,
        );

        return $this->upsertStudentRow($row, $class);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertStudentRow(array $row, SchoolClass $class): Student
    {
        $admissionNumber = trim((string) ($row['admission_number'] ?? $row['adm_or_staff_no'] ?? ''));
        $externalReference = trim((string) ($row['external_reference'] ?? $row['id'] ?? ''));

        $student = Student::query()
            ->when($externalReference !== '', fn ($query) => $query->where('external_reference', $externalReference))
            ->when($admissionNumber !== '', fn ($query) => $query->orWhere('admission_number', $admissionNumber))
            ->first();

        $payload = [
            'class_id' => $class->id,
            'admission_number' => $admissionNumber !== '' ? $admissionNumber : ('EXT-'.substr(md5($externalReference), 0, 10)),
            'first_name' => trim((string) ($row['first_name'] ?? 'Student')),
            'last_name' => trim((string) ($row['last_name'] ?? 'Unknown')),
            'email' => $row['email'] ?? null,
            'gender' => in_array($row['gender'] ?? null, ['male', 'female', 'other'], true) ? $row['gender'] : null,
            'date_of_birth' => $row['dob'] ?? null,
            'status' => 'active',
            'external_reference' => $externalReference !== '' ? $externalReference : null,
        ];

        if ($student) {
            $student->fill($payload)->save();

            return $student->fresh(['classRoom.school']) ?? $student;
        }

        return Student::query()->create($payload)->load('classRoom.school');
    }
}
