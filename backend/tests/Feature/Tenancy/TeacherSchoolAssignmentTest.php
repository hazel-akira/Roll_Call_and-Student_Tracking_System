<?php

namespace Tests\Feature\Tenancy;

use App\Models\School;
use App\Models\User;
use Database\Seeders\SchoolAndClassSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesWithJwt;
use Tests\TestCase;

class TeacherSchoolAssignmentTest extends TestCase
{
    use AuthenticatesWithJwt;
    use RefreshDatabase;

    public function test_admin_can_assign_schools_and_classes_to_teacher(): void
    {
        $this->seed(SchoolAndClassSeeder::class);

        $admin = $this->createUserWithRole('admin');
        $teacher = $this->createUserWithRole('teacher');

        $pioneer = School::query()->where('code', 'PS')->firstOrFail();
        $class = $pioneer->classes()->firstOrFail();

        $response = $this->putJson(
            "/api/v1/teachers/{$teacher->id}/assignments",
            [
                'school_ids' => [$pioneer->id],
                'class_ids' => [$class->id],
            ],
            $this->authHeaders($admin),
        );

        $response->assertOk();

        $this->assertDatabaseHas('school_user', [
            'user_id' => $teacher->id,
            'school_id' => $pioneer->id,
        ]);
        $this->assertDatabaseHas('teacher_subjects', [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
        ]);
    }

    public function test_admin_can_assign_schools_to_teacher(): void
    {
        $this->seed(SchoolAndClassSeeder::class);

        $admin = $this->createUserWithRole('admin');
        $teacher = $this->createUserWithRole('teacher');

        $pioneer = School::query()->where('code', 'PS')->firstOrFail();
        $girls = School::query()->where('code', 'PGS')->firstOrFail();

        $response = $this->putJson(
            "/api/v1/teachers/{$teacher->id}/schools",
            ['school_ids' => [$pioneer->id, $girls->id]],
            $this->authHeaders($admin),
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.email', $teacher->email);

        $this->assertDatabaseHas('school_user', [
            'user_id' => $teacher->id,
            'school_id' => $pioneer->id,
        ]);
        $this->assertDatabaseHas('school_user', [
            'user_id' => $teacher->id,
            'school_id' => $girls->id,
        ]);
    }

    public function test_admin_can_clear_school_context_for_aggregate_view(): void
    {
        $this->seed(SchoolAndClassSeeder::class);

        $admin = $this->createUserWithRole('admin');
        $school = School::query()->firstOrFail();

        $this->postJson('/api/v1/schools/select', [
            'school_id' => $school->id,
        ], $this->authHeaders($admin))->assertOk();

        $this->postJson('/api/v1/schools/clear', [], $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonPath('current_school_id', null);
    }

    public function test_teacher_cannot_clear_school_context(): void
    {
        $this->seed(SchoolAndClassSeeder::class);

        [$teacher] = (function (): array {
            $school = School::query()->firstOrFail();
            $teacher = $this->createUserWithRole('teacher');
            $teacher->schools()->attach($school->id);

            return [$teacher];
        })();

        $this->postJson('/api/v1/schools/clear', [], $this->authHeaders($teacher))
            ->assertStatus(403);
    }
}
