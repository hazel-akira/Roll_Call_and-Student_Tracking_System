<?php

namespace Tests\Unit\Services;

use App\Models\AttendanceSession;
use App\Models\GradeMasterAssignment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\Attendance\RollCallReportService;
use App\Services\Reports\AttendancePdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AuthenticatesWithJwt;
use Tests\TestCase;

class RollCallReportServiceTest extends TestCase
{
    use AuthenticatesWithJwt;
    use RefreshDatabase;

    public function test_get_recipients_includes_admin_form_master_grade_master_and_teacher_on_duty(): void
    {
        $school = School::query()->create([
            'name' => 'Test School',
            'code' => 'TEST',
            'active' => true,
        ]);

        $admin = $this->createUserWithRole('admin', [
            'email' => 'admin@example.test',
        ]);
        $admin->schools()->attach($school->id);

        $formMaster = $this->createUserWithRole('teacher', [
            'email' => 'form.master@example.test',
        ]);

        $gradeMaster = $this->createUserWithRole('teacher', [
            'email' => 'grade.master@example.test',
        ]);

        $dutyTeacher = $this->createUserWithRole('teacher', [
            'email' => 'duty.teacher@example.test',
        ]);

        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Form 2B',
            'code' => 'F2B',
            'academic_year' => '2026',
            'grade_level' => 'Form 2',
            'section' => 'B',
            'homeroom_teacher_id' => $formMaster->id,
        ]);

        GradeMasterAssignment::query()->create([
            'school_id' => $school->id,
            'grade_level' => 'Form 2',
            'user_id' => $gradeMaster->id,
        ]);

        $subject = Subject::query()->create([
            'name' => 'English',
            'code' => 'ENG',
        ]);

        $session = AttendanceSession::query()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $dutyTeacher->id,
            'title' => 'Roll Call',
            'session_date' => now()->toDateString(),
            'started_at' => now(),
            'status' => 'closed',
            'source' => 'web',
            'dynamics_sync_status' => 'pending',
        ]);

        $session->load(['classRoom.school', 'classRoom.homeroomTeacher', 'teacher']);

        $service = app(RollCallReportService::class);
        $recipients = $service->getRecipients($session);

        $this->assertEqualsCanonicalizing([
            'admin@example.test',
            'form.master@example.test',
            'grade.master@example.test',
            'duty.teacher@example.test',
        ], $recipients);
    }

    public function test_generate_and_send_skips_when_auto_send_disabled(): void
    {
        Mail::fake();
        config(['reports.roll_call_report.auto_send' => false]);

        $school = School::query()->create([
            'name' => 'Test School',
            'code' => 'TEST',
            'active' => true,
        ]);

        $admin = $this->createUserWithRole('admin', ['email' => 'admin@example.test']);
        $teacher = $this->createUserWithRole('teacher');
        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Form 1A',
            'code' => 'F1A',
            'academic_year' => '2026',
            'grade_level' => 'Form 1',
            'section' => 'A',
        ]);
        $subject = Subject::query()->create(['name' => 'Science', 'code' => 'SCI']);

        $session = AttendanceSession::query()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Roll Call',
            'session_date' => now()->toDateString(),
            'started_at' => now(),
            'status' => 'closed',
            'source' => 'web',
            'dynamics_sync_status' => 'pending',
        ]);

        app(RollCallReportService::class)->generateAndSend($session->load(['classRoom.school', 'teacher']));

        Mail::assertNothingSent();
    }

    public function test_get_recipients_includes_extra_configured_emails(): void
    {
        config(['reports.roll_call_report.extra_recipients' => ['extra@example.test']]);

        $school = School::query()->create([
            'name' => 'Test School',
            'code' => 'TEST',
            'active' => true,
        ]);

        $teacher = $this->createUserWithRole('teacher', ['email' => 'duty.teacher@example.test']);
        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Form 1A',
            'code' => 'F1A',
            'academic_year' => '2026',
            'grade_level' => 'Form 1',
            'section' => 'A',
        ]);
        $subject = Subject::query()->create(['name' => 'Science', 'code' => 'SCI']);

        $session = AttendanceSession::query()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Roll Call',
            'session_date' => now()->toDateString(),
            'started_at' => now(),
            'status' => 'closed',
            'source' => 'web',
            'dynamics_sync_status' => 'pending',
        ]);

        $recipients = app(RollCallReportService::class)->getRecipients(
            $session->load(['classRoom.school', 'teacher'])
        );

        $this->assertContains('extra@example.test', $recipients);
        $this->assertContains('duty.teacher@example.test', $recipients);
    }

    public function test_generate_for_session_stores_pdf_and_returns_path(): void
    {
        Storage::fake();

        $school = School::query()->create([
            'name' => 'Test School',
            'code' => 'TEST',
            'active' => true,
        ]);

        $teacher = $this->createUserWithRole('teacher');
        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Form 1A',
            'code' => 'F1A',
            'academic_year' => '2026',
            'grade_level' => 'Form 1',
            'section' => 'A',
        ]);
        $subject = Subject::query()->create([
            'name' => 'Science',
            'code' => 'SCI',
        ]);

        $session = AttendanceSession::query()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Roll Call',
            'session_date' => now()->toDateString(),
            'started_at' => now(),
            'status' => 'closed',
            'source' => 'web',
            'dynamics_sync_status' => 'pending',
        ]);

        $session->load(['classRoom.school', 'subject', 'teacher', 'records.student']);

        $path = app(AttendancePdfGenerator::class)->generateForSession($session);

        $this->assertStringStartsWith('exports/rollcall-session-'.$session->id.'-', $path);
        Storage::assertExists($path);
    }
}
