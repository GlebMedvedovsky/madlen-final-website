<?php

namespace App\Filament\Resources\Revisions;

use App\Filament\Resources\Revisions\Pages\ListRevisions;
use App\Models\ContentEntry;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Service;
use App\Services\RevisionRestorer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RevisionResource extends Resource
{
    protected static ?string $model = Revision::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;
    protected static ?string $navigationLabel = 'Änderungsverlauf';
    protected static ?string $modelLabel = 'Änderung';
    protected static ?string $pluralModelLabel = 'Änderungsverlauf';
    protected static ?int $navigationSort = 70;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('revisionable_type')->label('Bereich')->formatStateUsing(fn (string $state): string => match ($state) {
                    Project::class => 'Projekt', ContentEntry::class => 'Seite / Text', Service::class => 'Leistung', default => 'Inhalt',
                }),
                TextColumn::make('record_title')->label('Datensatz')->state(fn (Revision $record): string => app(RevisionRestorer::class)->title($record)),
                TextColumn::make('event')->label('Änderung')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'deleting' => 'Papierkorb', 'restoring' => 'Wiederhergestellt', default => 'Bearbeitet',
                }),
                TextColumn::make('user.name')->label('Benutzer')->placeholder('System'),
                TextColumn::make('created_at')->label('Zeitpunkt')->dateTime('d.m.Y H:i:s')->sortable(),
            ])
            ->recordActions([
                Action::make('restoreRevision')
                    ->label('Diesen Stand wiederherstellen')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->requiresConfirmation()
                    ->visible(fn (Revision $record): bool => app(RevisionRestorer::class)->supports($record))
                    ->action(function (Revision $record): void {
                        try {
                            app(RevisionRestorer::class)->restore($record);
                        } catch (\Throwable $error) {
                            Notification::make()->title('Stand nicht wiederherstellbar')->danger()->send();
                            return;
                        }
                        Notification::make()->title('Früherer Stand als neuer aktueller Stand gespeichert')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListRevisions::route('/')];
    }

}
