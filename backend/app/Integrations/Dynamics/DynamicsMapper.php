<?php

namespace App\Integrations\Dynamics;

use App\Models\AttendanceSession;

class DynamicsMapper
{
    public function mapAttendanceSession(AttendanceSession $session): array
    {
        $session->loadMissing(['classRoom', 'subject', 'teacher', 'records.student']);

        return [
            'sessionId' => $session->id,
            'title' => $session->title,
            'sessionDate' => optional($session->session_date)->toDateString(),
            'startedAt' => optional($session->started_at)->toIso8601String(),
            'closedAt' => optional($session->closed_at)->toIso8601String(),
            'class' => [
                'id' => $session->classRoom?->id,
                'name' => $session->classRoom?->name,
                'code' => $session->classRoom?->code,
            ],
            'subject' => [
                'id' => $session->subject?->id,
                'name' => $session->subject?->name,
                'code' => $session->subject?->code,
            ],
            'teacher' => [
                'id' => $session->teacher?->id,
                'name' => $session->teacher?->name,
                'email' => $session->teacher?->email,
            ],
            'records' => $session->records->map(fn ($record) => [
                'studentId' => $record->student?->id,
                'admissionNumber' => $record->student?->admission_number,
                'studentName' => $record->student?->full_name,
                'status' => $record->status,
                'remark' => $record->remark,
                'markedAt' => optional($record->marked_at)->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
