<?php

namespace App\Filament\Resources\MediaAssets\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use App\Models\MediaAsset;
use App\Support\AdminMedia;

class MediaAssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datei')
                    ->description('Dateien erhalten eindeutige Namen. Ein Ersatz überschreibt niemals die bisherige Datei.')
                    ->schema([
                        Placeholder::make('admin_preview')
                            ->label('Aktuelle Vorschau')
                            ->content(fn (?MediaAsset $record) => AdminMedia::preview($record))
                            ->visible(fn (?MediaAsset $record): bool => filled($record)),
                        FileUpload::make('path')
                            ->label('Bild oder vorbereitetes Web-Video')
                            ->disk('local')
                            ->directory('media/originals')
                            ->visibility('private')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/webm'])
                            ->maxSize(config('madlen.media.max_video_kb'))
                            ->required(fn ($record): bool => blank($record))
                            ->visible(fn ($record): bool => ! $record?->source_managed)
                            ->downloadable()
                            ->openable(),
                        Placeholder::make('source_path')
                            ->label('Importierte Quelldatei')
                            ->content(fn ($record): string => $record?->path ?? '')
                            ->visible(fn ($record): bool => (bool) $record?->source_managed),
                        Hidden::make('mime_type')->default('application/octet-stream'),
                        Hidden::make('kind')->default('image'),
                        Hidden::make('source_managed')->default(false),
                    ]),
                Tabs::make('Bildtexte')
                    ->tabs([
                        Tab::make('Deutsche Inhalte')->schema([
                            Textarea::make('alt_de')->label('Alternativtext')->rows(3)->maxLength(1000),
                            Textarea::make('caption_de')->label('Bildunterschrift')->rows(3)->maxLength(2000),
                        ]),
                        Tab::make('Englische Inhalte')->schema([
                            Textarea::make('alt_en')->label('Alternativtext')->rows(3)->maxLength(1000),
                            Textarea::make('caption_en')->label('Bildunterschrift')->rows(3)->maxLength(2000),
                        ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
