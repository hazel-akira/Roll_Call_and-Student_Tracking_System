<?php

namespace App\Services\Dashboard;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\DynamicsSync;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    public function adminSummary(?string $schoolId = null): array
    {
        $today = now()->toDateString();

        $sessionScope = function ($query) use ($today, $schoolId): void {
            $query->whereDate('session_date', $today);
            if ($schoolId !== null) {
                $query->whereHas('classRoom', fn ($classQuery) => $classQuery->where('school_id', $schoolId));
            }
        };

        $totalAttendanceRecordsToday = AttendanceRecord::query()
            ->whereHas('session', $sessionScope)
            ->count();

        $presentToday = AttendanceRecord::query()
            ->where('status', 'present')
            ->whereHas('session', $sessionScope)
            ->count();

        $unresolvedAbsences = AttendanceRecord::query()
            ->whereIn('status', ['absent', 'late', 'excused'])
            ->whereHas('session', $sessionScope)
            ->count();

        return [
            'stats' => [
                'students' => $this->scopedStudents($schoolId)->count(),
                'teachers' => User::query()->whereHas('role', fn ($query) => $query->where('slug', 'teacher'))->count(),
                'classes' => $this->scopedClasses($schoolId)->count(),
                'today_sessions' => $this->scopedSessions($schoolId)->whereDate('session_date', $today)->count(),
                'attendance_rate_today' => $totalAttendanceRecordsToday > 0
                    ? round(($presentToday / $totalAttendanceRecordsToday) * 100, 2)
                    : 0,
                'unresolved_absences' => $unresolvedAbsences,
            ],
            'daily_attendance_trends' => $this->dailyAttendanceTrends($schoolId),
            'school_id' => $schoolId,
            'recent_audit_logs' => AuditLog::query()->latest('created_at')->limit(5)->get(),
            'recent_sync_failures' => DynamicsSync::query()->where('status', 'failed')->latest()->limit(5)->get(),
        ];
    }

    private function dailyAttendanceTrends(?string $schoolId = null): Collection
    {
        $windowStart = now()->subDays(6)->toDateString();
        $raw = $this->scopedSessions($schoolId)
            ->selectRaw(
                "session_date, sum(case when attendance_records.status = 'present' then 1 else 0 end) as present, sum(case when attendance_records.status <> 'present' then 1 else 0 end) as absent"
            )
            ->join('attendance_records', 'attendance_records.attendance_session_id', '=', 'attendance_sessions.id')
            ->whereDate('session_date', '>=', $windowStart)
            ->groupBy('session_date')
            ->orderBy('session_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->session_date)->toDateString());

        return collect(range(0, 6))
            ->map(function (int $offset) use ($raw) {
                $date = now()->subDays(6 - $offset)->toDateString();
                $row = $raw->get($date);

                return [
                    'session_date' => $date,
                    'present' => (int) ($row->present ?? 0),
                    'absent' => (int) ($row->absent ?? 0),
                ];
            });
    }

    public function teacherSummary(User $teacher, ?string $schoolId = null): array
    {
        $today = now()->toDateString();
        $todaySessions = $this->scopedSessions($schoolId)
            ->with(['classRoom', 'subject'])
            ->where('teacher_id', $teacher->id)
            ->whereDate('session_date', $today)
            ->orderBy('started_at')
            ->get();

        $markedRecords = AttendanceRecord::query()
            ->where('marked_by', $teacher->id)
            ->whereDate('updated_at', $today)
            ->count();

        return [
            'stats' => [
                'today_sessions' => $todaySessions->count(),
                'open_sessions' => $todaySessions->where('status', 'open')->count(),
                'students_marked_today' => $markedRecords,
                'assigned_streams' => $teacher->assignedSubjects()->count(),
            ],
            'today_sessions_list' => $todaySessions,
            'notifications' => $teacher->notifications()->whereNull('read_at')->latest()->limit(5)->get(),
            'school_id' => $schoolId,
        ];
    }

    private function scopedClasses(?string $schoolId)
    {
        return SchoolClass::query()->when($schoolId !== null, fn ($query) => $query->where('school_id', $schoolId));
    }

    private function scopedStudents(?string $schoolId)
    {
        return Student::query()->when($schoolId !== null, function ($query) use ($schoolId): void {
            $query->whereHas('classRoom', fn ($classQuery) => $classQuery->where('school_id', $schoolId));
        });
    }

    private function scopedSessions(?string $schoolId)
    {
        return AttendanceSession::query()->when($schoolId !== null, function ($query) use ($schoolId): void {
            $query->whereHas('classRoom', fn ($classQuery) => $classQuery->where('school_id', $schoolId));
        });
    }
}
