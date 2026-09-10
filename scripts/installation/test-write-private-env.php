#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Support\HostingPathResolver;
use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__, 2);
$backend = $root.'/backend';
$helper = __DIR__.'/write-private-env.php';
$autoload = $backend.'/vendor/autoload.php';

require $autoload;

$temporaryRoot = sys_get_temp_dir().'/madlen-env-helper-test-'.bin2hex(random_bytes(6));

if (! mkdir($temporaryRoot, 0700) && ! is_dir($temporaryRoot)) {
    fail('Could not create the test directory.');
}

try {
    requireExecutable('/usr/bin/script');
    requireExecutable('/bin/bash');

    $appKey = 'base64:'.base64_encode(str_repeat("\x31", 32));
    $dbPassword = '  db ' .'$'. "'\"\\ umlaut-ü  ";
    $smtpPassword = " smtp ".'$'."'\"\\ emoji-☕ ";
    $target = $temporaryRoot.'/backend.env';

    $success = runPty(
        wrapperCommand($helper, $target),
        [
            ['Laravel APP_KEY: ', $appKey."\n"],
            ['Netcup database password: ', $dbPassword."\n"],
            ['Netcup SMTP password: ', $smtpPassword."\n"],
            ['ECHO_CHECK_READY:', "visible-echo-check\n"],
        ],
    );

    assertSame(0, $success['exit'], 'successful PTY run exit status');
    assertContains('ECHO_CHECK_VALUE=visible-echo-check', $success['transcript'], 'terminal echo restoration');
    assertNotContains($appKey, $success['transcript'], 'APP_KEY transcript leak');
    assertNotContains($dbPassword, $success['transcript'], 'DB password transcript leak');
    assertNotContains($smtpPassword, $success['transcript'], 'SMTP password transcript leak');
    assertTrue(is_file($target), 'environment file creation');
    assertSame(0600, fileperms($target) & 0777, 'environment file mode');

    $parsed = Dotenv::createArrayBacked($temporaryRoot, 'backend.env')->load();
    assertSame($appKey, $parsed['APP_KEY'] ?? null, 'APP_KEY round trip');
    assertSame($dbPassword, $parsed['DB_PASSWORD'] ?? null, 'DB password round trip');
    assertSame($smtpPassword, $parsed['MAIL_PASSWORD'] ?? null, 'SMTP password round trip');
    assertSame('netcup-sibling-private-v1', $parsed['MADLEN_INSTALL_LAYOUT'] ?? null, 'dynamic layout marker');
    assertSame('10.35.232.131', $parsed['DB_HOST'] ?? null, 'confirmed database host');
    assertSame('k248794_madlen_cms', $parsed['DB_DATABASE'] ?? null, 'confirmed database name');
    assertSame('k248794_madlen_app', $parsed['DB_USERNAME'] ?? null, 'confirmed database user');
    assertSame('database', $parsed['CACHE_STORE'] ?? null, 'database cache setting');
    assertSame('database', $parsed['SESSION_DRIVER'] ?? null, 'database session setting');
    assertSame('false', $parsed['MADLEN_CONTACT_ENABLED'] ?? null, 'contact remains disabled');

    verifyLaravelConfiguration($backend, $parsed, $appKey, $smtpPassword);
    verifyPathResolution();

    $nonTtyTarget = $temporaryRoot.'/non-tty.env';
    $nonTty = runNonTty([PHP_BINARY, $helper, $nonTtyTarget], 'not-a-secret'."\n");
    assertTrue($nonTty['exit'] !== 0, 'non-TTY rejection status');
    assertContains('interactive SSH terminal', $nonTty['output'], 'non-TTY rejection message');
    assertTrue(! file_exists($nonTtyTarget), 'non-TTY target absence');

    $eofTarget = $temporaryRoot.'/eof.env';
    $eof = runPty(
        wrapperCommand($helper, $eofTarget),
        [
            ['Laravel APP_KEY: ', "\x04"],
            ['ECHO_CHECK_READY:', "visible-after-eof\n"],
        ],
    );
    assertTrue($eof['exit'] !== 0, 'EOF cancellation status');
    assertContains('ECHO_CHECK_VALUE=visible-after-eof', $eof['transcript'], 'echo restoration after EOF');
    assertTrue(! file_exists($eofTarget), 'EOF target absence');

    $invalidTarget = $temporaryRoot.'/invalid-key.env';
    $invalidKey = 'base64:'.base64_encode(str_repeat("\x32", 16));
    $invalid = runPty(
        wrapperCommand($helper, $invalidTarget),
        [
            ['Laravel APP_KEY: ', $invalidKey."\n"],
            ['ECHO_CHECK_READY:', "visible-after-invalid\n"],
        ],
    );
    assertTrue($invalid['exit'] !== 0, 'invalid APP_KEY status');
    assertContains('wrong decoded length', $invalid['transcript'], 'invalid APP_KEY diagnosis');
    assertNotContains($invalidKey, $invalid['transcript'], 'invalid APP_KEY transcript leak');
    assertTrue(! file_exists($invalidTarget), 'invalid APP_KEY target absence');

    $lateFailureDirectory = $temporaryRoot.'/late-write-failure';
    mkdir($lateFailureDirectory, 0700);
    $lateFailureTarget = $lateFailureDirectory.'/backend.env';
    $lateFailure = runPty(
        wrapperCommand($helper, $lateFailureTarget),
        [
            ['Laravel APP_KEY: ', $appKey."\n"],
            ['Netcup database password: ', $dbPassword."\n"],
            ['Netcup SMTP password: ', static function () use ($lateFailureDirectory, $smtpPassword): string {
                chmod($lateFailureDirectory, 0500);

                return $smtpPassword."\n";
            }],
            ['ECHO_CHECK_READY:', "visible-after-write-failure\n"],
        ],
    );
    chmod($lateFailureDirectory, 0700);
    assertTrue($lateFailure['exit'] !== 0, 'late write failure status');
    assertContains('Could not create an exclusive private temporary file', $lateFailure['transcript'], 'late write failure diagnosis');
    assertNotContains($dbPassword, $lateFailure['transcript'], 'late write failure DB password leak');
    assertNotContains($smtpPassword, $lateFailure['transcript'], 'late write failure SMTP password leak');
    assertTrue(! file_exists($lateFailureTarget), 'late write failure target absence');
    assertSame([], glob($lateFailureDirectory.'/.backend.env.tmp-*') ?: [], 'late write failure temporary cleanup');

    $existingTarget = $temporaryRoot.'/existing.env';
    file_put_contents($existingTarget, 'keep-me');
    chmod($existingTarget, 0600);
    $existing = runNonTty([PHP_BINARY, $helper, $existingTarget]);
    assertTrue($existing['exit'] !== 0, 'existing target rejection status');
    assertContains('already exists', $existing['output'], 'existing target rejection message');
    assertSame('keep-me', file_get_contents($existingTarget), 'existing target preservation');

    $victim = $temporaryRoot.'/victim.env';
    $symlinkTarget = $temporaryRoot.'/symlink.env';
    file_put_contents($victim, 'victim-is-unchanged');
    symlink($victim, $symlinkTarget);
    $symlink = runNonTty([PHP_BINARY, $helper, $symlinkTarget]);
    assertTrue($symlink['exit'] !== 0, 'symlink target rejection status');
    assertContains('already exists', $symlink['output'], 'symlink target rejection message');
    assertSame('victim-is-unchanged', file_get_contents($victim), 'symlink victim preservation');

    $unwritable = $temporaryRoot.'/unwritable';
    mkdir($unwritable, 0500);
    chmod($unwritable, 0500);
    $writeFailure = runNonTty([PHP_BINARY, $helper, $unwritable.'/backend.env']);
    assertTrue($writeFailure['exit'] !== 0, 'unwritable target rejection status');
    assertContains('not writable', $writeFailure['output'], 'unwritable target diagnosis');
    chmod($unwritable, 0700);

    assertSame([], glob($temporaryRoot.'/.backend.env.tmp-*') ?: [], 'successful temporary cleanup');
    assertSame([], glob($temporaryRoot.'/.eof.env.tmp-*') ?: [], 'EOF temporary cleanup');

    fwrite(STDOUT, "PASS: hidden PTY input, exact dotenv round trip, Laravel config, path mapping, refusal and cleanup checks.\n");
} finally {
    removeTree($temporaryRoot);
}

/** @return array{exit: int, transcript: string} */
function runPty(string $command, array $interactions): array
{
    $process = proc_open(
        ['/usr/bin/script', '-qefc', $command, '/dev/null'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        ['TERM' => 'xterm', 'LC_ALL' => 'C.UTF-8'],
        ['bypass_shell' => true],
    );

    if (! is_resource($process)) {
        fail('Could not start the PTY driver.');
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $transcript = '';
    $deadline = microtime(true) + 20;

    foreach ($interactions as [$needle, $input]) {
        while (! str_contains($transcript, $needle)) {
            if (microtime(true) >= $deadline) {
                terminate($process, $pipes);
                fail("Timed out waiting for PTY prompt: {$needle}\n{$transcript}");
            }

            $transcript .= readAvailable($pipes[1], $pipes[2]);
            usleep(10000);
        }

        $resolvedInput = is_callable($input) ? $input() : $input;

        if (! is_string($resolvedInput) || fwrite($pipes[0], $resolvedInput) !== strlen($resolvedInput)) {
            terminate($process, $pipes);
            fail('Could not send a complete PTY interaction.');
        }

        fflush($pipes[0]);
    }

    fclose($pipes[0]);

    while (true) {
        $transcript .= readAvailable($pipes[1], $pipes[2]);
        $status = proc_get_status($process);

        if (! $status['running']) {
            $exit = $status['exitcode'];
            break;
        }

        if (microtime(true) >= $deadline) {
            terminate($process, $pipes);
            fail("PTY command timed out.\n{$transcript}");
        }

        usleep(10000);
    }

    $transcript .= stream_get_contents($pipes[1]);
    $transcript .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closed = proc_close($process);

    if ($exit === -1) {
        $exit = $closed;
    }

    return ['exit' => $exit, 'transcript' => str_replace("\r", '', $transcript)];
}

/** @return array{exit: int, output: string} */
function runNonTty(array $command, string $input = ''): array
{
    $process = proc_open(
        $command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        null,
        ['bypass_shell' => true],
    );

    if (! is_resource($process)) {
        fail('Could not start a non-TTY test process.');
    }

    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['exit' => proc_close($process), 'output' => $output];
}

function wrapperCommand(string $helper, string $target): string
{
    return 'PATH=/nonexistent '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($helper).' '.escapeshellarg($target)
        .'; status=$?; printf "ECHO_CHECK_READY:"; IFS= read -r visible; '
        .'printf "\\nECHO_CHECK_VALUE=%s\\n" "$visible"; exit "$status"';
}

function readAvailable($stdout, $stderr): string
{
    $read = [$stdout, $stderr];
    $write = null;
    $except = null;
    $selected = stream_select($read, $write, $except, 0, 100000);

    if ($selected === false || $selected === 0) {
        return '';
    }

    $output = '';

    foreach ($read as $stream) {
        $chunk = stream_get_contents($stream);

        if ($chunk !== false) {
            $output .= $chunk;
        }
    }

    return $output;
}

function verifyLaravelConfiguration(string $backend, array $values, string $appKey, string $smtpPassword): void
{
    foreach ($values as $name => $value) {
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv($name.'='.$value);
    }

    $app = require $backend.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $config = $app->make('config');

    assertSame($appKey, $config->get('app.key'), 'Laravel APP_KEY configuration');
    assertSame('AES-256-CBC', $config->get('app.cipher'), 'Laravel cipher configuration');
    assertSame($smtpPassword, $config->get('mail.mailers.smtp.password'), 'Laravel SMTP password configuration');
    assertSame('smtps', $config->get('mail.mailers.smtp.scheme'), 'Laravel SMTP scheme configuration');
    assertSame(false, $config->get('contact.enabled'), 'Laravel contact disabled configuration');
    assertSame('/private/storage', $config->get('filesystems.disks.local.root'), 'Laravel dynamic private storage path');
}

function verifyPathResolution(): void
{
    $jail = HostingPathResolver::resolve('/madebymadlen.de/app/backend', HostingPathResolver::NETCUP_SIBLING_PRIVATE);
    assertSame('/madebymadlen.de/private/storage', $jail['storage_root'] ?? null, 'SSH jail storage mapping');
    assertSame('/madebymadlen.de/app', $jail['repository_root'] ?? null, 'SSH jail repository mapping');

    $physical = HostingPathResolver::resolve(
        '/var/www/vhosts/hosting208697/madebymadlen.de/app/backend',
        HostingPathResolver::NETCUP_SIBLING_PRIVATE,
    );
    assertSame('/var/www/vhosts/hosting208697/madebymadlen.de/private/storage', $physical['storage_root'] ?? null, 'FPM physical storage mapping');
    assertSame('/var/www/vhosts/hosting208697/madebymadlen.de/app', $physical['repository_root'] ?? null, 'FPM physical repository mapping');
    assertSame([], HostingPathResolver::resolve('/workspace/backend', null), 'local layout remains explicit/default');
}

function requireExecutable(string $path): void
{
    if (! is_executable($path)) {
        fail("Required executable is missing: {$path}");
    }
}

function assertTrue(bool $condition, string $label): void
{
    if (! $condition) {
        fail("Assertion failed: {$label}");
    }
}

function assertSame(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        fail("Assertion failed: {$label}; expected ".var_export($expected, true).', got '.var_export($actual, true));
    }
}

function assertContains(string $needle, string $haystack, string $label): void
{
    assertTrue(str_contains($haystack, $needle), $label);
}

function assertNotContains(string $needle, string $haystack, string $label): void
{
    assertTrue(! str_contains($haystack, $needle), $label);
}

function terminate($process, array $pipes): void
{
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }

    proc_terminate($process);
    proc_close($process);
}

function removeTree(string $path): void
{
    if (! is_dir($path) || is_link($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}

function fail(string $message): never
{
    fwrite(STDERR, $message."\n");
    exit(1);
}
