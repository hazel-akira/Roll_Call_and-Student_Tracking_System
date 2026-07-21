<?php

namespace Tests\Unit\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Reports\RollCallMemoBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RollCallMemoBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_memo_matches_roll_call_template_shape(): void
    {
        config([
            'reports.roll_call_memo.boy_roll_start' => 1,
            'reports.roll_call_memo.boy_roll_end' => 7,
            'reports.roll_call_memo.girl_roll_start' => 14,
            'reports.roll_call_memo.rows_per_page' => 16,
        ]);

        $teacher = User::factory()->create();
        $school = School::query()->create([
            'name' => 'Pioneer School',
            'code' => 'PS',
            'level' => 'senior',
            'active' => true,
        ]);
        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Grade TEN',
            'code' => 'G10',
            'academic_year' => '2026',
            'grade_level' => 'Grade Ten',
            'section' => 'A',
            'homeroom_teacher_id' => $teacher->id,
        ]);
        $subject = Subject::query()->create([
            'name' => 'Chemistry',
            'code' => 'TA1',
        ]);
        $student = Student::query()->create([
            'class_id' => $class->id,
            'admission_number' => 'ADM-1401',
            'first_name' => 'Marion',
            'last_name' => 'Amuga',
            'gender' => 'female',
            'status' => 'active',
        ]);
        $session = AttendanceSession::query()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Chemistry Roll Call',
            'session_date' => '2026-02-26',
            'started_at' => '2026-02-26 08:00:00',
            'status' => 'closed',
            'source' => 'web',
            'dynamics_sync_status' => 'pending',
        ]);
        AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'marked_by' => $teacher->id,
            'status' => 'present',
            'marked_at' => '2026-02-26 09:09:58',
        ]);

        $session->load(['classRoom.school', 'subject', 'records.student']);
        $memo = app(RollCallMemoBuilder::class)->buildMemoForSession($session);

        $this->assertSame('PIONEER SCHOOL', $memo['school_name']);
        $this->assertSame('ROLL CALL MEMO', $memo['title']);
        $this->assertSame('TA1-2027 Term 1,Year 2026, Subject: CHEMISTRY', $memo['term_line']);
        $this->assertSame(1, $memo['student_count']);
        $this->assertSame('CHEMISTRY', $memo['department']);
        $this->assertSame('GRADE TEN A', $memo['stream_class']);
        $this->assertSame('Thursday, February 26, 2026', $memo['date_formatted']);
        $this->assertNotEmpty($memo['school_logo']);

        $rows = $memo['pages'][0]['rows'];
        $this->assertCount(1, $rows);
        $this->assertSame('AMUGA MARION', $rows[0]['student_name']);
        $this->assertSame('PRESENT', $rows[0]['present']);
        $this->assertSame(1, $memo['summary']['total_present']);
        $this->assertSame(0, $memo['summary']['total_absent']);
        $this->assertSame(0, $memo['summary']['total_excused']);
    }
}
