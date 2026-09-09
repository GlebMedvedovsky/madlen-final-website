<?php

namespace App\Filament\Resources\Releases\Tables;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Models\Release;
use App\Services\ReleasePublisher;

class ReleasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version')->label('Version')->sortable(),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'active' => 'Aktiv', 'superseded' => 'Früher', 'failed' => 'Fehlgeschlagen', default => 'Wird gebaut',
                }),
                TextColumn::make('creator.name')->label('Erstellt von')->default('System'),
                TextColumn::make('published_at')->label('Aktiviert')->dateTime('d.m.Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('error_message')->label('Fehler')->limit(80)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('rollback')
                    ->label('Auf diesen Stand zurücksetzen')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->requiresConfirmation()
                    ->visible(fn (Release $record): bool => $record->status === 'superseded')
                    ->action(function (Release $record): void {
                        app(ReleasePublisher::class)->rollback($record);
                        Notification::make()->title("Release {$record->version} ist wieder aktiv")->success()->send();
                    }),
                Action::make('retry')
                    ->label('Erneut versuchen')
                    ->visible(fn (Release $record): bool => $record->status === 'failed')
                    ->action(function (): void {
                        $release = app(ReleasePublisher::class)->publish();
                        Notification::make()->title("Release {$release->version} veröffentlicht")->success()->send();
                    }),
            ]);
    }
}
