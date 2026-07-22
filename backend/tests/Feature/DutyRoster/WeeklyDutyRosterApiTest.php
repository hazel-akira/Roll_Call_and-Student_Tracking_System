<?php

namespace Tests\Feature\DutyRoster;

use App\Models\WeeklyDutyRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesWithJwt;
use Tests\TestCase;

class WeeklyDutyRosterApiTest extends TestCase
{
    use AuthenticatesWithJwt;
    use RefreshDatabase;

    public function test_dean_can_create_and_update_weekly_duty_roster(): void
    {
        [$dean, $school] = $this->createDeanWithSchool();
        $teacher = $this->createUserWithRole('teacher');
        $teacher->schools()->attach($school->id);

        $createResponse = $this->postJson('/api/v1/duty-rosters', [
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end' => now()->startOfWeek()->addDays(6)->toDateString(),
        ], $this->authHeaders($dean));

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.school_id', $school->id)
            ->assertJsonStructure(['data' => ['id', 'entries']]);

        $rosterId = $createResponse->json('data.id');
        $entries = $createResponse->json('data.entries');
        $this->assertNotEmpty($entries);

        $updateResponse = $this->putJson("/api/v1/duty-rosters/{$rosterId}", [
            'entries' => [
                [
                    'id' => $entries[0]['id'],
                    'category' => $entries[0]['category'],
                    'location' => $entries[0]['location'],
                    'time_slot' => $entries[0]['time_slot'],
                    'sort_order' => $entries[0]['sort_order'],
                    'staff_ids' => [$teacher->id],
                ],
            ],
        ], $this->authHeaders($dean));

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.entries.0.staff.0.id', $teacher->id);
    }

    public function test_dean_can_fetch_current_roster(): void
    {
        [$dean, $school] = $this->createDeanWithSchool();

        $roster = WeeklyDutyRoster::query()->create([
            'school_id' => $school->id,
            'week_start' => now()->startOfWeek(),
            'week_end' => now()->startOfWeek()->addDays(6),
        ]);
        $roster->seedStandardTemplate();

        $response = $this->getJson('/api/v1/duty-rosters/current', $this->authHeaders($dean));

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $roster->id);
    }

    public function test_teacher_cannot_manage_duty_rosters(): void
    {
        [$teacher] = $this->createTeacherWithSchool();

        $response = $this->getJson('/api/v1/duty-rosters', $this->authHeaders($teacher));

        $response->assertForbidden();
    }

    public function test_dean_cannot_access_roster_from_another_school(): void
    {
        [$dean] = $this->createDeanWithSchool();
        [, $otherSchool] = $this->createTeacherWithSchool(['code' => 'OTHER-SCHOOL']);

        $roster = WeeklyDutyRoster::query()->create([
            'school_id' => $otherSchool->id,
            'week_start' => now()->startOfWeek(),
            'week_end' => now()->startOfWeek()->addDays(6),
        ]);

        $response = $this->getJson("/api/v1/duty-rosters/{$roster->id}", $this->authHeaders($dean));

        $response->assertNotFound();
    }

    public function test_dean_can_copy_staff_from_previous_week(): void
    {
        [$dean, $school] = $this->createDeanWithSchool();
        $teacher = $this->createUserWithRole('teacher');
        $teacher->schools()->attach($school->id);

        $previous = WeeklyDutyRoster::query()->create([
            'school_id' => $school->id,
            'week_start' => now()->startOfWeek()->subWeek(),
            'week_end' => now()->startOfWeek()->subWeek()->addDays(6),
            'status' => WeeklyDutyRoster::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
        $previous->seedStandardTemplate();
        $previousEntry = $previous->entries()->firstOrFail();
        $previousEntry->staff()->sync([$teacher->id]);

        $current = WeeklyDutyRoster::query()->create([
            'school_id' => $school->id,
            'week_start' => now()->startOfWeek(),
            'week_end' => now()->startOfWeek()->addDays(6),
            'status' => WeeklyDutyRoster::STATUS_DRAFT,
        ]);
        $current->seedStandardTemplate();

        $response = $this->postJson(
            "/api/v1/duty-rosters/{$current->id}/copy-from-previous",
            [],
            $this->authHeaders($dean),
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $copiedEntry = $current->fresh(['entries.staff'])->entries
            ->firstWhere('category', $previousEntry->category);

        $this->assertNotNull($copiedEntry);
        $this->assertTrue($copiedEntry->staff->contains('id', $teacher->id));
    }

    public function test_dean_can_publish_fully_assigned_roster(): void
    {
        [$dean, $school] = $this->createDeanWithSchool();
        $teacher = $this->createUserWithRole('teacher');
        $teacher->schools()->attach($school->id);

        $createResponse = $this->postJson('/api/v1/duty-rosters', [
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end' => now()->startOfWeek()->addDays(6)->toDateString(),
        ], $this->authHeaders($dean));

        $createResponse->assertCreated()->assertJsonPath('data.status', 'draft');

        $rosterId = $createResponse->json('data.id');
        $entries = $createResponse->json('data.entries');

        $this->putJson("/api/v1/duty-rosters/{$rosterId}", [
            'entries' => collect($entries)->map(fn (array $entry): array => [
                'id' => $entry['id'],
                'category' => $entry['category'],
                'location' => $entry['location'],
                'time_slot' => $entry['time_slot'],
                'sort_order' => $entry['sort_order'],
                'staff_ids' => [$teacher->id],
            ])->all(),
        ], $this->authHeaders($dean))->assertOk();

        $publishResponse = $this->postJson(
            "/api/v1/duty-rosters/{$rosterId}/publish",
            [],
            $this->authHeaders($dean),
        );

        $publishResponse
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertNotNull($publishResponse->json('data.published_at'));
    }

    public function test_publish_rejects_unassigned_rows(): void
    {
        [$dean, $school] = $this->createDeanWithSchool();

        $roster = WeeklyDutyRoster::query()->create([
            'school_id' => $school->id,
            'week_start' => now()->startOfWeek(),
            'week_end' => now()->startOfWeek()->addDays(6),
            'status' => WeeklyDutyRoster::STATUS_DRAFT,
        ]);
        $roster->seedStandardTemplate();

        $response = $this->postJson(
            "/api/v1/duty-rosters/{$roster->id}/publish",
            [],
            $this->authHeaders($dean),
        );

        $response->assertUnprocessable();
    }

    public function test_current_endpoint_returns_draft_for_editing(): void
    {
        [$dean, $school] = $this->createDeanWithSchool();

        $roster = WeeklyDutyRoster::query()->create([
            'school_id' => $school->id,
            'week_start' => now()->startOfWeek(),
            'week_end' => now()->startOfWeek()->addDays(6),
            'status' => WeeklyDutyRoster::STATUS_DRAFT,
        ]);
        $roster->seedStandardTemplate();

        $response = $this->getJson('/api/v1/duty-rosters/current', $this->authHeaders($dean));

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $roster->id)
            ->assertJsonPath('data.status', 'draft');
    }
}
