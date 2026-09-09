<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class AstroBuildService
{
    public function __construct(private RuntimeFilesystem $files) {}

    public function build(string $manifestPath, string $destination, array $mediaCopies = [], string $base = '/'): void
    {
        if (is_dir($destination) || is_file($destination)) {
            throw new RuntimeException('Das eindeutige Build-Ziel existiert bereits.');
        }
        if (env('MADLEN_SIMULATE_BUILD_FAILURE', false)) {
            throw new RuntimeException('Simulierter Build-Fehler für den Wiederherstellungstest.');
        }

        $process = new Process(
            ['npm', 'run', 'build'],
            config('madlen.repository_root'),
            [
                'ASTRO_TELEMETRY_DISABLED' => '1',
                'MADLEN_CONTENT_RELEASE' => $manifestPath,
                'MADLEN_OUT_DIR' => $destination,
                'MADLEN_BASE_PATH' => $base,
            ],
        );
        $process->setTimeout(300);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        $this->removeNonPublicArtifacts($destination);

        foreach ($mediaCopies as $publicPath => $privatePath) {
            $target = $destination.'/'.ltrim($publicPath, '/');
            $this->files->copy(Storage::disk('local')->path($privatePath), $target);
        }

        if ($base !== '/') {
            $this->scopePreviewUrls($destination, $base);
        }

        $this->validate($manifestPath, $destination);
        $this->writeSitemap($destination);
    }

    private function removeNonPublicArtifacts(string $destination): void
    {
        $root = realpath($destination);
        if (! $root) {
            throw new RuntimeException('Das Build-Ziel wurde nicht gefunden.');
        }

        foreach (config('madlen.public_exclude_paths', []) as $relativePath) {
            $candidate = $root.'/'.ltrim((string) $relativePath, '/');
            if (is_file($candidate) || is_link($candidate)) {
                unlink($candidate);
                continue;
            }
            if (! is_dir($candidate)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($candidate, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $entry) {
                $entry->isDir() && ! $entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($candidate);
        }
    }

    private function scopePreviewUrls(string $destination, string $base): void
    {
        $normalizedBase = '/'.trim($base, '/');
        $escapedBase = preg_quote(ltrim($normalizedBase, '/'), '~');
        $quotedRootUrl = "~([\"'`])/(?!/|{$escapedBase}(?:/|[\"'`]))~";
        $unquotedCssUrl = "~url\\(/(?!/|{$escapedBase}(?:/|\\)))~";
        $extensions = ['html', 'css', 'js', 'json', 'xml'];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($destination));
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), $extensions, true)) {
                continue;
            }

            $path = $file->getPathname();
            $content = file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException("Preview-Datei konnte nicht gelesen werden: {$path}");
            }

            $scoped = preg_replace($quotedRootUrl, '$1'.$normalizedBase.'/', $content);
            $scoped = preg_replace($unquotedCssUrl, 'url('.$normalizedBase.'/', $scoped ?? $content);
            if ($scoped === null || file_put_contents($path, $scoped) === false) {
                throw new RuntimeException("Preview-URLs konnten nicht geschützt werden: {$path}");
            }
        }
    }

    private function validate(string $manifestPath, string $destination): void
    {
        if (! is_file($destination.'/index.html') || ! is_file($destination.'/en/index.html')) {
            throw new RuntimeException('Build-Prüfung fehlgeschlagen: Startseiten fehlen.');
        }
        $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        foreach ($manifest['projects'] as $project) {
            foreach (["portfolio/{$project['slug']}/index.html", "en/portfolio/{$project['slug']}/index.html"] as $route) {
                if (! is_file($destination.'/'.$route)) {
                    throw new RuntimeException("Build-Prüfung fehlgeschlagen: {$route} fehlt.");
                }
            }
        }
    }

    private function writeSitemap(string $destination): void
    {
        $urls = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($destination));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getFilename() !== 'index.html') continue;
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($destination)));
            $path = preg_replace('#/index\.html$#', '/', $relative) ?: '/';
            $urls[] = '  <url><loc>'.htmlspecialchars(rtrim('https://foto-video-madlen.de', '/').$path, ENT_XML1).'</loc></url>';
        }
        sort($urls);
        $this->files->write($destination.'/sitemap.xml', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n".implode("\n", $urls)."\n</urlset>\n");
    }
}
