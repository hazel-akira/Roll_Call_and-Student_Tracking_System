<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class SchoolAndClassSeeder extends Seeder
{
    public function run(): void
    {
        $schools = [
            ...config('schools.tenants', []),
        ];

        $gradeMap = [
            'PS' => ['Grade 10', 'Form 3', 'Form 4'],
            'PGS' => ['Grade 10', 'Form 3', 'Form 4'],
            'PJA' => ['Grade 7', 'Grade 8', 'Grade 9'],
            'PGJA' => ['Grade 7', 'Grade 8', 'Grade 9'],
            'SPTA' => ['Grade 4', 'Grade 5', 'Grade 6'],
        ];

        $schools = array_map(function (array $school) use ($gradeMap): array {
            $school['grades'] = $gradeMap[$school['code']] ?? [];
            $school['streams'] = [];

            return $school;
        }, $schools);

        foreach ($schools as $definition) {
            $school = School::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'level' => $definition['level'],
                    'is_junior' => $definition['is_junior'],
                    'active' => true,
                ],
            );

            $streams = $definition['streams'] !== [] ? $definition['streams'] : [''];

            foreach ($definition['grades'] as $grade) {
                foreach ($streams as $stream) {
                    $streamSlug = $stream !== ''
                        ? '-'.strtoupper(str_replace(' ', '', $stream))
                        : '';

                    $classCode = sprintf(
                        '%s-%s%s',
                        $definition['code'],
                        strtoupper(str_replace(' ', '', $grade)),
                        $streamSlug,
                    );

                    SchoolClass::query()->updateOrCreate(
                        ['code' => $classCode],
                        [
                            'school_id' => $school->id,
                            'grade_level' => $grade,
                            'name' => trim($stream === '' ? $grade : $grade.' '.$stream),
                            'section' => $stream !== '' ? $stream : null,
                            'academic_year' => (string) now()->year,
                        ],
                    );
                }
            }
        }
    }
}
