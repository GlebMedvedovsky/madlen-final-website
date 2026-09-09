<?php

namespace App\Services;

use App\Models\Release;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReleasePublisher
{
    public function __construct(
        private ContentManifestService $manifests,
        private AstroBuildService $builder,
        private RuntimeFilesystem $files,
    ) {}

    public function publish(): Release
    {
        $lock = Cache::lock('madlen-publication', 360);
        if (! $lock->get()) throw new RuntimeException('Eine Veröffentlichung läuft bereits.');

        try {
            $version = DB::transaction(fn (): int => ((int) Release::query()->lockForUpdate()->max('version')) + 1);
            $release = Release::query()->create([
                'version' => $version,
                'status' => 'building',
                'manifest_path' => '',
                'created_by' => Auth::id(),
            ]);
            $root = rtrim(config('madlen.release_root'), '/');
            $manifestDir = $root.'/manifests';
            $this->files->ensureDirectory($manifestDir);
            $this->files->ensureDirectory($root.'/builds');
            $manifestPath = $manifestDir.'/'.$release->id.'.json';
            $buildPath = $root.'/builds/'.$release->id;
            $manifest = $this->manifests->make();
            $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $this->files->write($manifestPath, $json."\n");
            $release->update(['manifest_path' => $manifestPath, 'build_path' => $buildPath, 'checksum' => hash('sha256', $json)]);

            try {
                $this->builder->build($manifestPath, $buildPath, $this->manifests->mediaCopies($manifest));
                $this->promote($release);
            } catch (\Throwable $error) {
                $release->update(['status' => 'failed', 'error_message' => mb_substr($error->getMessage(), 0, 60000)]);
                throw $error;
            }

            return $release->refresh();
        } finally {
            $lock->release();
        }
    }

    public function rollback(Release $release): void
    {
        if (! $release->build_path || ! is_file($release->build_path.'/index.html')) {
            throw new RuntimeException('Das gewählte Release ist nicht vollständig vorhanden.');
        }
        $lock = Cache::lock('madlen-publication', 60);
        if (! $lock->get()) throw new RuntimeException('Eine Veröffentlichung läuft bereits.');
        try {
            $this->switchCurrent($release);
            Release::query()->where('status', 'active')->whereKeyNot($release->id)->update(['status' => 'superseded']);
            $release->update(['status' => 'active', 'published_at' => now(), 'error_message' => null]);
        } finally {
            $lock->release();
        }
    }

    private function promote(Release $release): void
    {
        $this->switchCurrent($release);
        Release::query()->where('status', 'active')->whereKeyNot($release->id)->update(['status' => 'superseded']);
        $release->update(['status' => 'active', 'published_at' => now(), 'error_message' => null]);
    }

    private function switchCurrent(Release $release): void
    {
        $root = rtrim(config('madlen.release_root'), '/');
        $next = $root.'/.current-'.$release->id;
        $current = $root.'/current';
        if (! symlink('builds/'.$release->id, $next)) {
            throw new RuntimeException('Der atomare Release-Zeiger konnte nicht erstellt werden.');
        }
        if (! rename($next, $current)) {
            @unlink($next);
            throw new RuntimeException('Das fertige Release konnte nicht atomar aktiviert werden.');
        }
    }
}
