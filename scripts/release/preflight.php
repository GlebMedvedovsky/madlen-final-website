<?php
// Read-only. No Laravel bootstrap, artisan, tinker, caches, dumps or network requests except SELECTs to its DB.
declare(strict_types=1);
$args = array_slice($argv, 1);
$toolsOnly = ($args[0] ?? '') === '--tools';
if ($toolsOnly) array_shift($args);
$backend = realpath($args[0] ?? '/madebymadlen.de/app/backend');
if (! $backend || basename($backend)!=='backend') { fwrite(STDERR,"Invalid backend\n"); exit(1); }
// This gate runs BEFORE reading env or bootstrapping the installed application.
// --tools is also used immediately before apply/rollback. No PATH Composer and
// no executable `stat` dependency; the trusted PHAR checksum comes from the operator.
try {
    if (PHP_VERSION_ID < 80400 || PHP_VERSION_ID >= 80500) throw new RuntimeException('PHP 8.4 is required');
    foreach (['proc_open', 'symlink', 'hash_file', 'fileperms'] as $function) {
        if (! function_exists($function)) throw new RuntimeException('Required PHP function: '.$function);
    }
    foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'zip', 'gd', 'intl', 'Phar'] as $extension) {
        if (! extension_loaded($extension)) throw new RuntimeException('Required PHP extension: '.$extension);
    }
    $run = static function (array $command): string {
        $process = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (! is_resource($process)) throw new RuntimeException('Cannot check required tool');
        fclose($pipes[0]); $output=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process)!==0) throw new RuntimeException('Required tool failed: '.basename($command[0]));
        return trim($output);
    };
    $tools = [];
    foreach (['tar','gzip','sha256sum','date','mkdir','chmod','cmp','id','ls'] as $name) {
        $binary=$run(['/bin/sh','-c','command -v "$1"','madlen-preflight',$name]);
        if (! is_executable($binary)) throw new RuntimeException('Missing tool: '.$name);
        $tools[$name]=$binary;
    }
    foreach (['/usr/bin/mysqldump','/usr/bin/mysql'] as $binary) {
        if (! is_executable($binary)) throw new RuntimeException('Missing tool: '.$binary);
        $tools[basename($binary)]=$run([$binary,'--version']);
    }
    $phar = $args[1] ?? '';
    $expected = strtolower($args[2] ?? '');
    $private = realpath(dirname($backend, 2).'/private');
    $resolved = realpath($phar);
    if (! $private || ! $resolved || ! str_starts_with($resolved, $private.DIRECTORY_SEPARATOR)
        || is_link($phar) || ! is_file($resolved) || ! is_readable($resolved)
        || (fileperms($resolved) & 0022) !== 0) {
        throw new RuntimeException('A readable, non-symlink, non-group/world-writable private composer.phar is required');
    }
    if (! preg_match('/\A[0-9a-f]{64}\z/', $expected) || ! hash_equals($expected, hash_file('sha256', $resolved))) {
        throw new RuntimeException('Private Composer SHA-256 does not match the previously verified checksum');
    }
    $tools['composer_phar']=$resolved;
    $tools['composer_sha256']=$expected;
    $tools['composer_version']=$run([PHP_BINARY,$resolved,'--version','--no-ansi','--no-plugins','--no-scripts']);
    if (! str_starts_with($tools['composer_version'], 'Composer version 2.')) throw new RuntimeException('Composer 2 is required');
} catch (Throwable $error) {
    fwrite(STDERR, 'STOP before changing code: '.$error->getMessage()."\n"); exit(1);
}
if ($toolsOnly) { echo json_encode(['tools'=>$tools],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n"; exit(0); }
require $backend.'/vendor/autoload.php';
try { $env = Dotenv\Dotenv::parse(file_get_contents($backend.'/.env')); }
catch(Throwable) { fwrite(STDERR,"Private configuration could not be parsed. Inspect locally; values are not printed.\n"); exit(1); }
$paths = App\Support\HostingPathResolver::resolve($backend, $env['MADLEN_INSTALL_LAYOUT']??null);
$report = ['php'=>PHP_VERSION,'sapi'=>PHP_SAPI,'php_binary'=>PHP_BINARY,'backend'=>$backend,
    'config_cache_exists'=>is_file($backend.'/bootstrap/cache/config.php'),
    'route_cache_files'=>array_map('basename',glob($backend.'/bootstrap/cache/routes*.php')),
    'extensions'=>array_combine(['pdo_mysql','mbstring','openssl','fileinfo','zip','gd','intl'],array_map('extension_loaded',['pdo_mysql','mbstring','openssl','fileinfo','zip','gd','intl'])),
    'proc_open'=>function_exists('proc_open'),'symlink'=>function_exists('symlink'),
    'dump_executable'=>is_executable('/usr/bin/mysqldump'),'mysql_executable'=>is_executable('/usr/bin/mysql'),
    'tools'=>$tools,
    'cli_limits'=>array_combine(['memory_limit','max_execution_time','upload_max_filesize','post_max_size','max_file_uploads'],
        array_map('ini_get',['memory_limit','max_execution_time','upload_max_filesize','post_max_size','max_file_uploads'])),
    'resolved_layout_paths'=>$paths,
    'media_disk_root'=>$env['MADLEN_PRIVATE_STORAGE_ROOT']??($paths['storage_root']??$backend.'/storage/app/private'),
];
foreach (['MADLEN_PRODUCTION_PUBLISHER','MADLEN_PRODUCTION_CONNECTED','MADLEN_PRODUCTION_DESTINATION_ROOT',
    'MADLEN_PRODUCTION_SOURCE_REVISION','MADLEN_PREVIEW_SOURCE_REVISION','MADLEN_GITHUB_WORKFLOW','MADLEN_GITHUB_REPOSITORY',
    'MADLEN_GITHUB_REF','MADLEN_PUBLIC_SITE_URL','MADLEN_CONTACT_ENABLED','MAIL_MAILER','MAIL_SCHEME','MAIL_PORT','MAIL_ENCRYPTION'] as $key) $report['settings'][$key]=$env[$key]??'NOT SET';
foreach (['APP_KEY','MADLEN_GITHUB_TOKEN','MADLEN_PUBLISHER_API_TOKEN','MAIL_PASSWORD'] as $key) $report['present_only'][$key]=!empty($env[$key]);
foreach (['/madebymadlen.de/releases/static','/madebymadlen.de/private','/madebymadlen.de/private/packages/production','/madebymadlen.de/private/incoming/production',$backend.'/storage',$backend.'/bootstrap/cache'] as $path) {
    $report['paths'][$path]=['exists'=>file_exists($path),'writable'=>is_writable($path),'realpath'=>realpath($path)?:null,
        'mode'=>file_exists($path)?sprintf('%04o',fileperms($path)&07777):null,
        'owner_uid'=>file_exists($path)?fileowner($path):null,'group_gid'=>file_exists($path)?filegroup($path):null,
        'free_bytes'=>is_dir($path)?disk_free_space($path):null];
}
$current='/madebymadlen.de/releases/static/current';
$report['current']=['is_link'=>is_link($current),'target'=>is_link($current)?readlink($current):null];
foreach (['App\\Services\\ProductionReleaseManager','App\\Console\\Commands\\RetryProductionPublication',
    'App\\Data\\ProjectPreviewSnapshot','App\\Services\\ProjectPreviewSnapshotFactory','App\\Http\\Controllers\\AdminSessionController'] as $class) $report['autoload'][$class]=class_exists($class);
try {
    if (($env['DB_CONNECTION']??'mysql')!=='mysql' || !empty($env['DB_URL'])) throw new RuntimeException('Unsupported preflight DB configuration');
    $pdo=new PDO('mysql:host='.($env['DB_HOST']??'127.0.0.1').';port='.($env['DB_PORT']??3306).';dbname='.$env['DB_DATABASE'].';charset=utf8mb4', $env['DB_USERNAME'],$env['DB_PASSWORD']??'', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $report['database']['connected']=true;
    $report['database']['migration_names']=$pdo->query('SELECT migration FROM migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN);
    $report['database']['preview_columns']=$pdo->query('SHOW COLUMNS FROM preview_builds')->fetchAll(PDO::FETCH_COLUMN);
    $report['database']['publication_columns']=$pdo->query('SHOW COLUMNS FROM production_publications')->fetchAll(PDO::FETCH_COLUMN);
    $report['database']['publication_statuses']=$pdo->query('SELECT status, COUNT(*) AS count FROM production_publications GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $error) { $report['database']['check']='FAILED; inspect connection locally, do not send credentials'; }
$report['not_verified']=['FastCGI paths and user','WCP document root / symlink serving','SSH/SFTP from GitHub','SMTP delivery','actual privileges for ALTER/index and mysqldump'];
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
