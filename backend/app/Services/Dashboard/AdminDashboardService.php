<?php

namespace App\Services\Dashboard;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\DynamicsSync;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;

class AdminDashboardService
{
    public function adminSummary(): array
    {
        $today = now()->toDateString();

        $totalAttendanceRecordsToday = AttendanceRecord::query()
            ->whereHas('session', fn ($query) => $query->whereDate('session_date', $today))
            ->count();

        $presentToday = AttendanceRecord::query()
            ->where('status', 'present')
            ->whereHas('session', fn ($query) => $query->whereDate('session_date', $today))
            ->count();

        return [
            'stats' => [
                'students' => Student::query()->count(),
                'teachers' => User::query()->whereHas('role', fn ($query) => $query->where('slug', 'teacher'))->count(),
                'classes' => SchoolClass::query()->count(),
                'today_sessions' => AttendanceSession::query()->whereDate('session_date', $today)->count(),
                'attendance_rate_today' => $totalAttendanceRecordsToday > 0
                    ? round(($presentToday / $totalAttendanceRecordsToday) * 100, 2)
                    : 0,
            ],
            'recent_audit_logs' => AuditLog::query()->latest('created_at')->limit(5)->get(),
            'recent_sync_failures' => DynamicsSync::query()->where('status', 'failed')->latest()->limit(5)->get(),
        ];
    }

    public function teacherSummary(User $teacher): array
    {
        $today = now()->toDateString();
        $todaySessions = AttendanceSession::query()
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
                'assigned_subjects' => $teacher->assignedSubjects()->count(),
            ],
            'today_sessions_list' => $todaySessions,
            'notifications' => $teacher->notifications()->whereNull('read_at')->latest()->limit(5)->get(),
        ];
    }
}
