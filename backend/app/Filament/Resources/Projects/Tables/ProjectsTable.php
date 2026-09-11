<?php

namespace App\Filament\Resources\Projects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use App\Models\Project;
use App\Support\AdminMedia;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_thumbnail')
                    ->label('Titelbild')
                    ->state(fn (Project $record): ?string => AdminMedia::url($record->cover))
                    ->checkFileExistence(false)
                    ->square(),
                TextColumn::make('title_de')->label('Titel')->searchable()->sortable()->description(fn (Project $record): string => $record->slug),
                TextColumn::make('category.label_de')->label('Kategorie')->badge()->sortable(),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'published' => 'Veröffentlicht', 'unpublished' => 'Nicht veröffentlicht', default => 'Entwurf',
                }),
                IconColumn::make('translation_ready')
                    ->label('DE/EN bereit')
                    ->state(fn (Project $record): bool => $record->isTranslationReady())
                    ->boolean(),
                TextColumn::make('updated_at')->label('Geändert')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')->relationship('category', 'label_de')->label('Kategorie'),
                SelectFilter::make('status')->label('Status')->options([
                    'draft' => 'Entwurf', 'published' => 'Veröffentlicht', 'unpublished' => 'Nicht veröffentlicht',
                ]),
                TrashedFilter::make(),
            ])
            ->reorderable('position')
            ->recordActions([
                EditAction::make(),
                Action::make('duplicate')
                    ->label('Als Entwurf duplizieren')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (Project $record): void {
                        $copy = $record->replicate(['source_key', 'published_at']);
                        $copy->slug = $record->slug.'-kopie-'.Str::lower(Str::random(5));
                        $copy->title_de .= ' – Kopie';
                        $copy->title_en = filled($copy->title_en) ? $copy->title_en.' – Copy' : null;
                        $copy->status = 'draft';
                        $copy->save();
                        foreach ($record->mediaItems as $item) $copy->mediaItems()->create($item->only(['media_asset_id', 'role', 'position', 'side']));
                        Notification::make()->title('Projekt als Entwurf dupliziert')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->before(function (DeleteBulkAction $action, $records): void {
                        if ($records->contains(fn (Project $project): bool => $project->status === 'published'
                            || \App\Models\ProductionPublication::where('project_id', $project->id)
                                ->whereIn('status', ['preparing', 'prepared', 'queued', 'dispatch_unknown', 'building', 'uploading'])->exists())) {
                            Notification::make()->title('Auswahl wurde nicht gelöscht')
                                ->body('Öffentliche oder gerade verarbeitete Projekte bitte einzeln im Editor entfernen. Alle ausgewählten Projekte bleiben erhalten.')
                                ->warning()->persistent()->send();
                            $action->halt();
                        }
                    })->databaseTransaction(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
