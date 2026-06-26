<?php

namespace Tests\Feature\Students;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesWithJwt;
use Tests\TestCase;

class StudentLookupTest extends TestCase
{
    use AuthenticatesWithJwt;
    use RefreshDatabase;

    public function test_lookup_returns_local_student_by_admission_number(): void
    {
        $school = School::query()->create([
            'name' => 'Pioneer School',
            'code' => 'PS',
            'active' => true,
        ]);
        $teacher = $this->createUserWithRole('teacher');
        $teacher->schools()->attach($school->id);
        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'Form 3 A',
            'code' => 'F3A',
            'academic_year' => '2026',
            'grade_level' => 'Form 3',
            'section' => 'A',
        ]);
        $student = Student::query()->create([
            'class_id' => $class->id,
            'admission_number' => '6165',
            'first_name' => 'Jane',
            'last_name' => 'Student',
            'status' => 'active',
        ]);

        $this->getJson('/api/v1/students/lookup?admission_number=6165', $this->authHeaders($teacher))
            ->assertOk()
            ->assertJsonPath('data.id', $student->id)
            ->assertJsonPath('meta.source', 'local');
    }
}
