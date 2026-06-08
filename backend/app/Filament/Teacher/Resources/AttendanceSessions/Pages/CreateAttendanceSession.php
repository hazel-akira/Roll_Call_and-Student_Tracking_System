<?php

namespace App\Filament\Teacher\Resources\AttendanceSessions\Pages;

use App\Filament\Teacher\Resources\AttendanceSessions\AttendanceSessionResource;
use App\Services\Attendance\AttendanceSessionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateAttendanceSession extends CreateRecord
{
    protected static string $resource = AttendanceSessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['teacher_id'] = Auth::id();
        $data['status'] = 'open';
        $data['source'] = 'filament';
        $data['dynamics_sync_status'] = 'pending';
        $data['started_at'] = now();

        if (empty($data['subject_id'])) {
            unset($data['subject_id']);
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $user = Auth::user();
        abort_unless($user, 403);

        if (empty($data['subject_id'])) {
            unset($data['subject_id']);
        }

        return app(AttendanceSessionService::class)->create($data, $user);
    }
}
