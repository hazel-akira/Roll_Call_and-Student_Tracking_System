<?php

namespace App\Listeners;

use App\Events\AttendanceSessionClosed;
use App\Jobs\SyncAttendanceToDynamics;
use App\Services\Integrations\DynamicsSyncService;

class QueueAttendanceSyncToDynamics
{
    public function __construct(private readonly DynamicsSyncService $syncService)
    {
    }

    public function handle(AttendanceSessionClosed $event): void
    {
        $sync = $this->syncService->queue($event->session);

        SyncAttendanceToDynamics::dispatch($sync->id);
    }
}
