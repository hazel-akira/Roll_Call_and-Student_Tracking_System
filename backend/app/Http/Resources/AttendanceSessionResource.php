<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'notes' => $this->notes,
            'session_date' => $this->session_date,
            'started_at' => $this->started_at,
            'closed_at' => $this->closed_at,
            'status' => $this->status,
            'source' => $this->source,
            'dynamics_sync_status' => $this->dynamics_sync_status,
            'class' => $this->classRoom ? [
                'id' => $this->classRoom->id,
                'name' => $this->classRoom->name,
                'code' => $this->classRoom->code,
            ] : null,
            'subject' => $this->subject ? [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
                'code' => $this->subject->code,
            ] : null,
            'teacher' => $this->teacher ? [
                'id' => $this->teacher->id,
                'name' => $this->teacher->name,
                'email' => $this->teacher->email,
            ] : null,
            'records' => $this->whenLoaded('records', fn () => $this->records->map(fn ($record) => [
                'id' => $record->id,
                'status' => $record->status,
                'remark' => $record->remark,
                'marked_at' => $record->marked_at,
                'student' => $record->student ? [
                    'id' => $record->student->id,
                    'admission_number' => $record->student->admission_number,
                    'full_name' => $record->student->full_name,
                ] : null,
            ])),
        ];
    }
}
