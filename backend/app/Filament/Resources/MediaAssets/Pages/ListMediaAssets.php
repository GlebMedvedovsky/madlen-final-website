<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Filament\Resources\MediaAssets\MediaAssetResource;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use App\Models\MediaAsset;
use App\Services\MediaProcessor;

class ListMediaAssets extends ListRecords
{
    protected static string $resource = MediaAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('batchUpload')
                ->label('Mehrere Dateien hochladen')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('files')
                        ->label('Bilder')
                        ->disk('local')
                        ->directory('media/originals')
                        ->visibility('private')
                        ->multiple()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(config('madlen.media.max_image_kb'))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $count = 0;
                    foreach ($data['files'] as $path) {
                        $asset = MediaAsset::query()->create([
                            'path' => $path,
                            'original_name' => basename($path),
                            'mime_type' => 'application/octet-stream',
                            'kind' => 'image',
                            'source_managed' => false,
                        ]);
                        app(MediaProcessor::class)->inspect($asset);
                        $count++;
                    }
                    Notification::make()->title("{$count} Datei(en) verarbeitet")->success()->send();
                }),
        ];
    }
}
