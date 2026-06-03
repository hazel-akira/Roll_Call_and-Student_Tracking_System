<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StudentIndexRequest;
use App\Http\Resources\StudentResource;
use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StudentController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService,
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
            ->orderBy('last_name')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'data' => StudentResource::collection($students),
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

    private function ensureStudentAccessible(Student $student, StudentIndexRequest $request): void
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
