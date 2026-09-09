<?php

namespace App\Filament\Resources\SiteMediaSlots;

use App\Filament\Resources\SiteMediaSlots\Pages\EditSiteMediaSlot;
use App\Filament\Resources\SiteMediaSlots\Pages\ListSiteMediaSlots;
use App\Models\SiteMediaSlot;
use BackedEnum;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Illuminate\Database\Eloquent\Builder;
use App\Models\MediaAsset;
use App\Support\AdminMedia;

class SiteMediaSlotResource extends Resource
{
    protected static ?string $model = SiteMediaSlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;
    protected static ?string $navigationLabel = 'Startseiten-Medien';
    protected static ?string $modelLabel = 'Startseiten-Medium';
    protected static ?string $pluralModelLabel = 'Startseiten-Medien';
    protected static ?string $recordTitleAttribute = 'label_de';
    protected static ?int $navigationSort = 55;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Medium auswählen')
                ->description('Neue Dateien zuerst unter „Medien“ hochladen. Änderungen werden erst mit einer Veröffentlichung öffentlich.')
                ->schema([
                    Placeholder::make('label_de')->label('Bereich')->content(fn (?SiteMediaSlot $record): string => $record?->label_de ?? ''),
                    Placeholder::make('current_preview')
                        ->label('Aktuelle Auswahl')
                        ->content(fn (?SiteMediaSlot $record) => AdminMedia::preview($record?->mediaAsset)),
                    Select::make('media_asset_id')
                        ->label('Anderes Medium auswählen')
                        ->relationship(
                            'mediaAsset',
                            'original_name',
                            modifyQueryUsing: fn (Builder $query, ?SiteMediaSlot $record): Builder => $query
                                ->where('kind', $record?->key === 'home_video' ? 'video' : 'image'),
                        )
                        ->getOptionLabelFromRecordUsing(fn (MediaAsset $record): string => AdminMedia::option($record))
                        ->allowHtml()
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Für das Video MP4/WebM, für Poster und Foto JPEG/PNG/WebP verwenden.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('Vorschau')
                    ->state(fn (SiteMediaSlot $record): ?string => $record->mediaAsset?->kind === 'image' ? AdminMedia::url($record->mediaAsset) : null)
                    ->checkFileExistence(false)
                    ->square(),
                TextColumn::make('label_de')->label('Bereich')->searchable(),
                TextColumn::make('mediaAsset.original_name')->label('Datei')->searchable(),
                TextColumn::make('mediaAsset.kind')->label('Typ')->badge()->formatStateUsing(
                    fn (?string $state): string => $state === 'video' ? 'Video' : 'Bild',
                ),
                TextColumn::make('updated_at')->label('Geändert')->since()->sortable(),
            ])
            ->recordActions([
                EditAction::make()->label('Auswählen'),
            ])
            ->recordUrl(fn (SiteMediaSlot $record): string => static::getUrl('edit', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiteMediaSlots::route('/'),
            'edit' => EditSiteMediaSlot::route('/{record}/edit'),
        ];
    }
}
