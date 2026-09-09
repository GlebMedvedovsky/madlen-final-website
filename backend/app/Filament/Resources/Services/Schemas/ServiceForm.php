<?php

namespace App\Filament\Resources\Services\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Darstellung')->columns(3)->schema([
                    TextInput::make('key')->label('Stabile Kennung')->required()->unique(ignoreRecord: true)->disabled(fn ($record): bool => filled($record))->dehydrated(),
                    TextInput::make('icon')->label('Symbol')->maxLength(20),
                    TextInput::make('position')->label('Reihenfolge')->numeric()->required()->default(100),
                    Toggle::make('highlighted')->label('Hervorgehoben'),
                ]),
                Tabs::make('Sprachinhalte')->tabs([
                    Tab::make('Deutsche Inhalte')->schema([
                        Toggle::make('active_de')->label('Auf deutscher Seite anzeigen')->default(true),
                        TextInput::make('title_de')->label('Titel')->required()->maxLength(255),
                        Textarea::make('description_de')->label('Beschreibung')->required()->rows(6),
                    ]),
                    Tab::make('Englische Inhalte')->schema([
                        Placeholder::make('english_grouping')
                            ->label('Englische Zuordnung')
                            ->content('Diese deutsche Leistung ist auf der englischen Seite im gemeinsamen Block „Videography & Editing“ enthalten.')
                            ->visible(fn ($record): bool => $record?->key === 'editing'),
                        Toggle::make('active_en')
                            ->label('Auf englischer Seite anzeigen')
                            ->default(true)
                            ->visible(fn ($record): bool => $record?->key !== 'editing'),
                        TextInput::make('title_en')
                            ->label('Titel')
                            ->required(fn ($get, $record): bool => $record?->key !== 'editing' && (bool) $get('active_en'))
                            ->maxLength(255)
                            ->visible(fn ($record): bool => $record?->key !== 'editing'),
                        Textarea::make('description_en')
                            ->label('Beschreibung')
                            ->required(fn ($get, $record): bool => $record?->key !== 'editing' && (bool) $get('active_en'))
                            ->rows(6)
                            ->visible(fn ($record): bool => $record?->key !== 'editing'),
                    ]),
                ])->columnSpanFull(),
            ]);
    }
}
