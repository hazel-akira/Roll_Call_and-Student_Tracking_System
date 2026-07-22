<?php

namespace App\Filament\Resources\Schools\RelationManagers;

use App\Services\DutyRoster\SchoolDutyRosterTemplateService;
use App\Support\DutyRosterCategories;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DutyRosterTemplateRelationManager extends RelationManager
{
    protected static string $relationship = 'dutyRosterTemplateEntries';

    protected static ?string $title = 'Duty roster default layout';

    protected static ?string $modelLabel = 'template row';

    protected static ?string $pluralModelLabel = 'template rows';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category')
                ->label('Section')
                ->options(DutyRosterCategories::labels())
                ->required()
                ->native(false),
            TextInput::make('location')
                ->label('Location / group')
                ->placeholder('e.g. G.10, F3, Upper Field, CU')
                ->maxLength(255),
            TextInput::make('time_slot')
                ->label('Time slot')
                ->placeholder('e.g. 7:00 AM – 5:00 PM')
                ->maxLength(255),
            TextInput::make('sort_order')
                ->label('Sort order')
                ->numeric()
                ->default(fn (): int => ((int) $this->getOwnerRecord()
                    ->dutyRosterTemplateEntries()
                    ->max('sort_order')) + 10)
                ->required()
                ->minValue(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('category')
                    ->label('Section')
                    ->formatStateUsing(fn (string $state): string => DutyRosterCategories::label($state))
                    ->sortable(),
                TextColumn::make('location')
                    ->label('Location')
                    ->placeholder('General')
                    ->searchable(),
                TextColumn::make('time_slot')
                    ->label('Time slot')
                    ->placeholder('All day')
                    ->searchable(),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add location')
                    ->mutateFormDataUsing(function (array $data): array {
                        app(SchoolDutyRosterTemplateService::class)
                            ->ensureTemplate($this->getOwnerRecord());

                        return $data;
                    }),
                Action::make('seedStandard')
                    ->label('Load Pioneer standard')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Replace this school’s default layout?')
                    ->modalDescription('This replaces the school default with the shared Pioneer standard. Existing weekly rosters are not changed.')
                    ->action(function (): void {
                        app(SchoolDutyRosterTemplateService::class)
                            ->resetToGlobalStandard($this->getOwnerRecord());

                        Notification::make()
                            ->title('School default layout restored to Pioneer standard')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No default locations yet')
            ->emptyStateDescription('Add the locations this school uses for weekly duty, or load the Pioneer standard layout.')
            ->emptyStateActions([
                Action::make('seedStandardEmpty')
                    ->label('Load Pioneer standard')
                    ->action(function (): void {
                        app(SchoolDutyRosterTemplateService::class)
                            ->resetToGlobalStandard($this->getOwnerRecord());

                        Notification::make()
                            ->title('Pioneer standard layout loaded')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
