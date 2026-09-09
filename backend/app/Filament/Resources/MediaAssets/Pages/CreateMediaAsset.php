<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Filament\Resources\MediaAssets\MediaAssetResource;
use Filament\Resources\Pages\CreateRecord;
use App\Services\MediaProcessor;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CreateMediaAsset extends CreateRecord
{
    protected static string $resource = MediaAssetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['original_name'] = basename($data['path']);
        $data['mime_type'] = 'application/octet-stream';
        $data['source_managed'] = false;
        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return DB::transaction(function () use ($data): MediaAsset {
                $record = MediaAsset::query()->create($data);
                app(MediaProcessor::class)->inspect($record);
                return $record;
            });
        } catch (\Throwable $error) {
            if (filled($data['path'] ?? null)) {
                Storage::disk('local')->delete($data['path']);
            }
            throw $error;
        }
    }
}
