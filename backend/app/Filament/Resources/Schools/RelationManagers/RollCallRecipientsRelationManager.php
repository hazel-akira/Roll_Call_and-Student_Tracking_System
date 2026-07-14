<?php

namespace App\Filament\Resources\Schools\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class RollCallRecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'rollCallReportRecipients';

    protected static ?string $title = 'Report email recipients';

    protected static ?string $modelLabel = 'recipient';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->label('Role / label')
                ->placeholder('e.g. Principal, Registrar')
                ->maxLength(255),
            Select::make('user_id')
                ->label('Staff member')
                ->relationship(
                    'user',
                    'name',
                    fn (Builder $query) => $this->schoolStaffQuery($query),
                )
                ->searchable()
                ->preload()
                ->live(),
            TextInput::make('email')
                ->label('External email')
                ->email()
                ->maxLength(255)
                ->helperText('Use when the recipient does not have a system account. Leave blank if a staff member is selected above.'),
            TextInput::make('grade_level')
                ->label('Grade level (optional)')
                ->placeholder('Leave empty for all grades')
                ->maxLength(255)
                ->datalist(fn (): array => $this->gradeLevelOptions()),
            Toggle::make('active')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Label')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Staff member')
                    ->placeholder('External email')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->state(fn ($record) => $record->resolvedEmail() ?? '—')
                    ->searchable(),
                TextColumn::make('grade_level')
                    ->label('Grade scope')
                    ->placeholder('All grades')
                    ->sortable(),
                IconColumn::make('active')
                    ->boolean(),
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

    /**
     * @param  array<string, mixed>  $data
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->validateRecipient($data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->validateRecipient($data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateRecipient(array $data): void
    {
        $hasUser = filled($data['user_id'] ?? null);
        $hasEmail = filled(trim((string) ($data['email'] ?? '')));

        if (! $hasUser && ! $hasEmail) {
            throw ValidationException::withMessages([
                'user_id' => 'Select a staff member or enter an external email address.',
            ]);
        }
    }

    /**
     * @param  Builder<\App\Models\User>  $query
     * @return Builder<\App\Models\User>
     */
    private function schoolStaffQuery(Builder $query): Builder
    {
        /** @var \App\Models\School $school */
        $school = $this->getOwnerRecord();

        return $query
            ->where('status', 'active')
            ->whereNotNull('email')
            ->whereHas('schools', fn (Builder $schoolQuery) => $schoolQuery->where('schools.id', $school->id));
    }

    /**
     * @return list<string>
     */
    private function gradeLevelOptions(): array
    {
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
    }
}
