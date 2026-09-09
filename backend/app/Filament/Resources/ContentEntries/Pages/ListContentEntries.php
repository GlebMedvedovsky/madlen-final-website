<?php

namespace App\Filament\Resources\ContentEntries\Pages;

use App\Filament\Resources\ContentEntries\ContentEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListContentEntries extends ListRecords
{
    protected static string $resource = ContentEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
