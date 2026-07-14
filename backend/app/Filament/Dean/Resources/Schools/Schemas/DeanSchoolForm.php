<?php

namespace App\Filament\Dean\Resources\Schools\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeanSchoolForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('School')
                    ->description('School details are managed by administrators. Use the tabs below to assign weekly duty, report recipients, and grade masters.')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('name')
                            ->label('School name')
                            ->content(fn ($record) => $record?->name ?? '—'),
                        Placeholder::make('code')
                            ->label('School code')
                            ->content(fn ($record) => $record?->code ?? '—'),
                    ]),
                Section::make('Roll call email rules')
                    ->description('Control who receives automatic roll call report emails for this school.')
                    ->relationship('rollCallSettings')
                    ->columns(2)
                    ->schema([
                        \Filament\Forms\Components\Toggle::make('assigned_recipients_only')
                            ->label('Only use assigned recipients'),
                        \Filament\Forms\Components\Toggle::make('notify_duty_roster')
                            ->label('Include weekly duty roster teachers')
                            ->default(true),
                        \Filament\Forms\Components\Toggle::make('notify_school_admins')
                            ->label('Include school admins / ICT staff')
                            ->default(true),
                        \Filament\Forms\Components\Toggle::make('notify_homeroom_teacher')
                            ->label('Include form master (homeroom teacher)')
                            ->default(true),
                        \Filament\Forms\Components\Toggle::make('notify_grade_master')
                            ->label('Include grade master')
                            ->default(true),
                        \Filament\Forms\Components\Toggle::make('notify_session_teacher')
                            ->label('Include teacher who took roll call')
                            ->default(true),
                    ]),
            ]);
    }
}
