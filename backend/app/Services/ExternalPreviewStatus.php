<?php

namespace App\Services;

use App\Models\PreviewBuild;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExternalPreviewStatus
{
    public function queued(PreviewBuild $preview): PreviewBuild
    {
        return DB::transaction(function () use ($preview): PreviewBuild {
            $preview = PreviewBuild::query()->lockForUpdate()->findOrFail($preview->id);
            $this->assertExternalAndCurrent($preview);
            if (in_array($preview->status, ['queued', 'building', 'ready'], true)) {
                return $preview;
            }
            if ($preview->status !== 'prepared') {
                throw new RuntimeException('Der Vorschau-Auftrag kann nicht mehr in die Warteschlange gestellt werden.');
            }

            $preview->update([
                'status' => 'queued',
                'progress_message' => 'Die Vorschau wartet auf den externen Build.',
                'error_message' => null,
            ]);

            return $preview->refresh();
        });
    }

    public function building(PreviewBuild $preview): PreviewBuild
    {
        return DB::transaction(function () use ($preview): PreviewBuild {
            $preview = PreviewBuild::query()->lockForUpdate()->findOrFail($preview->id);
            $this->assertExternalAndCurrent($preview);
            if ($preview->status === 'building') {
                return $preview;
            }
            if (! in_array($preview->status, ['prepared', 'queued'], true)) {
                throw new RuntimeException('Der Vorschau-Auftrag kann nicht mehr als laufend markiert werden.');
            }

            $preview->update([
                'status' => 'building',
                'progress_message' => 'Die deutsche und englische Vorschau wird extern gebaut.',
                'error_message' => null,
            ]);

            return $preview->refresh();
        });
    }

    public function failed(PreviewBuild $preview, ?string $error): PreviewBuild
    {
        return DB::transaction(function () use ($preview, $error): PreviewBuild {
            $preview = PreviewBuild::query()->lockForUpdate()->findOrFail($preview->id);
            $this->assertExternalAndCurrent($preview);
            if ($preview->status === 'failed') {
                return $preview;
            }
            if (! in_array($preview->status, ['preparing', 'prepared', 'queued', 'building'], true)) {
                throw new RuntimeException('Der Vorschau-Auftrag kann nicht mehr als fehlgeschlagen markiert werden.');
            }

            $preview->update([
                'status' => 'failed',
                'progress_message' => 'Die externe Vorschau ist fehlgeschlagen. Die öffentliche Website wurde nicht verändert.',
                'error_message' => mb_substr((string) $error, 0, 60000),
                'completed_at' => now(),
            ]);

            return $preview->refresh();
        });
    }

    public function expired(PreviewBuild $preview): PreviewBuild
    {
        return DB::transaction(function () use ($preview): PreviewBuild {
            $preview = PreviewBuild::query()->lockForUpdate()->findOrFail($preview->id);
            if ($preview->status !== 'expired') {
                $preview->update([
                    'status' => 'expired',
                    'progress_message' => 'Die geschützte Vorschau ist abgelaufen.',
                    'error_message' => null,
                    'completed_at' => $preview->completed_at ?: now(),
                ]);
            }

            return $preview->refresh();
        });
    }

    private function assertExternalAndCurrent(PreviewBuild $preview): void
    {
        if ($preview->execution_mode !== 'external') {
            throw new RuntimeException('Der Auftrag gehört nicht zur externen Vorschau.');
        }
        if ($preview->expires_at->isPast() || $preview->status === 'expired') {
            throw new RuntimeException('Die geschützte Vorschau ist abgelaufen.');
        }
    }
}
