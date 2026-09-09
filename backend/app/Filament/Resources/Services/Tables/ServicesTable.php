<?php

namespace App\Filament\Resources\Services\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use App\Models\Service;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title_de')->label('Leistung')->searchable()->sortable()->description(fn ($record): string => $record->key),
                TextColumn::make('de_readiness')
                    ->label('DE')
                    ->state(fn (Service $record): string => $record->active_de && $record->isLocaleReady('de') ? 'Bereit' : 'Unvollständig')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Bereit' ? 'success' : 'danger'),
                TextColumn::make('en_readiness')
                    ->label('EN')
                    ->state(fn (Service $record): string => $record->englishReadinessLabel())
                    ->description(fn (Service $record): ?string => $record->key === 'editing' ? 'in „Videography & Editing“' : null)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Bereit' => 'success',
                        'Zusammengefasst' => 'info',
                        default => 'danger',
                    }),
                TextColumn::make('position')->label('Reihenfolge')->sortable(),
                TextColumn::make('updated_at')->label('Geändert')->since()->sortable(),
            ])
            ->reorderable('position')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
