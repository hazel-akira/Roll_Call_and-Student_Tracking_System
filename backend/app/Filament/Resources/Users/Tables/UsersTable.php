<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Services\Auth\UserAccessService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_login_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('role.name')
                    ->label('Role')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Access')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('schools.name')
                    ->label('Schools')
                    ->badge()
                    ->placeholder('—')
                    ->limitList(3),
                IconColumn::make('identities_count')
                    ->counts('identities')
                    ->label('SSO')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedMinusCircle)
                    ->tooltip(fn (User $record): string => $record->identities_count > 0
                        ? 'Signed in with Microsoft before'
                        : 'No Microsoft sign-in yet'),
                TextColumn::make('last_login_at')
                    ->label('Last sign-in')
                    ->since()
                    ->sortable()
                    ->placeholder('Never'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->relationship('role', 'name'),
                SelectFilter::make('status')
                    ->label('Access status')
                    ->options([
                        'pending' => 'Pending approval',
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                Action::make('grantAccess')
                    ->label('Grant access')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->status === 'pending')
                    ->modalHeading('Grant system access')
                    ->modalDescription('Assign a role and schools, then activate the account so the user can sign in.')
                    ->modalSubmitActionLabel('Grant access')
                    ->schema(fn (): array => self::grantAccessFormSchema())
                    ->fillForm(fn (User $record): array => [
                        'role_id' => $record->role_id,
                        'schools' => $record->schools()->pluck('schools.id')->all(),
                    ])
                    ->action(function (User $record, array $data, UserAccessService $access): void {
                        try {
                            $access->activate(
                                $record,
                                (int) $data['role_id'],
                                $data['schools'] ?? [],
                            );
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Could not grant access')
                                ->body(collect($exception->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            throw $exception;
                        }

                        Notification::make()
                            ->title('Access granted')
                            ->body("{$record->name} can now sign in to the system.")
                            ->success()
                            ->send();
                    }),
                Action::make('revokeAccess')
                    ->label('Revoke')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->status === 'active')
                    ->action(function (User $record, UserAccessService $access): void {
                        $access->deactivate($record);

                        Notification::make()
                            ->title('Access revoked')
                            ->body("{$record->name} can no longer sign in.")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private static function grantAccessFormSchema(): array
    {
        $access = app(UserAccessService::class);

        return [
            Select::make('role_id')
                ->label('Role')
                ->options($access->roleOptions())
                ->required()
                ->native(false),
            Select::make('schools')
                ->label('Schools')
                ->options($access->schoolOptions())
                ->multiple()
                ->searchable()
                ->helperText('Required when the role is Teacher.'),
        ];
    }
}
