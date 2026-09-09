<?php

namespace App\Services;

use App\Models\ProductionPublication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class ProductionPublicationPackager
{
    public function __construct(
        private ContentManifestService $manifests,
        private RuntimeFilesystem $files,
    ) {}

    public function prepare(): ProductionPublication
    {
        $sourceRevision = strtolower(trim((string) config('madlen.publisher.source_revision')));
        if (! preg_match('/\A[0-9a-f]{40}\z/', $sourceRevision)) {
            throw new RuntimeException('Der feste Quellcode-Stand für den Produktiv-Build ist nicht konfiguriert.');
        }
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Die PHP-ZIP-Erweiterung fehlt; das Veröffentlichungspaket kann nicht erstellt werden.');
        }

        $lock = Cache::lock('madlen-production-package', 120);
        if (! $lock->get()) {
            throw new RuntimeException('Eine Produktiv-Veröffentlichung wird bereits vorbereitet.');
        }

        $publication = null;
        try {
            $publication = DB::transaction(function () use ($sourceRevision): ProductionPublication {
                $sequence = ((int) ProductionPublication::query()->lockForUpdate()->max('sequence')) + 1;

                return ProductionPublication::query()->create([
                    'sequence' => $sequence,
                    'status' => 'preparing',
                    'source_revision' => $sourceRevision,
                    'progress_message' => 'Der unveränderliche Inhaltsstand wird vorbereitet.',
                    'requested_by' => Auth::id(),
                ]);
            });

            $root = rtrim((string) config('madlen.publisher.package_root'), '/\\');
            if ($root === '') {
                throw new RuntimeException('Das private Verzeichnis für Veröffentlichungspakete ist nicht konfiguriert.');
            }

            $payloadRoot = $root.'/requests/'.$publication->id.'/payload';
            $manifestPath = $payloadRoot.'/content-manifest.json';
            $manifest = $this->manifests->make();
            $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
            $this->files->write($manifestPath, $json);

            foreach ($this->manifests->mediaCopies($manifest) as $publicPath => $privatePath) {
                $relative = str_replace('\\', '/', ltrim((string) $publicPath, '/'));
                if (! preg_match('#\Amedia/[a-zA-Z0-9_-]+/[a-zA-Z0-9._-]+\z#', $relative)) {
                    throw new RuntimeException("Ungültiger öffentlicher Medienpfad im Snapshot: {$relative}");
                }
                $this->files->copy(Storage::disk('local')->path($privatePath), $payloadRoot.'/'.$relative);
            }

            $metadata = [
                'schemaVersion' => 1,
                'publicationId' => $publication->id,
                'sequence' => $publication->sequence,
                'sourceRevision' => $sourceRevision,
                'contentManifest' => 'content-manifest.json',
                'contentChecksum' => hash('sha256', $json),
                'createdAt' => now()->toIso8601String(),
            ];
            $this->files->write(
                $payloadRoot.'/publication.json',
                json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n",
            );

            $packages = $root.'/packages';
            $this->files->ensureDirectory($packages);
            $archive = $packages.'/madlen-publication-'.$publication->sequence.'-'.$publication->id.'.zip';
            $temporaryArchive = $archive.'.tmp';
            if (is_file($archive) || is_file($temporaryArchive)) {
                throw new RuntimeException('Das eindeutige Veröffentlichungspaket existiert bereits.');
            }

            $this->createArchive($payloadRoot, $temporaryArchive);
            if (! @rename($temporaryArchive, $archive)) {
                throw new RuntimeException('Das Veröffentlichungspaket konnte nicht unveränderlich abgeschlossen werden.');
            }

            $checksum = hash_file('sha256', $archive);
            if ($checksum === false) {
                throw new RuntimeException('Die Prüfsumme des Veröffentlichungspakets konnte nicht ermittelt werden.');
            }

            $publication->update([
                'status' => 'prepared',
                'manifest_path' => $manifestPath,
                'package_path' => $archive,
                'package_checksum' => $checksum,
                'progress_message' => 'Das unveränderliche Paket ist bereit und wurde noch nicht übertragen.',
                'error_message' => null,
            ]);

            return $publication->refresh();
        } catch (\Throwable $error) {
            if ($publication?->exists) {
                $publication->update([
                    'status' => 'failed',
                    'progress_message' => 'Die Vorbereitung ist fehlgeschlagen; die öffentliche Website blieb unverändert.',
                    'error_message' => mb_substr($error->getMessage(), 0, 60000),
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
            throw new RuntimeException('Das ZIP-Veröffentlichungspaket konnte nicht geöffnet werden.');
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($payloadRoot, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $entry) {
                if ($entry->isLink() || ! $entry->isFile()) {
                    throw new RuntimeException('Das Veröffentlichungspaket darf keine Verknüpfungen enthalten.');
                }
                $relative = str_replace('\\', '/', substr($entry->getPathname(), strlen($payloadRoot) + 1));
                if (! $zip->addFile($entry->getPathname(), $relative)) {
                    throw new RuntimeException("Datei konnte dem Veröffentlichungspaket nicht hinzugefügt werden: {$relative}");
                }
            }
        } finally {
            if (! $zip->close()) {
                throw new RuntimeException('Das ZIP-Veröffentlichungspaket konnte nicht abgeschlossen werden.');
            }
        }
    }
}
