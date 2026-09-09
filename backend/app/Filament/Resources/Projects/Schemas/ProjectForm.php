<?php

namespace App\Filament\Resources\Projects\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use App\Models\MediaAsset;
use App\Support\AdminMedia;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Projektstruktur')
                    ->description('Die URL bleibt nach dem Anlegen stabil. Titeländerungen ändern sie nicht automatisch.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('slug')
                            ->label('Stabile URL-Kennung')
                            ->required()
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->disabled(fn ($record): bool => filled($record))
                            ->dehydrated(),
                        Select::make('category_id')
                            ->label('Kategorie')
                            ->relationship('category', 'label_de')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('cover_media_id')
                            ->label('Titelbild')
                            ->relationship(
                                'cover',
                                'original_name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('kind', 'image'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (MediaAsset $record): string => AdminMedia::option($record))
                            ->allowHtml()
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('position')
                            ->label('Reihenfolge')
                            ->numeric()
                            ->required()
                            ->default(100),
                    ]),
                Tabs::make('Website-Inhalte')
                    ->tabs([
                        Tab::make('Deutsche Inhalte')->schema([
                            TextInput::make('title_de')->label('Titel')->required()->maxLength(255),
                            Textarea::make('description_de')->label('Beschreibung')->required()->rows(5),
                        ]),
                        Tab::make('Englische Inhalte')->schema([
                            TextInput::make('title_en')->label('Titel')->required()->maxLength(255),
                            Textarea::make('description_en')->label('Beschreibung')->required()->rows(5),
                        ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Galerie')
                    ->description('Reihenfolge per Ziehen oder mit den zugänglichen Auf/Ab-Schaltflächen; links/rechts bleibt wie im bestehenden Portfolio erhalten.')
                    ->schema([
                        Repeater::make('mediaItems')
                            ->label('Galeriebilder')
                            ->relationship()
                            ->defaultItems(0)
                            ->orderColumn('position')
                            ->reorderableWithButtons()
                            ->schema([
                                Select::make('media_asset_id')
                                    ->label('Bild')
                                    ->relationship(
                                        'mediaAsset',
                                        'original_name',
                                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('kind', 'image'),
                                    )
                                    ->getOptionLabelFromRecordUsing(fn (MediaAsset $record): string => AdminMedia::option($record))
                                    ->allowHtml()
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('side')
                                    ->label('Platzierung')
                                    ->options(['left' => 'Links', 'right' => 'Rechts'])
                                    ->required()
                                    ->default('left'),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->cloneable()
                            ->addActionLabel('Galeriebild hinzufügen')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
