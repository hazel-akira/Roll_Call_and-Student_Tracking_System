<?php

namespace App\Services\Attendance;

use App\Events\AttendanceSessionClosed;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AttendanceSessionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RollCallReportService $rollCallReportService,
    ) {
    }

    public function create(array $payload, User $actor): AttendanceSession
    {
        $subjectId = $payload['subject_id'] ?? $this->defaultRollCallSubjectId();

        $session = AttendanceSession::query()->create([
            'class_id' => $payload['class_id'],
            'subject_id' => $subjectId,
            'teacher_id' => $actor->id,
            'title' => $payload['title'],
            'notes' => $payload['notes'] ?? null,
            'session_date' => $payload['session_date'],
            'started_at' => $payload['started_at'] ?? now(),
            'status' => 'open',
            'source' => $payload['source'] ?? 'web',
            'dynamics_sync_status' => 'pending',
        ]);

        $this->auditLogger->log(
            $actor,
            'attendance.session.created',
            'Created a new attendance session.',
            $session,
            [],
            $session->toArray(),
        );

        return $session->fresh(['classRoom', 'subject', 'teacher', 'records.student']);
    }

    /**
     * @return array{session: AttendanceSession, report_sent: bool}
     */
    public function upsertRecords(AttendanceSession $session, array $records, User $actor, bool $rosterComplete = false): array
    {
        if ($session->status === 'closed') {
            throw ValidationException::withMessages([
                'attendance_session' => 'Closed attendance sessions cannot be modified.',
            ]);
        }

        $studentIds = collect($records)->pluck('student_id')->all();
        $validStudentIds = Student::query()
            ->where('class_id', $session->class_id)
            ->whereIn('id', $studentIds)
            ->pluck('id')
            ->all();

        $invalidIds = array_values(array_diff($studentIds, $validStudentIds));

        if ($invalidIds !== []) {
            throw ValidationException::withMessages([
                'records' => 'One or more students do not belong to the selected class.',
            ]);
        }

        $changes = DB::transaction(function () use ($session, $records, $actor): array {
            $existingByStudentId = AttendanceRecord::query()
                ->where('attendance_session_id', $session->id)
                ->whereIn('student_id', collect($records)->pluck('student_id')->all())
                ->get()
                ->keyBy('student_id');

            $changes = [];

            foreach ($records as $record) {
                $normalizedStatus = $this->normalizeStatus($record['status']);
                /** @var AttendanceRecord|null $existing */
                $existing = $existingByStudentId->get($record['student_id']);

                $oldValues = $existing ? [
                    'status' => $this->presentedStatus($existing->status),
                    'remark' => $existing->remark,
                    'marked_at' => optional($existing->marked_at)->toDateTimeString(),
                ] : null;

                AttendanceRecord::query()->updateOrCreate(
                    [
                        'attendance_session_id' => $session->id,
                        'student_id' => $record['student_id'],
                    ],
                    [
                        'status' => $normalizedStatus,
                        'remark' => Arr::get($record, 'remark'),
                        'marked_by' => $actor->id,
                        'marked_at' => now(),
                    ],
                );

                $newValues = [
                    'status' => $record['status'],
                    'remark' => Arr::get($record, 'remark'),
                ];

                if (! $oldValues || $oldValues['status'] !== $newValues['status'] || $oldValues['remark'] !== $newValues['remark']) {
                    $changes[] = [
                        'student_id' => $record['student_id'],
                        'old' => $oldValues,
                        'new' => $newValues,
                    ];
                }
            }

            return $changes;
        });

        $this->auditLogger->log(
            $actor,
            'attendance.records.upserted',
            'Updated attendance records for a session.',
            $session,
            ['records' => collect($changes)->pluck('old', 'student_id')->filter(fn ($value) => $value !== null)->all()],
            [
                'record_count' => count($records),
                'changed_count' => count($changes),
                'changes' => $changes,
            ],
        );

        $session = $session->fresh(['classRoom.school', 'classRoom.homeroomTeacher', 'subject', 'teacher', 'records.student']);
        $reportSent = false;

        if ($this->shouldSendRollCallReport($session, $records, $rosterComplete)) {
            try {
                $this->rollCallReportService->generateAndSend($session);
                $reportSent = true;
            } catch (\Throwable $exception) {
                Log::error('Roll call report email failed after saving attendance', [
                    'session_id' => $session->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        } else {
            $activeCount = Student::query()
                ->where('class_id', $session->class_id)
                ->where('status', 'active')
                ->count();
            $markedCount = AttendanceRecord::query()
                ->where('attendance_session_id', $session->id)
                ->distinct()
                ->count('student_id');

            Log::info('Roll call report not sent: roster incomplete', [
                'session_id' => $session->id,
                'teacher_id' => $session->teacher_id,
                'marked_count' => $markedCount,
                'active_student_count' => $activeCount,
                'roster_complete' => $rosterComplete,
            ]);
        }

        return [
            'session' => $session,
            'report_sent' => $reportSent,
        ];
    }

    public function close(AttendanceSession $session, User $actor): AttendanceSession
    {
        if ($session->status === 'closed') {
            return $session->fresh(['classRoom', 'subject', 'teacher', 'records.student']);
        }

        $session->forceFill([
            'status' => 'closed',
            'closed_at' => now(),
            'dynamics_sync_status' => 'queued',
        ])->save();

        event(new AttendanceSessionClosed($session->fresh(['classRoom', 'subject', 'teacher', 'records.student'])));

        $this->auditLogger->log(
            $actor,
            'attendance.session.closed',
            'Closed an attendance session and queued sync processing.',
            $session,
            ['status' => 'open'],
            $session->only(['status', 'closed_at', 'dynamics_sync_status']),
        );

        return $session->fresh(['classRoom', 'subject', 'teacher', 'records.student']);
    }

    private function defaultRollCallSubjectId(): int
    {
        $subject = Subject::query()->firstOrCreate(
            ['code' => 'ROLL-CALL'],
            ['name' => 'General Roll Call', 'description' => 'Stream-based class roll call.'],
        );

        return $subject->id;
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'missing' => 'absent',
            'sick' => 'excused',
            'on_leave' => 'late',
            default => $status,
        };
    }

    private function presentedStatus(string $storedStatus): string
    {
        return match ($storedStatus) {
            'absent' => 'missing',
            'excused' => 'sick',
            'late' => 'on_leave',
            default => $storedStatus,
        };
    }

    private function allActiveStudentsMarked(AttendanceSession $session): bool
    {
        $activeStudentIds = Student::query()
            ->where('class_id', $session->class_id)
            ->where('status', 'active')
            ->pluck('id');

        if ($activeStudentIds->isEmpty()) {
            return false;
        }

        $markedCount = AttendanceRecord::query()
            ->where('attendance_session_id', $session->id)
            ->whereIn('student_id', $activeStudentIds)
            ->distinct()
            ->count('student_id');

        return $markedCount >= $activeStudentIds->count();
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    private function shouldSendRollCallReport(AttendanceSession $session, array $records, bool $rosterComplete): bool
    {
        if ($this->allActiveStudentsMarked($session)) {
            return true;
        }

        if (! $rosterComplete || $records === []) {
            return false;
        }

        $studentIdsInRequest = collect($records)->pluck('student_id')->unique();

        if ($studentIdsInRequest->isEmpty()) {
            return false;
        }

        $markedCount = AttendanceRecord::query()
            ->where('attendance_session_id', $session->id)
            ->whereIn('student_id', $studentIdsInRequest)
            ->distinct()
            ->count('student_id');

        return $markedCount >= $studentIdsInRequest->count();
    }
}
