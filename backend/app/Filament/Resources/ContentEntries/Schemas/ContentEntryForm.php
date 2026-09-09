<?php

namespace App\Filament\Resources\ContentEntries\Schemas;

use App\Models\ContentEntry;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ContentEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('key'),
            Hidden::make('group_name'),
            Hidden::make('type'),
            Hidden::make('position'),
            Hidden::make('value_de'),
            Hidden::make('value_en'),
            Section::make(fn (?ContentEntry $record): string => $record?->adminLabel() ?? 'Inhalt')
                ->description('Hier ändern Sie nur die sichtbaren Website-Texte. Layout, Schlüssel und Programmcode bleiben geschützt.')
                ->schema([
                    Tabs::make('Sprachinhalte')->tabs([
                        Tab::make('Deutsche Inhalte')->schema(self::localeFields('structured_de', 'plain_de')),
                        Tab::make('Englische Inhalte')->schema(self::localeFields('structured_en', 'plain_en')),
                    ]),
                ]),
        ]);
    }

    private static function localeFields(string $state, string $plain): array
    {
        return [
            Section::make('Navigation')
                ->visible(fn (?ContentEntry $record): bool => $record?->key === 'translations.nav')
                ->columns(2)
                ->schema([
                    self::text("{$state}.home", 'Startseite'),
                    self::text("{$state}.portfolio", 'Portfolio'),
                    self::text("{$state}.services", 'Leistungen'),
                    self::text("{$state}.about", 'Über mich'),
                    self::text("{$state}.contact", 'Kontakt'),
                    self::text("{$state}.inquiry", 'Anfrage-Schaltfläche'),
                    self::text("{$state}.questions", 'Fragen-Schaltfläche'),
                ]),
            Section::make('Startseite')
                ->visible(fn (?ContentEntry $record): bool => $record?->key === 'translations.home')
                ->schema([
                    Section::make('Einleitung „Hallo, ich bin Madlen“')->columns(2)->schema([
                        self::text("{$state}.eyebrow", 'Einleitungszeile'),
                        self::text("{$state}.name", 'Name'),
                        self::area("{$state}.intro", 'Standort und Verfügbarkeit'),
                        self::area("{$state}.description", 'Kurzbeschreibung'),
                        self::text("{$state}.moreAboutMe", 'Schaltfläche „Mehr über mich“'),
                        self::text("{$state}.viewPortfolio", 'Schaltfläche „Portfolio ansehen“'),
                        self::text("{$state}.sendInquiry", 'Schaltfläche „Anfrage senden“'),
                    ]),
                    Section::make('Video-Steuerung')->columns(2)->schema([
                        self::text("{$state}.video.pause", 'Video pausieren'),
                        self::text("{$state}.video.play", 'Video abspielen'),
                        self::text("{$state}.video.fallback", 'Hinweis bei nicht abspielbarem Video'),
                    ]),
                    Section::make('Erinnerungen')->schema([
                        self::text("{$state}.memories.title", 'Überschrift'),
                        self::simpleParagraphs("{$state}.memories.paragraphs", 'Einleitende Absätze'),
                        self::text("{$state}.memories.highlight", 'Hervorgehobene Zeile'),
                        self::simpleParagraphs("{$state}.memories.continuation", 'Weitere Absätze'),
                        self::simpleParagraphs("{$state}.memories.closing", 'Abschlusszeilen'),
                        self::text("{$state}.memories.imageAlt", 'Bildbeschreibung für Screenreader'),
                    ]),
                    Section::make('Was meine Arbeit ausmacht')->schema([
                        self::text("{$state}.workApart.title", 'Überschrift'),
                        Repeater::make("{$state}.workApart.items")
                            ->label('Vorteile')
                            ->schema([
                                TextInput::make('title')->label('Überschrift')->required()->maxLength(255),
                                Textarea::make('text')->label('Text')->required()->rows(5),
                            ])
                            ->columns(2)
                            ->reorderableWithButtons()
                            ->addActionLabel('Vorteil hinzufügen'),
                    ]),
                ]),
            Section::make('Portfolio')
                ->visible(fn (?ContentEntry $record): bool => $record?->key === 'translations.portfolio')
                ->columns(2)
                ->schema([
                    self::text("{$state}.label", 'Bereichsbezeichnung'),
                    self::text("{$state}.title", 'Seitentitel'),
                    self::area("{$state}.intro", 'Einleitung'),
                    self::text("{$state}.filters.all", 'Filter: Alle'),
                    self::text("{$state}.filters.portraits", 'Filter: Portraits'),
                    self::text("{$state}.filters.weddings", 'Filter: Hochzeiten'),
                    self::text("{$state}.filters.events", 'Filter: Events'),
                    self::text("{$state}.filters.editorial", 'Filter: Editorial'),
                    self::text("{$state}.filters.landscape", 'Filter: Landschaft'),
                    self::text("{$state}.nextProject", 'Nächstes Projekt'),
                ]),
            Section::make('Leistungen')
                ->visible(fn (?ContentEntry $record): bool => $record?->key === 'translations.services')
                ->columns(2)
                ->schema([
                    self::text("{$state}.eyebrow", 'Bereichsbezeichnung'),
                    self::text("{$state}.titleLead", 'Überschrift – erste Zeile'),
                    self::text("{$state}.titleEmphasis", 'Überschrift – hervorgehobene Zeile'),
                    self::area("{$state}.intro", 'Einleitung'),
                ]),
            Section::make('Über mich')
                ->visible(fn (?ContentEntry $record): bool => $record?->key === 'translations.about')
                ->schema([
                    self::text("{$state}.title", 'Seitentitel'),
                    self::text("{$state}.introTitle", 'Einleitungsüberschrift'),
                    self::simpleParagraphs("{$state}.text", 'Absätze'),
                    self::text("{$state}.closing", 'Abschlusszeile'),
                    self::text("{$state}.clientsTitle", 'Kunden-Überschrift'),
                ]),
            Section::make('Kontakt')
                ->visible(fn (?ContentEntry $record): bool => $record?->key === 'translations.contact')
                ->columns(2)
                ->schema([
                    self::text("{$state}.title", 'Seitentitel'),
                    self::area("{$state}.intro", 'Einleitung'),
                    self::area("{$state}.emailText", 'E-Mail-Hinweis'),
                    self::text("{$state}.formName", 'Formularfeld: Name'),
                    self::text("{$state}.namePlaceholder", 'Platzhalter: Name'),
                    self::text("{$state}.formEmail", 'Formularfeld: E-Mail'),
                    self::text("{$state}.emailPlaceholder", 'Platzhalter: E-Mail'),
                    self::text("{$state}.formPhone", 'Formularfeld: Telefon'),
                    self::text("{$state}.optional", 'Hinweis „optional“'),
                    self::text("{$state}.requestType", 'Formularfeld: Art der Anfrage'),
                    self::text("{$state}.requestSelect", 'Auswahl-Aufforderung'),
                    self::text("{$state}.preferredDate", 'Formularfeld: Wunschtermin'),
                    self::text("{$state}.datePlaceholder", 'Platzhalter: Wunschtermin'),
                    self::text("{$state}.location", 'Formularfeld: Ort'),
                    self::text("{$state}.locationPlaceholder", 'Platzhalter: Ort'),
                    self::text("{$state}.formMessage", 'Formularfeld: Nachricht'),
                    self::text("{$state}.messagePlaceholder", 'Platzhalter: Nachricht'),
                    self::text("{$state}.privacyStart", 'Datenschutzhinweis – Anfang'),
                    self::text("{$state}.privacyLink", 'Datenschutzhinweis – Linktext'),
                    self::area("{$state}.privacyEnd", 'Datenschutzhinweis – Abschluss'),
                    self::text("{$state}.requiredNote", 'Pflichtfeld-Hinweis'),
                    self::text("{$state}.formSubmit", 'Formular-Schaltfläche'),
                    self::text("{$state}.subject", 'Betreff der Kontaktanfrage'),
                    self::simpleParagraphs("{$state}.requestOptions", 'Arten der Anfrage'),
                ]),
            Section::make('Fußbereich')
                ->visible(fn (?ContentEntry $record): bool => $record?->key === 'translations.footer')
                ->columns(2)
                ->schema([
                    self::text("{$state}.impressum", 'Impressum'),
                    self::text("{$state}.datenschutz", 'Datenschutz'),
                    self::text("{$state}.agb", 'AGB / Hinweise'),
                    self::text("{$state}.copyright", 'Urheberrechtshinweis'),
                ]),
            Section::make('Rechtlicher Seitentext')
                ->description('Absätze und Zeilenumbrüche bleiben erhalten. Ausführbarer HTML-, CSS- oder JavaScript-Code ist nicht erlaubt.')
                ->visible(fn (?ContentEntry $record): bool => $record?->group_name === 'legal')
                ->schema([
                    Textarea::make($plain)->label('Inhalt')->rows(28)->required(),
                ]),
        ];
    }

    private static function text(string $name, string $label): TextInput
    {
        return TextInput::make($name)->label($label)->required()->maxLength(1000);
    }

    private static function area(string $name, string $label): Textarea
    {
        return Textarea::make($name)->label($label)->required()->rows(4);
    }

    private static function simpleParagraphs(string $name, string $label): Repeater
    {
        return Repeater::make($name)
            ->label($label)
            ->simple(Textarea::make('text')->label('Text')->required()->rows(4))
            ->reorderableWithButtons()
            ->addActionLabel('Absatz hinzufügen');
    }
}
