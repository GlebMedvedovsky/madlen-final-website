<?php
$app=require __DIR__.'/qa-bootstrap.php';
$pdo=new PDO('mysql:host=madlen-release-qa-db;dbname=qa_source','root','qa-db-only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='qa_migrations_'.bin2hex(random_bytes(4));
$pdo->exec('CREATE DATABASE '.$name);
config(['database.default'=>'mysql','database.connections.mysql.host'=>'madlen-release-qa-db',
    'database.connections.mysql.port'=>3306,'database.connections.mysql.database'=>$name,
    'database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'qa-db-only']);
Illuminate\Support\Facades\DB::purge('mysql');
$migration='database/migrations/2026_09_12_000006_add_production_operation_identity.php';
$existing=array_values(array_filter(glob('/workspace/backend/database/migrations/*.php'),fn($path)=>basename($path)!==basename($migration)));
Illuminate\Support\Facades\Artisan::call('migrate',['--path'=>$existing,'--realpath'=>true,'--force'=>true]);
$code=Illuminate\Support\Facades\Artisan::call('migrate',['--path'=>$migration,'--force'=>true]);
if($code!==0)throw new RuntimeException(Illuminate\Support\Facades\Artisan::output());
foreach(['request_id','project_id','operation','runner_id','previous_project_state'] as $column)if(!Illuminate\Support\Facades\Schema::hasColumn('production_publications',$column))throw new RuntimeException('Missing column');
$request='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
App\Models\ProductionPublication::create(['sequence'=>1,'status'=>'failed','source_revision'=>str_repeat('a',40),'request_id'=>$request]);
try {App\Models\ProductionPublication::create(['sequence'=>2,'status'=>'failed','source_revision'=>str_repeat('a',40),'request_id'=>$request]);throw new RuntimeException('Unique index failed');}
catch(Illuminate\Database\UniqueConstraintViolationException){}
$instance=require '/workspace/backend/'.$migration;
$instance->down();
if(Illuminate\Support\Facades\Schema::hasColumn('production_publications','request_id'))throw new RuntimeException('Down failed');
$instance->up();
if(!Illuminate\Support\Facades\Schema::hasColumn('production_publications','request_id'))throw new RuntimeException('Reapply failed');
echo "PASS: isolated MySQL 8.4 migration from installed schema, all five columns, unique request_id, down/up; no existing database touched. Fixture: $name\n";
