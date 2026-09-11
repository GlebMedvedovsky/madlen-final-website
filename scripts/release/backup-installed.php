<?php
// Explicit operator-only private database backup; works before installing the BackupService fix.
declare(strict_types=1);
$backend=realpath($argv[1]??''); $directory=$argv[2]??'';
if (!$backend || basename($backend)!=='backend' || !str_starts_with($directory,'/') || file_exists($directory)) throw new RuntimeException('Existing backend and new absolute private backup directory required');
$parent=realpath(dirname($directory));
if (!$parent || str_starts_with($parent.'/',dirname($backend).'/')) throw new RuntimeException('Backup must be outside app/public');
require $backend.'/vendor/autoload.php';
try { $env=Dotenv\Dotenv::parse(file_get_contents($backend.'/.env')); }
catch(Throwable) { fwrite(STDERR,"Private configuration could not be parsed; inspect locally without sharing values.\n"); exit(1); }
if (($env['DB_CONNECTION']??'mysql')!=='mysql' || !empty($env['DB_URL'])) throw new RuntimeException('Review unsupported DB configuration locally');
umask(0077); if(!mkdir($directory,0700)) throw new RuntimeException('Cannot create backup directory');
$sql=$directory.'/database.sql'; $handle=fopen($sql,'xb');
if(!$handle) throw new RuntimeException('Cannot create SQL backup');
$process=new Symfony\Component\Process\Process(['/usr/bin/mysqldump','--single-transaction','--skip-comments','--no-tablespaces',
    '-h',$env['DB_HOST'],'-P',(string)($env['DB_PORT']??3306),'-u',$env['DB_USERNAME'],$env['DB_DATABASE']],env:['MYSQL_PWD'=>$env['DB_PASSWORD']??'']);
$process->setTimeout(300);
try {
    $process->run(function($type,$bytes) use($handle):void {if($type===Symfony\Component\Process\Process::OUT && fwrite($handle,$bytes)!==strlen($bytes))throw new RuntimeException('Incomplete SQL write');});
} finally {fclose($handle);}
if(!$process->isSuccessful() || filesize($sql)===0) throw new RuntimeException('Dump failed. Partial private file retained; do not use it for restoration. Inspect credentials/rights locally.');
file_put_contents($directory.'/database.sql.sha256',hash_file('sha256',$sql)."  database.sql\n");
$paths=App\Support\HostingPathResolver::resolve($backend,$env['MADLEN_INSTALL_LAYOUT']??null);
$storage=$env['MADLEN_PRIVATE_STORAGE_ROOT']??($paths['storage_root']??$backend.'/storage/app/private');
if(!is_dir($storage.'/media')) throw new RuntimeException('Confirm media storage root before continuing');
$tar=new Symfony\Component\Process\Process(['tar','-czf',$directory.'/media.tar.gz','-C',$storage,'media']);
$tar->setTimeout(300);$tar->mustRun();
if(!copy($backend.'/.env',$directory.'/backend.env.backup')) throw new RuntimeException('Private env backup failed');
chmod($directory.'/backend.env.backup',0600);
$sums='';foreach(['database.sql','media.tar.gz','backend.env.backup'] as $name) $sums.=hash_file('sha256',$directory.'/'.$name).'  '.$name."\n";
file_put_contents($directory.'/backup.sha256',$sums);
echo "Private SQL/media/env backups created and checksummed. No database or source data changed.\n";
