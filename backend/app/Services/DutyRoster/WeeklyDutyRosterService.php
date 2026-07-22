<?php

namespace App\Services\DutyRoster;

use App\Models\User;
use App\Models\WeeklyDutyRoster;
use App\Models\WeeklyDutyRosterEntry;
use App\Support\DutyRosterCategories;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WeeklyDutyRosterService
{
    /**
     * @return Builder<User>
     */
    public function schoolStaffQuery(int $schoolId): Builder
    {
        return User::query()
            ->where('status', 'active')
            ->whereNotNull('email')
            ->whereHas('schools', fn (Builder $schoolQuery) => $schoolQuery->where('schools.id', $schoolId))
            ->orderBy('name');
    }

    /**
     * @param  list<int>  $staffIds
     * @return list<int>
     */
    public function validateStaffIds(int $schoolId, array $staffIds): array
    {
        $staffIds = array_values(array_unique(array_map('intval', $staffIds)));

        if ($staffIds === []) {
            return [];
        }

        $allowed = $this->schoolStaffQuery($schoolId)
            ->whereIn('id', $staffIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $invalid = array_diff($staffIds, $allowed);
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'entries' => 'One or more selected staff members are not assigned to this school.',
            ]);
        }

        return $allowed;
    }

    public function create(int $schoolId, string $weekStart, ?string $weekEnd = null): WeeklyDutyRoster
    {
        $start = Carbon::parse($weekStart)->startOfDay();
        $end = $weekEnd !== null && $weekEnd !== ''
            ? Carbon::parse($weekEnd)->startOfDay()
            : $start->copy()->addDays(6);

        $existing = WeeklyDutyRoster::query()
            ->where('school_id', $schoolId)
            ->whereDate('week_start', $start->toDateString())
            ->with(['entries.staff'])
            ->first();

        if ($existing !== null) {
            if ($existing->entries()->doesntExist()) {
                $existing->seedStandardTemplate();
                $existing->load(['entries.staff']);
            }

            return $existing;
        }

        return DB::transaction(function () use ($schoolId, $start, $end): WeeklyDutyRoster {
            $roster = WeeklyDutyRoster::query()->create([
                'school_id' => $schoolId,
                'week_start' => $start,
                'week_end' => $end,
                'status' => WeeklyDutyRoster::STATUS_DRAFT,
                'published_at' => null,
            ]);

            $roster->seedStandardTemplate();
            $roster->load(['entries.staff']);

            return $roster;
        });
    }

    /**
     * Copy staff assignments from the most recent earlier week onto this roster.
     */
    public function copyFromPrevious(WeeklyDutyRoster $roster): WeeklyDutyRoster
    {
        $previous = WeeklyDutyRoster::query()
            ->where('school_id', $roster->school_id)
            ->where('id', '!=', $roster->id)
            ->whereDate('week_start', '<', $roster->week_start?->toDateString())
            ->orderByDesc('week_start')
            ->with(['entries.staff'])
            ->first();

        if ($previous === null) {
            throw ValidationException::withMessages([
                'roster' => 'No previous week roster was found to copy from.',
            ]);
        }

        $roster->loadMissing('entries.staff');

        $sourceByKey = [];
        foreach ($previous->entries as $entry) {
            $sourceByKey[$this->entryMatchKey($entry)] = $entry->staff->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        DB::transaction(function () use ($roster, $sourceByKey): void {
            foreach ($roster->entries as $entry) {
                $staffIds = $sourceByKey[$this->entryMatchKey($entry)] ?? [];
                $staffIds = $this->validateStaffIds((int) $roster->school_id, $staffIds);
                $entry->staff()->sync($staffIds);
            }
        });

        if ($roster->isPublished()) {
            $roster->status = WeeklyDutyRoster::STATUS_DRAFT;
            $roster->published_at = null;
            $roster->published_by = null;
            $roster->save();
        }

        return $roster->fresh(['entries.staff', 'publisher']);
    }

    public function publish(WeeklyDutyRoster $roster, ?int $publisherId = null): WeeklyDutyRoster
    {
        $roster->loadMissing('entries.staff');

        $unassigned = $roster->entries->filter(fn (WeeklyDutyRosterEntry $entry) => $entry->staff->isEmpty())->count();
        if ($unassigned > 0) {
            throw ValidationException::withMessages([
                'roster' => "{$unassigned} duty row(s) still need staff before publishing.",
            ]);
        }

        $roster->status = WeeklyDutyRoster::STATUS_PUBLISHED;
        $roster->published_at = now();
        $roster->published_by = $publisherId;
        $roster->save();

        return $roster->fresh(['entries.staff', 'publisher']);
    }

    public function unpublish(WeeklyDutyRoster $roster): WeeklyDutyRoster
    {
        $roster->status = WeeklyDutyRoster::STATUS_DRAFT;
        $roster->published_at = null;
        $roster->published_by = null;
        $roster->save();

        return $roster->fresh(['entries.staff', 'publisher']);
    }

    /**
     * @param  array{week_start?: string, week_end?: string|null, entries?: list<array<string, mixed>>}  $data
     */
    public function update(WeeklyDutyRoster $roster, array $data): WeeklyDutyRoster
    {
        if (isset($data['week_start'])) {
            $roster->week_start = Carbon::parse($data['week_start'])->startOfDay();
        }

        if (array_key_exists('week_end', $data)) {
            $roster->week_end = filled($data['week_end'])
                ? Carbon::parse((string) $data['week_end'])->startOfDay()
                : ($roster->week_start?->copy()->addDays(6));
        }

        $roster->save();

        if (isset($data['entries']) && is_array($data['entries'])) {
            $this->syncEntries($roster, $data['entries']);

            // Saving edits on a published roster returns it to draft until re-published.
            if ($roster->isPublished()) {
                $roster->status = WeeklyDutyRoster::STATUS_DRAFT;
                $roster->published_at = null;
                $roster->published_by = null;
                $roster->save();
            }
        }

        return $roster->fresh(['entries.staff']);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    public function syncEntries(WeeklyDutyRoster $roster, array $entries): void
    {
        DB::transaction(function () use ($roster, $entries): void {
            $keptIds = [];

            foreach ($entries as $index => $entryData) {
                $category = (string) ($entryData['category'] ?? '');
                if (! array_key_exists($category, DutyRosterCategories::labels())) {
                    throw ValidationException::withMessages([
                        "entries.{$index}.category" => 'The selected duty section is invalid.',
                    ]);
                }

                $staffIds = $this->validateStaffIds(
                    (int) $roster->school_id,
                    array_map('intval', $entryData['staff_ids'] ?? []),
                );

                $attributes = [
                    'category' => $category,
                    'location' => $entryData['location'] ?? null,
                    'time_slot' => $entryData['time_slot'] ?? null,
                    'sort_order' => (int) ($entryData['sort_order'] ?? (($index + 1) * 10)),
                ];

                $entryId = isset($entryData['id']) ? (int) $entryData['id'] : null;
                if ($entryId !== null) {
                    $entry = WeeklyDutyRosterEntry::query()
                        ->where('weekly_duty_roster_id', $roster->id)
                        ->find($entryId);

                    if ($entry === null) {
                        throw ValidationException::withMessages([
                            "entries.{$index}.id" => 'The duty row could not be found on this roster.',
                        ]);
                    }

                    $entry->update($attributes);
                } else {
                    $entry = $roster->entries()->create($attributes);
                }

                $entry->staff()->sync($staffIds);
                $keptIds[] = $entry->id;
            }

            $roster->entries()
                ->whereNotIn('id', $keptIds)
                ->each(function (WeeklyDutyRosterEntry $entry): void {
                    $entry->staff()->detach();
                    $entry->delete();
                });
        });
    }

    public function resetTemplate(WeeklyDutyRoster $roster): WeeklyDutyRoster
    {
        $roster->entries()->each(function (WeeklyDutyRosterEntry $entry): void {
            $entry->staff()->detach();
            $entry->delete();
        });

        $roster->seedStandardTemplate();

        $roster->status = WeeklyDutyRoster::STATUS_DRAFT;
        $roster->published_at = null;
        $roster->published_by = null;
        $roster->save();

        return $roster->fresh(['entries.staff', 'publisher']);
    }

    public function resolveCurrent(int $schoolId, ?CarbonInterface $date = null): ?WeeklyDutyRoster
    {
        return WeeklyDutyRoster::resolveForSchoolDate($schoolId, $date ?? now());
    }

    /**
     * Current week for the editor — includes drafts that are not yet published.
     */
    public function resolveCurrentForEditing(int $schoolId, ?CarbonInterface $date = null): ?WeeklyDutyRoster
    {
        return WeeklyDutyRoster::query()
            ->forSchoolWeek($schoolId, $date ?? now())
            ->with(['entries.staff'])
            ->orderByDesc('week_start')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(WeeklyDutyRoster $roster): array
    {
        $roster->loadMissing(['entries.staff', 'publisher']);

        return [
            'id' => $roster->id,
            'school_id' => $roster->school_id,
            'week_start' => $roster->week_start?->toDateString(),
            'week_end' => $roster->week_end?->toDateString(),
            'week_label' => $roster->weekLabel(),
            'status' => $roster->status ?? WeeklyDutyRoster::STATUS_DRAFT,
            'published_at' => $roster->published_at?->toIso8601String(),
            'published_by' => $roster->published_by,
            'published_by_name' => $roster->publisher?->name,
            'entries' => $roster->entries->map(fn (WeeklyDutyRosterEntry $entry): array => [
                'id' => $entry->id,
                'category' => $entry->category,
                'category_label' => DutyRosterCategories::label($entry->category),
                'location' => $entry->location,
                'time_slot' => $entry->time_slot,
                'sort_order' => $entry->sort_order,
                'staff_ids' => $entry->staff->pluck('id')->values()->all(),
                'staff' => $entry->staff->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])->values()->all(),
            ])->values()->all(),
            'sections' => $roster->sectionsForDisplay(),
        ];
    }

    private function entryMatchKey(WeeklyDutyRosterEntry $entry): string
    {
        return implode('|', [
            $entry->category,
            (string) ($entry->location ?? ''),
            (string) ($entry->time_slot ?? ''),
        ]);
    }
}
