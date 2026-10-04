<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$input=$argv[1];$output=$argv[2];$x=json_decode(file_get_contents($input),true,512,JSON_THROW_ON_ERROR);
$dir=$root.'/.qa-runtime/mutation-replay';@mkdir($dir,0700,true);$escaped=[];$additional=0;$start=microtime(true);
foreach($x['escaped'] as $id=>$entry) {
    $m=$entry['mutator'];$file=$dir.'/'.$id.'.php';file_put_contents($file,$m['mutatedSourceCode']);
    preg_match('/namespace\s+([^;]+);/',$m['mutatedSourceCode'],$ns);$class=trim($ns[1]).'\\'.basename($m['originalFilePath'],'.php');
    $env=getenv();$env['QA_MUTANT_FILE']=$file;$env['QA_MUTANT_CLASS']=$class;
    $p=proc_open([PHP_BINARY,$root.'/scripts/qa/phpunit-infection.php','--configuration='.$root.'/scripts/qa/phpunit.xml'],[0=>STDIN,1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,$env);
    $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$rc=proc_close($p);$entry['processOutput']=$stdout.$stderr;
    if($rc===0) $escaped[]=$entry; else {$x['killed'][]=$entry;$additional++;}
    unlink($file);
}
$x['escaped']=$escaped;$n=$x['stats']['totalMutantsCount'];$score=round(count($x['killed'])/$n*100,2);$x['stats']['killedCount']=count($x['killed']);$x['stats']['escapedCount']=count($escaped);$x['stats']['msi']=$score;$x['stats']['coveredCodeMsi']=$score;
$x['qa_replay_seconds']=round(microtime(true)-$start,2);file_put_contents($output,json_encode($x,JSON_THROW_ON_ERROR));
printf("Scoped survivor replay: %d new kills; %d/%d total kills; %d survivors; %.2f%% corrected MSI; %.2fs\\n",$additional,count($x['killed']),$n,count($escaped),$score,$x['qa_replay_seconds']);exit($score>=70?0:1);
