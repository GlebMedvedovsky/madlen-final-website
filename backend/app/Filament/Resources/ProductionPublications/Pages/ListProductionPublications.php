<?php

namespace App\Filament\Resources\ProductionPublications\Pages;

use App\Filament\Resources\ProductionPublications\ProductionPublicationResource;
use Filament\Resources\Pages\ListRecords;

class ListProductionPublications extends ListRecords
{
    protected static string $resource = ProductionPublicationResource::class;
}
