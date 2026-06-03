<?php

namespace Tests\Feature\Authorization;

use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesWithJwt;
use Tests\TestCase;

class RoleRestrictionsTest extends TestCase
{
    use AuthenticatesWithJwt;
    use RefreshDatabase;

    public function test_teacher_cannot_access_admin_dashboard_endpoint(): void
    {
        [$teacher] = $this->createTeacherWithSchool();

        $response = $this->getJson('/api/v1/dashboard/admin', $this->authHeaders($teacher));

        $response
            ->assertStatus(403)
            ->assertJsonPath('message', 'You do not have permission to access this resource.');
    }

    public function test_teacher_cannot_trigger_dynamics_student_sync(): void
    {
        [$teacher, $school] = $this->createTeacherWithSchool();
        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Form 2',
            'code' => 'F2A',
            'academic_year' => '2026',
            'grade_level' => 'Form 2',
            'section' => 'A',
        ]);

        $response = $this->postJson(
            "/api/v1/dynamics/classes/{$class->id}/students/sync",
            [],
            $this->authHeaders($teacher)
        );

        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_dashboard_endpoint(): void
    {
        $admin = $this->createUserWithRole('admin');

        $response = $this->getJson('/api/v1/dashboard/admin', $this->authHeaders($admin));

        $response
            ->assertOk()
            ->assertJsonStructure([
                'stats' => [
                    'students',
                    'teachers',
                    'classes',
                    'today_sessions',
                    'attendance_rate_today',
                    'unresolved_absences',
                ],
                'daily_attendance_trends',
                'recent_audit_logs',
                'recent_sync_failures',
            ]);

        $this->assertIsArray($response->json('recent_audit_logs'));
        $this->assertIsArray($response->json('recent_sync_failures'));
    }
}
