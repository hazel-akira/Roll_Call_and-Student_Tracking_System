<?php

namespace App\Filament\Teacher\Resources\AttendanceSessions\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AttendanceSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('session_date', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('classRoom.grade_level')
                    ->label('Grade')
                    ->toggleable(),
                TextColumn::make('classRoom.section')
                    ->label('Stream')
                    ->toggleable(),
                TextColumn::make('classRoom.school.name')
                    ->label('School')
                    ->toggleable(),
                TextColumn::make('session_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'success',
                        'closed' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('dynamics_sync_status')
                    ->label('Dynamics')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'closed' => 'Closed',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
