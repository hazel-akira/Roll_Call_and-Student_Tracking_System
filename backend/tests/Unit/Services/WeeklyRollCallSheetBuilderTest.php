<?php

namespace Tests\Unit\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Reports\ReportPdfTemplateResolver;
use App\Services\Reports\WeeklyRollCallSheetBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyRollCallSheetBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_junior_school_uses_weekly_roll_call_sheet_template(): void
    {
        $school = School::query()->create([
            'name' => 'Pioneer Girls Junior Academy',
            'code' => 'PGJA',
            'level' => 'junior',
            'is_junior' => true,
            'active' => true,
        ]);

        $this->assertSame(
            'weekly_roll_call_sheet',
            app(ReportPdfTemplateResolver::class)->resolve($school->id),
        );
    }

    public function test_build_weekly_sheet_groups_classes_by_day_and_period(): void
    {
        $teacher = User::factory()->create();
        $school = School::query()->create([
            'name' => 'Pioneer Girls Junior Academy',
            'code' => 'PGJA',
            'level' => 'junior',
            'is_junior' => true,
            'active' => true,
        ]);
        $subject = Subject::query()->create([
            'name' => 'Roll Call',
            'code' => 'ROLL-CALL',
        ]);

        $classes = collect([
            ['grade' => 'Grade 7', 'section' => 'N', 'count' => 6],
            ['grade' => 'Grade 8', 'section' => 'N', 'count' => 13],
            ['grade' => 'Grade 9', 'section' => 'N', 'count' => 15],
        ])->map(function (array $row) use ($school, $teacher): SchoolClass {
            return SchoolClass::query()->create([
                'school_id' => $school->id,
                'name' => $row['grade'].' '.$row['section'],
                'code' => str_replace(' ', '', $row['grade']).$row['section'],
                'academic_year' => '2026',
                'grade_level' => $row['grade'],
                'section' => $row['section'],
                'homeroom_teacher_id' => $teacher->id,
            ]);
        });

        foreach ($classes as $index => $class) {
            $expectedCount = [6, 13, 15][$index];

            for ($studentIndex = 1; $studentIndex <= $expectedCount; $studentIndex++) {
                Student::query()->create([
                    'class_id' => $class->id,
                    'admission_number' => sprintf('PGJA-%d-%02d', $class->id, $studentIndex),
                    'first_name' => 'Student',
                    'last_name' => (string) $studentIndex,
                    'gender' => 'female',
                    'status' => 'active',
                ]);
            }
        }

        foreach ([['17:30:00', 'evening'], ['19:30:00', 'night']] as [$time, $label]) {
            foreach ($classes as $class) {
                $session = AttendanceSession::query()->create([
                    'class_id' => $class->id,
                    'subject_id' => $subject->id,
                    'teacher_id' => $teacher->id,
                    'title' => 'Friday Roll Call',
                    'session_date' => '2026-06-19',
                    'started_at' => "2026-06-19 {$time}",
                    'status' => 'closed',
                    'source' => 'web',
                    'dynamics_sync_status' => 'pending',
                ]);

                $students = Student::query()->where('class_id', $class->id)->get();

                foreach ($students as $student) {
                    AttendanceRecord::query()->create([
                        'attendance_session_id' => $session->id,
                        'student_id' => $student->id,
                        'marked_by' => $teacher->id,
                        'status' => 'present',
                        'marked_at' => "2026-06-19 {$time}",
                    ]);
                }
            }
        }

        $sheet = app(WeeklyRollCallSheetBuilder::class)->build([
            'school_id' => $school->id,
            'from' => '2026-06-19',
            'to' => '2026-06-19',
        ]);

        $this->assertSame('PIONEER GIRLS JUNIOR ACADEMY', $sheet['school_name']);
        $this->assertSame('2026', $sheet['year']);
        $this->assertCount(1, $sheet['period_groups']);
        $this->assertSame('Friday', $sheet['period_groups'][0]['day']);
        $this->assertCount(2, $sheet['period_groups'][0]['periods']);

        $evening = $sheet['period_groups'][0]['periods'][0];
        $this->assertStringContainsString('EVENING', strtoupper($evening['period']));
        $this->assertSame('CLASS 7N', strtoupper($evening['classes'][0]['class']));
        $this->assertSame(34, $evening['total']['expected']);
        $this->assertSame(34, $evening['total']['present']);
        $this->assertSame(0, $evening['total']['absent']);
        $this->assertSame([], $sheet['absentees']);
    }
}
