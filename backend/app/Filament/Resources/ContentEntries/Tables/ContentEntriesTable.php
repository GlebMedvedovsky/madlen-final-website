<?php

namespace App\Filament\Resources\ContentEntries\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use App\Models\ContentEntry;

class ContentEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('visible_label')->label('Bereich')->state(fn (ContentEntry $record): string => $record->adminLabel())->searchable(['key']),
                TextColumn::make('group_name')->label('Seite')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'home' => 'Startseite', 'about' => 'Über mich', 'portfolio' => 'Portfolio', 'contact' => 'Kontakt',
                    'footer' => 'Fußbereich', 'nav' => 'Navigation', 'legal' => 'Rechtliche Seiten', default => $state,
                })->sortable(),
                TextColumn::make('updated_at')->label('Geändert')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('group_name')->label('Seite')->options([
                    'home' => 'Startseite', 'about' => 'Über mich', 'portfolio' => 'Portfolio', 'contact' => 'Kontakt',
                    'footer' => 'Footer', 'nav' => 'Navigation', 'legal' => 'Rechtliche Seiten',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
