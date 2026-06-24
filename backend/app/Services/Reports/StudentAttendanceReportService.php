<?php

namespace App\Services\Reports;

use App\Models\AttendanceRecord;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentAttendanceReportService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(Student $student, array $filters = []): Collection
    {
        return $this->recordsQuery($student, $filters)
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->orderByDesc('attendance_sessions.session_date')
            ->orderByDesc('attendance_records.marked_at')
            ->select('attendance_records.*')
            ->with(['session.subject', 'session.classRoom', 'session.teacher'])
            ->get()
            ->map(function (AttendanceRecord $record): array {
                return [
                    'session_date' => optional($record->session?->session_date)->toDateString(),
                    'session_title' => $record->session?->title,
                    'class' => $record->session?->classRoom?->name,
                    'subject' => $record->session?->subject?->name,
                    'teacher' => $record->session?->teacher?->name,
                    'status' => strtoupper((string) $record->status),
                    'remark' => $record->remark,
                    'marked_at' => optional($record->marked_at)?->toIso8601String(),
                ];
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{
     *     records: int,
     *     present: int,
     *     absent: int,
     *     late: int,
     *     excused: int,
     *     missing: int,
     *     sick: int,
     *     on_leave: int,
     *     attendance_rate: float
     * }
     */
    public function summary(Collection $rows): array
    {
        $counts = $rows->countBy(fn (array $row): string => strtolower((string) ($row['status'] ?? '')));

        $present = (int) ($counts['present'] ?? 0);
        $total = $rows->count();

        return [
            'records' => $total,
            'present' => $present,
            'absent' => (int) ($counts['absent'] ?? 0),
            'late' => (int) ($counts['late'] ?? 0),
            'excused' => (int) ($counts['excused'] ?? 0),
            'missing' => (int) ($counts['missing'] ?? 0),
            'sick' => (int) ($counts['sick'] ?? 0),
            'on_leave' => (int) ($counts['on_leave'] ?? 0),
            'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0.0,
        ];
    }

    /**
     * @return array{
     *     student: array<string, mixed>,
     *     filters: array<string, mixed>,
     *     summary: array<string, mixed>,
     *     rows: Collection<int, array<string, mixed>>
     * }
     */
    public function build(Student $student, array $filters = []): array
    {
        $student->loadMissing('classRoom.school');
        $rows = $this->rows($student, $filters);

        return [
            'student' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'admission_number' => $student->admission_number,
                'email' => $student->email,
                'class' => $student->classRoom?->name,
                'school' => $student->classRoom?->school?->name,
                'status' => $student->status,
            ],
            'filters' => [
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ],
            'summary' => $this->summary($rows),
            'rows' => $rows,
        ];
    }

    private function recordsQuery(Student $student, array $filters): Builder
    {
        return AttendanceRecord::query()
            ->where('student_id', $student->id)
            ->whereHas('session', function (Builder $query) use ($filters): void {
                $query
                    ->when($filters['from'] ?? null, fn (Builder $builder, string $from) => $builder->whereDate('session_date', '>=', $from))
                    ->when($filters['to'] ?? null, fn (Builder $builder, string $to) => $builder->whereDate('session_date', '<=', $to));
            });
    }
}
