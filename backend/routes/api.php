<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Attendance\AttendanceRecordController;
use App\Http\Controllers\Attendance\AttendanceSessionController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\MicrosoftAuthController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\Dynamics\DynamicsStudentSyncController;
use App\Http\Controllers\Dynamics\DynamicsSyncController;
use App\Http\Controllers\Dynamics\DynamicsAttendanceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\Students\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('microsoft/exchange', [MicrosoftAuthController::class, 'exchange']);
        Route::post('refresh', [MicrosoftAuthController::class, 'refresh']);

        Route::middleware('auth.jwt')->group(function (): void {
            Route::get('me', [MicrosoftAuthController::class, 'me']);
            Route::post('logout', [MicrosoftAuthController::class, 'logout']);
        });
    });

    Route::middleware(['auth.jwt', 'tenant.class'])->group(function (): void {
        Route::get('dashboard/admin', [AdminDashboardController::class, 'index'])->middleware('role:admin,ict_staff');
        Route::get('dashboard/teacher', [AdminDashboardController::class, 'teacher']);

        Route::get('classes', [ClassController::class, 'index']);
        Route::get('schools', [SchoolController::class, 'index']);
        Route::get('schools/current', [SchoolController::class, 'current']);
        Route::post('schools/select', [SchoolController::class, 'select']);
        Route::post('schools/clear', [SchoolController::class, 'clear'])->middleware('role:admin,ict_staff');
        Route::get('subjects', [SubjectController::class, 'index']);
        Route::get('teachers', [TeacherController::class, 'index'])->middleware('role:admin,ict_staff');
        Route::put('teachers/{user}/schools', [TeacherController::class, 'syncSchools'])->middleware('role:admin,ict_staff');
        Route::put('teachers/{user}/assignments', [TeacherController::class, 'syncAssignments'])->middleware('role:admin,ict_staff');
        Route::get('students', [StudentController::class, 'index']);
        Route::get('students/{student}', [StudentController::class, 'show']);
        Route::get('students/{student}/attendance-history', [StudentController::class, 'history']);

        Route::get('attendance-sessions', [AttendanceSessionController::class, 'index']);
        Route::post('attendance-sessions', [AttendanceSessionController::class, 'store'])->middleware('role:teacher,admin,ict_staff');
        Route::get('attendance-sessions/{attendanceSession}', [AttendanceSessionController::class, 'show']);
        Route::patch('attendance-sessions/{attendanceSession}/close', [AttendanceSessionController::class, 'close'])->middleware('role:teacher,admin,ict_staff');
        Route::put('attendance-sessions/{attendanceSession}/records', [AttendanceRecordController::class, 'upsert'])->middleware('role:teacher,admin,ict_staff');

        Route::get('reports/attendance-summary', [ReportController::class, 'attendanceSummary'])->middleware('role:admin,ict_staff');
        Route::get('reports/class-trends', [ReportController::class, 'classTrends'])->middleware('role:admin,ict_staff');
        Route::get('reports/student-trends', [ReportController::class, 'studentTrends'])->middleware('role:admin,ict_staff');
        Route::get('reports/export', [ReportController::class, 'export'])->middleware('role:admin,ict_staff');
        Route::get('reports/exports/{notification}/download', [ReportController::class, 'downloadExport'])->middleware('role:admin,ict_staff');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('role:admin,ict_staff');
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);

        Route::get('dynamics/syncs', [DynamicsSyncController::class, 'index'])->middleware('role:admin,ict_staff');
        Route::post('dynamics/syncs/{dynamicsSync}/retry', [DynamicsSyncController::class, 'retry'])->middleware('role:admin,ict_staff');
        Route::post('dynamics/classes/{class}/students/sync', [DynamicsStudentSyncController::class, 'syncClass'])->middleware('role:admin,ict_staff');
        Route::get('dynamics/attendance/form-streams', [DynamicsAttendanceController::class, 'formStreams']);
        Route::get('dynamics/attendance/students', [DynamicsAttendanceController::class, 'students']);
    });
});
