<?php

namespace App\Providers;

use App\Events\AttendanceSessionClosed;
use App\Listeners\QueueAttendanceSyncToDynamics;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        AttendanceSessionClosed::class => [
            QueueAttendanceSyncToDynamics::class,
        ],
    ];
}
