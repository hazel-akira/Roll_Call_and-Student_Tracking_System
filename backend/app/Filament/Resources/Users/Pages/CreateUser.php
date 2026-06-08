<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\Auth\UserAccessService;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['set_panel_password']);

        if (! isset($data['status'])) {
            $data['status'] = 'pending';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        app(UserAccessService::class)->validateCanActivate($this->record->fresh(['role', 'schools']));
    }
}
