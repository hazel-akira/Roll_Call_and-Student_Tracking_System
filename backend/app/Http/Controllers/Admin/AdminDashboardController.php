<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\AttendanceSessionResource;
use App\Http\Resources\DynamicsSyncResource;
use App\Http\Resources\NotificationResource;
use App\Services\Dashboard\AdminDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __construct(private readonly AdminDashboardService $dashboardService)
    {
    }

    public function index(): JsonResponse
    {
        $summary = $this->dashboardService->adminSummary();

        return response()->json([
            'stats' => $summary['stats'],
            'recent_audit_logs' => AuditLogResource::collection($summary['recent_audit_logs']),
            'recent_sync_failures' => DynamicsSyncResource::collection($summary['recent_sync_failures']),
        ]);
    }

    public function teacher(Request $request): JsonResponse
    {
        $summary = $this->dashboardService->teacherSummary($request->user());

        return response()->json([
            'stats' => $summary['stats'],
            'today_sessions' => AttendanceSessionResource::collection($summary['today_sessions_list']),
            'notifications' => NotificationResource::collection($summary['notifications']),
        ]);
    }
}
