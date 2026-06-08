<?php

namespace App\Filament\Teacher\Resources\AttendanceSessions\Pages;

use App\Filament\Teacher\Resources\AttendanceSessions\AttendanceSessionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditAttendanceSession extends EditRecord
{
    protected static string $resource = AttendanceSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('close')
                ->label('Close session')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === 'open')
                ->action(function (): void {
                    $this->record = app(\App\Services\Attendance\AttendanceSessionService::class)
                        ->close($this->record, Auth::user());
                    $this->refreshFormData(['status', 'closed_at', 'dynamics_sync_status']);
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['teacher_id'], $data['status'], $data['source'], $data['dynamics_sync_status']);

        return $data;
    }
}
