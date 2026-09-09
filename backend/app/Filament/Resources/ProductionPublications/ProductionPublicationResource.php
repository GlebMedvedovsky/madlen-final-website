<?php

namespace App\Filament\Resources\ProductionPublications;

use App\Filament\Resources\ProductionPublications\Pages\ListProductionPublications;
use App\Filament\Resources\ProductionPublications\Tables\ProductionPublicationsTable;
use App\Models\ProductionPublication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductionPublicationResource extends Resource
{
    protected static ?string $model = ProductionPublication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    protected static ?string $navigationLabel = 'Produktiv-Aufträge';

    protected static ?string $modelLabel = 'Produktiv-Auftrag';

    protected static ?string $pluralModelLabel = 'Produktiv-Aufträge';

    protected static ?int $navigationSort = 51;

    public static function shouldRegisterNavigation(): bool
    {
        return config('madlen.production_connected') && config('madlen.production_publisher') === 'github-actions';
    }

    public static function canViewAny(): bool
    {
        return static::shouldRegisterNavigation() && parent::canViewAny();
    }

    public static function table(Table $table): Table
    {
        return ProductionPublicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListProductionPublications::route('/')];
    }
}
