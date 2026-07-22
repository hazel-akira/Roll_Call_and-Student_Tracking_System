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
            ->join('students', 'students.id', '=', 'attendance_records.student_id')
            ->orderBy('students.admission_number')
            ->select('attendance_records.*')
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

    /**
     * Week-by-week attendance overview for the Reports table.
     *
     * @return list<array<string, mixed>>
     */
    public function weeklyOverview(array $filters): array
    {
        $sessions = AttendanceSession::query()
            ->with(['classRoom.school', 'records'])
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('session_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('session_date', '<=', $to))
            ->when($filters['school_id'] ?? null, fn (Builder $query, int $schoolId) => $query->whereHas('classRoom', fn (Builder $classQuery) => $classQuery->where('school_id', $schoolId)))
            ->when($filters['class_id'] ?? null, fn (Builder $query, int $classId) => $query->where('class_id', $classId))
            ->when($filters['academic_year'] ?? null, function (Builder $query, string $year): void {
                $query->whereHas('classRoom', fn (Builder $classQuery) => $classQuery->where('academic_year', $year));
            })
            ->when($filters['term'] ?? null, function (Builder $query, int $term): void {
                $months = match ($term) {
                    1 => [1, 2, 3, 4],
                    2 => [5, 6, 7, 8],
                    default => [9, 10, 11, 12],
                };
                $query->where(function (Builder $monthQuery) use ($months): void {
                    foreach ($months as $month) {
                        $monthQuery->orWhereMonth('session_date', $month);
                    }
                });
            })
            ->when($filters['week_start'] ?? null, function (Builder $query, string $weekStart): void {
                $start = \Illuminate\Support\Carbon::parse($weekStart)->startOfWeek();
                $end = $start->copy()->endOfWeek();
                $query->whereDate('session_date', '>=', $start->toDateString())
                    ->whereDate('session_date', '<=', $end->toDateString());
            })
            ->orderByDesc('session_date')
            ->limit(400)
            ->get();

        $weeks = [];

        foreach ($sessions as $session) {
            $sessionDate = $session->session_date?->copy() ?? now();
            $weekStart = $sessionDate->copy()->startOfWeek();
            $weekEnd = $weekStart->copy()->addDays(6);
            $schoolId = (int) ($session->classRoom?->school_id ?? 0);
            $key = $weekStart->toDateString().'|'.$schoolId;

            if (! isset($weeks[$key])) {
                $roster = $schoolId > 0
                    ? \App\Models\WeeklyDutyRoster::resolveForSchoolDate($schoolId, $weekStart)
                    : null;

                $dutyNames = $roster
                    ? $roster->entries->flatMap(fn ($entry) => $entry->staff->pluck('name'))->unique()->filter()->values()->take(3)->implode(', ')
                    : '';

                $weeks[$key] = [
                    'week_start' => $weekStart->toDateString(),
                    'week_end' => $weekEnd->toDateString(),
                    'week_label' => strtoupper($weekStart->format('jS M').' - '.$weekEnd->format('jS M')),
                    'school_id' => $schoolId ?: null,
                    'school_name' => $session->classRoom?->school?->name,
                    'academic_year' => $session->classRoom?->academic_year,
                    'term' => $this->schoolTerm($weekStart),
                    'teacher_on_duty' => $dutyNames !== '' ? $dutyNames : '—',
                    'present' => 0,
                    'absent' => 0,
                    'excused' => 0,
                    'late' => 0,
                    'records' => 0,
                    'generated_on' => null,
                ];
            }

            foreach ($session->records as $record) {
                $status = (string) $record->status;
                if (isset($weeks[$key][$status])) {
                    $weeks[$key][$status]++;
                }
                $weeks[$key]['records']++;
            }

            $generatedCandidate = optional($session->closed_at ?? $session->updated_at)->toDateString();
            if ($generatedCandidate && (
                $weeks[$key]['generated_on'] === null
                || $generatedCandidate > $weeks[$key]['generated_on']
            )) {
                $weeks[$key]['generated_on'] = $generatedCandidate;
            }
        }

        return array_values($weeks);
    }

    private function schoolTerm(\Illuminate\Support\Carbon $date): int
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
