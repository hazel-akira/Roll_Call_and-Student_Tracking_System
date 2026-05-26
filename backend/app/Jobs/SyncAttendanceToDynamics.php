<?php

namespace App\Jobs;

use App\Models\DynamicsSync;
use App\Services\Integrations\DynamicsSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncAttendanceToDynamics implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $dynamicsSyncId)
    {
    }

    public function handle(DynamicsSyncService $syncService): void
    {
        $sync = DynamicsSync::query()->find($this->dynamicsSyncId);

        if (! $sync) {
            return;
        }

        $syncService->sync($sync);
    }
}
