<?php
$app = require __DIR__.'/qa-bootstrap.php';
$pdo = new PDO('mysql:host=madlen-release-qa-db;dbname=qa_source;charset=utf8mb4', 'root', 'qa-db-only', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE IF NOT EXISTS qa_restore_test');
$pdo->exec('CREATE TABLE IF NOT EXISTS release_sentinel (id INT PRIMARY KEY, body TEXT)');
$pdo->exec("REPLACE INTO release_sentinel VALUES (1, 'Prüfung – фотографии DE/EN')");
config([
    'database.connections.mysql.host'=>'madlen-release-qa-db', 'database.connections.mysql.port'=>3306,
    'database.connections.mysql.database'=>'qa_source', 'database.connections.mysql.username'=>'root',
    'database.connections.mysql.password'=>'qa-db-only', 'madlen.backup.dump_binary'=>'/usr/bin/mysqldump',
    'madlen.backup.client_binary'=>'/usr/bin/mysql',
]);
foreach (['MADLEN_RESTORE_DB_HOST'=>'madlen-release-qa-db','MADLEN_RESTORE_DB_DATABASE'=>'qa_restore_test',
    'MADLEN_RESTORE_DB_USERNAME'=>'root','MADLEN_RESTORE_DB_PASSWORD'=>'qa-db-only'] as $key=>$value) {
    putenv("$key=$value"); $_ENV[$key]=$_SERVER[$key]=$value;
}
Illuminate\Support\Facades\Storage::disk('local')->put('media/qa-sentinel.txt','synthetic media, no user files');
$service = app(App\Services\BackupService::class);
$backup = $service->create();
$target = $service->restoreIntoTest($backup);
$restored = new PDO('mysql:host=madlen-release-qa-db;dbname=qa_restore_test;charset=utf8mb4','root','qa-db-only');
if ($pdo->query('SELECT body FROM release_sentinel')->fetchColumn() !== $restored->query('SELECT body FROM release_sentinel')->fetchColumn()) throw new RuntimeException('Database round trip mismatch');
if (file_get_contents($target.'/restored-media/media/qa-sentinel.txt') !== 'synthetic media, no user files') throw new RuntimeException('Media round trip mismatch');
echo json_encode(['result'=>'PASS', 'database'=>'isolated MySQL 8.4', 'dump'=>trim(shell_exec('/usr/bin/mysqldump --version')), 'backupSha256'=>$backup->checksum, 'restored'=>'SQL UTF-8 row and media sentinel'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
