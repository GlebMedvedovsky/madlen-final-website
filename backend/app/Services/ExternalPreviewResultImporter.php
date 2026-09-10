<?php

namespace App\Services;

use App\Models\PreviewBuild;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class ExternalPreviewResultImporter
{
    public function __construct(
        private RuntimeFilesystem $files,
        private ExternalPreviewStorage $storage,
        private ExternalPreviewStatus $statuses,
        private PreviewCleanupService $cleanup,
    ) {}

    public function accept(PreviewBuild $preview, string $archiveName, string $checksum): PreviewBuild
    {
        if (! preg_match('/\A[0-9a-f]{64}\z/i', $checksum)) {
            throw new RuntimeException('Ungültige Prüfsumme des Vorschau-Ergebnisses.');
        }
        $expectedName = 'madlen-preview-result-'.$preview->id.'.zip';
        if ($archiveName !== $expectedName || basename($archiveName) !== $archiveName) {
            throw new RuntimeException('Das Vorschau-Ergebnis gehört nicht zum erwarteten Auftrag.');
        }

        $lock = Cache::lock('madlen-external-preview-result-'.$preview->id, 120);
        if (! $lock->get()) {
            throw new RuntimeException('Das Vorschau-Ergebnis wird bereits verarbeitet.');
        }

        $staging = null;
        try {
            $preview = $preview->fresh();
            if ($preview->expires_at->isPast() || $preview->status === 'expired') {
                $this->cleanup->expire($preview);
                throw new RuntimeException('Die geschützte Vorschau ist abgelaufen.');
            }
            if ($preview->status === 'ready') {
                if (hash_equals((string) $preview->result_checksum, strtolower($checksum))) {
                    $archive = $this->storage->resultArchive($preview->id);
                    if (is_file($archive)) {
                        $duplicateChecksum = hash_file('sha256', $archive);
                        if ($duplicateChecksum !== false && hash_equals(strtolower($checksum), strtolower($duplicateChecksum))) {
                            @unlink($archive);
                        }
                    }

                    return $preview;
                }
                throw new RuntimeException('Ein anderes Ergebnis wurde für diesen Vorschau-Auftrag bereits angenommen.');
            }
            if (! in_array($preview->status, ['prepared', 'queued', 'building'], true)) {
                throw new RuntimeException('Der Vorschau-Auftrag nimmt kein Ergebnis mehr an.');
            }

            $this->storage->assertReady();
            $archive = $this->storage->resultArchive($preview->id);
            if (! is_file($archive) || ! is_readable($archive)) {
                throw new RuntimeException('Das übertragene Vorschau-Ergebnis wurde nicht gefunden.');
            }
            $actual = hash_file('sha256', $archive);
            if ($actual === false || ! hash_equals(strtolower($checksum), strtolower($actual))) {
                throw new RuntimeException('Die Prüfsumme des übertragenen Vorschau-Ergebnisses stimmt nicht.');
            }

            $resultRoot = $this->storage->root('result_root');
            $staging = $resultRoot.'/staging/'.$preview->id.'-'.bin2hex(random_bytes(6));
            $this->storage->assertWithin($staging, $resultRoot);
            $this->files->ensureDirectory($staging);
            $this->extractSafely($archive, $staging);
            $this->validate($preview, $staging);

            $destination = $this->storage->resolve(
                (string) $preview->build_path,
                'result_root',
                'builds/'.$preview->token,
            );
            if (file_exists($destination)) {
                throw new RuntimeException('Das eindeutige private Vorschau-Ziel ist ungültig oder existiert bereits.');
            }
            $this->files->ensureDirectory(dirname($destination));
            if (! @rename($staging, $destination)) {
                throw new RuntimeException('Das geprüfte Vorschau-Ergebnis konnte nicht atomar bereitgestellt werden.');
            }
            $staging = null;

            $preview->update([
                'status' => 'ready',
                'result_checksum' => strtolower($actual),
                'progress_message' => 'Die geschützte Vorschau ist bereit.',
                'error_message' => null,
                'completed_at' => now(),
            ]);
            @unlink($archive);

            return $preview->refresh();
        } catch (\Throwable $error) {
            $current = $preview->fresh();
            if ($current && in_array($current->status, ['preparing', 'prepared', 'queued', 'building'], true)
                && $current->expires_at->isFuture()) {
                $this->statuses->failed($current, $error->getMessage());
            }
            throw $error;
        } finally {
            if ($staging && is_dir($staging)) {
                File::deleteDirectory($staging);
            }
            $lock->release();
        }
    }

    private function extractSafely(string $archive, string $destination): void
    {
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Das ZIP-Vorschau-Ergebnis konnte nicht geöffnet werden.');
        }

        try {
            $seen = [];
            $totalSize = 0;
            if ($zip->numFiles > 20000) {
                throw new RuntimeException('Das Vorschau-Ergebnis enthält zu viele Dateien.');
            }
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = $stat['name'] ?? '';
                if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\')
                    || str_starts_with($name, '/') || preg_match('/\A[a-zA-Z]:/', $name)
                    || in_array('..', explode('/', trim($name, '/')), true)
                    || isset($seen[$name])) {
                    throw new RuntimeException('Das Vorschau-Ergebnis enthält einen unsicheren oder doppelten Pfad.');
                }
                $seen[$name] = true;
                $totalSize += (int) ($stat['size'] ?? 0);
                if ($totalSize > 536870912) {
                    throw new RuntimeException('Das entpackte Vorschau-Ergebnis überschreitet 512 MiB.');
                }
                if ($zip->getExternalAttributesIndex($index, $opsys, $attributes)
                    && (($attributes >> 16) & 0170000) === 0120000) {
                    throw new RuntimeException('Das Vorschau-Ergebnis darf keine symbolischen Links enthalten.');
                }
            }
            if (! $zip->extractTo($destination)) {
                throw new RuntimeException('Das Vorschau-Ergebnis konnte nicht entpackt werden.');
            }
        } finally {
            $zip->close();
        }
    }

    private function validate(PreviewBuild $preview, string $root): void
    {
        foreach (['index.html', 'en/index.html', '.madlen-preview.json'] as $required) {
            if (! is_file($root.'/'.$required)) {
                throw new RuntimeException("Vorschau-Prüfung fehlgeschlagen: {$required} fehlt.");
            }
        }

        $metadata = json_decode(file_get_contents($root.'/.madlen-preview.json'), true, flags: JSON_THROW_ON_ERROR);
        $manifestPath = $this->storage->resolve(
            (string) $preview->manifest_path,
            'package_root',
            'requests/'.$preview->id.'/payload/content-manifest.json',
        );
        $manifestBytes = file_get_contents($manifestPath);
        if ($manifestBytes === false) {
            throw new RuntimeException('Der unveränderliche Vorschau-Inhalt wurde nicht gefunden.');
        }
        $manifestChecksum = hash('sha256', $manifestBytes);
        if (($metadata['schemaVersion'] ?? null) !== 1
            || ($metadata['previewId'] ?? null) !== $preview->id
            || ($metadata['previewToken'] ?? null) !== $preview->token
            || ($metadata['sourceRevision'] ?? null) !== $preview->source_revision
            || ($metadata['contentChecksum'] ?? null) !== $manifestChecksum
            || ($metadata['expiresAt'] ?? null) !== $preview->expires_at->toIso8601String()) {
            throw new RuntimeException('Die Metadaten des Ergebnisses gehören nicht zu diesem Vorschau-Auftrag.');
        }

        $manifest = json_decode($manifestBytes, true, flags: JSON_THROW_ON_ERROR);
        $mediaPaths = [];
        foreach ($manifest['projects'] ?? [] as $project) {
            $slug = $project['slug'] ?? '';
            if (! preg_match('/\A[a-zA-Z0-9-]+\z/', $slug)) {
                throw new RuntimeException('Ungültiger Projekt-Slug im Vorschau-Manifest.');
            }
            foreach (["portfolio/{$slug}/index.html", "en/portfolio/{$slug}/index.html"] as $route) {
                if (! is_file($root.'/'.$route)) {
                    throw new RuntimeException("Vorschau-Prüfung fehlgeschlagen: {$route} fehlt.");
                }
            }
            $mediaPaths[] = $project['cover'] ?? null;
            foreach ($project['images'] ?? [] as $image) {
                $mediaPaths[] = $image['src'] ?? null;
            }
        }
        foreach ($manifest['settings']['mediaSlots'] ?? [] as $mediaPath) {
            $mediaPaths[] = $mediaPath;
        }
        foreach (array_unique(array_filter($mediaPaths)) as $mediaPath) {
            if (is_string($mediaPath) && str_starts_with($mediaPath, '/media/')
                && ! is_file($root.'/'.ltrim($mediaPath, '/'))) {
                throw new RuntimeException("Referenziertes Vorschau-Medium fehlt: {$mediaPath}");
            }
        }

        $base = '/admin/preview/'.$preview->token;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            if ($entry->isLink()) {
                throw new RuntimeException('Das Vorschau-Ergebnis darf keine symbolischen Links enthalten.');
            }
            if (! $entry->isFile()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($entry->getPathname(), strlen($root) + 1));
            if (preg_match('~(^|/)(\.env(?:\.|$)|backend|storage|vendor|node_modules|private)(/|$)~i', $relative)) {
                throw new RuntimeException("Nicht öffentlicher Inhalt im Vorschau-Ergebnis: {$relative}");
            }
            if (! in_array(strtolower($entry->getExtension()), ['html', 'css', 'js', 'json', 'xml'], true)) {
                continue;
            }
            $content = file_get_contents($entry->getPathname());
            if (preg_match('~(?<!'.preg_quote($base, '~').')/media/~', $content)) {
                throw new RuntimeException('Ein Medienpfad im Vorschau-Ergebnis umgeht die geschützte Route.');
            }
            preg_match_all('~/admin/preview/([a-zA-Z0-9]{48})(?:/|["\'`])~', $content, $tokens);
            foreach ($tokens[1] as $token) {
                if (! hash_equals($preview->token, $token)) {
                    throw new RuntimeException('Ein Pfad im Ergebnis gehört zu einer anderen Vorschau.');
                }
            }
            preg_match_all('~'.preg_quote($base, '~').'/media/([a-zA-Z0-9._/-]+)~', $content, $matches);
            foreach ($matches[1] as $media) {
                if (! is_file($root.'/media/'.$media)) {
                    throw new RuntimeException("Die Vorschau referenziert ein fehlendes Medium: {$media}");
                }
            }
        }
    }
}
