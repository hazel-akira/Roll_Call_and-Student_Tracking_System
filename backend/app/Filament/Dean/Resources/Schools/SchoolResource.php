<?php

namespace App\Filament\Dean\Resources\Schools;

use App\Filament\Dean\Resources\Schools\Pages\EditSchool;
use App\Filament\Dean\Resources\Schools\Pages\ListSchools;
use App\Filament\Dean\Resources\Schools\Schemas\DeanSchoolForm;
use App\Filament\Resources\Schools\RelationManagers\GradeMastersRelationManager;
use App\Filament\Resources\Schools\RelationManagers\RollCallRecipientsRelationManager;
use App\Filament\Resources\Schools\RelationManagers\WeeklyDutyRostersRelationManager;
use App\Filament\Resources\Schools\Tables\SchoolsTable;
use App\Models\School;
use App\Support\RoleSlugs;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SchoolResource extends Resource
{
    protected static ?string $model = School::class;

    protected static ?string $navigationLabel = 'My schools';

    protected static ?string $modelLabel = 'school';

    protected static ?string $pluralModelLabel = 'schools';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Roll call';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DeanSchoolForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchoolsTable::configure($table)
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [
            WeeklyDutyRostersRelationManager::class,
            RollCallRecipientsRelationManager::class,
            GradeMastersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchools::route('/'),
            'edit' => EditSchool::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (in_array($user->role?->slug, RoleSlugs::allSchoolAccessSlugs(), true)) {
            return $query;
        }

        if (in_array($user->role?->slug, RoleSlugs::deanSlugs(), true)) {
            return $query->whereHas(
                'users',
                fn (Builder $schoolUserQuery) => $schoolUserQuery->where('users.id', $user->id),
            );
        }

        return $query->whereRaw('1 = 0');
    }
}
