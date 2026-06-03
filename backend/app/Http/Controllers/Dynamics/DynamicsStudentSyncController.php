<?php

namespace App\Http\Controllers\Dynamics;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Services\Integrations\DynamicsStudentSyncService;
use Illuminate\Http\JsonResponse;

class DynamicsStudentSyncController extends Controller
{
    public function __construct(
        private readonly DynamicsStudentSyncService $syncService,
    ) {
    }

    public function syncClass(SchoolClass $class): JsonResponse
    {
        $result = $this->syncService->syncClassStudents($class);

        return response()->json([
            'message' => 'Dynamics class student sync completed.',
            'data' => $result,
        ]);
    }
}
