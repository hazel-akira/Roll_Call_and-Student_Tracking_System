<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesWithJwt;
use Tests\TestCase;

class AttendanceFlowTest extends TestCase
{
    use AuthenticatesWithJwt;
    use RefreshDatabase;

    public function test_teacher_can_create_mark_and_close_attendance_session(): void
    {
        $school = School::query()->create([
            'name' => 'Pioneer School',
            'code' => 'PIONEER-BOYS',
            'active' => true,
        ]);
        $teacher = $this->createUserWithRole('teacher');
        $teacher->schools()->attach($school->id);
        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Form 3',
            'code' => 'F3A',
            'academic_year' => '2026',
            'grade_level' => 'Form 3',
            'section' => 'A',
            'homeroom_teacher_id' => $teacher->id,
        ]);

        $student = Student::query()->create([
            'class_id' => $class->id,
            'admission_number' => 'ADM-1001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'status' => 'active',
        ]);

        $createResponse = $this->postJson('/api/v1/attendance-sessions', [
            'class_id' => $class->id,
            'title' => 'Morning Roll Call',
            'session_date' => now()->toDateString(),
        ], $this->authHeaders($teacher));

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');

        $sessionId = $createResponse->json('data.id');

        $upsertResponse = $this->putJson("/api/v1/attendance-sessions/{$sessionId}/records", [
            'records' => [
                [
                    'student_id' => $student->id,
                    'status' => 'present',
                    'remark' => 'On time',
                ],
            ],
        ], $this->authHeaders($teacher));

        $upsertResponse->assertOk();

        $this->assertDatabaseHas('attendance_records', [
            'attendance_session_id' => $sessionId,
            'student_id' => $student->id,
            'status' => 'present',
        ]);

        $closeResponse = $this->patchJson("/api/v1/attendance-sessions/{$sessionId}/close", [], $this->authHeaders($teacher));

        $closeResponse
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('attendance_sessions', [
            'id' => $sessionId,
            'status' => 'closed',
        ]);
    }

    public function test_cannot_mark_attendance_for_student_from_different_class(): void
    {
        $school = School::query()->create([
            'name' => 'Pioneer School',
            'code' => 'PIONEER-BOYS',
            'active' => true,
        ]);
        $teacher = $this->createUserWithRole('teacher');
        $teacher->schools()->attach($school->id);
        $classA = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Form 3',
            'code' => 'F3A',
            'academic_year' => '2026',
            'grade_level' => 'Form 3',
            'section' => 'A',
        ]);
        $classB = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Form 4',
            'code' => 'F4B',
            'academic_year' => '2026',
            'grade_level' => 'Form 4',
            'section' => 'B',
        ]);

        $studentFromOtherClass = Student::query()->create([
            'class_id' => $classB->id,
            'admission_number' => 'ADM-2001',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'status' => 'active',
        ]);

        $sessionResponse = $this->postJson('/api/v1/attendance-sessions', [
            'class_id' => $classA->id,
            'title' => 'Afternoon Roll Call',
            'session_date' => now()->toDateString(),
        ], $this->authHeaders($teacher));

        $sessionId = $sessionResponse->json('data.id');

        $upsertResponse = $this->putJson("/api/v1/attendance-sessions/{$sessionId}/records", [
            'records' => [
                [
                    'student_id' => $studentFromOtherClass->id,
                    'status' => 'present',
                ],
            ],
        ], $this->authHeaders($teacher));

        $upsertResponse
            ->assertStatus(422)
            ->assertJsonPath('errors.records.0', 'One or more students do not belong to the selected class.');

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_admin_can_create_session_for_dynamics_stream_without_existing_class(): void
    {
        $school = School::query()->create([
            'name' => 'Pioneer Junior Academy',
            'code' => 'PJA',
            'active' => true,
        ]);
        $admin = $this->createUserWithRole('admin');

        $response = $this->postJson('/api/v1/attendance-sessions', [
            'title' => 'Grade Seven S Roll Call',
            'session_date' => now()->toDateString(),
            'grade_level' => 'Grade 7',
            'stream' => 'Grade Seven S',
            'school_id' => $school->id,
        ], array_merge($this->authHeaders($admin), [
            'X-School-Id' => (string) $school->id,
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('classes', [
            'school_id' => $school->id,
            'section' => 'Grade Seven S',
            'grade_level' => 'Grade 7',
        ]);

        $this->assertDatabaseHas('attendance_sessions', [
            'title' => 'Grade Seven S Roll Call',
            'status' => 'open',
        ]);
    }
}
