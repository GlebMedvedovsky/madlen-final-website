<?php

namespace App\Services;

use RuntimeException;

class ExternalPreviewStorage
{
    private const LOCATOR_PREFIX = 'madlen-preview-storage-v1://';

    private const MARKERS = [
        'package_root' => ['.madlen-preview-packages', 'madlen-preview-packages-v1'],
        'incoming_root' => ['.madlen-preview-incoming', 'madlen-preview-incoming-v1'],
        'result_root' => ['.madlen-preview-results', 'madlen-preview-results-v1'],
    ];

    public function root(string $name): string
    {
        if (! isset(self::MARKERS[$name])) {
            throw new RuntimeException('Unbekannter Speicherbereich der externen Vorschau.');
        }

        $path = rtrim((string) config("madlen.preview_runner.{$name}"), '/\\');
        if ($path === '' || ! $this->isAbsolute($path)) {
            throw new RuntimeException('Die privaten Verzeichnisse der externen Vorschau sind nicht vollständig konfiguriert.');
        }

        return $path;
    }

    public function assertReady(): void
    {
        $roots = [];
        foreach (array_keys(self::MARKERS) as $name) {
            $root = $this->root($name);
            $roots[$name] = $this->normalize($root);
            if (! is_dir($root) || ! is_writable($root)) {
                throw new RuntimeException("Privates Vorschau-Verzeichnis ist nicht beschreibbar: {$root}");
            }
            [$marker, $expected] = self::MARKERS[$name];
            $actual = @file_get_contents($root.'/'.$marker);
            if ($actual === false || trim($actual) !== $expected) {
                throw new RuntimeException("Sicherheitsmarkierung für das Vorschau-Verzeichnis fehlt: {$root}");
            }
        }

        if (count(array_unique(array_values($roots))) !== count($roots)) {
            throw new RuntimeException('Paket-, Eingangs- und Ergebnisverzeichnis der Vorschau müssen getrennt sein.');
        }

        $publicRoots = array_filter([
            public_path(),
            rtrim((string) config('madlen.repository_root'), '/\\').'/public',
            config('madlen.publisher.destination_root'),
        ]);
        foreach ($roots as $root) {
            foreach ($publicRoots as $publicRoot) {
                if ($this->contains((string) $publicRoot, $root) || $this->contains($root, (string) $publicRoot)) {
                    throw new RuntimeException('Vorschau-Pakete und -Ergebnisse müssen außerhalb aller öffentlichen Verzeichnisse liegen.');
                }
            }
        }
    }

    public function resultArchive(string $previewId): string
    {
        return $this->path('incoming_root', 'madlen-preview-result-'.$previewId.'.zip');
    }

    public function buildPath(string $token): string
    {
        return $this->path('result_root', 'builds/'.$token);
    }

    public function packagePath(string $previewId): string
    {
        return $this->path('package_root', 'madlen-preview-'.$previewId.'.zip');
    }

    public function manifestPath(string $previewId): string
    {
        return $this->path('package_root', 'requests/'.$previewId.'/payload/content-manifest.json');
    }

    public function packageLocator(string $previewId): string
    {
        return $this->locator('package_root', 'madlen-preview-'.$previewId.'.zip');
    }

    public function manifestLocator(string $previewId): string
    {
        return $this->locator('package_root', 'requests/'.$previewId.'/payload/content-manifest.json');
    }

    public function buildLocator(string $token): string
    {
        return $this->locator('result_root', 'builds/'.$token);
    }

    public function resolve(string $storedLocator, string $rootName, string $expectedRelative): string
    {
        $expected = $this->locator($rootName, $expectedRelative);
        if (! hash_equals($expected, $storedLocator)) {
            throw new RuntimeException('Der gespeicherte Vorschau-Pfad gehört nicht zum erwarteten privaten Auftrag.');
        }

        return $this->path($rootName, $expectedRelative);
    }

    public function assertWithin(string $path, string $root): void
    {
        if (! $this->contains($root, $path) || $this->normalize($path) === $this->normalize($root)) {
            throw new RuntimeException('Unsicherer Pfad außerhalb des privaten Vorschau-Verzeichnisses.');
        }
    }

    private function contains(string $root, string $path): bool
    {
        $root = $this->normalize($root);
        $path = $this->normalize($path);

        return $path === $root || str_starts_with($path, $root.'/');
    }

    private function normalize(string $path): string
    {
        $normalized = str_replace('\\', '/', rtrim($path, '/\\'));

        return PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/\A[a-zA-Z]:[\\\\\/]/', $path) === 1;
    }

    private function path(string $rootName, string $relative): string
    {
        $this->assertRelative($relative);

        return $this->root($rootName).'/'.$relative;
    }

    private function locator(string $rootName, string $relative): string
    {
        if (! isset(self::MARKERS[$rootName])) {
            throw new RuntimeException('Unbekannter Speicherbereich der externen Vorschau.');
        }
        $this->assertRelative($relative);

        return self::LOCATOR_PREFIX.$rootName.'/'.$relative;
    }

    private function assertRelative(string $relative): void
    {
        if ($relative === ''
            || str_starts_with($relative, '/')
            || str_contains($relative, '\\')
            || str_contains($relative, "\0")
            || preg_match('/\A[a-zA-Z0-9._\/-]+\z/', $relative) !== 1
            || in_array('..', explode('/', $relative), true)
            || in_array('.', explode('/', $relative), true)) {
            throw new RuntimeException('Ungültiger relativer Pfad im privaten Vorschau-Speicher.');
        }
    }
}
