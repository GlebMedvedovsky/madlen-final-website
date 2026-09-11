<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/backend/vendor/autoload.php';
use Symfony\Component\Process\Process;
function run(array $args, bool $success=true): string {
    $p=new Process($args); $p->setTimeout(60); $p->run();
    if($p->isSuccessful()!==$success)throw new RuntimeException($p->getOutput().$p->getErrorOutput());
    return $p->getOutput();
}
function check(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$kit=realpath($argv[1]??'');if(!$kit)throw new RuntimeException('Supply extracted kit path');
$manifest=json_decode(file_get_contents($kit.'/manifest.json'),true,flags:JSON_THROW_ON_ERROR);
$repo=dirname(__DIR__,2);$root=sys_get_temp_dir().'/madlen-kit-test-'.bin2hex(random_bytes(6));
$backend=$root.'/backend';mkdir($backend,0755,true);
foreach($manifest['files'] as $file=>$sums){
    if($sums['before']===null)continue;
    $bytes=run(['git','-c','safe.directory='.$repo,'-C',$repo,'show',$manifest['referenceRevision'].':backend/'.$file]);
    check(hash('sha256',$bytes)===$sums['before'],'Reference checksum mismatch');
    if(!is_dir(dirname($backend.'/'.$file)))mkdir(dirname($backend.'/'.$file),0755,true);
    file_put_contents($backend.'/'.$file,$bytes);
}
mkdir($backend.'/storage/media',0755,true);
file_put_contents($backend.'/.env','SYNTHETIC_SECRET=must-not-change');
file_put_contents($backend.'/storage/media/sentinel','must-not-change');
$cmd=[PHP_BINARY,$kit.'/overlay.php'];
$inspect=function()use($cmd,$backend,$root):void{file_put_contents($root.'/observed.json',run([...$cmd,'inspect',$backend]));};
$inspect();run([...$cmd,'check',$backend,$root.'/backup',$root.'/observed.json']);
$sample='app/Models/Project.php';$old=file_get_contents($backend.'/'.$sample);
file_put_contents($backend.'/'.$sample,$old."\n// unreviewed edit");
run([...$cmd,'check',$backend,$root.'/backup',$root.'/observed.json'],false);
$inspect();run([...$cmd,'apply',$backend,$root.'/backup',$root.'/observed.json'],false);
check(!file_exists($root.'/backup'),'Unexpected backup/write on source mismatch');
file_put_contents($backend.'/'.$sample,$old);
unlink($backend.'/'.$sample);symlink($backend.'/.env',$backend.'/'.$sample);
run([...$cmd,'inspect',$backend],false);unlink($backend.'/'.$sample);file_put_contents($backend.'/'.$sample,$old);
$inspect();run([...$cmd,'apply',$backend,$root.'/backup',$root.'/observed.json']);
foreach($manifest['files'] as $file=>$sums)check(hash_file('sha256',$backend.'/'.$file)===$sums['after'],'Installed checksum mismatch');
$inspect();check(str_contains(run([...$cmd,'apply',$backend,$root.'/backup',$root.'/observed.json']),'Already installed'),'Idempotence failed');
$new=file_get_contents($backend.'/'.$sample);file_put_contents($backend.'/'.$sample,$new."\n// later edit");
run([...$cmd,'rollback',$backend,$root.'/backup'],false);file_put_contents($backend.'/'.$sample,$new);
run([...$cmd,'rollback',$backend,$root.'/backup']);
foreach($manifest['files'] as $file=>$sums){
    if(str_starts_with($file,'database/migrations/')){check(is_file($backend.'/'.$file),'Migration must be retained');continue;}
    check($sums['before']===null ? !file_exists($backend.'/'.$file) : hash_file('sha256',$backend.'/'.$file)===$sums['before'],'Rollback mismatch');
}
check(file_get_contents($backend.'/.env')==='SYNTHETIC_SECRET=must-not-change','Env changed');
check(file_get_contents($backend.'/storage/media/sentinel')==='must-not-change','Media changed');
$inspect();run([...$cmd,'check',$backend,$root.'/reapply',$root.'/observed.json']);
run([...$cmd,'apply',$backend,$root.'/reapply',$root.'/observed.json']);
run([...$cmd,'rollback',$backend,$root.'/reapply']);
foreach($manifest['files'] as $file=>$sums) {
    if (str_ends_with($file,'.php')) run([PHP_BINARY,'-l',$kit.'/payload/backend/'.$file]);
    else json_decode(file_get_contents($kit.'/payload/backend/'.$file),true,flags:JSON_THROW_ON_ERROR);
}
echo "PASS: source checksum guard; stale report; symlink refusal; apply; per-file checksum; idempotence; edited-file rollback refusal; code rollback; additive migration retained; reapply after rollback; env/media untouched; all payload PHP syntax.\n";
echo "Disposable fixture retained at $root\n";
