<?php

namespace Tests\Feature\DutyRoster;

use App\Models\School;
use App\Models\SchoolDutyRosterTemplateEntry;
use App\Services\DutyRoster\SchoolDutyRosterTemplateService;
use App\Support\DutyRosterCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesWithJwt;
use Tests\TestCase;

class SchoolDutyRosterTemplateTest extends TestCase
{
    use AuthenticatesWithJwt;
    use RefreshDatabase;

    public function test_ensure_template_seeds_global_standard_when_empty(): void
    {
        $school = School::query()->create([
            'name' => 'Pioneer School',
            'code' => 'PS',
            'level' => 'senior',
            'active' => true,
        ]);

        $rows = app(SchoolDutyRosterTemplateService::class)->ensureTemplate($school);

        $this->assertCount(count(DutyRosterCategories::standardTemplate()), $rows);
        $this->assertDatabaseCount('school_duty_roster_template_entries', count(DutyRosterCategories::standardTemplate()));
    }

    public function test_new_weekly_roster_uses_school_specific_template(): void
    {
        [$dean, $school] = $this->createDeanWithSchool();

        SchoolDutyRosterTemplateEntry::query()->where('school_id', $school->id)->delete();
        SchoolDutyRosterTemplateEntry::query()->create([
            'school_id' => $school->id,
            'category' => DutyRosterCategories::GAMES,
            'location' => 'Main Pitch',
            'time_slot' => null,
            'sort_order' => 10,
        ]);
        SchoolDutyRosterTemplateEntry::query()->create([
            'school_id' => $school->id,
            'category' => DutyRosterCategories::DINING_HALL,
            'location' => 'Hall A',
            'time_slot' => 'Lunch',
            'sort_order' => 20,
        ]);

        $response = $this->postJson('/api/v1/duty-rosters', [
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end' => now()->startOfWeek()->addDays(6)->toDateString(),
        ], $this->authHeaders($dean));

        $response->assertCreated();
        $rosterId = (int) $response->json('data.id');

        $this->assertDatabaseCount('weekly_duty_roster_entries', 2);
        $this->assertDatabaseHas('weekly_duty_roster_entries', [
            'weekly_duty_roster_id' => $rosterId,
            'category' => DutyRosterCategories::GAMES,
            'location' => 'Main Pitch',
        ]);
        $this->assertDatabaseHas('weekly_duty_roster_entries', [
            'weekly_duty_roster_id' => $rosterId,
            'category' => DutyRosterCategories::DINING_HALL,
            'location' => 'Hall A',
            'time_slot' => 'Lunch',
        ]);
    }

    public function test_reset_template_restores_school_default_not_global_standard(): void
    {
        [$dean, $school] = $this->createDeanWithSchool();

        app(SchoolDutyRosterTemplateService::class)->resetToGlobalStandard($school);
        SchoolDutyRosterTemplateEntry::query()->where('school_id', $school->id)->delete();
        SchoolDutyRosterTemplateEntry::query()->create([
            'school_id' => $school->id,
            'category' => DutyRosterCategories::BOARDING,
            'location' => 'House 1',
            'time_slot' => null,
            'sort_order' => 10,
        ]);

        $create = $this->postJson('/api/v1/duty-rosters', [
            'week_start' => now()->startOfWeek()->toDateString(),
        ], $this->authHeaders($dean));
        $create->assertCreated();
        $rosterId = (int) $create->json('data.id');

        // Mutate the week away from the school default.
        $this->putJson("/api/v1/duty-rosters/{$rosterId}", [
            'entries' => [
                [
                    'category' => DutyRosterCategories::ENTERTAINMENT,
                    'location' => 'Hall',
                    'time_slot' => null,
                    'sort_order' => 10,
                    'staff_ids' => [],
                ],
            ],
        ], $this->authHeaders($dean))->assertOk();

        $reset = $this->postJson(
            "/api/v1/duty-rosters/{$rosterId}/reset-template",
            [],
            $this->authHeaders($dean),
        );
        $reset->assertOk();

        $this->assertDatabaseCount('weekly_duty_roster_entries', 1);
        $this->assertDatabaseHas('weekly_duty_roster_entries', [
            'weekly_duty_roster_id' => $rosterId,
            'category' => DutyRosterCategories::BOARDING,
            'location' => 'House 1',
        ]);
    }

    public function test_meta_endpoint_returns_school_template(): void
    {
        [$dean, $school] = $this->createDeanWithSchool();
        app(SchoolDutyRosterTemplateService::class)->ensureTemplate($school);

        SchoolDutyRosterTemplateEntry::query()->where('school_id', $school->id)->delete();
        SchoolDutyRosterTemplateEntry::query()->create([
            'school_id' => $school->id,
            'category' => DutyRosterCategories::GAMES,
            'location' => 'Court 1',
            'time_slot' => null,
            'sort_order' => 10,
        ]);

        $response = $this->getJson(
            '/api/v1/duty-roster-meta',
            $this->authHeaders($dean),
        );

        $response
            ->assertOk()
            ->assertJsonPath('school_template.0.location', 'Court 1')
            ->assertJsonPath('school_template.0.category', DutyRosterCategories::GAMES);
    }
}
