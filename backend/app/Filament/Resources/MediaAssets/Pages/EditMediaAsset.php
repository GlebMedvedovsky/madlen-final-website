<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Filament\Resources\MediaAssets\MediaAssetResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use App\Services\MediaProcessor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EditMediaAsset extends EditRecord
{
    protected static string $resource = MediaAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->before(function (DeleteAction $action): void {
                if ($this->getRecord()->isUsed()) {
                    \Filament\Notifications\Notification::make()->title('Medium wird noch verwendet')
                        ->body('Zuerst aus Titelbild, Galerie und Website-Medien lösen. Wiederherstellbare Projekte behalten ihre Medien.')
                        ->warning()->persistent()->send();
                    $action->halt();
                }
            }),
            RestoreAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $previousPath = $record->path;
        try {
            return DB::transaction(function () use ($record, $data): Model {
                $record->update($data);
                if ($record->wasChanged('path') && ! $record->source_managed) {
                    app(MediaProcessor::class)->inspect($record);
                }
                return $record;
            });
        } catch (\Throwable $error) {
            $newPath = $data['path'] ?? null;
            if (filled($newPath) && $newPath !== $previousPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $error;
        }
    }
}
