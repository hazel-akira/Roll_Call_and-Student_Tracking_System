<?php

namespace App\Services\DutyRoster;

use App\Models\School;
use App\Models\SchoolDutyRosterTemplateEntry;
use App\Support\DutyRosterCategories;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SchoolDutyRosterTemplateService
{
    /**
     * Ensure the school has a default template, seeding from the global standard when empty.
     *
     * @return Collection<int, SchoolDutyRosterTemplateEntry>
     */
    public function ensureTemplate(School $school): Collection
    {
        if (! $this->templateTableReady()) {
            return collect();
        }

        try {
            $existing = $school->dutyRosterTemplateEntries()->orderBy('sort_order')->get();
            if ($existing->isNotEmpty()) {
                return $existing;
            }

            return $this->resetToGlobalStandard($school);
        } catch (QueryException $exception) {
            Log::warning('Unable to load school duty roster template; using global standard.', [
                'school_id' => $school->id,
                'error' => $exception->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Replace the school's default template with the shared Pioneer standard layout.
     *
     * @return Collection<int, SchoolDutyRosterTemplateEntry>
     */
    public function resetToGlobalStandard(School $school): Collection
    {
        if (! $this->templateTableReady()) {
            return collect();
        }

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

        if ($schoolModel === null || ! $this->templateTableReady()) {
            return DutyRosterCategories::standardTemplate();
        }

        $rows = $this->ensureTemplate($schoolModel);
        if ($rows->isEmpty()) {
            return DutyRosterCategories::standardTemplate();
        }

        return $rows
            ->map(fn (SchoolDutyRosterTemplateEntry $entry): array => $entry->toTemplateRow())
            ->values()
            ->all();
    }

    private function templateTableReady(): bool
    {
        try {
            return Schema::hasTable('school_duty_roster_template_entries');
        } catch (\Throwable) {
            return false;
        }
    }
}
