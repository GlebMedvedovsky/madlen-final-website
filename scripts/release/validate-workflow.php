<?php
require dirname(__DIR__,2).'/backend/vendor/autoload.php';
$workflow=Symfony\Component\Yaml\Yaml::parseFile(dirname(__DIR__,2).'/.github/workflows/madlen-production-publisher.yml');
if(!isset($workflow['on']['workflow_dispatch'],$workflow['jobs'])) throw new RuntimeException('Workflow schema missing');
$count=0;
foreach($workflow['jobs'] as $job)foreach($job['steps'] as $step)if(isset($step['run'])){
    $process=new Symfony\Component\Process\Process(['bash','-n']);$process->setInput($step['run']);$process->mustRun();$count++;
}
echo "PASS: Symfony YAML parser and bash -n for $count shell blocks; workflow not dispatched.\n";
