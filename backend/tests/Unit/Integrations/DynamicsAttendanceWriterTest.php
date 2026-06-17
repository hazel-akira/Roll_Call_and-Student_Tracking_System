<?php

namespace Tests\Unit\Integrations;

use App\Integrations\Dynamics\DynamicsAttendanceWriter;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\DynamicsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DynamicsAttendanceWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_creates_attendance_header_and_roll_records_in_dataverse(): void
    {
        config([
            'dynamics.enabled' => true,
            'dynamics.url' => 'https://example.crm.dynamics.com',
            'dynamics.attendance_push_mode' => 'dataverse',
        ]);

        $school = School::query()->create([
            'name' => 'Pioneer Girls Junior Academy',
            'code' => 'PGJA',
            'level' => 'junior',
            'active' => true,
            'dynamics_id' => '1ae2fb27-8153-ed11-bba2-0022489ba7d6',
        ]);
        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Grade 9 Grade Nine East',
            'code' => 'PGJA-G9-EAST',
            'grade_level' => 'Grade 9',
            'section' => 'Grade Nine East',
            'academic_year' => '2026',
        ]);
        $subject = Subject::query()->create([
            'name' => 'General Roll Call',
            'code' => 'ROLL-CALL',
        ]);
        $teacher = User::factory()->create();
        $student = Student::query()->create([
            'class_id' => $class->id,
            'admission_number' => 'ADM-9001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'status' => 'active',
            'external_reference' => '11111111-1111-1111-1111-111111111111',
        ]);
        $session = AttendanceSession::query()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Morning Roll Call',
            'session_date' => '2026-06-11',
            'started_at' => now(),
            'status' => 'closed',
            'closed_at' => now(),
            'dynamics_sync_status' => 'queued',
        ]);
        AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'present',
            'remark' => 'On time',
            'marked_at' => now(),
            'marked_by' => $teacher->id,
        ]);

        $dynamics = Mockery::mock(DynamicsService::class);
        $dynamics->shouldReceive('isEnabled')->andReturn(true);
        $dynamics->shouldReceive('resolveDataverseSchoolName')->andReturn('Pioneer Girls Junior Academy');
        $dynamics->shouldReceive('resolveRoomIdByName')->andReturn('f2600c27-5bed-f011-8543-002248a46710');
        $dynamics->shouldReceive('resolveClassDynamicsId')->andReturn('22222222-2222-2222-2222-222222222222');
        $dynamics->shouldReceive('create')
            ->once()
            ->with('ses_attendances', Mockery::on(function (array $payload): bool {
                return str_starts_with((string) ($payload['ses_attendance'] ?? ''), 'ATD-')
                    && ($payload['ses_date'] ?? null) === '2026-06-11'
                    && ($payload['ses_classname'] ?? null) === 'Grade 9 Grade Nine East'
                    && ($payload['ses_academicyear'] ?? null) === '2026'
                    && str_starts_with((string) ($payload['ses_lmsid'] ?? ''), 'RC-SESSION:')
                    && ($payload['ses_schoolid@odata.bind'] ?? null) === '/ses_schools(1ae2fb27-8153-ed11-bba2-0022489ba7d6)';
            }))
            ->andReturn(['id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'status' => 204, 'body' => null]);
        $dynamics->shouldReceive('create')
            ->once()
            ->with('ses_attendancerolls', Mockery::on(function (array $payload): bool {
                return str_starts_with((string) ($payload['ses_attendanceroll'] ?? ''), 'ATR-')
                    && ($payload['ses_present'] ?? null) === true
                    && ($payload['ses_studentname'] ?? null) === 'JANE DOE'
                    && ($payload['ses_institutionstudentid'] ?? null) === '9001'
                    && ($payload['ses_attendancestatus'] ?? null) === 284210000
                    && ($payload['ses_attendancerollstatus'] ?? null) === 284210000
                    && ($payload['ses_studentid@odata.bind'] ?? null) === '/ses_students(11111111-1111-1111-1111-111111111111)'
                    && ($payload['ses_attendanceid@odata.bind'] ?? null) === '/ses_attendances(aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa)';
            }))
            ->andReturn(['id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', 'status' => 204, 'body' => null]);

        $writer = new DynamicsAttendanceWriter($dynamics);
        $result = $writer->push($session->fresh(['classRoom.school', 'records.student']));

        $this->assertSame('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', $result['attendance_id']);
        $this->assertSame(1, $result['rolls_created']);
    }
}
