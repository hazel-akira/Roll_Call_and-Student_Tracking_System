<?php

namespace App\Services\Attendance;

use App\Events\AttendanceSessionClosed;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceSessionService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function create(array $payload, User $actor): AttendanceSession
    {
        $session = AttendanceSession::query()->create([
            'class_id' => $payload['class_id'],
            'subject_id' => $payload['subject_id'],
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

    public function upsertRecords(AttendanceSession $session, array $records, User $actor): AttendanceSession
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

        DB::transaction(function () use ($session, $records, $actor): void {
            foreach ($records as $record) {
                AttendanceRecord::query()->updateOrCreate(
                    [
                        'attendance_session_id' => $session->id,
                        'student_id' => $record['student_id'],
                    ],
                    [
                        'status' => $record['status'],
                        'remark' => Arr::get($record, 'remark'),
                        'marked_by' => $actor->id,
                        'marked_at' => now(),
                    ],
                );
            }
        });

        $this->auditLogger->log(
            $actor,
            'attendance.records.upserted',
            'Updated attendance records for a session.',
            $session,
            [],
            ['record_count' => count($records)],
        );

        return $session->fresh(['classRoom', 'subject', 'teacher', 'records.student']);
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
}
