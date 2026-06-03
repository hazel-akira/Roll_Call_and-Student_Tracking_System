<?php

namespace App\Services\Integrations;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\DynamicsService;

class DynamicsStudentSyncService
{
    public function __construct(
        private readonly DynamicsService $dynamicsService,
    ) {
    }

    public function syncClassStudents(SchoolClass $class): array
    {
        $class->loadMissing('school');

        $rows = $this->dynamicsService->getStudentsForClass($class);
        $synced = 0;

        foreach ($rows as $row) {
            $admissionNumber = trim((string) ($row['admission_number'] ?? ''));
            $externalReference = trim((string) ($row['external_reference'] ?? ''));

            if ($admissionNumber === '' && $externalReference === '') {
                continue;
            }

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
            } else {
                Student::query()->create($payload);
            }

            $synced++;
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
}
