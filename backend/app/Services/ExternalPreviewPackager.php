<?php

namespace App\Services;

use App\Data\ProjectPreviewSnapshot;
use App\Models\PreviewBuild;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ExternalPreviewPackager
{
    public function __construct(
        private ContentManifestService $manifests,
        private RuntimeFilesystem $files,
        private ExternalPreviewStorage $storage,
    ) {}

    public function prepare(?ProjectPreviewSnapshot $projectSnapshot = null, ?string $requestId = null): PreviewBuild
    {
        $sourceRevision = strtolower(trim((string) config('madlen.preview_runner.source_revision')));
        if (! preg_match('/\A[0-9a-f]{40}\z/', $sourceRevision)) {
            throw new RuntimeException('Der feste Quellcode-Stand für die externe Vorschau ist nicht konfiguriert.');
        }
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Die PHP-ZIP-Erweiterung fehlt; das Vorschau-Paket kann nicht erstellt werden.');
        }
        if (! Auth::id()) {
            throw new RuntimeException('Die Vorschau benötigt eine angemeldete Administratorin oder einen Administrator.');
        }

        $this->storage->assertReady();
        $lock = Cache::lock('madlen-external-preview-package', 120);
        if (! $lock->get()) {
            throw new RuntimeException('Eine externe Vorschau wird bereits vorbereitet.');
        }

        $preview = null;
        try {
            $token = Str::random(48);
            $expiresAt = now()->addMinutes(max(5, (int) config('madlen.preview_ttl_minutes', 120)));
            $preview = PreviewBuild::query()->create([
                'token' => $token,
                'execution_mode' => 'external',
                'status' => 'preparing',
                'source_revision' => $sourceRevision,
                'manifest_path' => $this->storage->manifestLocator('pending'),
                'build_path' => $this->storage->buildLocator($token),
                'target_path' => $projectSnapshot?->targetPath(),
                'request_id' => $requestId,
                'user_id' => Auth::id(),
                'expires_at' => $expiresAt,
                'progress_message' => 'Der unveränderliche Entwurfsstand wird vorbereitet.',
            ]);

            $manifestPath = $this->storage->manifestPath($preview->id);
            $payloadRoot = dirname($manifestPath);
            $manifest = $this->manifests->make(includeDrafts: true, projectSnapshot: $projectSnapshot);
            $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
            $this->files->write($manifestPath, $json);

            foreach ($this->manifests->mediaCopies($manifest) as $publicPath => $privatePath) {
                $relative = str_replace('\\', '/', ltrim((string) $publicPath, '/'));
                if (! preg_match('#\Amedia/[a-zA-Z0-9_-]+/[a-zA-Z0-9._-]+\z#', $relative)) {
                    throw new RuntimeException("Ungültiger Medienpfad im Vorschau-Snapshot: {$relative}");
                }
                $this->files->copy(Storage::disk('local')->path($privatePath), $payloadRoot.'/'.$relative);
            }

            $metadata = [
                'schemaVersion' => 1,
                'previewId' => $preview->id,
                'previewToken' => $token,
                'sourceRevision' => $sourceRevision,
                'contentManifest' => 'content-manifest.json',
                'contentChecksum' => hash('sha256', $json),
                'basePath' => '/admin/preview/'.$token,
                'locales' => ['de', 'en'],
                'createdAt' => now()->toIso8601String(),
                'expiresAt' => $expiresAt->toIso8601String(),
            ];
            $this->files->write(
                $payloadRoot.'/preview.json',
                json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n",
            );

            $archive = $this->storage->packagePath($preview->id);
            $temporary = $archive.'.tmp';
            if (is_file($archive) || is_file($temporary)) {
                throw new RuntimeException('Das eindeutige Vorschau-Paket existiert bereits.');
            }
            $this->createArchive($payloadRoot, $temporary);
            if (! @rename($temporary, $archive)) {
                throw new RuntimeException('Das Vorschau-Paket konnte nicht unveränderlich abgeschlossen werden.');
            }
            $checksum = hash_file('sha256', $archive);
            if ($checksum === false) {
                throw new RuntimeException('Die Prüfsumme des Vorschau-Pakets konnte nicht ermittelt werden.');
            }

            $preview->update([
                'status' => 'prepared',
                'manifest_path' => $this->storage->manifestLocator($preview->id),
                'package_path' => $this->storage->packageLocator($preview->id),
                'package_checksum' => $checksum,
                'progress_message' => 'Der unveränderliche Entwurfsstand wartet auf den externen Build.',
                'error_message' => null,
            ]);

            return $preview->refresh();
        } catch (\Throwable $error) {
            if ($preview?->exists) {
                $preview->update([
                    'status' => 'failed',
                    'progress_message' => 'Die Vorbereitung der externen Vorschau ist fehlgeschlagen.',
                    'error_message' => mb_substr($error->getMessage(), 0, 60000),
                    'completed_at' => now(),
                ]);
            }
            throw $error;
        } finally {
            $lock->release();
        }
    }

    private function createArchive(string $payloadRoot, string $archive): void
    {
        $zip = new ZipArchive;
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Das ZIP-Vorschau-Paket konnte nicht geöffnet werden.');
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($payloadRoot, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $entry) {
                if ($entry->isLink() || ! $entry->isFile()) {
                    throw new RuntimeException('Das Vorschau-Paket darf keine Verknüpfungen enthalten.');
                }
                $relative = str_replace('\\', '/', substr($entry->getPathname(), strlen($payloadRoot) + 1));
                if (! $zip->addFile($entry->getPathname(), $relative)) {
                    throw new RuntimeException("Datei konnte dem Vorschau-Paket nicht hinzugefügt werden: {$relative}");
                }
            }
        } finally {
            if (! $zip->close()) {
                throw new RuntimeException('Das ZIP-Vorschau-Paket konnte nicht abgeschlossen werden.');
            }
        }
    }
}
