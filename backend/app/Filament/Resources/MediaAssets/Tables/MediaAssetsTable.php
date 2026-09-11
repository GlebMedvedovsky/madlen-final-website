<?php

namespace App\Filament\Resources\MediaAssets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use App\Models\MediaAsset;
use App\Support\AdminMedia;
use Illuminate\Database\Eloquent\Builder;

class MediaAssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['projects', 'coveredProjects', 'siteSlots']))
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('Vorschau')
                    ->state(fn (MediaAsset $record): ?string => $record->kind === 'image' ? AdminMedia::url($record) : null)
                    ->defaultImageUrl(fn (MediaAsset $record): ?string => $record->kind === 'image' ? AdminMedia::url($record) : null)
                    ->checkFileExistence(false)
                    ->square(),
                TextColumn::make('original_name')->label('Datei')->searchable()->description(fn (MediaAsset $record): string => $record->mime_type),
                TextColumn::make('dimensions')->label('Abmessungen')->state(fn (MediaAsset $record): string => $record->width ? "{$record->width} × {$record->height}" : '—'),
                TextColumn::make('usage')->label('Verwendung')->state(fn (MediaAsset $record): string => ($record->projects_count + $record->covered_projects_count + $record->site_slots_count).' Zuordnung(en)'),
                TextColumn::make('updated_at')->label('Geändert')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('kind')->label('Typ')->options(['image' => 'Bild', 'video' => 'Video']),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->before(function (DeleteBulkAction $action, $records): void {
                        if ($records->contains(fn (MediaAsset $asset): bool => $asset->isUsed())) {
                            \Filament\Notifications\Notification::make()->title('Auswahl wurde nicht gelöscht')
                                ->body('Mindestens ein Medium wird noch verwendet, auch in wiederherstellbaren Projekten. Zuerst die Zuordnung lösen.')
                                ->warning()->persistent()->send();
                            $action->halt();
                        }
                    })->databaseTransaction(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
