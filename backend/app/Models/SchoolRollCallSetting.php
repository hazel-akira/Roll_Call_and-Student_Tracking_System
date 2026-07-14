<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolRollCallSetting extends Model
{
    protected $primaryKey = 'school_id';

    public $incrementing = false;

    protected $fillable = [
        'school_id',
        'notify_school_admins',
        'notify_homeroom_teacher',
        'notify_grade_master',
        'notify_session_teacher',
        'notify_duty_roster',
        'assigned_recipients_only',
    ];

    protected function casts(): array
    {
        return [
            'notify_school_admins' => 'boolean',
            'notify_homeroom_teacher' => 'boolean',
            'notify_grade_master' => 'boolean',
            'notify_session_teacher' => 'boolean',
            'notify_duty_roster' => 'boolean',
            'assigned_recipients_only' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
