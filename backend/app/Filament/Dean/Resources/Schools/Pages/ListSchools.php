<?php

namespace App\Filament\Dean\Resources\Schools\Pages;

use App\Filament\Dean\Resources\Schools\SchoolResource;
use Filament\Resources\Pages\ListRecords;

class ListSchools extends ListRecords
{
    protected static string $resource = SchoolResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
