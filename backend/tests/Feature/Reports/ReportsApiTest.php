<?php

namespace Tests\Feature\Reports;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Notification;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AuthenticatesWithJwt;
use Tests\TestCase;

class ReportsApiTest extends TestCase
{
    use AuthenticatesWithJwt;
    use RefreshDatabase;

    public function test_admin_can_fetch_attendance_summary(): void
    {
        $admin = $this->createUserWithRole('admin');
        $teacher = $this->createUserWithRole('teacher');
        $class = SchoolClass::query()->create([
            'name' => 'Form 3',
            'code' => 'F3A',
            'academic_year' => '2026',
            'grade_level' => 'Form 3',
            'section' => 'A',
            'homeroom_teacher_id' => $teacher->id,
        ]);
        $subject = Subject::query()->create([
            'name' => 'Roll Call',
            'code' => 'ROLL-CALL',
        ]);
        $student = Student::query()->create([
            'class_id' => $class->id,
            'admission_number' => 'ADM-3001',
            'first_name' => 'Mercy',
            'last_name' => 'Wanjiru',
            'status' => 'active',
        ]);
        $session = AttendanceSession::query()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Morning Roll Call',
            'session_date' => now()->toDateString(),
            'started_at' => now(),
            'status' => 'open',
            'source' => 'web',
            'dynamics_sync_status' => 'pending',
        ]);
        AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'marked_by' => $teacher->id,
            'status' => 'present',
            'marked_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/reports/attendance-summary', $this->authHeaders($admin));

        $response
            ->assertOk()
            ->assertJsonPath('totals.records', 1)
            ->assertJsonPath('totals.present', 1)
            ->assertJsonPath('totals.attendance_rate', 100);
    }

    public function test_teacher_cannot_fetch_admin_reports(): void
    {
        [$teacher] = $this->createTeacherWithSchool();

        $response = $this->getJson('/api/v1/reports/attendance-summary', $this->authHeaders($teacher));

        $response
            ->assertStatus(403)
            ->assertJsonPath('message', 'You do not have permission to access this resource.');
    }

    public function test_dean_can_fetch_attendance_summary_for_assigned_school(): void
    {
        [$dean, $school] = $this->createDeanWithSchool();
        $teacher = $this->createUserWithRole('teacher');
        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Form 3',
            'code' => 'F3A',
            'academic_year' => '2026',
            'grade_level' => 'Form 3',
            'section' => 'A',
            'homeroom_teacher_id' => $teacher->id,
        ]);
        $subject = Subject::query()->create([
            'name' => 'Roll Call',
            'code' => 'ROLL-CALL',
        ]);
        $student = Student::query()->create([
            'class_id' => $class->id,
            'admission_number' => 'ADM-3001',
            'first_name' => 'Mercy',
            'last_name' => 'Wanjiru',
            'status' => 'active',
        ]);
        $session = AttendanceSession::query()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Morning Roll Call',
            'session_date' => now()->toDateString(),
            'started_at' => now(),
            'status' => 'open',
            'source' => 'web',
            'dynamics_sync_status' => 'pending',
        ]);
        AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'marked_by' => $teacher->id,
            'status' => 'present',
            'marked_at' => now(),
        ]);

        $otherSchool = \App\Models\School::query()->create([
            'name' => 'Other School',
            'code' => 'OTHER-DEAN-TEST',
            'active' => true,
        ]);
        $otherClass = SchoolClass::query()->create([
            'school_id' => $otherSchool->id,
            'name' => 'Form 4',
            'code' => 'F4A',
            'academic_year' => '2026',
            'grade_level' => 'Form 4',
            'section' => 'A',
            'homeroom_teacher_id' => $teacher->id,
        ]);
        $otherStudent = Student::query()->create([
            'class_id' => $otherClass->id,
            'admission_number' => 'ADM-4001',
            'first_name' => 'Other',
            'last_name' => 'Student',
            'status' => 'active',
        ]);
        $otherSession = AttendanceSession::query()->create([
            'class_id' => $otherClass->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Other Roll Call',
            'session_date' => now()->toDateString(),
            'started_at' => now(),
            'status' => 'open',
            'source' => 'web',
            'dynamics_sync_status' => 'pending',
        ]);
        AttendanceRecord::query()->create([
            'attendance_session_id' => $otherSession->id,
            'student_id' => $otherStudent->id,
            'marked_by' => $teacher->id,
            'status' => 'absent',
            'marked_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/reports/attendance-summary', $this->authHeaders($dean));

        $response
            ->assertOk()
            ->assertJsonPath('totals.records', 1)
            ->assertJsonPath('totals.present', 1);
    }

    public function test_dean_without_school_assignment_is_rejected(): void
    {
        $dean = $this->createUserWithRole('dean_of_students');

        $response = $this->getJson('/api/v1/reports/attendance-summary', $this->authHeaders($dean));

        $response
            ->assertStatus(403)
            ->assertJsonPath('message', 'Your account is not assigned to any school. Contact an administrator.');
    }

    public function test_admin_can_queue_attendance_export(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole('admin');
        $teacher = $this->createUserWithRole('teacher');
        $class = SchoolClass::query()->create([
            'name' => 'Form 3',
            'code' => 'F3A',
            'academic_year' => '2026',
            'grade_level' => 'Form 3',
            'section' => 'A',
            'homeroom_teacher_id' => $teacher->id,
        ]);
        $subject = Subject::query()->create([
            'name' => 'Roll Call',
            'code' => 'ROLL-CALL',
        ]);
        $student = Student::query()->create([
            'class_id' => $class->id,
            'admission_number' => 'ADM-3001',
            'first_name' => 'Mercy',
            'last_name' => 'Wanjiru',
            'status' => 'active',
        ]);
        $session = AttendanceSession::query()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Morning Roll Call',
            'session_date' => now()->toDateString(),
            'started_at' => now(),
            'status' => 'open',
            'source' => 'web',
            'dynamics_sync_status' => 'pending',
        ]);
        AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'marked_by' => $teacher->id,
            'status' => 'present',
            'marked_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/reports/export?format=xlsx', $this->authHeaders($admin));

        $response
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('message', 'Report export generated successfully.');

        $notification = Notification::query()
            ->where('user_id', $admin->id)
            ->where('type', 'report')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame('xlsx', $notification->data['format'] ?? null);
        Storage::assertExists($notification->data['path']);
    }

    public function test_admin_can_queue_attendance_export_on_database_queue(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();

        $admin = $this->createUserWithRole('admin');

        $response = $this->getJson('/api/v1/reports/export?format=pdf', $this->authHeaders($admin));

        $response
            ->assertStatus(202)
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('message', 'Report export queued successfully.');

        Queue::assertPushed(\App\Jobs\GenerateAttendanceExport::class);
    }

    public function test_admin_can_download_their_report_export(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole('admin');
        $path = 'exports/attendance-report-test.xlsx';
        Storage::put($path, 'spreadsheet-content');

        $notification = Notification::query()->create([
            'user_id' => $admin->id,
            'type' => 'report',
            'channel' => 'in_app',
            'title' => 'Attendance export ready',
            'body' => 'Your attendance export has been generated successfully.',
            'data' => ['path' => $path, 'format' => 'xlsx'],
            'sent_at' => now(),
        ]);

        $response = $this->get(
            '/api/v1/reports/exports/'.$notification->id.'/download',
            $this->authHeaders($admin),
        );

        $response
            ->assertOk()
            ->assertDownload('attendance-report-test.xlsx');
    }

    public function test_admin_cannot_download_another_users_report_export(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole('admin');
        $otherAdmin = $this->createUserWithRole('admin');
        $path = 'exports/attendance-report-test.xlsx';
        Storage::put($path, 'spreadsheet-content');

        $notification = Notification::query()->create([
            'user_id' => $otherAdmin->id,
            'type' => 'report',
            'channel' => 'in_app',
            'title' => 'Attendance export ready',
            'body' => 'Your attendance export has been generated successfully.',
            'data' => ['path' => $path, 'format' => 'xlsx'],
            'sent_at' => now(),
        ]);

        $response = $this->get(
            '/api/v1/reports/exports/'.$notification->id.'/download',
            $this->authHeaders($admin),
        );

        $response->assertForbidden();
    }

    public function test_dean_can_fetch_attendance_weeks_overview(): void
    {
        [$dean, $school] = $this->createDeanWithSchool();
        $teacher = $this->createUserWithRole('teacher');
        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Form 3',
            'code' => 'F3A-WEEKS',
            'academic_year' => '2026',
            'grade_level' => 'Form 3',
            'section' => 'A',
            'homeroom_teacher_id' => $teacher->id,
        ]);
        $subject = Subject::query()->create([
            'name' => 'Roll Call',
            'code' => 'ROLL-CALL-WEEKS',
        ]);
        $student = Student::query()->create([
            'class_id' => $class->id,
            'admission_number' => 'ADM-WEEKS-1',
            'first_name' => 'Mercy',
            'last_name' => 'Wanjiru',
            'status' => 'active',
        ]);
        $session = AttendanceSession::query()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Morning Roll Call',
            'session_date' => now()->toDateString(),
            'started_at' => now(),
            'closed_at' => now(),
            'status' => 'closed',
            'source' => 'web',
            'dynamics_sync_status' => 'pending',
        ]);
        AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'marked_by' => $teacher->id,
            'status' => 'present',
            'marked_at' => now(),
        ]);

        $response = $this->getJson(
            '/api/v1/reports/attendance-weeks',
            array_merge($this->authHeaders($dean), ['X-School-Id' => (string) $school->id]),
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.0.school_name', $school->name)
            ->assertJsonPath('data.0.present', 1);
    }

    public function test_dean_can_list_and_export_duty_roster_report(): void
    {
        [$dean, $school] = $this->createDeanWithSchool();
        $teacher = $this->createUserWithRole('teacher');
        $teacher->schools()->attach($school->id);

        $roster = \App\Models\WeeklyDutyRoster::query()->create([
            'school_id' => $school->id,
            'week_start' => now()->startOfWeek(),
            'week_end' => now()->startOfWeek()->addDays(6),
            'status' => \App\Models\WeeklyDutyRoster::STATUS_PUBLISHED,
            'published_at' => now(),
            'published_by' => $dean->id,
        ]);
        $roster->seedStandardTemplate();
        $roster->entries()->each(function ($entry) use ($teacher): void {
            $entry->staff()->sync([$teacher->id]);
        });

        $headers = array_merge($this->authHeaders($dean), ['X-School-Id' => (string) $school->id]);

        $list = $this->getJson('/api/v1/reports/duty-rosters', $headers);
        $list
            ->assertOk()
            ->assertJsonPath('data.0.id', $roster->id)
            ->assertJsonPath('data.0.published_by_name', $dean->name);

        $show = $this->getJson("/api/v1/reports/duty-rosters/{$roster->id}", $headers);
        $show
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonStructure(['data' => ['sections']]);

        $pdf = $this->get("/api/v1/reports/duty-rosters/{$roster->id}/export?format=pdf", $headers);
        $pdf->assertOk();

        $xlsx = $this->get("/api/v1/reports/duty-rosters/{$roster->id}/export?format=xlsx", $headers);
        $xlsx->assertOk();
    }
}
