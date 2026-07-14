<?php

namespace App\Models;

use App\Support\DutyRosterCategories;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyDutyRoster extends Model
{
    protected $fillable = [
        'school_id',
        'week_start',
        'week_end',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(WeeklyDutyRosterEntry::class)->orderBy('sort_order');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForSchoolWeek(Builder $query, int $schoolId, CarbonInterface $date): Builder
    {
        return $query
            ->where('school_id', $schoolId)
            ->whereDate('week_start', '<=', $date->toDateString())
            ->where(function (Builder $weekQuery) use ($date): void {
                $weekQuery
                    ->whereNull('week_end')
                    ->orWhereDate('week_end', '>=', $date->toDateString());
            })
            ->orderByDesc('week_start');
    }

    public function weekLabel(): string
    {
        $start = $this->week_start?->format('jS M');
        $end = $this->week_end?->format('jS M') ?? $this->week_start?->copy()->addDays(6)->format('jS M');

        if ($start && $end) {
            return strtoupper("{$start} - {$end}");
        }

        return $this->week_start?->format('D, M j, Y') ?? 'Weekly duty';
    }

    /**
     * @return list<array{category: string, location: string|null, time_slot: string|null, sort_order: int}>
     */
    public static function standardTemplateEntries(): array
    {
        return DutyRosterCategories::standardTemplate();
    }

    /**
     * @return list<string>
     */
    public function staffEmails(): array
    {
        $this->loadMissing('entries.staff');

        return $this->entries
            ->flatMap(fn (WeeklyDutyRosterEntry $entry) => $entry->staff->pluck('email'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array{title: string, rows: list<array{location: string|null, time_slot: string|null, staff: string}>}>
     */
    public function sectionsForDisplay(): array
    {
        $this->loadMissing('entries.staff');

        return $this->entries
            ->groupBy('category')
            ->map(function ($entries, string $category): array {
                return [
                    'title' => DutyRosterCategories::label($category),
                    'rows' => $entries->map(function (WeeklyDutyRosterEntry $entry): array {
                        return [
                            'location' => $entry->location,
                            'time_slot' => $entry->time_slot,
                            'staff' => $entry->staff->pluck('name')->filter()->implode(', '),
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function seedStandardTemplate(): void
    {
        if ($this->entries()->exists()) {
            return;
        }

        foreach (self::standardTemplateEntries() as $row) {
            $this->entries()->create($row);
        }
    }

    public static function resolveForSchoolDate(int $schoolId, CarbonInterface $date): ?self
    {
        return static::query()
            ->forSchoolWeek($schoolId, $date)
            ->with(['entries.staff'])
            ->orderByDesc('week_start')
            ->first();
    }
}

