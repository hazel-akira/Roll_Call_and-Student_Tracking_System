<?php

namespace Tests\Feature\Integrations;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\DynamicsService;
use App\Services\Integrations\DynamicsStudentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DynamicsStudentSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_class_students_updates_existing_records_without_duplicates(): void
    {
        $school = School::query()->create([
            'name' => 'Sample High School',
            'code' => 'SHS',
            'level' => 'high',
            'active' => true,
        ]);
        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Form 3',
            'code' => 'F3A',
            'academic_year' => '2026',
            'grade_level' => 'Form 3',
            'section' => 'A',
        ]);

        $existing = Student::query()->create([
            'class_id' => $class->id,
            'admission_number' => 'ADM-1001',
            'first_name' => 'Old',
            'last_name' => 'Name',
            'status' => 'active',
            'external_reference' => 'ext-1001',
        ]);

        $rows = [
            [
                'admission_number' => 'ADM-1001',
                'external_reference' => 'ext-1001',
                'first_name' => 'Updated',
                'last_name' => 'Student',
                'email' => 'updated.student@example.test',
                'gender' => 'female',
                'dob' => '2010-01-01',
            ],
            [
                'admission_number' => 'ADM-2001',
                'external_reference' => 'ext-2001',
                'first_name' => 'New',
                'last_name' => 'Student',
                'email' => 'new.student@example.test',
                'gender' => 'male',
                'dob' => '2011-02-02',
            ],
        ];

        $dynamics = Mockery::mock(DynamicsService::class);
        $dynamics->shouldReceive('getStudentsForClass')
            ->once()
            ->withArgs(fn (SchoolClass $arg) => $arg->id === $class->id)
            ->andReturn($rows);

        $service = new DynamicsStudentSyncService($dynamics);

        $result = $service->syncClassStudents($class);

        $this->assertSame(2, $result['fetched_count']);
        $this->assertSame(2, $result['synced_count']);
        $this->assertDatabaseCount('students', 2);
        $this->assertDatabaseHas('students', [
            'id' => $existing->id,
            'admission_number' => 'ADM-1001',
            'first_name' => 'Updated',
            'last_name' => 'Student',
            'external_reference' => 'ext-1001',
        ]);
        $this->assertDatabaseHas('students', [
            'admission_number' => 'ADM-2001',
            'first_name' => 'New',
            'last_name' => 'Student',
            'class_id' => $class->id,
        ]);
    }
}
