<?php

namespace App\Filament\Teacher\Resources\AttendanceSessions\Pages;

use App\Filament\Teacher\Resources\AttendanceSessions\AttendanceSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceSessions extends ListRecords
{
    protected static string $resource = AttendanceSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
