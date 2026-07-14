<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'level',
        'is_junior',
        'active',
        'dynamics_id',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_junior' => 'boolean',
    ];

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'school_user')
            ->withTimestamps();
    }

    public function students(): HasManyThrough
    {
        return $this->hasManyThrough(Student::class, SchoolClass::class, 'school_id', 'class_id');
    }

    public function gradeMasterAssignments(): HasMany
    {
        return $this->hasMany(GradeMasterAssignment::class);
    }

    public function rollCallReportRecipients(): HasMany
    {
        return $this->hasMany(RollCallReportRecipient::class);
    }

    public function weeklyDutyRosters(): HasMany
    {
        return $this->hasMany(WeeklyDutyRoster::class);
    }

    public function rollCallSettings(): HasOne
    {
        return $this->hasOne(SchoolRollCallSetting::class);
    }

    public function rollCallSettingsOrDefault(): SchoolRollCallSetting
    {
        return $this->rollCallSettings()->firstOrCreate(
            ['school_id' => $this->id],
            [
                'notify_school_admins' => true,
                'notify_homeroom_teacher' => true,
                'notify_grade_master' => true,
                'notify_session_teacher' => true,
                'notify_duty_roster' => true,
                'assigned_recipients_only' => false,
            ],
        );
    }
}
