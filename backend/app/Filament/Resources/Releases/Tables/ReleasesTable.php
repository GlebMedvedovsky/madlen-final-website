<?php

namespace App\Filament\Resources\Releases\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
            ]);
    }
}
