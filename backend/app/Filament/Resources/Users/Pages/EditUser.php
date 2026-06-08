<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\Auth\UserAccessService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('grantAccess')
                ->label('Grant access')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'pending')
                ->requiresConfirmation()
                ->modalDescription('Activate this account using the role and schools saved on this form.')
                ->action(function (UserAccessService $access): void {
                    $this->form->validate();
                    $data = $this->form->getState();

                    try {
                        $this->record = $access->activate(
                            $this->record,
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

                    $this->refreshFormData(['status', 'role_id', 'schools']);

                    Notification::make()
                        ->title('Access granted')
                        ->success()
                        ->send();
                }),
            Action::make('revokeAccess')
                ->label('Revoke access')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === 'active')
                ->action(function (UserAccessService $access): void {
                    $this->record = $access->deactivate($this->record);
                    $this->refreshFormData(['status']);

                    Notification::make()
                        ->title('Access revoked')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['set_panel_password']);

        return $data;
    }

    protected function afterSave(): void
    {
        app(UserAccessService::class)->validateCanActivate($this->record->fresh(['role', 'schools']));
    }
}
