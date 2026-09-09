<?php

namespace App\Services;

use App\Models\Release;
use RuntimeException;
use Symfony\Component\Process\Process;

class ReleaseExporter
{
    public function __construct(private RuntimeFilesystem $files) {}

    public function export(Release $release): array
    {
        if (! $release->build_path || ! is_file($release->build_path.'/index.html')) {
            throw new RuntimeException('Nur ein vollständiges Release kann exportiert werden.');
        }

        $root = rtrim(config('madlen.release_root'), '/').'/exports';
        $this->files->ensureDirectory($root);
        $archive = $root.'/madlen-release-'.$release->version.'-'.$release->id.'.tar.gz';
        if (! is_file($archive)) {
            $process = new Process(['tar', '-czf', $archive, '-C', $release->build_path, '.']);
            $process->setTimeout(180);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Das Release-Paket konnte nicht erstellt werden: '.trim($process->getErrorOutput()));
            }
        }

        $checksum = hash_file('sha256', $archive);
        $this->files->write($archive.'.sha256', $checksum.'  '.basename($archive)."\n");

        return ['archive' => $archive, 'checksum' => $checksum];
    }
}
