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

        $roster = WeeklyDutyRoster::query()->create([
            'school_id' => $schoolId,
            'week_start' => $start,
            'week_end' => $end,
        ]);

        $roster->seedStandardTemplate();
        $roster->load(['entries.staff']);

        return $roster;
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

        return $roster->fresh(['entries.staff']);
    }

    public function resolveCurrent(int $schoolId, ?CarbonInterface $date = null): ?WeeklyDutyRoster
    {
        return WeeklyDutyRoster::resolveForSchoolDate($schoolId, $date ?? now());
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(WeeklyDutyRoster $roster): array
    {
        $roster->loadMissing('entries.staff');

        return [
            'id' => $roster->id,
            'school_id' => $roster->school_id,
            'week_start' => $roster->week_start?->toDateString(),
            'week_end' => $roster->week_end?->toDateString(),
            'week_label' => $roster->weekLabel(),
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
        ];
    }
}
