<?php

namespace App\Events;

use App\Models\AttendanceSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceSessionClosed
{
    use Dispatchable, SerializesModels;

    public function __construct(public AttendanceSession $session)
    {
    }
}
