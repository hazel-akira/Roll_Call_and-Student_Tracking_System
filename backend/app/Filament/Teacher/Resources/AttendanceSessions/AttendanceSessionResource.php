<?php

namespace App\Filament\Teacher\Resources\AttendanceSessions;

use App\Filament\Teacher\Resources\AttendanceSessions\Pages\CreateAttendanceSession;
use App\Filament\Teacher\Resources\AttendanceSessions\Pages\EditAttendanceSession;
use App\Filament\Teacher\Resources\AttendanceSessions\Pages\ListAttendanceSessions;
use App\Filament\Teacher\Resources\AttendanceSessions\Schemas\AttendanceSessionForm;
use App\Filament\Teacher\Resources\AttendanceSessions\Tables\AttendanceSessionsTable;
use App\Models\AttendanceSession;
use App\Services\TenantService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AttendanceSessionResource extends Resource
{
    protected static ?string $model = AttendanceSession::class;

    protected static ?string $navigationLabel = 'Attendance sessions';

    protected static ?string $modelLabel = 'attendance session';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Roll call';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if (! $user) {
            return $query->whereRaw('0 = 1');
        }

        if ($user->role?->slug === 'teacher') {
            return $query->where('teacher_id', $user->id);
        }

        $tenant = app(TenantService::class);
        if ($tenant->hasAccessToAllSchools($user)) {
            return $query;
        }

        $schoolIds = $user->schools()->pluck('schools.id');
        if ($schoolIds->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas(
            'classRoom',
            fn (Builder $classQuery) => $classQuery->whereIn('school_id', $schoolIds),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return AttendanceSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceSessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceSessions::route('/'),
            'create' => CreateAttendanceSession::route('/create'),
            'edit' => EditAttendanceSession::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
