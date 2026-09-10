<?php

namespace App\Services;

use App\Models\PreviewBuild;
use Illuminate\Support\Facades\File;

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

        if ($preview->package_path) {
            $expectedPackage = $this->externalStorage->resolve(
                (string) $preview->package_path,
                'package_root',
                'madlen-preview-'.$preview->id.'.zip',
            );
            if (is_file($expectedPackage)) {
                @unlink($expectedPackage);
            }
        }

        $pendingManifest = $this->externalStorage->manifestLocator('pending');
        if (! hash_equals($pendingManifest, (string) $preview->manifest_path)) {
            $this->externalStorage->resolve(
                (string) $preview->manifest_path,
                'package_root',
                'requests/'.$preview->id.'/payload/content-manifest.json',
            );
        }

        $request = $packageRoot.'/requests/'.$preview->id;
        $this->externalStorage->assertWithin($request, $packageRoot);
        if (is_dir($request)) {
            File::deleteDirectory($request);
        }

        $expectedBuild = $this->externalStorage->resolve(
            (string) $preview->build_path,
            'result_root',
            'builds/'.$preview->token,
        );
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
