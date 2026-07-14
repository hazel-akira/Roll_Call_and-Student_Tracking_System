<?php

namespace App\Filament\Resources\Schools\RelationManagers;

use App\Models\WeeklyDutyRoster;
use App\Support\DutyRosterCategories;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WeeklyDutyRostersRelationManager extends RelationManager
{
    protected static string $relationship = 'weeklyDutyRosters';

    protected static ?string $title = 'Weekly duty roster';

    protected static ?string $modelLabel = 'weekly roster';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('week_start')
                ->label('Week starts')
                ->required()
                ->native(false)
                ->default(now()->startOfWeek())
                ->live(),
            DatePicker::make('week_end')
                ->label('Week ends')
                ->native(false)
                ->default(fn (callable $get) => filled($get('week_start'))
                    ? \Illuminate\Support\Carbon::parse($get('week_start'))->addDays(6)
                    : null)
                ->helperText('Matches the date range shown on the paper roster (e.g. 10th – 17th).'),
            Repeater::make('entries')
                ->relationship()
                ->label('Duty assignments')
                ->addActionLabel('Add duty row')
                ->reorderable()
                ->orderColumn('sort_order')
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $this->entryLabel($state))
                ->schema([
                    Select::make('category')
                        ->label('Section')
                        ->options(DutyRosterCategories::labels())
                        ->required()
                        ->native(false)
                        ->columnSpan(1),
                    TextInput::make('location')
                        ->label('Location / group')
                        ->placeholder('e.g. G.10, F3, Upper Field, CU')
                        ->maxLength(255)
                        ->columnSpan(1),
                    TextInput::make('time_slot')
                        ->label('Time slot')
                        ->placeholder('e.g. 7:00 AM – 5:00 PM')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Select::make('staff')
                        ->label('Staff on duty')
                        ->relationship(
                            'staff',
                            'name',
                            fn (Builder $query) => $this->schoolStaffQuery($query),
                        )
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('week_start', 'desc')
            ->columns([
                TextColumn::make('week_start')
                    ->label('Week')
                    ->formatStateUsing(fn (WeeklyDutyRoster $record): string => $record->weekLabel())
                    ->sortable(),
                TextColumn::make('entries_count')
                    ->label('Duty rows')
                    ->counts('entries'),
                TextColumn::make('staff_count')
                    ->label('Staff assigned')
                    ->state(fn (WeeklyDutyRoster $record): int => $record->entries()
                        ->withCount('staff')
                        ->get()
                        ->sum('staff_count')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New weekly roster')
                    ->after(function (WeeklyDutyRoster $record): void {
                        $record->seedStandardTemplate();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('loadTemplate')
                    ->label('Reset to standard layout')
                    ->icon('heroicon-o-document-duplicate')
                    ->requiresConfirmation()
                    ->action(function (WeeklyDutyRoster $record): void {
                        $record->entries()->each(function ($entry): void {
                            $entry->staff()->detach();
                            $entry->delete();
                        });
                        $record->seedStandardTemplate();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function entryLabel(array $state): ?string
    {
        $category = DutyRosterCategories::label((string) ($state['category'] ?? ''));

        if ($category === '') {
            return null;
        }

        $parts = array_filter([
            $category,
            $state['location'] ?? null,
            $state['time_slot'] ?? null,
        ]);

        return implode(' · ', $parts);
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
}
