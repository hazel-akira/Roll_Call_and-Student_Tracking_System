<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicsSync extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_session_id',
        'integration',
        'direction',
        'status',
        'external_reference',
        'payload',
        'response',
        'error_message',
        'synced_at',
        'retries',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }
}
