<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WeeklyDutyRosterEntry extends Model
{
    protected $fillable = [
        'weekly_duty_roster_id',
        'category',
        'location',
        'time_slot',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function roster(): BelongsTo
    {
        return $this->belongsTo(WeeklyDutyRoster::class, 'weekly_duty_roster_id');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'weekly_duty_roster_entry_user');
    }
}
