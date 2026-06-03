<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

class SchoolUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->assignFromEnvPairs();

        $defaultCode = env('SEED_DEFAULT_TEACHER_SCHOOL', 'PS');
        $defaultSchool = School::query()->where('code', $defaultCode)->first();

        if (! $defaultSchool) {
            return;
        }

        $teacherRole = Role::query()->where('slug', 'teacher')->first();
        if (! $teacherRole) {
            return;
        }

        User::query()
            ->where('role_id', $teacherRole->id)
            ->whereDoesntHave('schools')
            ->each(function (User $teacher) use ($defaultSchool): void {
                $teacher->schools()->syncWithoutDetaching([$defaultSchool->id]);
            });
    }

    private function assignFromEnvPairs(): void
    {
        $raw = env('SEED_TEACHER_SCHOOL_ASSIGNMENTS', '');
        if (! is_string($raw) || trim($raw) === '') {
            return;
        }

        foreach (explode(',', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '' || ! str_contains($pair, ':')) {
                continue;
            }

            [$email, $schoolCode] = array_map('trim', explode(':', $pair, 2));
            $email = strtolower($email);
            $school = School::query()->where('code', $schoolCode)->first();
            $teacher = User::query()
                ->whereHas('role', fn ($query) => $query->where('slug', 'teacher'))
                ->whereRaw('lower(email) = ?', [$email])
                ->first();

            if (! $school || ! $teacher) {
                continue;
            }

            $teacher->schools()->syncWithoutDetaching([$school->id]);
        }
    }
}
