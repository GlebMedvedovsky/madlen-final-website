<?php

declare(strict_types=1);

use App\Support\HostingPathResolver;

header('Cache-Control: no-store, max-age=0');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:\d+$/', '', $host) ?? '';
$https = ($_SERVER['HTTPS'] ?? '') === 'on'
    || ($_SERVER['SERVER_PORT'] ?? '') === '443'
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

if (PHP_SAPI === 'cli' || $host !== 'admin.madebymadlen.de' || ! $https) {
    http_response_code(404);
    echo "{\"ok\":false}\n";
    exit;
}

$backend = realpath(dirname(__DIR__));

if ($backend === false || ! is_file($backend.'/vendor/autoload.php')) {
    http_response_code(500);
    echo "{\"ok\":false,\"error\":\"backend-not-found\"}\n";
    exit;
}

require $backend.'/vendor/autoload.php';

$paths = HostingPathResolver::resolve($backend, HostingPathResolver::NETCUP_SIBLING_PRIVATE);
$runtime = $paths['private_root'].'/runtime';
$probeFile = $runtime.'/.fpm-path-probe-'.bin2hex(random_bytes(8));
$writeDelete = false;
$previousUmask = umask(0077);

try {
    $handle = @fopen($probeFile, 'x+b');

    if (is_resource($handle)) {
        $writeDelete = fwrite($handle, 'probe') === 5 && fflush($handle);
        fclose($handle);
        $writeDelete = @unlink($probeFile) && $writeDelete;
    }
} finally {
    umask($previousUmask);

    if (file_exists($probeFile) || is_link($probeFile)) {
        @unlink($probeFile);
    }
}

$result = [
    'ok' => $writeDelete
        && is_readable($paths['repository_root'].'/content/baseline.json')
        && is_readable($backend.'/.env'),
    'sapi' => PHP_SAPI,
    'php_version' => PHP_VERSION,
    'backend_base_path' => $backend,
    'repository_root' => $paths['repository_root'],
    'private_root' => $paths['private_root'],
    'storage_root' => $paths['storage_root'],
    'baseline_readable' => is_readable($paths['repository_root'].'/content/baseline.json'),
    'env_is_link' => is_link($backend.'/.env'),
    'env_readable' => is_readable($backend.'/.env'),
    'private_runtime_write_delete' => $writeDelete,
    'proc_open_available' => function_exists('proc_open'),
    'open_basedir' => (string) ini_get('open_basedir'),
];

http_response_code($result['ok'] ? 200 : 500);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
