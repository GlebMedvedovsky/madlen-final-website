<?php

namespace App\Filament\Resources\ContentEntries;

use App\Filament\Resources\ContentEntries\Pages\EditContentEntry;
use App\Filament\Resources\ContentEntries\Pages\ListContentEntries;
use App\Filament\Resources\ContentEntries\Schemas\ContentEntryForm;
use App\Filament\Resources\ContentEntries\Tables\ContentEntriesTable;
use App\Models\ContentEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContentEntryResource extends Resource
{
    protected static ?string $model = ContentEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Seiten & Texte';
    protected static ?string $modelLabel = 'Textbereich';
    protected static ?string $pluralModelLabel = 'Seiten & Texte';
    protected static ?string $recordTitleAttribute = 'key';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return ContentEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContentEntriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentEntries::route('/'),
            'edit' => EditContentEntry::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
