<?php

namespace App\Filament\Resources\Settings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use App\Models\Setting;

class SettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('visible_label')->label('Einstellung')->state(fn (Setting $record): string => $record->adminLabel())->searchable(['key', 'label_de']),
                TextColumn::make('visible_value')->label('Wert')->state(fn (Setting $record): string => (string) ($record->value['value'] ?? '')),
                TextColumn::make('updated_at')->label('Geändert')->since()->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
