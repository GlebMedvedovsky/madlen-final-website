<?php
$app = require __DIR__.'/qa-bootstrap.php';
$mode = $argv[1] ?? '';
if ($mode === 'seed') {
    if (file_exists('/tmp/madlen-release-qa/database.sqlite')) throw new RuntimeException('QA database already exists.');
    touch('/tmp/madlen-release-qa/database.sqlite');
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force'=>true]);
    Illuminate\Support\Facades\Artisan::call('madlen:import');
    App\Models\User::create(['name'=>'Release QA', 'email'=>'qa@example.test', 'password'=>bcrypt('Local-QA-only-928!')]);
    file_put_contents('/tmp/madlen-release-qa/static/.madlen-publisher-root', "madlen-production-root-v1\n");
    file_put_contents('/tmp/madlen-release-qa/incoming/.madlen-publisher-incoming', "madlen-production-incoming-v1\n");
    echo "Synthetic database seeded.\n";
} elseif ($mode === 'state') {
    echo json_encode([
        'projects'=>App\Models\Project::withTrashed()->where('slug','like','qa-%')->with('mediaItems')->get(),
        'previews'=>App\Models\PreviewBuild::all(), 'publications'=>App\Models\ProductionPublication::all(),
        'media'=>App\Models\MediaAsset::where('source_managed',false)->get(),
    ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} elseif ($mode === 'build-preview' || $mode === 'build-production') {
    $preview = $mode === 'build-preview';
    $job = $preview ? App\Models\PreviewBuild::findOrFail($argv[2]) : App\Models\ProductionPublication::findOrFail($argv[2]);
    $payload = $preview ? dirname(app(App\Services\ExternalPreviewStorage::class)->manifestPath($job->id)) : dirname($job->manifest_path);
    $output = '/tmp/madlen-release-qa/build-'.$job->id;
    $source = '/tmp/madlen-release-qa/build-source';
    if (! is_dir($source)) {
        mkdir($source,0755);
        foreach (['src','public','content','scripts'] as $dir) Illuminate\Support\Facades\File::copyDirectory('/workspace/'.$dir,$source.'/'.$dir);
        foreach (['package.json','package-lock.json','tsconfig.json'] as $file) copy('/workspace/'.$file,$source.'/'.$file);
        // Only this disposable copy receives a writable Vite cache path; repo is read-only.
        file_put_contents($source.'/astro.config.mjs',str_replace('compressHTML: true,', 'compressHTML: true, vite: { cacheDir: "/tmp/madlen-release-qa/vite-cache" },',file_get_contents('/workspace/astro.config.mjs')));
        symlink('/workspace/node_modules',$source.'/node_modules');
    }
    Illuminate\Support\Facades\File::copyDirectory('/workspace/src',$source.'/src');
    $process = new Symfony\Component\Process\Process(['node', $source.'/scripts/'.($preview ? 'preview/build-preview.mjs' : 'production/build-publication.mjs'), $payload, $output], $source, [
        'ASTRO_TELEMETRY_DISABLED'=>'1', 'MADLEN_CONTACT_ENDPOINT'=>'http://127.0.0.1:8097/api/contact',
    ]);
    $process->setTimeout(180);
    $process->mustRun();
    $archive = $preview ? app(App\Services\ExternalPreviewStorage::class)->resultArchive($job->id) : '/tmp/madlen-release-qa/incoming/'.$job->id.'.zip';
    Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($archive));
    $zip = new ZipArchive; $zip->open($archive, ZipArchive::CREATE|ZipArchive::EXCL);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($output, FilesystemIterator::SKIP_DOTS)) as $entry) {
        if ($entry->isFile()) $zip->addFile($entry->getPathname(), substr($entry->getPathname(), strlen($output)+1));
    }
    $zip->close();
    echo json_encode(['archive'=>basename($archive), 'sha256'=>hash_file('sha256',$archive)]);
} elseif ($mode === 'normal-frontend') {
    $source = '/tmp/madlen-release-qa/build-source';
    if (! is_dir($source)) throw new RuntimeException('Run the package build fixture first.');
    Illuminate\Support\Facades\File::copyDirectory('/workspace/src', $source.'/src');
    $process = new Symfony\Component\Process\Process(['npm', 'run', 'build'], $source, [
        'ASTRO_TELEMETRY_DISABLED'=>'1', 'MADLEN_BASE_PATH'=>'/',
        'MADLEN_CONTENT_RELEASE'=>false, 'MADLEN_OUT_DIR'=>'/tmp/madlen-release-qa/normal-final',
        'MADLEN_CONTACT_ENDPOINT'=>'http://127.0.0.1:8097/api/contact',
    ]);
    $process->setTimeout(180); $process->mustRun(); echo $process->getOutput();
    file_put_contents('/tmp/madlen-release-qa/serve-normal-site', 'normal frontend QA only');
} elseif ($mode === 'artisan') {
    $args = array_slice($argv, 2);
    exit($app->make(Illuminate\Contracts\Console\Kernel::class)->handle(new Symfony\Component\Console\Input\ArgvInput(['artisan', ...$args])));
} else { throw new RuntimeException('Unknown QA operation'); }
