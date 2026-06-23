<?php

namespace App\Services\Reports;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RollCallMemoBuilder
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildPages(array $filters): array
    {
        $sessions = $this->sessionsQuery($filters)
            ->with([
                'classRoom.school',
                'subject',
                'teacher',
                'records.student',
            ])
            ->orderBy('session_date')
            ->orderBy('id')
            ->get();

        $memos = [];

        foreach ($sessions as $session) {
            $memo = $this->buildMemoForSession($session);

            if ($memo['pages'] !== []) {
                $memos[] = $memo;
            }
        }

        return $memos;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildMemoForSession(AttendanceSession $session): array
    {
        $class = $session->classRoom;
        $school = $class?->school;
        $subject = $session->subject;
        $sessionDate = $session->session_date ?? now();

        $recordsByStudent = $session->records->keyBy('student_id');
        $students = $this->classRoster($session->class_id);
        $rollAssignments = $this->assignRollNumbers($students);

        $rows = [];

        foreach ($rollAssignments as $assignment) {
            $student = $assignment['student'];
            $record = $student ? $recordsByStudent->get($student->id) : null;

            $rows[] = $this->buildRow(
                $assignment['roll_number'],
                $assignment['gender_label'],
                $student,
                $record,
            );
        }

        $rowsPerPage = max(1, (int) config('reports.roll_call_memo.rows_per_page', 16));

        return [
            'school_name' => strtoupper((string) ($school?->name ?? 'SCHOOL')),
            'title' => 'ROLL CALL MEMO',
            'term_line' => $this->termLine($subject, $class?->academic_year, $sessionDate),
            'student_count' => $students->count(),
            'department' => strtoupper((string) ($subject?->name ?? 'ROLL CALL')),
            'stream_class' => $this->formatStreamClass($class?->name, $class?->grade_level, $class?->section),
            'date_formatted' => $sessionDate->format('l, F j, Y'),
            'pages' => array_map(
                static fn (array $chunk): array => ['rows' => $chunk],
                array_chunk($rows, $rowsPerPage),
            ),
        ];
    }

    private function sessionsQuery(array $filters): Builder
    {
        return AttendanceSession::query()
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('session_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('session_date', '<=', $to))
            ->when($filters['school_id'] ?? null, fn (Builder $query, int $schoolId) => $query->whereHas('classRoom', fn (Builder $classQuery) => $classQuery->where('school_id', $schoolId)))
            ->when($filters['class_id'] ?? null, fn (Builder $query, int $classId) => $query->where('class_id', $classId))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, int $subjectId) => $query->where('subject_id', $subjectId))
            ->when($filters['teacher_id'] ?? null, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId));
    }

    private function classRoster(int $classId): Collection
    {
        return Student::query()
            ->where('class_id', $classId)
            ->where('status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * @return array<int, array{roll_number: int, gender_label: string, student: ?Student}>
     */
    private function assignRollNumbers(Collection $students): array
    {
        $boyStart = (int) config('reports.roll_call_memo.boy_roll_start', 1);
        $boyEnd = (int) config('reports.roll_call_memo.boy_roll_end', 7);
        $girlStart = (int) config('reports.roll_call_memo.girl_roll_start', 14);

        $boys = $students->filter(fn (Student $student): bool => $student->gender === 'male')->values();
        $girls = $students->filter(fn (Student $student): bool => $student->gender !== 'male')->values();

        $assignments = [];

        for ($roll = $boyStart, $index = 0; $roll <= $boyEnd; $roll++, $index++) {
            $assignments[] = [
                'roll_number' => $roll,
                'gender_label' => 'Boys',
                'student' => $boys->get($index),
            ];
        }

        foreach ($girls as $index => $student) {
            $assignments[] = [
                'roll_number' => $girlStart + $index,
                'gender_label' => 'Girls',
                'student' => $student,
            ];
        }

        return $assignments;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRow(int $rollNumber, string $genderLabel, ?Student $student, ?AttendanceRecord $record): array
    {
        $status = $record?->status;
        $timeIn = $record?->marked_at ? $record->marked_at->format('g:i:s A') : '';

        return [
            'roll_label' => sprintf('%s: %d', $genderLabel, $rollNumber),
            'roll_number' => $rollNumber,
            'student_name' => $student ? $this->formatStudentName($student) : '',
            'present' => $status === 'present' ? 'PRESENT' : '',
            'absent' => $status === 'absent' ? 'ABSENT' : '',
            'late' => $status === 'late' ? 'LATE' : '',
            'excused' => $status === 'excused' ? 'EXCUSED' : '',
            'time_in' => in_array($status, ['absent', 'late'], true) ? $timeIn : '',
            'far' => '',
        ];
    }

    private function termLine(?object $subject, ?string $academicYear, Carbon $sessionDate): string
    {
        $subjectCode = strtoupper((string) ($subject?->code ?? 'RC'));
        $subjectName = strtoupper((string) ($subject?->name ?? 'ROLL CALL'));
        $year = $academicYear ?: $sessionDate->format('Y');
        $nextYear = (int) $year + 1;
        $term = $this->schoolTerm($sessionDate);

        return sprintf(
            '%s-%d Term %d,Year %s, Subject: %s',
            $subjectCode,
            $nextYear,
            $term,
            $year,
            $subjectName,
        );
    }

    private function schoolTerm(Carbon $date): int
    {
        $month = (int) $date->format('n');

        if ($month >= 1 && $month <= 4) {
            return 1;
        }

        if ($month >= 5 && $month <= 8) {
            return 2;
        }

        return 3;
    }

    private function formatStreamClass(?string $name, ?string $gradeLevel, ?string $section): string
    {
        $parts = array_filter([
            $gradeLevel,
            $section,
        ]);

        if ($parts !== []) {
            return strtoupper(trim(implode(' ', $parts)));
        }

        return strtoupper((string) ($name ?? ''));
    }

    private function formatStudentName(Student $student): string
    {
        return strtoupper(trim(implode(' ', array_filter([
            $student->last_name,
            $student->first_name,
            $student->middle_name,
        ]))));
    }
}
