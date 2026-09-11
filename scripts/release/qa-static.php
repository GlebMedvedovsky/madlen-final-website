<?php
// Static-only server for the disposable current symlink, never executes build files.
$base = is_file('/tmp/madlen-release-qa/serve-normal-site')
    ? '/tmp/madlen-release-qa/normal-final'
    : '/tmp/madlen-release-qa/static/current';
// The long-lived PHP development server caches realpath across HTTP requests.
// A static Apache document root is not served through this PHP test router.
clearstatcache(true);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('#(^|/)\.#', $path) || str_contains($path, '\\')) { http_response_code(404); exit; }
$file = realpath($base.$path);
$root = realpath($base);
if ($file && is_dir($file)) $file = realpath($file.'/index.html');
if (! $root || ! $file || ! str_starts_with($file, $root.'/') || ! is_file($file)) { http_response_code(404); exit; }
$type = pathinfo($file, PATHINFO_EXTENSION);
header('Content-Type: '.(['html'=>'text/html','css'=>'text/css','js'=>'text/javascript','svg'=>'image/svg+xml','webp'=>'image/webp','jpg'=>'image/jpeg','png'=>'image/png','mp4'=>'video/mp4','woff2'=>'font/woff2','xml'=>'application/xml'][$type] ?? 'application/octet-stream'));
readfile($file);
