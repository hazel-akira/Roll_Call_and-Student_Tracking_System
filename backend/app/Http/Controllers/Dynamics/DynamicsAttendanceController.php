<?php

namespace App\Http\Controllers\Dynamics;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\ClassStreamResolver;
use App\Services\DynamicsService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynamicsAttendanceController extends Controller
{
    public function __construct(
        private readonly DynamicsService $dynamicsService,
        private readonly TenantService $tenantService,
        private readonly ClassStreamResolver $classStreamResolver,
    ) {
    }

    public function formStreams(Request $request): JsonResponse
    {
        $school = $this->resolveSchool($request);
        if (! $school) {
            return response()->json([
                'message' => 'Select a school in the header before loading forms and streams.',
                'data' => [
                    'grade_levels' => [],
                    'streams' => [],
                    'school_name' => null,
                ],
            ], 422);
        }

        if (! $this->dynamicsService->isEnabled()) {
            return response()->json([
                'message' => 'Dynamics integration is not configured.',
                'data' => [
                    'grade_levels' => [],
                    'streams' => [],
                    'school_name' => null,
                ],
            ], 503);
        }

        try {
            $schoolName = $this->dynamicsService->resolveDataverseSchoolName((string) $school->id);
            $streams = $this->dynamicsService->getAttendanceFormStreams($schoolName);

            $gradeLevels = array_values(array_unique(array_filter(array_map(
                fn (array $row) => $row['grade_level'] ?? null,
                $streams,
            ))));

            usort($gradeLevels, fn (string $a, string $b) => strcmp($a, $b));

            $warning = null;
            if ($streams === []) {
                $probe = $this->dynamicsService->probeSchoolRooms($schoolName);
                if (! $probe['token_ok']) {
                    return response()->json([
                        'message' => $probe['token_error'] ?? 'Unable to connect to Dataverse. Check DYNAMICS_* environment variables.',
                        'data' => [
                            'grade_levels' => [],
                            'streams' => [],
                            'school_name' => $schoolName,
                        ],
                    ], 503);
                }

                $samples = $probe['sample_school_names'];
                $warning = $samples === []
                    ? "No class streams (ses_rooms) were returned from Dataverse for \"{$schoolName}\". Confirm DYNAMICS_BASE_URL points at the correct environment."
                    : "No class streams found for \"{$schoolName}\". Dataverse has streams for: ".implode(', ', array_slice($samples, 0, 5)).'. Update schools.dynamics_names or the school name in Dataverse.';
            }

            return response()->json([
                'message' => $warning,
                'data' => [
                    'grade_levels' => $gradeLevels,
                    'streams' => $streams,
                    'school_name' => $schoolName,
                    'local_school' => [
                        'id' => $school->id,
                        'name' => $school->name,
                        'code' => $school->code,
                    ],
                ],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Dataverse is temporarily unavailable.',
                'data' => [
                    'grade_levels' => [],
                    'streams' => [],
                    'school_name' => null,
                ],
            ], 503);
        }
    }

    public function students(Request $request): JsonResponse
    {
        $school = $this->resolveSchool($request);
        if (! $school) {
            return response()->json([
                'message' => 'Select a school in the header before loading students.',
            ], 422);
        }

        $schoolName = $this->dynamicsService->resolveDataverseSchoolName((string) $school->id);
        $gradeLevel = $request->string('grade_level')->toString() ?: null;
        $stream = $request->string('stream')->toString() ?: null;
        $roomId = $request->string('room_id')->toString() ?: null;
        $classId = $request->integer('class_id');

        if (! $this->dynamicsService->isEnabled()) {
            return response()->json([
                'message' => 'Dynamics integration is not configured.',
                'data' => [],
            ], 503);
        }

        try {
            $data = $this->dynamicsService->getStudentsByFormStream(
                gradeLevel: $gradeLevel,
                stream: $stream,
                schoolName: $schoolName,
                roomId: $roomId,
            );

            $resolvedClass = null;
            if ($classId <= 0 && ($stream !== null && $stream !== '')) {
                $resolvedClass = $this->classStreamResolver->resolveOrCreate($school, $gradeLevel, $stream);
                $classId = $resolvedClass->id;
            }

            if ($classId > 0 && $data !== []) {
                $data = $this->upsertStudentsToClass($data, $classId);
            } elseif ($data !== []) {
                $data = $this->formatDynamicsStudentsForResponse($data);
            }

            return response()->json([
                'data' => $data,
                'meta' => [
                    'source' => 'dynamics',
                    'school_name' => $schoolName,
                    'count' => count($data),
                    'local_class_id' => $classId > 0 ? $classId : null,
                    'local_class' => $resolvedClass ? [
                        'id' => $resolvedClass->id,
                        'name' => $resolvedClass->name,
                        'grade_level' => $resolvedClass->grade_level,
                        'section' => $resolvedClass->section,
                    ] : null,
                ],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Dataverse is temporarily unavailable. Try again or use locally synced students.',
                'data' => [],
            ], 503);
        }
    }

    private function resolveSchool(Request $request): ?School
    {
        $schoolId = $this->tenantService->effectiveSchoolId($request);
        if ($schoolId === null) {
            return null;
        }

        return School::query()->find($schoolId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $dynamicsStudents
     * @return array<int, array<string, mixed>>
     */
    private function formatDynamicsStudentsForResponse(array $dynamicsStudents): array
    {
        return array_map(function (array $row): array {
            $first = (string) ($row['first_name'] ?? '');
            $last = (string) ($row['last_name'] ?? '');

            return [
                'id' => null,
                'admission_number' => $row['admission_number'] ?? $row['adm_or_staff_no'] ?? null,
                'first_name' => $first,
                'last_name' => $last,
                'full_name' => trim($first.' '.$last),
                'email' => $row['email'] ?? null,
                'status' => 'active',
                'external_reference' => $row['external_reference'] ?? null,
            ];
        }, $dynamicsStudents);
    }

    private function upsertStudentsToClass(array $dynamicsStudents, int $classId): array
    {
        $syncedIds = [];

        foreach ($dynamicsStudents as $row) {
            $admissionNumber = trim((string) ($row['admission_number'] ?? $row['adm_or_staff_no'] ?? ''));
            $externalReference = trim((string) ($row['external_reference'] ?? ''));

            if ($admissionNumber === '' && $externalReference === '') {
                continue;
            }

            $student = Student::query()
                ->when($externalReference !== '', fn ($query) => $query->where('external_reference', $externalReference))
                ->when($admissionNumber !== '', fn ($query) => $query->orWhere('admission_number', $admissionNumber))
                ->first();

            $payload = [
                'class_id' => $classId,
                'admission_number' => $admissionNumber !== '' ? $admissionNumber : ('EXT-'.substr(md5($externalReference), 0, 10)),
                'first_name' => trim((string) ($row['first_name'] ?? 'Student')),
                'last_name' => trim((string) ($row['last_name'] ?? 'Unknown')),
                'email' => $row['email'] ?? null,
                'gender' => in_array($row['gender'] ?? null, ['male', 'female', 'other'], true) ? $row['gender'] : null,
                'date_of_birth' => $row['dob'] ?? null,
                'status' => 'active',
                'external_reference' => $externalReference !== '' ? $externalReference : null,
            ];

            if ($student) {
                $student->fill($payload)->save();
            } else {
                $student = Student::query()->create($payload);
            }

            $syncedIds[] = $student->id;
        }

        $students = Student::query()
            ->with('classRoom.school')
            ->where('class_id', $classId)
            ->when($syncedIds !== [], fn ($query) => $query->whereIn('id', $syncedIds))
            ->orderBy('last_name')
            ->get();

        return StudentResource::collection($students)->resolve();
    }
}
