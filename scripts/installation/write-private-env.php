#!/usr/bin/env php
<?php

declare(strict_types=1);

use Illuminate\Encryption\Encrypter;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This helper may only be run from the command line.\n");
    exit(64);
}

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php write-private-env.php /absolute/path/to/private/backend.env\n");
    exit(64);
}

$target = $argv[1];
$secrets = [];

try {
    assertTargetCanBeCreated($target);
    assertHiddenInputAvailable();

    $cipher = configuredLaravelCipher();

    $secrets['APP_KEY'] = hiddenPrompt('Laravel APP_KEY: ');
    assertLaravelAppKey($secrets['APP_KEY'], $cipher);

    $secrets['DB_PASSWORD'] = hiddenPrompt('Netcup database password: ');
    assertSecretValue($secrets['DB_PASSWORD'], 'database password');

    $secrets['MAIL_PASSWORD'] = hiddenPrompt('Netcup SMTP password: ');
    assertSecretValue($secrets['MAIL_PASSWORD'], 'SMTP password');

    $values = [
        'APP_NAME' => 'Madlen CMS',
        'APP_ENV' => 'production',
        'APP_KEY' => $secrets['APP_KEY'],
        'APP_DEBUG' => 'false',
        'APP_URL' => 'https://admin.madebymadlen.de',
        'APP_LOCALE' => 'de',
        'APP_FALLBACK_LOCALE' => 'de',
        'APP_TIMEZONE' => 'Europe/Berlin',
        'LOG_CHANNEL' => 'stack',
        'LOG_LEVEL' => 'warning',
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => '10.35.232.131',
        'DB_PORT' => '3306',
        'DB_DATABASE' => 'k248794_madlen_cms',
        'DB_USERNAME' => 'k248794_madlen_app',
        'DB_PASSWORD' => $secrets['DB_PASSWORD'],
        'CACHE_STORE' => 'database',
        'SESSION_DRIVER' => 'database',
        'SESSION_SECURE_COOKIE' => 'true',
        'SESSION_HTTP_ONLY' => 'true',
        'SESSION_SAME_SITE' => 'lax',
        'SESSION_DOMAIN' => 'admin.madebymadlen.de',
        'QUEUE_CONNECTION' => 'sync',
        'FILESYSTEM_DISK' => 'local',
        'MAIL_MAILER' => 'smtp',
        'MAIL_SCHEME' => 'smtps',
        'MAIL_HOST' => 'mxe884.netcup.net',
        'MAIL_PORT' => '465',
        'MAIL_USERNAME' => 'hi@madebymadlen.de',
        'MAIL_PASSWORD' => $secrets['MAIL_PASSWORD'],
        'MAIL_TIMEOUT' => '15',
        'MAIL_FROM_ADDRESS' => 'hi@madebymadlen.de',
        'MAIL_FROM_NAME' => 'Made by Madlen',
        'MADLEN_INSTALL_LAYOUT' => 'netcup-sibling-private-v1',
        'MADLEN_PUBLIC_URL' => 'https://madebymadlen.de',
        'MADLEN_PUBLIC_SITE_URL' => 'https://madebymadlen.de',
        'MADLEN_CONTACT_ENABLED' => 'false',
        'MADLEN_CONTACT_MAILER' => 'smtp',
        'MADLEN_CONTACT_FROM_ADDRESS' => 'hi@madebymadlen.de',
        'MADLEN_CONTACT_FROM_NAME' => 'Made by Madlen',
        'MADLEN_CONTACT_RECIPIENT' => 'contact@madlenmedvedovskyy.de',
        'MADLEN_CONTACT_ALLOWED_ORIGINS' => 'https://madebymadlen.de,https://www.madebymadlen.de,https://admin.madebymadlen.de',
        'MADLEN_CONTACT_REQUIRE_ORIGIN' => 'true',
        'MADLEN_CONTACT_RATE_PER_MINUTE' => '3',
        'MADLEN_CONTACT_RATE_PER_HOUR' => '10',
        'MADLEN_CONTACT_MIN_SECONDS' => '3',
        'MADLEN_CONTACT_MAX_SECONDS' => '7200',
        'MADLEN_PRODUCTION_PUBLISHER' => 'unconfigured',
        'MADLEN_PRODUCTION_CONNECTED' => 'false',
        'MADLEN_PRODUCTION_SOURCE_REVISION' => '',
        'MADLEN_PUBLISHER_API_TOKEN' => '',
        'MADLEN_GITHUB_REPOSITORY' => '',
        'MADLEN_GITHUB_WORKFLOW' => 'madlen-production-publisher.yml',
        'MADLEN_GITHUB_REF' => 'main',
        'MADLEN_GITHUB_TOKEN' => '',
        'MADLEN_PREVIEW_TTL' => '120',
        'MADLEN_PREVIEW_EXECUTION' => 'local',
        'MADLEN_EXTERNAL_PREVIEW_CONNECTED' => 'false',
        'MADLEN_EXTERNAL_PREVIEW_DRIVER' => 'unconfigured',
        'MADLEN_PREVIEW_SOURCE_REVISION' => '',
        'MADLEN_PREVIEW_API_TOKEN' => '',
        'MADLEN_PREVIEW_GITHUB_REPOSITORY' => '',
        'MADLEN_PREVIEW_GITHUB_WORKFLOW' => 'madlen-external-preview.yml',
        'MADLEN_PREVIEW_GITHUB_REF' => 'main',
        'MADLEN_PREVIEW_GITHUB_TOKEN' => '',
    ];

    $payload = serializeEnv($values);
    writePrivateFile($target, $payload);

    fwrite(STDOUT, "Private environment file created with mode 0600.\n");
    fwrite(STDOUT, "Contact delivery and both remote runners remain disabled.\n");
    fwrite(STDOUT, "Keep Laravel configuration uncached until CLI/FPM path checks are complete.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'ERROR: '.$exception->getMessage()."\n");
    exit(1);
} finally {
    foreach (array_keys($secrets) as $name) {
        $secrets[$name] = '';
        unset($secrets[$name]);
    }
}

function assertTargetCanBeCreated(string $target): void
{
    if ($target === '' || $target[0] !== '/') {
        throw new RuntimeException('The target must be an absolute path.');
    }

    if (file_exists($target) || is_link($target)) {
        throw new RuntimeException('The target already exists; it was not overwritten.');
    }

    $parent = dirname($target);

    if (! is_dir($parent) || ! is_writable($parent)) {
        throw new RuntimeException('The target directory is missing or is not writable.');
    }
}

function assertHiddenInputAvailable(): void
{
    if (PHP_OS_FAMILY === 'Windows' || ! function_exists('proc_open') || ! function_exists('stream_isatty')) {
        throw new RuntimeException('Secure hidden input is unavailable in this PHP environment.');
    }

    if (! stream_isatty(STDIN) || ! is_executable('/bin/bash') || ! is_readable('/dev/tty')) {
        throw new RuntimeException('Run this helper in an interactive SSH terminal; no visible-input fallback is allowed.');
    }

    $process = proc_open(
        ['/bin/bash', '--noprofile', '--norc', '-c', 'set +x; [[ -t 0 ]]'],
        [
            0 => ['file', '/dev/tty', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        null,
        childEnvironment(),
        ['bypass_shell' => true],
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Secure terminal preflight could not be started.');
    }

    stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $status = proc_close($process);

    if ($status !== 0) {
        throw new RuntimeException('The SSH terminal did not pass the secure hidden-input preflight.');
    }
}

function hiddenPrompt(string $prompt): string
{
    $program = <<<'BASH'
set +x
secret=
if ! IFS= read -r -s -p "$1" secret; then
    printf '\n' > /dev/tty
    unset secret
    exit 72
fi
printf '\n' > /dev/tty
printf '%s' "$secret"
unset secret
BASH;

    $process = proc_open(
        ['/bin/bash', '--noprofile', '--norc', '-c', $program, 'madlen-hidden-prompt', $prompt],
        [
            0 => ['file', '/dev/tty', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', '/dev/tty', 'w'],
        ],
        $pipes,
        null,
        childEnvironment(),
        ['bypass_shell' => true],
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Secure hidden input could not be started.');
    }

    $value = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $status = proc_close($process);

    if ($status !== 0) {
        throw new RuntimeException('Secret input was cancelled or reached end-of-file.');
    }

    assertSecretValue($value, 'secret');

    return $value;
}

/** @return array<string, string> */
function childEnvironment(): array
{
    return [
        'PATH' => '/nonexistent',
        'BASH_ENV' => '/dev/null',
        'ENV' => '/dev/null',
        'LC_ALL' => 'C',
    ];
}

function configuredLaravelCipher(): string
{
    $backend = dirname(__DIR__, 2).'/backend';
    $autoload = $backend.'/vendor/autoload.php';
    $configFile = $backend.'/config/app.php';

    if (! is_file($autoload) || ! is_file($configFile)) {
        throw new RuntimeException('Laravel dependencies or app configuration are missing.');
    }

    require_once $autoload;
    $config = require $configFile;
    $cipher = $config['cipher'] ?? null;

    if (! is_string($cipher) || $cipher === '') {
        throw new RuntimeException('Laravel has no configured encryption cipher.');
    }

    return $cipher;
}

function assertLaravelAppKey(string $value, string $cipher): void
{
    assertSecretValue($value, 'APP_KEY');

    if (! str_starts_with($value, 'base64:')) {
        throw new RuntimeException('APP_KEY must use Laravel base64: format.');
    }

    $encoded = substr($value, 7);
    $decoded = base64_decode($encoded, true);

    if ($decoded === false || $encoded === '' || base64_encode($decoded) !== $encoded) {
        throw new RuntimeException('APP_KEY is not strict canonical base64.');
    }

    if (! Encrypter::supported($decoded, $cipher)) {
        throw new RuntimeException("APP_KEY has the wrong decoded length for {$cipher}.");
    }
}

function assertSecretValue(string $value, string $label): void
{
    if ($value === '') {
        throw new RuntimeException("The {$label} must not be empty.");
    }

    if (strlen($value) > 4096) {
        throw new RuntimeException("The {$label} is unexpectedly long.");
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
        throw new RuntimeException("The {$label} contains a forbidden control character.");
    }

    if (preg_match('//u', $value) !== 1) {
        throw new RuntimeException("The {$label} is not valid UTF-8.");
    }
}

/** @param array<string, string> $values */
function serializeEnv(array $values): string
{
    $lines = [];

    foreach ($values as $name => $value) {
        $lines[] = $name.'='.quoteEnv($value);
    }

    return implode("\n", $lines)."\n";
}

function quoteEnv(string $value): string
{
    return '"'.strtr($value, [
        '\\' => '\\\\',
        '"' => '\\"',
        '$' => '\\$',
        "\n" => '\\n',
        "\r" => '\\r',
    ]).'"';
}

function writePrivateFile(string $target, string $payload): void
{
    $parent = dirname($target);
    $temporary = $parent.'/.'.basename($target).'.tmp-'.bin2hex(random_bytes(12));
    $handle = null;
    $published = false;
    $previousUmask = umask(0077);

    try {
        $handle = @fopen($temporary, 'x+b');

        if (! is_resource($handle)) {
            throw new RuntimeException('Could not create an exclusive private temporary file.');
        }

        $createdStat = fstat($handle);

        if ($createdStat === false || ($createdStat['mode'] & 0777) !== 0600) {
            throw new RuntimeException('The temporary file was not private at creation time.');
        }

        if (! chmod($temporary, 0600)) {
            throw new RuntimeException('Could not enforce mode 0600 on the temporary file.');
        }

        $length = strlen($payload);
        $written = 0;

        while ($written < $length) {
            $chunk = fwrite($handle, substr($payload, $written));

            if ($chunk === false || $chunk === 0) {
                throw new RuntimeException('The private environment file could not be written completely.');
            }

            $written += $chunk;
        }

        if (! fflush($handle)) {
            throw new RuntimeException('The private environment file could not be flushed.');
        }

        if (function_exists('fsync') && ! fsync($handle)) {
            throw new RuntimeException('The private environment file could not be synchronized.');
        }

        if (! fclose($handle)) {
            $handle = null;
            throw new RuntimeException('The private environment file could not be closed safely.');
        }

        $handle = null;
        clearstatcache(true, $temporary);
        $temporaryStat = lstat($temporary);

        if ($temporaryStat === false
            || ($temporaryStat['mode'] & 0777) !== 0600
            || $temporaryStat['size'] !== $length
            || hash_file('sha256', $temporary) !== hash('sha256', $payload)) {
            throw new RuntimeException('The private temporary file failed its integrity or permission check.');
        }

        if (! @link($temporary, $target)) {
            if (file_exists($target) || is_link($target)) {
                throw new RuntimeException('The target appeared concurrently; it was not overwritten.');
            }

            throw new RuntimeException('Could not publish the private file without overwrite semantics.');
        }

        $published = true;
        clearstatcache(true, $target);
        $targetStat = lstat($target);

        if ($targetStat === false
            || $targetStat['dev'] !== $temporaryStat['dev']
            || $targetStat['ino'] !== $temporaryStat['ino']
            || ($targetStat['mode'] & 0777) !== 0600
            || $targetStat['size'] !== $length
            || hash_file('sha256', $target) !== hash('sha256', $payload)) {
            throw new RuntimeException('The published file needs manual inspection; integrity verification failed.');
        }

        if (! unlink($temporary)) {
            throw new RuntimeException('The target is active, but its private temporary hard link needs manual removal.');
        }
    } finally {
        umask($previousUmask);

        if (is_resource($handle)) {
            fclose($handle);
        }

        if (file_exists($temporary) || is_link($temporary)) {
            @unlink($temporary);
        }

        if ($published) {
            clearstatcache(true, $target);
        }
    }
}
