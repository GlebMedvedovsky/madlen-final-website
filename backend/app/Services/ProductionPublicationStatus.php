<?php

namespace App\Services;

use App\Models\ProductionPublication;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductionPublicationStatus
{
    private const TRANSITIONS = [
        'prepared' => ['queued', 'building', 'failed'],
        'queued' => ['building', 'failed'],
        'dispatch_unknown' => ['building', 'failed'],
        'building' => ['uploading', 'failed'],
        'uploading' => ['active', 'failed'],
        'active' => [],
        'failed' => [],
        'superseded' => [],
    ];

    public function update(ProductionPublication $publication, string $status, ?string $error = null, ?string $targetRelease = null): ProductionPublication
    {
        return DB::transaction(function () use ($publication, $status, $error, $targetRelease): ProductionPublication {
            $publication = ProductionPublication::query()->lockForUpdate()->findOrFail($publication->id);
            if ($publication->status === $status) {
                return $publication;
            }
            if (! in_array($status, self::TRANSITIONS[$publication->status] ?? [], true)) {
                throw new RuntimeException('Ungültiger Statuswechsel für die Produktiv-Veröffentlichung.');
            }

            if ($status === 'active') {
                // Only the local activation command may make a publication active.
                throw new RuntimeException('Die Aktivierung muss lokal bestätigt werden.');
            }

            $publication->update([
                'status' => $status,
                'target_release' => $targetRelease ?: $publication->target_release,
                'progress_message' => match ($status) {
                    'queued' => 'Der Auftrag wartet auf den externen Build.',
                    'building' => 'Die zweisprachige Website wird aus dem unveränderlichen Stand gebaut.',
                    'uploading' => 'Der geprüfte Stand wird in ein neues Zielverzeichnis übertragen.',
                    'active' => 'Die Produktiv-Veröffentlichung wurde vom Zielsystem bestätigt.',
                    'failed' => 'Die Produktiv-Veröffentlichung ist fehlgeschlagen; der vorherige Stand blieb aktiv.',
                    default => $publication->progress_message,
                },
                'error_message' => $status === 'failed' ? mb_substr((string) $error, 0, 60000) : null,
                'activated_at' => $status === 'active' ? now() : $publication->activated_at,
            ]);

            return $publication->refresh();
        });
    }
}
