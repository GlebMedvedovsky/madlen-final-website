<?php

namespace App\Services;

use App\Data\ProjectPreviewSnapshot;
use App\Models\PreviewBuild;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class PreviewBuilder
{
    public const NOT_CONFIGURED_MESSAGE = 'Vorschau ist noch nicht eingerichtet.';

    public function __construct(
        private ContentManifestService $manifests,
        private AstroBuildService $builder,
        private RuntimeFilesystem $files,
        private ExternalPreviewBuilder $external,
    ) {}

    public function build(?ProjectPreviewSnapshot $projectSnapshot = null, ?string $requestId = null): PreviewBuild
    {
        $execution = (string) config('madlen.preview_execution', 'local');
        if ($execution === 'external') {
            return $this->external->build($projectSnapshot, $requestId);
        }
        if ($execution !== 'local') {
            throw new \RuntimeException('Der Vorschau-Ausführungsmodus ist ungültig konfiguriert.');
        }
        if (! $this->builder->isAvailable()) {
            throw new \RuntimeException(self::NOT_CONFIGURED_MESSAGE);
        }

        $token = Str::random(48);
        $root = rtrim(config('madlen.release_root'), '/');
        $manifestDir = $root.'/previews/manifests';
        $this->files->ensureDirectory($manifestDir);
        $this->files->ensureDirectory($root.'/previews/builds');
        $manifestPath = $manifestDir.'/'.$token.'.json';
        $buildPath = $root.'/previews/builds/'.$token;
        $preview = PreviewBuild::query()->create([
            'token' => $token,
            'execution_mode' => 'local',
            'status' => 'building',
            'manifest_path' => $manifestPath,
            'build_path' => $buildPath,
            'target_path' => $projectSnapshot?->targetPath(),
            'request_id' => $requestId,
            'user_id' => Auth::id(),
            'expires_at' => now()->addMinutes(config('madlen.preview_ttl_minutes')),
            'progress_message' => 'Die lokale Projektvorschau wird gebaut.',
        ]);

        try {
            $manifest = $this->manifests->make(includeDrafts: true, projectSnapshot: $projectSnapshot);
            $this->files->write(
                $manifestPath,
                json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n",
            );
            $base = '/admin/preview/'.$token;
            $this->builder->build($manifestPath, $buildPath, $this->manifests->mediaCopies($manifest), $base);
            $preview->update([
                'status' => 'ready',
                'progress_message' => 'Die lokale Vorschau ist bereit.',
                'completed_at' => now(),
            ]);

            return $preview->refresh();
        } catch (Throwable $error) {
            $preview->update([
                'status' => 'failed',
                'progress_message' => 'Die lokale Projektvorschau ist fehlgeschlagen.',
                'error_message' => mb_substr($error->getMessage(), 0, 60000),
                'completed_at' => now(),
            ]);

            throw $error;
        }
    }
}
