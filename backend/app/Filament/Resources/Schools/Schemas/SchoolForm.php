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
            ]);
    }
}
