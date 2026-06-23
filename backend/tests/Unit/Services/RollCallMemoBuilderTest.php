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
            'name' => 'Riara Springs Girls Secondary School',
            'code' => 'RSGSS',
            'level' => 'senior',
            'active' => true,
        ]);
        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Grade Eleven South',
            'code' => 'G11S',
            'academic_year' => '2026',
            'grade_level' => 'Grade Eleven',
            'section' => 'South',
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

        $this->assertSame('RIARA SPRINGS GIRLS SECONDARY SCHOOL', $memo['school_name']);
        $this->assertSame('ROLL CALL MEMO', $memo['title']);
        $this->assertSame('TA1-2027 Term 1,Year 2026, Subject: CHEMISTRY', $memo['term_line']);
        $this->assertSame(1, $memo['student_count']);
        $this->assertSame('CHEMISTRY', $memo['department']);
        $this->assertSame('GRADE ELEVEN SOUTH', $memo['stream_class']);
        $this->assertSame('Thursday, February 26, 2026', $memo['date_formatted']);

        $rows = $memo['pages'][0]['rows'];
        $this->assertSame('Boys: 1', $rows[0]['roll_label']);
        $this->assertSame('', $rows[0]['student_name']);

        $girlRow = collect($rows)->firstWhere('roll_label', 'Girls: 14');
        $this->assertNotNull($girlRow);
        $this->assertSame('AMUGA MARION', $girlRow['student_name']);
        $this->assertSame('PRESENT', $girlRow['present']);
    }
}
