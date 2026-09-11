<?php
// Disposable local acceptance environment. Never load the real backend/.env.
if (getenv('MADLEN_DISPOSABLE_QA') !== '1' || PHP_OS_FAMILY === 'Windows') {
    throw new RuntimeException('Run only inside the disposable QA container.');
}
$root = '/tmp/madlen-release-qa';
foreach (['empty', 'storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'cache', 'incoming', 'static', 'preview/packages','preview/incoming','preview/results'] as $dir) {
    if (! is_dir($root.'/'.$dir)) mkdir($root.'/'.$dir, 0775, true);
}
foreach (['packages','incoming','results'] as $area) {
    file_put_contents($root.'/preview/'.$area.'/.madlen-preview-'.$area, 'madlen-preview-'.$area."-v1\n");
}
$environment = [
    'APP_ENV'=>'local', 'APP_DEBUG'=>'true', 'APP_URL'=>'http://127.0.0.1:8097', 'APP_LOCALE'=>'de',
    'APP_KEY'=>'base64:'.base64_encode(str_repeat('q', 32)), 'APP_TIMEZONE'=>'Europe/Berlin',
    'DB_CONNECTION'=>'sqlite', 'DB_DATABASE'=>$root.'/database.sqlite', 'DB_URL'=>'',
    'CACHE_STORE'=>'file', 'SESSION_DRIVER'=>'file', 'SESSION_SECURE_COOKIE'=>'false', 'SESSION_DOMAIN'=>'',
    'QUEUE_CONNECTION'=>'sync', 'MAIL_MAILER'=>'array', 'LOG_CHANNEL'=>'single',
    'APP_CONFIG_CACHE'=>$root.'/cache/config.php', 'APP_SERVICES_CACHE'=>$root.'/cache/services.php',
    'APP_PACKAGES_CACHE'=>$root.'/cache/packages.php', 'APP_ROUTES_CACHE'=>$root.'/cache/routes.php',
    'MADLEN_INSTALL_LAYOUT'=>'', 'MADLEN_REPOSITORY_ROOT'=>'/workspace', 'MADLEN_BASELINE_PATH'=>'/workspace/content/baseline.json',
    'MADLEN_RELEASE_ROOT'=>$root.'/releases', 'MADLEN_PUBLIC_URL'=>'http://127.0.0.1:8098',
    'MADLEN_PUBLIC_SITE_URL'=>'http://127.0.0.1:8098',
    'MADLEN_PRODUCTION_PUBLISHER'=>'github-actions', 'MADLEN_PRODUCTION_CONNECTED'=>'true',
    'MADLEN_PRODUCTION_SOURCE_REVISION'=>str_repeat('a',40), 'MADLEN_PUBLISHER_API_TOKEN'=>'qa-only-api',
    'MADLEN_PRODUCTION_PACKAGE_ROOT'=>$root.'/packages', 'MADLEN_PRODUCTION_INCOMING_ROOT'=>$root.'/incoming',
    'MADLEN_PRODUCTION_DESTINATION_ROOT'=>$root.'/static', 'MADLEN_GITHUB_REPOSITORY'=>'synthetic/madlen',
    'MADLEN_GITHUB_TOKEN'=>'qa-only', 'MADLEN_GITHUB_REF'=>'main', 'MADLEN_GITHUB_WORKFLOW'=>'madlen-production-publisher.yml',
    'MADLEN_PREVIEW_EXECUTION'=>'external', 'MADLEN_EXTERNAL_PREVIEW_CONNECTED'=>'true',
    'MADLEN_EXTERNAL_PREVIEW_DRIVER'=>'github-actions', 'MADLEN_PREVIEW_SOURCE_REVISION'=>str_repeat('a',40),
    'MADLEN_PREVIEW_API_TOKEN'=>'qa-only-preview', 'MADLEN_PREVIEW_GITHUB_REPOSITORY'=>'synthetic/madlen',
    'MADLEN_PREVIEW_GITHUB_TOKEN'=>'qa-only', 'MADLEN_PREVIEW_GITHUB_REF'=>'main',
    'MADLEN_PREVIEW_PACKAGE_ROOT'=>$root.'/preview/packages', 'MADLEN_PREVIEW_INCOMING_ROOT'=>$root.'/preview/incoming',
    'MADLEN_PREVIEW_RESULT_ROOT'=>$root.'/preview/results',
    'MADLEN_CONTACT_ENABLED'=>'true', 'MADLEN_CONTACT_MAILER'=>'array',
    'MADLEN_CONTACT_FROM_ADDRESS'=>'sender@example.test', 'MADLEN_CONTACT_RECIPIENT'=>'recipient@example.test',
    'MADLEN_CONTACT_ALLOWED_ORIGINS'=>'http://127.0.0.1:8098,http://127.0.0.1:8097',
];
foreach ($environment as $key=>$value) { putenv("$key=$value"); $_ENV[$key] = $_SERVER[$key] = $value; }
require '/workspace/backend/vendor/autoload.php';
$app = require '/workspace/backend/bootstrap/app.php';
$app->useEnvironmentPath($root.'/empty');
$app->useStoragePath($root.'/storage');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Http::preventStrayRequests();
Illuminate\Support\Facades\Http::fake(['https://api.github.com/*' => Illuminate\Support\Facades\Http::response(null, 204)]);
return $app;
