<?php

namespace Tests\Feature\Students;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesWithJwt;
use Tests\TestCase;

class StudentAttendanceReportTest extends TestCase
{
    use AuthenticatesWithJwt;
    use RefreshDatabase;

    public function test_teacher_can_fetch_student_attendance_report_json_and_pdf(): void
    {
        $school = School::query()->create([
            'name' => 'Pioneer School',
            'code' => 'PS',
            'active' => true,
        ]);
        $teacher = $this->createUserWithRole('teacher');
        $teacher->schools()->attach($school->id);
        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Form 3 A',
            'code' => 'F3A',
            'academic_year' => '2026',
            'grade_level' => 'Form 3',
            'section' => 'A',
            'homeroom_teacher_id' => $teacher->id,
        ]);
        $subject = Subject::query()->create([
            'name' => 'Mathematics',
            'code' => 'MATH',
        ]);
        $student = Student::query()->create([
            'class_id' => $class->id,
            'admission_number' => 'ADM-5001',
            'first_name' => 'Mary',
            'last_name' => 'Wanjiku',
            'status' => 'active',
        ]);
        $session = AttendanceSession::query()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Morning Roll Call',
            'session_date' => now()->toDateString(),
            'started_at' => now(),
            'status' => 'closed',
        ]);
        AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'present',
            'remark' => 'On time',
            'marked_at' => now(),
        ]);

        $jsonResponse = $this->getJson(
            "/api/v1/students/{$student->id}/attendance-report",
            $this->authHeaders($teacher),
        );

        $jsonResponse
            ->assertOk()
            ->assertJsonPath('data.student.admission_number', 'ADM-5001')
            ->assertJsonPath('data.summary.records', 1)
            ->assertJsonPath('data.summary.present', 1)
            ->assertJsonPath('data.rows.0.status', 'PRESENT');

        $pdfResponse = $this->get(
            "/api/v1/students/{$student->id}/attendance-report?format=pdf",
            $this->authHeaders($teacher),
        );

        $pdfResponse
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
