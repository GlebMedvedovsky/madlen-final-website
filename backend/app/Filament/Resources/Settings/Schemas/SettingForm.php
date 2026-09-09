<?php

namespace App\Filament\Resources\Settings\Schemas;

use App\Models\Setting;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('key'),
            Hidden::make('label_de'),
            Hidden::make('value'),
            Section::make(fn (?Setting $record): string => $record?->adminLabel() ?? 'Website-Einstellung')
                ->description('Keine Zugangsdaten oder Geheimnisse hier speichern.')
                ->schema([
                    Select::make('editor_locale')
                        ->label('Hauptsprache')
                        ->options(['de' => 'Deutsch', 'en' => 'Englisch'])
                        ->required()
                        ->visible(fn (?Setting $record): bool => $record?->key === 'primaryLocale'),
                    TextInput::make('editor_text')
                        ->label(fn (?Setting $record): string => $record?->key === 'siteUrl' ? 'Website-Adresse' : 'Kontakt-E-Mail')
                        ->email(fn (?Setting $record): bool => $record?->key === 'contactEmail')
                        ->url(fn (?Setting $record): bool => $record?->key === 'siteUrl')
                        ->required()
                        ->maxLength(1000)
                        ->visible(fn (?Setting $record): bool => in_array($record?->key, ['contactEmail', 'siteUrl'], true)),
                ]),
        ]);
    }
}
