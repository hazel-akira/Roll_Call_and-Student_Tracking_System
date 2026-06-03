<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\AttendanceSessionResource;
use App\Http\Resources\DynamicsSyncResource;
use App\Http\Resources\NotificationResource;
use App\Services\Dashboard\AdminDashboardService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $dashboardService,
        private readonly TenantService $tenantService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $summary = $this->dashboardService->adminSummary(
            $this->tenantService->effectiveSchoolId($request),
        );

        return response()->json([
            'stats' => $summary['stats'],
            'daily_attendance_trends' => $summary['daily_attendance_trends'],
            'recent_audit_logs' => AuditLogResource::collection($summary['recent_audit_logs'])->resolve(),
            'recent_sync_failures' => DynamicsSyncResource::collection($summary['recent_sync_failures'])->resolve(),
        ]);
    }

    public function teacher(Request $request): JsonResponse
    {
        $summary = $this->dashboardService->teacherSummary(
            $request->user(),
            $this->tenantService->effectiveSchoolId($request),
        );

        return response()->json([
            'stats' => $summary['stats'],
            'today_sessions' => AttendanceSessionResource::collection($summary['today_sessions_list'])->resolve(),
            'notifications' => NotificationResource::collection($summary['notifications'])->resolve(),
        ]);
    }
}
