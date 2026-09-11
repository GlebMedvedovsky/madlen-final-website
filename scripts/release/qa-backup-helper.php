<?php
$app=require __DIR__.'/qa-bootstrap.php';
$root='/tmp/madlen-backup-helper-'.bin2hex(random_bytes(5));
$backend=$root.'/app/backend';mkdir($backend,0755,true);mkdir($root.'/private',0700);
symlink('/workspace/backend/vendor',$backend.'/vendor');
$storage='/tmp/madlen-release-qa/storage/app/private';
file_put_contents($backend.'/.env',"DB_CONNECTION=mysql\nDB_HOST=madlen-release-qa-db\nDB_PORT=3306\nDB_DATABASE=qa_source\nDB_USERNAME=root\nDB_PASSWORD=qa-db-only\nMADLEN_PRIVATE_STORAGE_ROOT=$storage\n");
$backup=$root.'/private/backup';
$process=new Symfony\Component\Process\Process([PHP_BINARY,__DIR__.'/backup-installed.php',$backend,$backup]);
$process->mustRun();
foreach(explode("\n",trim(file_get_contents($backup.'/backup.sha256')))as $line){[$sha,$file]=explode('  ',$line);if(hash_file('sha256',$backup.'/'.$file)!==$sha)throw new RuntimeException('Backup checksum mismatch');}
if(file_get_contents($backup.'/backend.env.backup')!==file_get_contents($backend.'/.env'))throw new RuntimeException('Private env backup differs');
$preflight=new Symfony\Component\Process\Process([PHP_BINARY,__DIR__.'/preflight.php',$backend]);
$preflight->mustRun();$report=json_decode($preflight->getOutput(),true,flags:JSON_THROW_ON_ERROR);
if(str_contains($preflight->getOutput(),'qa-db-only') || ($report['database']['connected']??false)!==true)throw new RuntimeException('Read-only preflight leaked a credential or could not connect to the synthetic database');
echo "PASS: install-time helper created private SQL/media/env backup with matching checksums; read-only preflight connected without exposing credentials; disposable fixture only.\n";
