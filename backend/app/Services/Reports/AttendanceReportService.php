<?php

namespace App\Services\Reports;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceReportService
{
    public function attendanceSummary(array $filters): array
    {
        $records = $this->recordsQuery($filters);
        $total = (clone $records)->count();

        $statusCounts = (clone $records)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $dailyBreakdown = AttendanceSession::query()
            ->selectRaw('session_date, attendance_records.status, count(*) as aggregate')
            ->join('attendance_records', 'attendance_records.attendance_session_id', '=', 'attendance_sessions.id')
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('session_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('session_date', '<=', $to))
            ->when($filters['school_id'] ?? null, fn (Builder $query, int $schoolId) => $query->whereHas('classRoom', fn (Builder $classQuery) => $classQuery->where('school_id', $schoolId)))
            ->when($filters['class_id'] ?? null, fn (Builder $query, int $classId) => $query->where('class_id', $classId))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, int $subjectId) => $query->where('subject_id', $subjectId))
            ->when($filters['teacher_id'] ?? null, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->groupBy('session_date', 'attendance_records.status')
            ->orderBy('session_date')
            ->get();

        return [
            'totals' => [
                'records' => $total,
                'present' => (int) ($statusCounts['present'] ?? 0),
                'absent' => (int) ($statusCounts['absent'] ?? 0),
                'late' => (int) ($statusCounts['late'] ?? 0),
                'excused' => (int) ($statusCounts['excused'] ?? 0),
                'attendance_rate' => $total > 0 ? round(((int) ($statusCounts['present'] ?? 0) / $total) * 100, 2) : 0,
            ],
            'daily_breakdown' => $dailyBreakdown,
        ];
    }

    public function classTrends(array $filters): Collection
    {
        return AttendanceSession::query()
            ->selectRaw('classes.id as class_id, classes.name as class_name, attendance_records.status, count(*) as aggregate')
            ->join('classes', 'classes.id', '=', 'attendance_sessions.class_id')
            ->join('attendance_records', 'attendance_records.attendance_session_id', '=', 'attendance_sessions.id')
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('session_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('session_date', '<=', $to))
            ->when($filters['school_id'] ?? null, fn (Builder $query, int $schoolId) => $query->where('classes.school_id', $schoolId))
            ->when($filters['class_id'] ?? null, fn (Builder $query, int $classId) => $query->where('attendance_sessions.class_id', $classId))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, int $subjectId) => $query->where('attendance_sessions.subject_id', $subjectId))
            ->groupBy('classes.id', 'classes.name', 'attendance_records.status')
            ->orderBy('classes.name')
            ->get();
    }

    public function studentTrends(array $filters): Collection
    {
        return AttendanceRecord::query()
            ->selectRaw("students.id as student_id, students.first_name, students.last_name, sum(case when attendance_records.status = 'absent' then 1 else 0 end) as absent_count, sum(case when attendance_records.status = 'late' then 1 else 0 end) as late_count")
            ->join('students', 'students.id', '=', 'attendance_records.student_id')
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('attendance_sessions.session_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('attendance_sessions.session_date', '<=', $to))
            ->when($filters['school_id'] ?? null, fn (Builder $query, int $schoolId) => $query->whereHas('student.classRoom', fn (Builder $classQuery) => $classQuery->where('school_id', $schoolId)))
            ->when($filters['class_id'] ?? null, fn (Builder $query, int $classId) => $query->where('students.class_id', $classId))
            ->groupBy('students.id', 'students.first_name', 'students.last_name')
            ->orderByDesc('absent_count')
            ->limit(20)
            ->get();
    }

    public function exportRows(array $filters): Collection
    {
        return $this->recordsQuery($filters)
            ->with(['student.classRoom', 'session.subject', 'session.teacher'])
            ->get()
            ->map(function (AttendanceRecord $record): array {
                return [
                    'session_date' => optional($record->session->session_date)->toDateString(),
                    'class' => $record->student?->classRoom?->name,
                    'student' => $record->student?->full_name,
                    'admission_number' => $record->student?->admission_number,
                    'subject' => $record->session?->subject?->name,
                    'teacher' => $record->session?->teacher?->name,
                    'status' => strtoupper($record->status),
                    'remark' => $record->remark,
                ];
            });
    }

    private function recordsQuery(array $filters): Builder
    {
        return AttendanceRecord::query()
            ->whereHas('session', function (Builder $query) use ($filters): void {
                $query
                    ->when($filters['from'] ?? null, fn (Builder $builder, string $from) => $builder->whereDate('session_date', '>=', $from))
                    ->when($filters['to'] ?? null, fn (Builder $builder, string $to) => $builder->whereDate('session_date', '<=', $to))
                    ->when($filters['school_id'] ?? null, fn (Builder $builder, int $schoolId) => $builder->whereHas('classRoom', fn (Builder $classQuery) => $classQuery->where('school_id', $schoolId)))
                    ->when($filters['class_id'] ?? null, fn (Builder $builder, int $classId) => $builder->where('class_id', $classId))
                    ->when($filters['subject_id'] ?? null, fn (Builder $builder, int $subjectId) => $builder->where('subject_id', $subjectId))
                    ->when($filters['teacher_id'] ?? null, fn (Builder $builder, int $teacherId) => $builder->where('teacher_id', $teacherId));
            });
    }
}
