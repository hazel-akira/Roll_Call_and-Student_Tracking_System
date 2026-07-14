<?php

namespace Tests\Unit\Services;

use App\Models\AttendanceSession;
use App\Models\RollCallReportRecipient;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolRollCallSetting;
use App\Models\Subject;
use App\Models\WeeklyDutyRoster;
use App\Services\Attendance\RollCallReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesWithJwt;
use Tests\TestCase;

class RollCallRecipientAssignmentTest extends TestCase
{
    use AuthenticatesWithJwt;
    use RefreshDatabase;

    public function test_assigned_recipients_are_included_for_matching_school(): void
    {
        $school = School::query()->create([
            'name' => 'Test School',
            'code' => 'TEST',
            'active' => true,
        ]);

        $assigned = $this->createUserWithRole('teacher', [
            'email' => 'assigned.recipient@example.test',
        ]);
        $assigned->schools()->attach($school->id);

        RollCallReportRecipient::query()->create([
            'school_id' => $school->id,
            'user_id' => $assigned->id,
            'label' => 'Principal',
            'active' => true,
        ]);

        $session = $this->makeSession($school);

        $recipients = app(RollCallReportService::class)->getRecipients($session);

        $this->assertContains('assigned.recipient@example.test', $recipients);
    }

    public function test_assigned_recipients_only_mode_excludes_automatic_groups(): void
    {
        $school = School::query()->create([
            'name' => 'Test School',
            'code' => 'TEST2',
            'active' => true,
        ]);

        SchoolRollCallSetting::query()->create([
            'school_id' => $school->id,
            'assigned_recipients_only' => true,
            'notify_school_admins' => true,
            'notify_session_teacher' => true,
        ]);

        $assigned = $this->createUserWithRole('teacher', [
            'email' => 'only.assigned@example.test',
        ]);
        $assigned->schools()->attach($school->id);

        RollCallReportRecipient::query()->create([
            'school_id' => $school->id,
            'user_id' => $assigned->id,
            'active' => true,
        ]);

        $sessionTeacher = $this->createUserWithRole('teacher', [
            'email' => 'session.teacher@example.test',
        ]);

        $session = $this->makeSession($school, $sessionTeacher);

        $recipients = app(RollCallReportService::class)->getRecipients($session);

        $this->assertSame(['only.assigned@example.test'], $recipients);
    }

    public function test_weekly_duty_roster_teachers_are_included_in_recipients(): void
    {
        $school = School::query()->create([
            'name' => 'Test School',
            'code' => 'TEST3',
            'active' => true,
        ]);

        $dutyTeacher = $this->createUserWithRole('teacher', [
            'email' => 'duty.week@example.test',
        ]);
        $dutyTeacher->schools()->attach($school->id);

        $roster = WeeklyDutyRoster::query()->create([
            'school_id' => $school->id,
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end' => now()->startOfWeek()->addDays(6)->toDateString(),
        ]);

        $entry = $roster->entries()->create([
            'category' => 'dining_hall',
            'sort_order' => 10,
        ]);
        $entry->staff()->attach($dutyTeacher->id);

        $session = $this->makeSession($school);

        $recipients = app(RollCallReportService::class)->getRecipients($session);

        $this->assertContains('duty.week@example.test', $recipients);
    }

    private function makeSession(School $school, ?\App\Models\User $teacher = null): AttendanceSession
    {
        $teacher ??= $this->createUserWithRole('teacher', [
            'email' => 'session.teacher@example.test',
        ]);

        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Form 1A',
            'code' => 'F1A',
            'academic_year' => '2026',
            'grade_level' => 'Form 1',
            'section' => 'A',
        ]);

        $subject = Subject::query()->create([
            'name' => 'Roll Call',
            'code' => 'ROLL-CALL',
        ]);

        $session = AttendanceSession::query()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Morning Roll Call',
            'session_date' => now()->toDateString(),
            'started_at' => now(),
            'status' => 'closed',
            'source' => 'web',
            'dynamics_sync_status' => 'pending',
        ]);

        return $session->load(['classRoom.school', 'classRoom.homeroomTeacher', 'teacher']);
    }
}
