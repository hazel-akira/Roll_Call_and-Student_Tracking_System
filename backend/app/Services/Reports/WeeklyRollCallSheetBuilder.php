<?php

namespace App\Services\Reports;

use App\Models\AttendanceSession;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WeeklyRollCallSheetBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(array $filters): array
    {
        $from = isset($filters['from']) ? Carbon::parse($filters['from']) : now()->startOfWeek();
        $to = isset($filters['to']) ? Carbon::parse($filters['to']) : now()->endOfWeek();
        $school = isset($filters['school_id']) ? School::query()->find($filters['school_id']) : null;

        $sessions = $this->sessionsQuery($filters)
            ->with(['classRoom', 'records.student'])
            ->orderBy('session_date')
            ->orderBy('started_at')
            ->orderBy('class_id')
            ->get();

        $rosterCounts = $this->rosterCountsByClass($sessions);
        $periodGroups = $this->groupSessions($sessions, $rosterCounts);

        return [
            'school_name' => strtoupper((string) ($school?->name ?? 'SCHOOL')),
            'year' => $to->format('Y'),
            'week_number' => $from->isoWeek(),
            'from_date' => $from->format('d/m/Y'),
            'to_date' => $to->format('d/m/Y'),
            'period_groups' => $periodGroups,
            'absentees' => $this->collectAbsentees($sessions),
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

    /**
     * @param  Collection<int, AttendanceSession>  $sessions
     * @return array<int, int>
     */
    private function rosterCountsByClass(Collection $sessions): array
    {
        $classIds = $sessions->pluck('class_id')->unique()->filter()->values();

        if ($classIds->isEmpty()) {
            return [];
        }

        return Student::query()
            ->selectRaw('class_id, count(*) as aggregate')
            ->whereIn('class_id', $classIds)
            ->where('status', 'active')
            ->groupBy('class_id')
            ->pluck('aggregate', 'class_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @param  Collection<int, AttendanceSession>  $sessions
     * @param  array<int, int>  $rosterCounts
     * @return array<int, array<string, mixed>>
     */
    private function groupSessions(Collection $sessions, array $rosterCounts): array
    {
        $groups = [];

        foreach ($sessions as $session) {
            $sessionDate = $session->session_date ?? $session->started_at ?? now();
            $dayKey = $sessionDate->toDateString();
            $periodKey = $this->periodKey($session);
            $class = $session->classRoom;
            $classId = $session->class_id;
            $expected = $rosterCounts[$classId] ?? $session->records->count();
            $present = $session->records->whereIn('status', ['present', 'late', 'excused'])->count();
            $absent = max(0, $expected - $present);

            if (! isset($groups[$dayKey])) {
                $groups[$dayKey] = [
                    'day' => $sessionDate->format('l'),
                    'periods' => [],
                ];
            }

            if (! isset($groups[$dayKey]['periods'][$periodKey])) {
                $groups[$dayKey]['periods'][$periodKey] = [
                    'period' => $this->periodLabel($session),
                    'classes' => [],
                ];
            }

            $groups[$dayKey]['periods'][$periodKey]['classes'][$classId] = [
                'class' => $this->formatClassLabel($class),
                'expected' => $expected,
                'present' => $present,
                'absent' => $absent,
                'sort_key' => $this->classSortKey($class),
            ];
        }

        $result = [];

        foreach ($groups as $dayGroup) {
            $periods = [];

            foreach ($dayGroup['periods'] as $periodGroup) {
                $classes = collect($periodGroup['classes'])
                    ->sortBy('sort_key')
                    ->values()
                    ->all();

                $periods[] = [
                    'period' => $periodGroup['period'],
                    'classes' => $classes,
                    'total' => [
                        'class' => 'TOTAL',
                        'expected' => collect($classes)->sum('expected'),
                        'present' => collect($classes)->sum('present'),
                        'absent' => collect($classes)->sum('absent'),
                    ],
                ];
            }

            $result[] = [
                'day' => $dayGroup['day'],
                'periods' => $periods,
            ];
        }

        return $result;
    }

    /**
     * @param  Collection<int, AttendanceSession>  $sessions
     * @return array<int, array{name: string, reason: string}>
     */
    private function collectAbsentees(Collection $sessions): array
    {
        $absentees = [];

        foreach ($sessions as $session) {
            foreach ($session->records as $record) {
                if ($record->status !== 'absent') {
                    continue;
                }

                $student = $record->student;
                $name = $student ? $this->formatStudentName($student) : 'UNKNOWN';

                $absentees[] = [
                    'name' => $name,
                    'reason' => trim((string) ($record->remark ?? '')) ?: '—',
                    'day' => optional($session->session_date)->format('l') ?? '',
                    'period' => $this->periodLabel($session),
                    'class' => $this->formatClassLabel($session->classRoom),
                ];
            }
        }

        return $absentees;
    }

    private function periodKey(AttendanceSession $session): string
    {
        $sessionDate = $session->session_date ?? $session->started_at ?? now();
        $startedAt = $session->started_at ?? $sessionDate;
        $hour = (int) $startedAt->format('G');
        $bucket = $hour >= 19 ? 'night' : 'evening';

        return $sessionDate->toDateString().'|'.$bucket;
    }

    private function periodLabel(AttendanceSession $session): string
    {
        $startedAt = $session->started_at ?? $session->session_date ?? now();
        $hour = (int) $startedAt->format('G');
        $periodName = $hour >= 19 ? 'Night' : 'Evening';
        $time = str_replace(['AM', 'PM'], ['A.M', 'P.M'], $startedAt->format('g:i A'));

        return sprintf('%s %s', $periodName, $time);
    }

    private function formatClassLabel(?SchoolClass $class): string
    {
        if (! $class) {
            return 'Class';
        }

        if (preg_match('/(\d+)/', (string) $class->grade_level, $matches) === 1 && filled($class->section)) {
            return 'Class '.$matches[1].strtoupper((string) $class->section);
        }

        return strtoupper((string) $class->name);
    }

    private function classSortKey(?SchoolClass $class): string
    {
        if (! $class) {
            return 'zzzz';
        }

        if (preg_match('/(\d+)/', (string) $class->grade_level, $matches) === 1) {
            return sprintf('%04d-%s', (int) $matches[1], strtoupper((string) ($class->section ?? '')));
        }

        return strtoupper((string) $class->name);
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
