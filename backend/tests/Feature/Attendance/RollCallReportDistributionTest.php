<?php

namespace Tests\Feature\Attendance;

use App\Mail\RollCallReportMail;
use App\Models\GradeMasterAssignment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AuthenticatesWithJwt;
use Tests\TestCase;

class RollCallReportDistributionTest extends TestCase
{
    use AuthenticatesWithJwt;
    use RefreshDatabase;

    public function test_saving_attendance_sends_roll_call_report_email_to_stakeholders(): void
    {
        Mail::fake();

        $school = School::query()->create([
            'name' => 'Pioneer School',
            'code' => 'PIONEER-BOYS',
            'active' => true,
        ]);

        $admin = $this->createUserWithRole('admin', [
            'email' => 'admin@example.test',
        ]);
        $admin->schools()->attach($school->id);

        $gradeMaster = $this->createUserWithRole('teacher', [
            'name' => 'Grade Master',
            'email' => 'grade.master@example.test',
        ]);

        $homeroomTeacher = $this->createUserWithRole('teacher', [
            'name' => 'Form Master',
            'email' => 'form.master@example.test',
        ]);

        $sessionTeacher = $this->createUserWithRole('teacher', [
            'name' => 'Duty Teacher',
            'email' => 'duty.teacher@example.test',
        ]);
        $sessionTeacher->schools()->attach($school->id);

        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Form 3A',
            'code' => 'F3A',
            'academic_year' => '2026',
            'grade_level' => 'Form 3',
            'section' => 'A',
            'homeroom_teacher_id' => $homeroomTeacher->id,
        ]);

        GradeMasterAssignment::query()->create([
            'school_id' => $school->id,
            'grade_level' => 'Form 3',
            'user_id' => $gradeMaster->id,
        ]);

        $subject = Subject::query()->create([
            'name' => 'Mathematics',
            'code' => 'MATH',
        ]);

        $student = Student::query()->create([
            'class_id' => $class->id,
            'admission_number' => '1001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'status' => 'active',
        ]);

        $createResponse = $this->postJson('/api/v1/attendance-sessions', [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'title' => 'Morning Roll Call',
            'session_date' => now()->toDateString(),
        ], $this->authHeaders($sessionTeacher));

        $sessionId = $createResponse->json('data.id');

        $this->putJson("/api/v1/attendance-sessions/{$sessionId}/records", [
            'records' => [
                [
                    'student_id' => $student->id,
                    'status' => 'present',
                ],
            ],
            'roster_complete' => true,
        ], $this->authHeaders($sessionTeacher))
            ->assertOk()
            ->assertJsonPath('report_sent', true)
            ->assertJsonPath('message', 'Attendance saved and roll call report sent to stakeholders.');

        Mail::assertSent(RollCallReportMail::class, function (RollCallReportMail $mail) use (
            $admin,
            $gradeMaster,
            $homeroomTeacher,
            $sessionTeacher,
        ): bool {
            $recipients = collect($mail->to)->pluck('address')->all();

            return in_array($admin->email, $recipients, true)
                && in_array($gradeMaster->email, $recipients, true)
                && in_array($homeroomTeacher->email, $recipients, true)
                && in_array($sessionTeacher->email, $recipients, true);
        });
    }

    public function test_roster_complete_sends_report_when_dynamics_roster_is_smaller_than_local_class(): void
    {
        Mail::fake();

        $school = School::query()->create([
            'name' => 'Pioneer School',
            'code' => 'PIONEER-BOYS',
            'active' => true,
        ]);

        $teacher = $this->createUserWithRole('teacher', [
            'email' => 'duty.teacher@example.test',
        ]);
        $teacher->schools()->attach($school->id);

        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Form 3A',
            'code' => 'F3A',
            'academic_year' => '2026',
            'grade_level' => 'Form 3',
            'section' => 'A',
        ]);

        $subject = Subject::query()->create([
            'name' => 'Mathematics',
            'code' => 'MATH',
        ]);

        $onRollStudent = Student::query()->create([
            'class_id' => $class->id,
            'admission_number' => '1001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'status' => 'active',
        ]);

        Student::query()->create([
            'class_id' => $class->id,
            'admission_number' => '9999',
            'first_name' => 'Stale',
            'last_name' => 'Student',
            'status' => 'active',
        ]);

        $sessionId = $this->postJson('/api/v1/attendance-sessions', [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'title' => 'Morning Roll Call',
            'session_date' => now()->toDateString(),
        ], $this->authHeaders($teacher))->json('data.id');

        $this->putJson("/api/v1/attendance-sessions/{$sessionId}/records", [
            'records' => [
                [
                    'student_id' => $onRollStudent->id,
                    'status' => 'present',
                ],
            ],
            'roster_complete' => true,
        ], $this->authHeaders($teacher))
            ->assertOk()
            ->assertJsonPath('report_sent', true);

        Mail::assertSent(\App\Mail\RollCallReportMail::class);
    }
}
