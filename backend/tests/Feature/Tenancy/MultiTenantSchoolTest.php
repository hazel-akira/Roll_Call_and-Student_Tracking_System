<?php

namespace Tests\Feature\Tenancy;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\TenantService;
use Database\Seeders\SchoolAndClassSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesWithJwt;
use Tests\TestCase;

class MultiTenantSchoolTest extends TestCase
{
    use AuthenticatesWithJwt;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SchoolAndClassSeeder::class);
    }

    public function test_seeder_creates_all_five_tenant_schools(): void
    {
        $this->assertDatabaseCount('schools', 5);
        $this->assertDatabaseHas('schools', ['code' => 'PS', 'name' => 'Pioneer School']);
        $this->assertDatabaseHas('schools', ['code' => 'PGS', 'name' => 'Pioneer Girls School']);
        $this->assertDatabaseHas('schools', ['code' => 'PJA', 'name' => 'Pioneer Junior Academy']);
        $this->assertDatabaseHas('schools', ['code' => 'PGJA', 'name' => 'Pioneer Girls Junior Academy']);
        $this->assertDatabaseHas('schools', ['code' => 'SPTA', 'name' => 'St Paul Thomas Academy']);
    }

    public function test_teacher_only_sees_students_in_assigned_school(): void
    {
        $pioneer = School::query()->where('code', 'PS')->firstOrFail();
        $girls = School::query()->where('code', 'PGS')->firstOrFail();

        $pioneerClass = SchoolClass::query()->where('school_id', $pioneer->id)->firstOrFail();
        $girlsClass = SchoolClass::query()->where('school_id', $girls->id)->firstOrFail();

        $pioneerStudent = Student::query()->create([
            'class_id' => $pioneerClass->id,
            'admission_number' => 'P-001',
            'first_name' => 'Alex',
            'last_name' => 'Pioneer',
            'status' => 'active',
        ]);

        Student::query()->create([
            'class_id' => $girlsClass->id,
            'admission_number' => 'G-001',
            'first_name' => 'Bella',
            'last_name' => 'Girls',
            'status' => 'active',
        ]);

        $teacher = $this->createUserWithRole('teacher');
        $teacher->schools()->attach($pioneer->id);

        $response = $this->getJson('/api/v1/students', $this->authHeaders($teacher));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.admission_number', $pioneerStudent->admission_number);
    }

    public function test_teacher_without_school_assignment_is_rejected(): void
    {
        $teacher = $this->createUserWithRole('teacher');

        $response = $this->getJson('/api/v1/classes', $this->authHeaders($teacher));

        $response
            ->assertStatus(403)
            ->assertJsonPath('message', 'Your account is not assigned to any school. Contact an administrator.');
    }

    public function test_admin_lists_all_schools(): void
    {
        $admin = $this->createUserWithRole('admin');

        $response = $this->getJson('/api/v1/schools', $this->authHeaders($admin));

        $response->assertOk()->assertJsonCount(5, 'data');
    }

    public function test_teacher_can_switch_school_context_when_assigned_to_multiple(): void
    {
        $pioneer = School::query()->where('code', 'PS')->firstOrFail();
        $junior = School::query()->where('code', 'PJA')->firstOrFail();

        $teacher = $this->createUserWithRole('teacher');
        $teacher->schools()->sync([$pioneer->id, $junior->id]);

        $selectResponse = $this->postJson('/api/v1/schools/select', [
            'school_id' => $junior->id,
        ], $this->authHeaders($teacher));

        $selectResponse
            ->assertOk()
            ->assertJsonPath('current_school_id', (string) $junior->id)
            ->assertJsonPath('data.code', 'PJA');

        $classesResponse = $this->getJson('/api/v1/classes', array_merge(
            $this->authHeaders($teacher),
            [TenantService::SCHOOL_HEADER => (string) $junior->id],
        ));

        $classesResponse->assertOk();
        foreach ($classesResponse->json('data') as $class) {
            $this->assertSame($junior->id, $class['school_id']);
        }
    }
}
