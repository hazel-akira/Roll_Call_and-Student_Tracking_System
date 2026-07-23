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
        'logo_path',
        'mail_from',
    ];

    protected $appends = [
        'logo_url',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_junior' => 'boolean',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        return \App\Support\ReportBranding::logoPublicUrl($this);
    }

    /**
     * Resolve the Microsoft Graph send-as mailbox for this school.
     * Order: school.mail_from → config schools.mail_from[code] → MS_GRAPH_MAIL_FROM.
     */
    public function resolvedMailFrom(): ?string
    {
        if (filled($this->mail_from)) {
            return (string) $this->mail_from;
        }

        $code = is_string($this->code) ? $this->code : '';
        $byCode = config('schools.mail_from.'.$code);
        if (is_string($byCode) && filled($byCode)) {
            return $byCode;
        }

        $fallback = config('services.microsoft_graph.mail_from');

        return filled($fallback) ? (string) $fallback : null;
    }

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

    public function dutyRosterTemplateEntries(): HasMany
    {
        return $this->hasMany(SchoolDutyRosterTemplateEntry::class)->orderBy('sort_order');
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
