<?php

namespace App\Services;

use App\Models\PreviewBuild;
use Illuminate\Support\Facades\File;
use RuntimeException;

class PreviewCleanupService
{
    public function __construct(
        private ExternalPreviewStorage $externalStorage,
        private ExternalPreviewStatus $statuses,
    ) {}

    public function expire(PreviewBuild $preview): PreviewBuild
    {
        $preview = $this->statuses->expired($preview);
        $this->removeFiles($preview);

        return $preview;
    }

    public function cleanupExpired(): int
    {
        $count = 0;
        PreviewBuild::query()
            ->where('expires_at', '<=', now())
            ->orderBy('created_at')
            ->each(function (PreviewBuild $preview) use (&$count): void {
                $wasExpired = $preview->status === 'expired';
                $this->expire($preview);
                if (! $wasExpired) {
                    $count++;
                }
            });

        return $count;
    }

    private function removeFiles(PreviewBuild $preview): void
    {
        if ($preview->execution_mode === 'external') {
            $this->removeExternalFiles($preview);

            return;
        }

        $root = rtrim((string) config('madlen.release_root'), '/\\');
        $manifest = $root.'/previews/manifests/'.$preview->token.'.json';
        $build = $root.'/previews/builds/'.$preview->token;
        if ($preview->manifest_path === $manifest && is_file($manifest)) {
            @unlink($manifest);
        }
        if ($preview->build_path === $build && is_dir($build)) {
            File::deleteDirectory($build);
        }
    }

    private function removeExternalFiles(PreviewBuild $preview): void
    {
        $this->externalStorage->assertReady();
        $packageRoot = $this->externalStorage->root('package_root');
        $resultRoot = $this->externalStorage->root('result_root');
        $incomingRoot = $this->externalStorage->root('incoming_root');

        $expectedPackage = $packageRoot.'/madlen-preview-'.$preview->id.'.zip';
        if ($preview->package_path === $expectedPackage && is_file($expectedPackage)) {
            @unlink($expectedPackage);
        }

        $request = $packageRoot.'/requests/'.$preview->id;
        $this->externalStorage->assertWithin($request, $packageRoot);
        if (is_dir($request)) {
            File::deleteDirectory($request);
        }

        $expectedBuild = $resultRoot.'/builds/'.$preview->token;
        if ($preview->build_path !== $expectedBuild) {
            throw new RuntimeException('Der gespeicherte Vorschau-Buildpfad ist nicht auf den Auftrag begrenzt.');
        }
        $this->externalStorage->assertWithin($expectedBuild, $resultRoot);
        if (is_dir($expectedBuild)) {
            File::deleteDirectory($expectedBuild);
        }

        $incoming = $incomingRoot.'/madlen-preview-result-'.$preview->id.'.zip';
        $this->externalStorage->assertWithin($incoming, $incomingRoot);
        if (is_file($incoming)) {
            @unlink($incoming);
        }
    }
}
