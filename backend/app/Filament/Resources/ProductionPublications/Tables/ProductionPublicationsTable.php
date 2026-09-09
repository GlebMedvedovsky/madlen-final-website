<?php

namespace App\Filament\Resources\ProductionPublications\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductionPublicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sequence', 'desc')
            ->columns([
                TextColumn::make('sequence')->label('Auftrag')->sortable(),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'preparing' => 'Wird vorbereitet',
                    'prepared' => 'Paket bereit',
                    'queued' => 'Wartet auf Build',
                    'building' => 'Website wird gebaut',
                    'uploading' => 'Wird übertragen',
                    'active' => 'Produktiv aktiv',
                    'superseded' => 'Früher aktiv',
                    'failed' => 'Fehlgeschlagen',
                    default => $state,
                }),
                TextColumn::make('progress_message')->label('Fortschritt')->wrap(),
                TextColumn::make('requester.name')->label('Angefordert von')->default('System'),
                TextColumn::make('activated_at')->label('Aktiviert')->dateTime('d.m.Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('error_message')->label('Fehler')->limit(100)->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
