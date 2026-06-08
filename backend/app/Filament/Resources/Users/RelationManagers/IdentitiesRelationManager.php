<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IdentitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'identities';

    protected static ?string $title = 'Sign-in history (Microsoft)';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider')
                    ->badge(),
                TextColumn::make('provider_email')
                    ->label('Microsoft email'),
                TextColumn::make('tenant_id')
                    ->label('Tenant ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_login_at')
                    ->label('Last Microsoft sign-in')
                    ->since()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Linked')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('last_login_at', 'desc')
            ->paginated([10, 25]);
    }
}
