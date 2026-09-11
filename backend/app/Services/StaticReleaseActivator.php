<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class StaticReleaseActivator
{
    public function __construct(private RuntimeFilesystem $files) {}

    public function currentRelease(): ?string
    {
        [$root] = $this->configuredRoots();
        return $this->activeRelease($root);
    }

    public function activate(string $publicationId, int $sequence, string $archiveName, string $expectedChecksum): string
    {
        if (! preg_match('/\A[a-zA-Z0-9-]{8,64}\z/', $publicationId) || $sequence < 1) {
            throw new RuntimeException('Ungültige Kennung der Produktiv-Veröffentlichung.');
        }
        if (! preg_match('/\A[a-zA-Z0-9._-]+\.zip\z/', $archiveName)) {
            throw new RuntimeException('Ungültiger Name des übertragenen Release-Pakets.');
        }
        if (! preg_match('/\A[0-9a-f]{64}\z/i', $expectedChecksum)) {
            throw new RuntimeException('Ungültige SHA-256-Prüfsumme.');
        }

        [$root, $incoming] = $this->configuredRoots();
        $archive = realpath($incoming.'/'.$archiveName);
        if ($archive === false || ! is_file($archive) || ! $this->isInside($archive, $incoming)) {
            throw new RuntimeException('Das übertragene Release-Paket wurde im freigegebenen Eingangsverzeichnis nicht gefunden.');
        }
        $actualChecksum = hash_file('sha256', $archive);
        if ($actualChecksum === false || ! hash_equals(strtolower($expectedChecksum), strtolower($actualChecksum))) {
            throw new RuntimeException('Die Prüfsumme des übertragenen Release-Pakets stimmt nicht überein.');
        }

        $releaseName = $sequence.'-'.$publicationId;
        $lock = $this->lock($root);
        try {
            $state = $this->readState($root);
            $activeRelease = $this->activeRelease($root);
            $highestSequence = max((int) ($state['highestSequence'] ?? 0), $this->sequenceFromRelease($activeRelease));
            if ($activeRelease === $releaseName) {
                $stored = trim((string) @file_get_contents($root.'/releases/'.$releaseName.'/.madlen-package.sha256'));
                if (! hash_equals(strtolower($expectedChecksum), strtolower($stored))) {
                    throw new RuntimeException('Der wiederholte Auftrag enthält ein anderes Paket.');
                }
                return $releaseName;
            }
            if ($sequence < $highestSequence || ($sequence === $highestSequence && ($state['failedActivation'] ?? null) !== $releaseName)) {
                throw new RuntimeException('Dieser Auftrag ist älter als der bereits aktivierte Stand und wurde abgelehnt.');
            }

            $target = $root.'/releases/'.$releaseName;
            $staging = $root.'/.incoming/'.$releaseName;
            $this->files->ensureDirectory($root.'/releases');
            $this->files->ensureDirectory($root.'/.incoming');

            $replaceFailed = false;
            if (is_link($target) || (file_exists($target) && ! is_dir($target))) {
                throw new RuntimeException('Das Release-Ziel ist kein sicheres Verzeichnis.');
            }
            if (is_dir($target)) {
                $storedChecksum = trim((string) @file_get_contents($target.'/.madlen-package.sha256'));
                if (! hash_equals(strtolower($expectedChecksum), strtolower($storedChecksum))) {
                    // A rebuild of the SAME immutable request has a different builtAt.
                    // Only a recorded, compensated CMS failure may replace its inactive
                    // directory. Active/older releases were rejected above, and the old
                    // package identity must still match the compensation record.
                    $replaceFailed = ($state['failedActivation'] ?? null) === $releaseName
                        && $sequence === $highestSequence
                        && preg_match('/\A[0-9a-f]{64}\z/', $storedChecksum)
                        && hash_equals($storedChecksum, $state['failedPackageChecksum'] ?? '');
                    if (! $replaceFailed) {
                        throw new RuntimeException('Das vorhandene Zielverzeichnis gehört nicht zu diesem geprüften Paket.');
                    }
                }
                $this->validateRelease($target, $publicationId, $sequence);
            }
            if (! is_dir($target) || $replaceFailed) {
                if (file_exists($staging) || is_link($staging)) {
                    throw new RuntimeException('Ein unvollständiges Ziel für diesen Auftrag ist bereits vorhanden.');
                }
                try {
                    $this->extractArchive($archive, $staging);
                    $this->files->write($staging.'/.madlen-package.sha256', strtolower($expectedChecksum)."\n");
                    $this->validateRelease($staging, $publicationId, $sequence);
                    if (config('madlen.publisher.simulate_transfer_failure')) {
                        throw new RuntimeException('Simulierter Übertragungsfehler; der bisherige Stand bleibt aktiv.');
                    }
                    if ($replaceFailed) {
                        // Keep the failed build for inspection, outside releases/current.
                        // Do not move it until the replacement has passed ALL checks.
                        $quarantine = $root.'/.incoming/failed-'.$releaseName.'-'.bin2hex(random_bytes(8));
                        if (! rename($target, $quarantine)) {
                            throw new RuntimeException('Der fehlgeschlagene Stand konnte nicht sicher verwahrt werden.');
                        }
                    }
                    $moved = @rename($staging, $target);
                    if (! $moved && ! (is_dir($target) && ! file_exists($staging))) {
                        $detail = error_get_last()['message'] ?? 'unbekannter Dateisystemfehler';
                        throw new RuntimeException("Der geprüfte Stand konnte nicht in das Release-Verzeichnis übernommen werden: {$detail}");
                    }
                } catch (\Throwable $error) {
                    if (is_dir($staging) && $this->isInside($staging, $root.'/.incoming')) {
                        $this->deleteDirectory($staging);
                    }
                    throw $error;
                }
            }

            $this->switchCurrent($root, $releaseName);
            try {
            $this->writeState($root, [
                'highestSequence' => $sequence,
                'activeRelease' => $releaseName,
                'activatedAt' => gmdate(DATE_ATOM),
            ]);
            } catch (\Throwable $error) {
                $this->restorePointer($root, $releaseName, $activeRelease);
                throw $error;
            }

            return $releaseName;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function rollback(string $releaseName): string
    {
        if (! preg_match('/\A[1-9][0-9]*-[a-zA-Z0-9-]{8,64}\z/', $releaseName)) {
            throw new RuntimeException('Ungültiger Ziel-Release für die Rücksetzung.');
        }

        [$root] = $this->configuredRoots();
        $lock = $this->lock($root);
        try {
            $target = $root.'/releases/'.$releaseName;
            if (! is_dir($target) || ! $this->isInside((string) realpath($target), $root.'/releases')) {
                throw new RuntimeException('Der gewählte frühere Release ist nicht vorhanden.');
            }
            $this->validateRelease($target, substr($releaseName, strpos($releaseName, '-') + 1), $this->sequenceFromRelease($releaseName));
            $state = $this->readState($root);
            $previous = $this->activeRelease($root);
            $this->switchCurrent($root, $releaseName);
            try {
            $this->writeState($root, [
                'highestSequence' => max((int) ($state['highestSequence'] ?? 0), $this->sequenceFromRelease($this->activeRelease($root))),
                'activeRelease' => $releaseName,
                'activatedAt' => gmdate(DATE_ATOM),
                'rollback' => true,
            ]);
            } catch (\Throwable $error) {
                $this->restorePointer($root, $releaseName, $previous);
                throw $error;
            }

            return $releaseName;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function restoreAfterFailedActivation(string $failed, ?string $previous): void
    {
        [$root] = $this->configuredRoots();
        $lock = $this->lock($root);
        try {
            $this->restorePointer($root, $failed, $previous);
            $state = $this->readState($root);
            $state['activeRelease'] = $previous;
            $state['failedActivation'] = $failed;
            $state['failedPackageChecksum'] = trim((string) file_get_contents($root.'/releases/'.$failed.'/.madlen-package.sha256'));
            $this->writeState($root, $state);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function restorePointer(string $root, string $failed, ?string $previous): void
    {
        if ($this->activeRelease($root) !== $failed) throw new RuntimeException('Der Produktiv-Zeiger hat sich während der Wiederherstellung geändert.');
        if ($previous) {
            $this->switchCurrent($root, $previous);
        } elseif (! unlink($root.'/current')) {
            throw new RuntimeException('Der erste Produktiv-Zeiger konnte nicht zurückgenommen werden.');
        }
    }

    private function configuredRoots(): array
    {
        $destination = trim((string) config('madlen.publisher.destination_root'));
        $incoming = trim((string) config('madlen.publisher.incoming_root'));
        if ($destination === '' || $incoming === '') {
            throw new RuntimeException('Produktiv-Ziel und Eingangsverzeichnis sind nicht konfiguriert.');
        }

        $this->files->ensureDirectory($destination);
        $this->files->ensureDirectory($incoming);
        $root = realpath($destination);
        $incomingRoot = realpath($incoming);
        if ($root === false || $incomingRoot === false || $root === $incomingRoot || $this->isInside($root, $incomingRoot) || $this->isInside($incomingRoot, $root)) {
            throw new RuntimeException('Produktiv-Ziel und Eingangsverzeichnis müssen getrennte, eindeutige Verzeichnisse sein.');
        }
        if (trim((string) @file_get_contents($root.'/.madlen-publisher-root')) !== 'madlen-production-root-v1'
            || trim((string) @file_get_contents($incomingRoot.'/.madlen-publisher-incoming')) !== 'madlen-production-incoming-v1') {
            throw new RuntimeException('Die konfigurierten Verzeichnisse besitzen nicht die erforderlichen Madlen-Sicherheitsmarkierungen.');
        }

        return [$root, $incomingRoot];
    }

    /** @return resource */
    private function lock(string $root)
    {
        $handle = @fopen($root.'/.publisher.lock', 'c+');
        if ($handle === false || ! flock($handle, LOCK_EX)) {
            throw new RuntimeException('Die Produktiv-Veröffentlichung konnte nicht exklusiv gesperrt werden.');
        }

        return $handle;
    }

    private function extractArchive(string $archive, string $destination): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Die PHP-ZIP-Erweiterung fehlt auf dem Zielsystem.');
        }
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Das übertragene Release-Paket kann nicht geöffnet werden.');
        }

        try {
            $seen = [];
            $total = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);
                $normalized = str_replace('\\', '/', $name);
                if ($name !== $normalized || $normalized === '' || str_starts_with($normalized, '/') || preg_match('#(^|/)\.\.?(/|$)#', $normalized) || str_contains($normalized, ':')) {
                    throw new RuntimeException('Das Release-Paket enthält einen unsicheren Dateipfad.');
                }
                if (isset($seen[$normalized])) {
                    throw new RuntimeException('Das Release-Paket enthält einen Dateipfad mehrfach.');
                }
                $seen[$normalized] = true;
                $zip->getExternalAttributesIndex($index, $system, $attributes);
                $type = ($attributes >> 16) & 0170000;
                $total += (int) ($zip->statIndex($index)['size'] ?? 0);
                if (($type && ! in_array($type, [0100000, 0040000], true)) || $total > 2 * 1024 * 1024 * 1024) {
                    throw new RuntimeException('Unzulässiger Dateityp oder zu großes Release-Paket.');
                }
            }
            $this->files->ensureDirectory($destination);
            if (! $zip->extractTo($destination)) {
                throw new RuntimeException('Das Release-Paket konnte nicht in das isolierte Ziel entpackt werden.');
            }
        } finally {
            $zip->close();
        }
    }

    private function validateRelease(string $directory, string $publicationId, int $sequence): void
    {
        foreach (['index.html', 'en/index.html', 'sitemap.xml', '.madlen-release.json'] as $required) {
            if (! is_file($directory.'/'.$required)) {
                throw new RuntimeException("Release-Prüfung fehlgeschlagen: {$required} fehlt.");
            }
        }
        $metadata = json_decode((string) file_get_contents($directory.'/.madlen-release.json'), true);
        if (! is_array($metadata)
            || ($metadata['publicationId'] ?? null) !== $publicationId
            || (int) ($metadata['sequence'] ?? 0) !== $sequence) {
            throw new RuntimeException('Release-Prüfung fehlgeschlagen: Paket und Auftrag stimmen nicht überein.');
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            if ($entry->isLink()) {
                throw new RuntimeException('Der statische Release darf keine Verknüpfungen enthalten.');
            }
            $relative = str_replace('\\', '/', substr($entry->getPathname(), strlen($directory) + 1));
            if (preg_match('/\.(?:php[0-9]?|phtml|phar|cgi|pl|sh)$/i', $relative)) {
                throw new RuntimeException('Der statische Release darf keinen ausführbaren Servercode enthalten.');
            }
            if (preg_match('#(^|/)(\.env(?:\.|$)|backend|storage|vendor|node_modules|private)(/|$)#i', $relative)) {
                throw new RuntimeException("Nicht öffentlicher Inhalt im Release gefunden: {$relative}");
            }
            if (! $entry->isFile() || strtolower($entry->getExtension()) !== 'html') {
                continue;
            }
            $html = file_get_contents($entry->getPathname());
            if ($html === false) {
                throw new RuntimeException("Release-Datei ist nicht lesbar: {$relative}");
            }
            preg_match_all('#/media/[a-zA-Z0-9._/-]+#', $html, $matches);
            foreach (array_unique($matches[0]) as $mediaUrl) {
                $mediaPath = parse_url($mediaUrl, PHP_URL_PATH);
                if (! is_string($mediaPath) || ! is_file($directory.'/'.ltrim($mediaPath, '/'))) {
                    throw new RuntimeException("Release-Prüfung fehlgeschlagen: referenziertes Medium {$mediaUrl} fehlt.");
                }
            }
        }
    }

    private function switchCurrent(string $root, string $releaseName): void
    {
        $current = $root.'/current';
        if (file_exists($current) && ! is_link($current)) {
            throw new RuntimeException('Der Produktiv-Zeiger ist kein symbolischer Link; Aktivierung abgebrochen.');
        }
        $next = $root.'/.current-'.$releaseName;
        if (file_exists($next) || is_link($next)) {
            throw new RuntimeException('Ein temporärer Produktiv-Zeiger ist bereits vorhanden.');
        }
        if (! @symlink('releases/'.$releaseName, $next)) {
            throw new RuntimeException('Das Zielsystem erlaubt keinen atomaren symbolischen Release-Zeiger.');
        }
        if (! @rename($next, $current)) {
            @unlink($next);
            throw new RuntimeException('Der geprüfte Release konnte nicht atomar aktiviert werden.');
        }
    }

    private function activeRelease(string $root): ?string
    {
        if (! is_link($root.'/current')) {
            return null;
        }
        $target = str_replace('\\', '/', (string) readlink($root.'/current'));

        return preg_match('#\Areleases/([1-9][0-9]*-[a-zA-Z0-9-]{8,64})\z#', $target, $matches) ? $matches[1] : null;
    }

    private function sequenceFromRelease(?string $release): int
    {
        return $release && preg_match('/\A([1-9][0-9]*)-/', $release, $matches) ? (int) $matches[1] : 0;
    }

    private function readState(string $root): array
    {
        $path = $root.'/.publisher-state.json';
        if (! is_file($path)) {
            return [];
        }
        try {
            $state = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new RuntimeException('Der gespeicherte Produktivstatus ist beschädigt; Aktivierung wurde sicher abgebrochen.');
        }

        if (! is_array($state) || ! isset($state['highestSequence']) || (int) $state['highestSequence'] < 1) {
            throw new RuntimeException('Der gespeicherte Produktivstatus ist ungültig; Aktivierung wurde sicher abgebrochen.');
        }

        return $state;
    }

    private function writeState(string $root, array $state): void
    {
        $temporary = $root.'/.publisher-state.'.bin2hex(random_bytes(6)).'.tmp';
        $this->files->write($temporary, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        if (! @rename($temporary, $root.'/.publisher-state.json')) {
            @unlink($temporary);
            throw new RuntimeException('Der Produktivstatus konnte nicht atomar gespeichert werden.');
        }
    }

    private function isInside(string $candidate, string $root): bool
    {
        $candidate = rtrim(str_replace('\\', '/', $candidate), '/');
        $root = rtrim(str_replace('\\', '/', $root), '/');

        return $candidate !== $root && str_starts_with($candidate.'/', $root.'/');
    }

    private function deleteDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() && ! $entry->isLink() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }
        @rmdir($directory);
    }
}
