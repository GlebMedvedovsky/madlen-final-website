<?php
// Run as an unprivileged user in a disposable Linux container, with PHP 8.4
// at /usr/local/php84/bin/php. Tests the actual function extracted from the runbook.
declare(strict_types=1);
require __DIR__.'/../../backend/vendor/autoload.php';

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

$files = new Filesystem;
$root = sys_get_temp_dir().'/madlen-marker-test-'.bin2hex(random_bytes(8));
$files->makeDirectory($root.'/empty-bin', 0755, true);
$check = static function (bool $ok, string $message): void {
    if (! $ok) throw new RuntimeException($message);
};
$doc = str_replace("\r\n", "\n", file_get_contents($argv[1] ?? __DIR__.'/../../docs/NETCUP_RELEASE_RU.md'));
$check(preg_match('/^ensure_marker\(\) \{\n.*?^\}/ms', $doc, $match) === 1, 'Cannot find actual ensure_marker function');
$function = $match[0];
$check(is_executable('/usr/local/php84/bin/php'), 'Netcup PHP path must exist in the disposable fixture');
$cases = 0;

try {
    foreach (['/bin/sh', '/bin/bash'] as $shell) {
        $dir = $root.'/'.basename($shell);
        $files->makeDirectory($dir, 0755);
        $run = static function (string $path, string $expected) use ($function, $root, $shell): Process {
            $p = new Process([$shell, '-c', "set -eu\n".$function."\n".'ensure_marker "$1" "$2"', 'marker-test', $path, $expected], env:['PATH'=>$root.'/empty-bin']);
            $p->setTimeout(5);
            $p->run();
            return $p;
        };
        $refuse = static function (string $path, string $expected) use ($run, $check, &$cases): void {
            $p = $run($path, $expected);
            $check(! $p->isSuccessful(), 'Unsafe marker accepted: '.basename($path));
            $check(str_contains($p->getErrorOutput(), 'STOP:'), 'Missing marker refusal diagnostic');
            $cases++;
        };
        foreach (['madlen-production-incoming-v1', 'madlen-production-root-v1'] as $value) {
            $path = $dir.'/'.$value;
            $created = $run($path, $value);
            $check($created->isSuccessful(), 'New marker failed: '.$created->getErrorOutput());
            $check(file_get_contents($path) === $value."\n", 'New marker must end in exactly one LF');
            $cases++;
            clearstatcache(true, $path);
            $before = lstat($path);
            $same = $run($path, $value);
            $check($same->isSuccessful(), 'Matching marker failed: '.$same->getErrorOutput());
            clearstatcache(true, $path);
            $after = lstat($path);
            foreach (['ino','mode','size','mtime'] as $field) {
                $check($before[$field] === $after[$field], 'Matching marker was rewritten: '.$field);
            }
            $check(file_get_contents($path) === $value."\n", 'Matching marker bytes changed');
            $cases++;
        }
        $value = 'madlen-production-root-v1';
        foreach ([
            'wrong-value'=>"different\n",
            'missing-lf'=>$value,
            'crlf'=>$value."\r\n",
            'extra-lf'=>$value."\n\n",
            'nul-suffix'=>$value."\n\0",
            'long-suffix'=>$value."\n".str_repeat('x', 8192),
        ] as $label=>$bytes) {
            $path = $dir.'/'.$label;
            file_put_contents($path, $bytes);
            $refuse($path, $value);
            $check(file_get_contents($path) === $bytes, 'Unexpected marker overwritten: '.$label);
        }
        $target = $dir.'/regular-target';
        file_put_contents($target, $value."\n");
        symlink($target, $dir.'/linked');
        $refuse($dir.'/linked', $value);
        $check(is_link($dir.'/linked') && file_get_contents($target) === $value."\n", 'Symlink/target changed');
        symlink($dir.'/absent-target', $dir.'/dangling');
        $refuse($dir.'/dangling', $value);
        $check(is_link($dir.'/dangling') && ! file_exists($dir.'/absent-target'), 'Dangling symlink followed');
        mkdir($dir.'/directory');
        $refuse($dir.'/directory', $value);
        $check(is_dir($dir.'/directory'), 'Directory marker changed');

        $check(function_exists('posix_mkfifo') && posix_mkfifo($dir.'/fifo', 0600), 'Cannot create synthetic FIFO');
        $refuse($dir.'/fifo', $value); // Must refuse promptly, not block waiting for a writer.
        $check(filetype($dir.'/fifo') === 'fifo', 'FIFO marker changed');
        $socket = stream_socket_server('unix://'.$dir.'/socket', $errno, $error);
        $check(is_resource($socket), 'Cannot create synthetic local socket');
        try {
            $refuse($dir.'/socket', $value);
            $check(filetype($dir.'/socket') === 'socket', 'Socket marker changed');
        } finally { fclose($socket); }

        $unreadable = $dir.'/unreadable';
        file_put_contents($unreadable, $value."\n");
        chmod($unreadable, 0000);
        clearstatcache(true, $unreadable);
        try {
            $check(! is_readable($unreadable), 'Run marker test as a non-root user to verify read denial');
            $refuse($unreadable, $value);
            $check((fileperms($unreadable) & 0777) === 0, 'Unreadable marker permissions changed');
        } finally { chmod($unreadable, 0600); }
        $check(file_get_contents($unreadable) === $value."\n", 'Unreadable marker overwritten');
    }
    echo "Markers: $cases cases passed in sh/bash with empty PATH (no cmp/stat/Composer); exact LF/bytes, unchanged matching files, no unexpected overwrites, symlink/dangling/directory/FIFO/socket/unreadable refusals.\n";
} finally {
    $files->deleteDirectory($root);
}
