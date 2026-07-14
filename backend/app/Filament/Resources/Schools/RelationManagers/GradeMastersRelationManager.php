<?php

namespace App\Filament\Resources\Schools\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GradeMastersRelationManager extends RelationManager
{
    protected static string $relationship = 'gradeMasterAssignments';

    protected static ?string $title = 'Grade masters';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('grade_level')
                ->label('Grade level')
                ->required()
                ->maxLength(255)
                ->datalist(function (): array {
                    /** @var \App\Models\School $school */
                    $school = $this->getOwnerRecord();

                    return $school->classes()
                        ->whereNotNull('grade_level')
                        ->distinct()
                        ->orderBy('grade_level')
                        ->pluck('grade_level')
                        ->filter()
                        ->values()
                        ->all();
                }),
            Select::make('user_id')
                ->label('Grade master')
                ->relationship(
                    'user',
                    'name',
                    fn (Builder $query) => $query
                        ->where('status', 'active')
                        ->whereNotNull('email')
                )
                ->searchable()
                ->preload()
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('grade_level')
                    ->label('Grade level')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Grade master')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
