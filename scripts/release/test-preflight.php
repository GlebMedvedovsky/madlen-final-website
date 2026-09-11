<?php
// Local disposable fixture: no installed env/DB, real Composer, Netcup or network.
declare(strict_types=1);
require __DIR__.'/../../backend/vendor/autoload.php';
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
$files = new Filesystem;
$root = sys_get_temp_dir().'/madlen-preflight-test-'.bin2hex(random_bytes(8));
$check = static function (bool $condition, string $message): void { if (! $condition) throw new RuntimeException($message); };
$backend = $root.'/site/app/backend';
$private = $root.'/site/private';
foreach ([$backend,$private,$root.'/bin',$root.'/empty-bin'] as $path) $files->makeDirectory($path,0755,true);
$phar = $private.'/composer.phar';
$marker = $private.'/executed';
$files->put($phar, '<?php if (PHP_VERSION_ID < 80400 || !in_array("--no-plugins", $argv) || !in_array("--no-scripts", $argv)) exit(2); file_put_contents(__DIR__."/executed", "synthetic"); echo "Composer version 2.8.0 (synthetic fixture)\\n";');
chmod($phar,0600);
$sha = hash_file('sha256',$phar);
$preflightPath = $argv[1] ?? __DIR__.'/preflight.php';
$requiredTools = ['tar','gzip','sha256sum','date','mkdir','chmod','id','ls'];
foreach ($requiredTools as $name) {
    $lookup = new Process(['/bin/sh','-c','command -v "$1"','lookup',$name]); $lookup->mustRun();
    symlink(trim($lookup->getOutput()),$root.'/bin/'.$name);
}
$run = static function (string $candidate, string $checksum, string $path) use ($backend, $preflightPath): Process {
    $process=new Process([PHP_BINARY,$preflightPath,'--tools',$backend,$candidate,$checksum],env:['PATH'=>$path]);
    $process->setTimeout(30); $process->run(); return $process;
};
try {
    $pass = $run($phar,$sha,$root.'/bin');
    $check($pass->isSuccessful(),$pass->getErrorOutput());
    $report=json_decode($pass->getOutput(),true,flags:JSON_THROW_ON_ERROR);
    $check(isset($report['tools']['composer_version']), 'Composer was not checked');
    foreach (['cmp','stat','composer'] as $name) {
        $lookup = new Process(['/bin/sh','-c','command -v "$1"','lookup',$name],env:['PATH'=>$root.'/bin']);
        $lookup->run();
        $check(!$lookup->isSuccessful(), 'Unexpected PATH tool: '.$name);
        $check(!isset($report['tools'][$name]), 'Unavailable tool still required: '.$name);
    }
    $check(file_exists($marker),'Verified PHAR was not executed');
    unlink($marker);
    foreach ([[$phar,str_repeat('0',64),$root.'/bin'],[$private.'/missing.phar',$sha,$root.'/bin'],[$phar,$sha,$root.'/empty-bin']] as $case) {
        $failed=$run(...$case);
        $check(!$failed->isSuccessful() && str_contains($failed->getErrorOutput(),'STOP before changing code'), 'Missing/checksum tool gate failed');
        $check(!file_exists($marker),'Unverified PHAR executed');
    }
    foreach ($requiredTools as $name) {
        $target=readlink($root.'/bin/'.$name);
        unlink($root.'/bin/'.$name);
        try {
            $failed=$run($phar,$sha,$root.'/bin');
            $check(!$failed->isSuccessful(), 'Missing tool was accepted: '.$name);
            $check(str_contains($failed->getErrorOutput(),'Required tool failed: '.$name), 'Missing tool diagnostic must name '.$name);
            $check(preg_match('/Required tool failed: sh(?:\r?\n|$)/', $failed->getErrorOutput()) === 0, 'Shell name hides missing tool');
            $check(!file_exists($marker),'PHAR executed before tool checks completed');
        } finally { symlink($target,$root.'/bin/'.$name); }
    }
    $files->copy($phar,$backend.'/public-composer.phar');
    $check(!$run($backend.'/public-composer.phar',$sha,$root.'/bin')->isSuccessful(),'Public PHAR allowed');
    symlink($phar,$private.'/linked.phar');
    $check(!$run($private.'/linked.phar',$sha,$root.'/bin')->isSuccessful(),'Symlink PHAR allowed');
    chmod($phar,0666);
    $check(!$run($phar,$sha,$root.'/bin')->isSuccessful(),'Writable PHAR allowed');
    $check(!file_exists($marker),'Unsafe PHAR executed');

    $doc=file_get_contents(__DIR__.'/../../docs/NETCUP_RELEASE_RU.md');
    preg_match_all('/```bash\n(.*?)```/s',str_replace("\r\n","\n",$doc),$matches);
    foreach ($matches[1] as $block) {
        $syntax=new Process(['bash','-n']); $syntax->setInput($block); $syntax->mustRun();
        $check(!str_contains($block,'stat -') && !str_contains($block,'cmp -') && !str_contains($block,'command -v composer'),'Unavailable tool in runbook');
        $check(!str_contains($block,'artisan config:cache'),'Forbidden config caching');
        if (str_contains($block,'overlay.php apply') || str_contains($block,'overlay.php" rollback')) {
            $check(strpos($block,'--tools') < strpos($block,'overlay.php'), 'Tool gate must precede any overlay mutation');
            $check(str_contains($block,'"$COMPOSER_PHAR" dump-autoload'),'Private Composer required');
        }
    }
    echo 'Preflight: passed without cmp/stat/PATH Composer; 6 security rejection cases and '.count($requiredTools).' named missing-tool cases; '.count($matches[1])." runbook bash blocks parsed. Synthetic PHAR, not Netcup.\n";
} finally {
    $files->deleteDirectory($root);
}
