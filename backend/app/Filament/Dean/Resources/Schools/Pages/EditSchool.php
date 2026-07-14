<?php

namespace App\Filament\Dean\Resources\Schools\Pages;

use App\Filament\Dean\Resources\Schools\SchoolResource;
use Filament\Resources\Pages\EditRecord;

class EditSchool extends EditRecord
{
    protected static string $resource = SchoolResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->rollCallSettingsOrDefault();

        return $data;
    }
}
