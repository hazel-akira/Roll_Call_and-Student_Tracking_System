<?php

namespace App\Http\Controllers\Dynamics;

use App\Http\Controllers\Controller;
use App\Http\Resources\DynamicsSyncResource;
use App\Jobs\SyncAttendanceToDynamics;
use App\Models\DynamicsSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynamicsSyncController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $syncs = DynamicsSync::query()
            ->with('attendanceSession')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => DynamicsSyncResource::collection($syncs),
        ]);
    }

    public function retry(DynamicsSync $dynamicsSync): JsonResponse
    {
        $dynamicsSync->forceFill([
            'status' => 'retrying',
            'error_message' => null,
        ])->save();

        SyncAttendanceToDynamics::dispatch($dynamicsSync->id);

        return response()->json([
            'message' => 'Dynamics retry queued successfully.',
            'data' => DynamicsSyncResource::make($dynamicsSync),
        ], 202);
    }
}
