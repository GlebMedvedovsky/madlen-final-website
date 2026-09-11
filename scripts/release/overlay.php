<?php
/** Self-contained, fail-closed backend-only installer. Does not bootstrap Laravel. */
declare(strict_types=1);

function stop(string $message): never { throw new RuntimeException($message); }
function digest(string $file): ?string {
    if (is_link($file)) stop('Symlink target refused: '.$file);
    if (! file_exists($file)) return null;
    if (! is_file($file) || ! is_readable($file)) stop('Not a readable regular file: '.$file);
    return hash_file('sha256', $file);
}
function target(string $root, string $relative): string {
    if (! preg_match('#\A(?:(?:app|config|routes|database/migrations)/[A-Za-z0-9_./-]+\.php|bootstrap/app\.php|lang/de/(?:passwords|validation)\.php|lang/de\.json)\z#', $relative)
        || str_contains($relative, '..') || str_contains($relative, '//')) stop('Path outside backend allowlist');
    $pieces = explode('/', $relative); array_pop($pieces); $parent = $root;
    foreach ($pieces as $piece) {
        $parent .= '/'.$piece;
        if (is_link($parent)) stop('Symlink parent refused: '.$parent);
        if (file_exists($parent) && ! is_dir($parent)) stop('Parent is not a directory');
    }
    return $root.'/'.$relative;
}
function saveJson(string $file, array $data): void {
    if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n") === false) stop('Cannot write private record');
    chmod($file, 0600);
}
function replaceFile(string $source, string $dest): void {
    if (! is_dir(dirname($dest)) && ! mkdir(dirname($dest), 0755, true)) stop('Cannot create code directory');
    $tmp = $dest.'.madlen-'.bin2hex(random_bytes(5));
    if (! copy($source, $tmp)) stop('Cannot stage code file');
    chmod($tmp, 0644);
    if (! rename($tmp, $dest)) { unlink($tmp); stop('Atomic code replacement failed'); }
}
try {
    [$program, $mode, $argument] = array_pad($argv, 3, '');
    $manifestPath = __DIR__.'/manifest.json';
    $manifest = json_decode(file_get_contents($manifestPath), true, flags:JSON_THROW_ON_ERROR);
    $root = realpath($argument);
    if (! $root || ! is_dir($root) || basename($root) !== 'backend') stop('Supply the existing absolute backend directory');
    if (($manifest['schemaVersion'] ?? 0) !== 1) stop('Unsupported manifest');
    $files = $manifest['files'];
    $actual = [];
    foreach ($files as $relative=>$hashes) {
        $actual[$relative] = digest(target($root, $relative));
        if (digest(target(__DIR__.'/payload/backend', $relative)) !== $hashes['after']) stop('Payload checksum mismatch: '.$relative);
    }
    if ($mode === 'inspect') {
        echo json_encode(['schemaVersion'=>1,'backend'=>$root,'referenceRevision'=>$manifest['referenceRevision'],
            'note'=>'Actual installed files, read-only. Reference hashes are not evidence of installation.',
            'files'=>$actual], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
        exit(0);
    }
    $backupArgument = $argv[3] ?? '';
    if ($backupArgument === '' || ! str_starts_with($backupArgument, '/')) stop('Supply an absolute private backup directory');
    $parent = realpath(dirname($backupArgument));
    if (! $parent || is_link($backupArgument) || $parent === $root || str_starts_with($parent.'/', $root.'/')) stop('Backup must be outside backend');
    $backup = $parent.'/'.basename($backupArgument);
    if ($mode === 'rollback') {
        $record = json_decode(file_get_contents($backup.'/overlay-record.json'), true, flags:JSON_THROW_ON_ERROR);
        if ($record['backend'] !== $root || $record['manifestSha256'] !== hash_file('sha256',$manifestPath)) stop('Wrong rollback record');
        foreach ($files as $relative=>$hashes) {
            if (! in_array($actual[$relative], [$hashes['after'],$record['before'][$relative]], true)) stop('File changed after installation: '.$relative);
            if ($record['before'][$relative] !== null && digest(target($backup.'/originals', $relative)) !== $record['before'][$relative]) stop('Backup checksum mismatch');
        }
        foreach ($files as $relative=>$hashes) {
            // Retain additive migration source/ledger. Never drop CMS data on a code rollback.
            if (str_starts_with($relative,'database/migrations/')) continue;
            $destination = target($root,$relative);
            if ($record['before'][$relative] === null) {
                if (file_exists($destination) && ! unlink($destination)) stop('Cannot remove added class');
            } else replaceFile(target($backup.'/originals',$relative), $destination);
        }
        echo "Code restored. Additive migration retained. Keep publisher disabled; rebuild autoload and clear caches as documented.\n";
        exit(0);
    }
    if (! in_array($mode,['check','apply'],true)) stop('Modes: inspect, check, apply, rollback');
    $observedPath = $argv[4] ?? '';
    $observed = json_decode(file_get_contents($observedPath),true,flags:JSON_THROW_ON_ERROR);
    if (($observed['backend'] ?? '') !== $root || ($observed['files'] ?? []) !== $actual) stop('Installed-state report is missing, stale, or from another backend');
    $allAfter = true;
    foreach ($files as $relative=>$hashes) {
        if (! in_array($actual[$relative],[$hashes['before'],$hashes['after']],true)) stop('Unexpected installed source: '.$relative.'; review it, do not substitute main checksums');
        if ($actual[$relative] !== $hashes['after']) $allAfter = false;
    }
    if ($allAfter) { echo "Already installed; no changes.\n"; exit(0); }
    // Refuse partial/unknown mixtures; a prior failed apply is recovered using its exact backup.
    foreach ($files as $relative=>$hashes) {
        // A completed code rollback deliberately retains the additive migration.
        $retainedMigration = str_starts_with($relative, 'database/migrations/')
            && $hashes['before'] === null && $actual[$relative] === $hashes['after'];
        if ($actual[$relative] !== $hashes['before'] && ! $retainedMigration) stop('Partial installation detected; use its rollback record first');
    }
    if ($mode === 'check') { echo "Source and payload verified. No files changed.\n"; exit(0); }
    if (file_exists($backup)) stop('Backup directory already exists; choose a new path');
    umask(0077);
    if (! mkdir($backup,0700)) stop('Cannot create private backup');
    saveJson($backup.'/overlay-record.json',['backend'=>$root,'manifestSha256'=>hash_file('sha256',$manifestPath),'before'=>$actual]);
    foreach ($files as $relative=>$hashes) {
        if ($actual[$relative] === null) continue;
        $dest=target($backup.'/originals',$relative);
        if (! is_dir(dirname($dest))) mkdir(dirname($dest),0700,true);
        if (! copy(target($root,$relative),$dest) || digest($dest)!==$actual[$relative]) stop('Backup failed; backend not modified');
    }
    foreach ($files as $relative=>$hashes) replaceFile(target(__DIR__.'/payload/backend',$relative),target($root,$relative));
    foreach ($files as $relative=>$hashes) if (digest(target($root,$relative))!==$hashes['after']) stop('Post-apply checksum mismatch');
    echo "Backend code installed and checked. No database, .env, media, autoload or caches changed. Complete the documented steps before enabling publishing.\n";
} catch (Throwable $error) { fwrite(STDERR, 'STOP: '.$error->getMessage()."\n"); exit(1); }
