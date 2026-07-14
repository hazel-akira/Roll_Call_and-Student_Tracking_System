<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StudentAttendanceReportRequest;
use App\Http\Requests\Students\StudentIndexRequest;
use App\Http\Requests\Students\StudentLookupRequest;
use App\Http\Resources\StudentResource;
use App\Models\AttendanceRecord;
use App\Models\School;
use App\Models\Student;
use App\Services\DynamicsService;
use App\Services\Integrations\DynamicsStudentSyncService;
use App\Services\Reports\StudentAttendanceReportService;
use App\Services\TenantService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StudentController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService,
        private readonly StudentAttendanceReportService $studentAttendanceReportService,
        private readonly DynamicsService $dynamicsService,
        private readonly DynamicsStudentSyncService $dynamicsStudentSyncService,
    ) {
    }

    public function index(StudentIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $students = $this->tenantService
            ->scopeViaClassSchool(Student::query(), $request)
            ->with(['classRoom.school'])
            ->when($validated['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('admission_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($validated['class_id'] ?? null, fn ($query, int $classId) => $query->where('class_id', $classId))
            ->when(
                ! ($validated['class_id'] ?? null) && ($validated['grade_level'] ?? null),
                function ($query) use ($validated): void {
                    $gradeLevel = $validated['grade_level'];
                    $stream = $validated['stream'] ?? null;

                    $query->whereHas('classRoom', function ($classQuery) use ($gradeLevel, $stream): void {
                        $classQuery->where('grade_level', $gradeLevel);

                        if ($stream === null || $stream === '') {
                            $classQuery->where(function ($sectionQuery): void {
                                $sectionQuery->whereNull('section')->orWhere('section', '');
                            });
                        } else {
                            $classQuery->where('section', $stream);
                        }
                    });
                },
            )
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('admission_number')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'data' => StudentResource::collection($students),
        ]);
    }

    public function lookup(StudentLookupRequest $request): JsonResponse
    {
        $admissionNumber = trim((string) $request->validated('admission_number'));
        $schoolId = $request->validated('school_id') ?? $this->tenantService->effectiveSchoolId($request);

        $localQuery = $this->tenantService
            ->scopeViaClassSchool(Student::query(), $request)
            ->with(['classRoom.school'])
            ->where(function ($query) use ($admissionNumber): void {
                $query
                    ->where('admission_number', $admissionNumber)
                    ->orWhere('admission_number', 'like', '%'.$admissionNumber);
            });

        $localMatches = $localQuery->orderBy('admission_number')->get();
        $localStudent = $localMatches->first(function (Student $student) use ($admissionNumber): bool {
            return strcasecmp($student->admission_number, $admissionNumber) === 0;
        }) ?? ($localMatches->count() === 1 ? $localMatches->first() : null);

        if ($localStudent) {
            return response()->json([
                'data' => StudentResource::make($localStudent),
                'meta' => ['source' => 'local'],
            ]);
        }

        if ($localMatches->count() > 1) {
            return response()->json([
                'message' => "Multiple local students matched \"{$admissionNumber}\". Use the full admission number.",
            ], 422);
        }

        if (! $this->dynamicsService->isEnabled()) {
            return response()->json([
                'message' => "No student found with admission number \"{$admissionNumber}\".",
            ], 404);
        }

        if ($schoolId === null) {
            return response()->json([
                'message' => 'Select a school in the header before searching Dataverse.',
            ], 422);
        }

        $school = School::query()->find($schoolId);
        if (! $school) {
            return response()->json([
                'message' => 'Selected school was not found.',
            ], 404);
        }

        $dataverseSchoolName = $this->dynamicsService->resolveDataverseSchoolName((string) $school->id);
        $dynamicsRows = $this->dynamicsService->findStudentsByAdmissionNumber($admissionNumber, $dataverseSchoolName);

        if ($dynamicsRows === []) {
            return response()->json([
                'message' => "No student found in Dataverse for admission number \"{$admissionNumber}\" at {$school->name}.",
            ], 404);
        }

        $dynamicsRow = collect($dynamicsRows)->first(function (array $row) use ($admissionNumber): bool {
            $candidate = trim((string) ($row['admission_number'] ?? ''));

            return strcasecmp($candidate, $admissionNumber) === 0;
        }) ?? ($dynamicsRows[0] ?? null);

        if (! is_array($dynamicsRow)) {
            return response()->json([
                'message' => "No student found in Dataverse for admission number \"{$admissionNumber}\".",
            ], 404);
        }

        if (count($dynamicsRows) > 1 && collect($dynamicsRows)->contains(function (array $row) use ($admissionNumber): bool {
            return strcasecmp(trim((string) ($row['admission_number'] ?? '')), $admissionNumber) === 0;
        }) === false) {
            return response()->json([
                'message' => "Multiple students in Dataverse matched \"{$admissionNumber}\". Use the full admission number.",
            ], 422);
        }

        try {
            $student = $this->dynamicsStudentSyncService->upsertStudentFromDynamics($dynamicsRow, $school);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Student was found in Dataverse but could not be synced locally.',
            ], 503);
        }

        return response()->json([
            'data' => StudentResource::make($student),
            'meta' => [
                'source' => 'dynamics',
                'dataverse_school' => $dynamicsRow['school_name'] ?? $dataverseSchoolName,
            ],
        ]);
    }

    public function show(Student $student, StudentIndexRequest $request): JsonResponse
    {
        $this->ensureStudentAccessible($student, $request);

        return response()->json([
            'data' => StudentResource::make($student->load('classRoom.school')),
        ]);
    }

    public function history(Student $student, StudentIndexRequest $request): JsonResponse
    {
        $this->ensureStudentAccessible($student, $request);
        $history = AttendanceRecord::query()
            ->with(['session.subject', 'session.classRoom'])
            ->where('student_id', $student->id)
            ->latest('marked_at')
            ->paginate(20)
            ->through(function (AttendanceRecord $record): array {
                return [
                    'id' => $record->id,
                    'status' => $record->status,
                    'remark' => $record->remark,
                    'marked_at' => $record->marked_at,
                    'session' => [
                        'id' => $record->session?->id,
                        'title' => $record->session?->title,
                        'session_date' => $record->session?->session_date,
                        'class' => $record->session?->classRoom?->name,
                        'subject' => $record->session?->subject?->name,
                    ],
                ];
            });

        return response()->json([
            'student' => StudentResource::make($student->load('classRoom')),
            'history' => $history,
        ]);
    }

    public function attendanceReport(
        Student $student,
        StudentAttendanceReportRequest $request,
    ): JsonResponse|StreamedResponse {
        $this->ensureStudentAccessible($student, $request);

        $filters = $request->validated();
        $report = $this->studentAttendanceReportService->build($student, $filters);

        if (($filters['format'] ?? 'json') === 'pdf') {
            $admission = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $student->admission_number) ?: 'student';
            $fileName = sprintf('student-attendance-%s-%s.pdf', $admission, now()->format('Ymd'));

            return response()->streamDownload(function () use ($report): void {
                echo Pdf::loadView('reports.student-attendance-report', [
                    'report' => $report,
                    'generated_at' => now()->format('Y-m-d H:i'),
                ])->output();
            }, $fileName, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        return response()->json([
            'data' => $report,
        ]);
    }

    private function ensureStudentAccessible(Student $student, StudentIndexRequest|StudentAttendanceReportRequest $request): void
    {
        if (! $this->tenantService->shouldApplySchoolScope($request->user(), $request)) {
            return;
        }

        $student->loadMissing('classRoom');
        if (! $student->classRoom || ! $this->tenantService->classBelongsToEffectiveSchool($student->classRoom, $request)) {
            throw new NotFoundHttpException('Student not found.');
        }
    }
}
