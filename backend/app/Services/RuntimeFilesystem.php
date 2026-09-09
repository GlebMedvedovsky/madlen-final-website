<?php

namespace App\Services;

use RuntimeException;

class RuntimeFilesystem
{
    public function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! @mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException("Laufzeitverzeichnis konnte nicht erstellt werden: {$path}");
        }

        if (! is_writable($path)) {
            throw new RuntimeException("Laufzeitverzeichnis ist nicht beschreibbar: {$path}");
        }
    }

    public function write(string $path, string $contents): void
    {
        $this->ensureDirectory(dirname($path));
        $bytes = @file_put_contents($path, $contents, LOCK_EX);

        if ($bytes === false || $bytes !== strlen($contents)) {
            throw new RuntimeException("Laufzeitdatei konnte nicht vollständig geschrieben werden: {$path}");
        }
    }

    public function copy(string $source, string $target): void
    {
        if (! is_file($source) || ! is_readable($source)) {
            throw new RuntimeException("Quelldatei ist nicht lesbar: {$source}");
        }

        $this->ensureDirectory(dirname($target));
        if (! @copy($source, $target) || ! is_file($target)) {
            throw new RuntimeException("Datei konnte nicht kopiert werden: {$target}");
        }
    }
}
