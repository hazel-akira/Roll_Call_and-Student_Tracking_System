<?php

namespace App\Filament\Resources\Schools\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SchoolForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('School details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('level')
                            ->maxLength(255),
                        TextInput::make('dynamics_id')
                            ->label('Dynamics ID')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Toggle::make('is_junior')
                            ->label('Junior school'),
                        Toggle::make('active')
                            ->default(true),
                    ]),
                Section::make('Roll call email rules')
                    ->description('Control who receives automatic roll call report emails for this school. Assigned recipients and weekly duty teachers are managed in the tabs below.')
                    ->relationship('rollCallSettings')
                    ->columns(2)
                    ->schema([
                        Toggle::make('assigned_recipients_only')
                            ->label('Only use assigned recipients')
                            ->helperText('When enabled, only staff listed under Report email recipients (plus weekly duty teachers if enabled below) receive emails.'),
                        Toggle::make('notify_duty_roster')
                            ->label('Include weekly duty roster teachers')
                            ->default(true),
                        Toggle::make('notify_school_admins')
                            ->label('Include school admins / ICT staff')
                            ->default(true),
                        Toggle::make('notify_homeroom_teacher')
                            ->label('Include form master (homeroom teacher)')
                            ->default(true),
                        Toggle::make('notify_grade_master')
                            ->label('Include grade master')
                            ->default(true),
                        Toggle::make('notify_session_teacher')
                            ->label('Include teacher who took roll call')
                            ->default(true),
                    ]),
            ]);
    }
}
