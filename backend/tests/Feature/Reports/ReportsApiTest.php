<?php

namespace Tests\Feature\Reports;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Notification;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
