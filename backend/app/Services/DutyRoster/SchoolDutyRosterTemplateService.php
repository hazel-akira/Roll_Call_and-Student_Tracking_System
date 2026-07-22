<?php

namespace App\Services\DutyRoster;

use App\Models\School;
use App\Models\SchoolDutyRosterTemplateEntry;
use App\Support\DutyRosterCategories;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SchoolDutyRosterTemplateService
{
    /**
     * Ensure the school has a default template, seeding from the global standard when empty.
     *
     * @return Collection<int, SchoolDutyRosterTemplateEntry>
     */
    public function ensureTemplate(School $school): Collection
    {
        $existing = $school->dutyRosterTemplateEntries()->orderBy('sort_order')->get();
        if ($existing->isNotEmpty()) {
            return $existing;
        }

        return $this->resetToGlobalStandard($school);
    }

    /**
     * Replace the school's default template with the shared Pioneer standard layout.
     *
     * @return Collection<int, SchoolDutyRosterTemplateEntry>
     */
    public function resetToGlobalStandard(School $school): Collection
    {
        return DB::transaction(function () use ($school): Collection {
            $school->dutyRosterTemplateEntries()->delete();

            foreach (DutyRosterCategories::standardTemplate() as $row) {
                $school->dutyRosterTemplateEntries()->create($row);
            }

            return $school->dutyRosterTemplateEntries()->orderBy('sort_order')->get();
        });
    }

    /**
     * Rows used when creating or resetting a weekly roster for this school.
     *
     * @return list<array{category: string, location: string|null, time_slot: string|null, sort_order: int}>
     */
    public function templateRowsForSchool(School|int $school): array
    {
        $schoolModel = $school instanceof School
            ? $school
            : School::query()->find($school);

        if ($schoolModel === null) {
            return DutyRosterCategories::standardTemplate();
        }

        return $this->ensureTemplate($schoolModel)
            ->map(fn (SchoolDutyRosterTemplateEntry $entry): array => $entry->toTemplateRow())
            ->values()
            ->all();
    }
}
