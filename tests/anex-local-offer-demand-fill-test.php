<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/ops/anex_local_offer_demand_fill.php';
function ck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
function reject(callable $fn,string $label):void{try{$fn();}catch(InvalidArgumentException){return;}throw new RuntimeException($label);}
$scope=['departureId'=>1,'countryId'=>4,'regionId'=>20,'dateFrom'=>'2026-09-22','dateTo'=>'2026-09-22','nights'=>7,'adults'=>2,'childAges'=>[],
    'searches'=>107,'observations'=>562,'lastSeen'=>'2026-09-19 20:16:12'];
$cmd=AnyTourAnexDemandFillV1::collectorCommand($scope,'/tmp/collector.php',123,600,600);
ck($cmd[0]===PHP_BINARY&&$cmd[1]==='/tmp/collector.php','binary');
ck(in_array('--departure=1',$cmd,true)&&in_array('--country=4',$cmd,true)&&in_array('--region=20',$cmd,true),'identity');
ck(in_array('--date-from=2026-09-22',$cmd,true)&&in_array('--nights=7',$cmd,true)&&in_array('--generation=123',$cmd,true),'context');
ck(!array_filter($cmd,static fn($x)=>str_starts_with($x,'--child-ages=')),'no-children');
$family=array_replace($scope,['regionId'=>null,'childAges'=>[7,3],'searches'=>2]);
$familyCmd=AnyTourAnexDemandFillV1::collectorCommand($family,'/tmp/collector.php',124);
ck(!in_array('--region=20',$familyCmd,true)&&in_array('--child-ages=3,7',$familyCmd,true),'children-sorted');
reject(fn()=>AnyTourAnexDemandFillV1::collectorCommand(array_replace($scope,['dateTo'=>'2026-09-23']),'/tmp/c.php',1),'date-mismatch');
$summary=AnyTourAnexDemandFillV1::summarize($scope,['grouped_candidates'=>4,'expand_calls'=>4,'charter_concrete_candidates'=>24,
 'apd_batch_items'=>24,'final_price_ready_offers'=>24,'retryable_offers'=>0,'discovered_set_drained'=>true,
 'search_client_instances'=>5,'apd_client_instances'=>0,'snapshot_finalize'=>['published'=>true]]);
ck($summary['finalPriceReadyOffers']===24&&$summary['discoveredSetDrained']===true&&$summary['scope']['regionId']===20,'summary');
// Exercise the unchanged CLI entrypoint, not a second implementation of its runner.
// All queue/collector programs below are local fixtures: no supplier, DB or credentials.
function cliFixture(array $scopes,string $queueBody,string $collectorBody,int $limit=3):array
{
    $root=sys_get_temp_dir().'/anex demand worker '.bin2hex(random_bytes(8));
    $ops=$root.'/scripts/ops';$process=null;
    ck(mkdir($ops,0700,true)&&mkdir($root.'/app/integrations',0700,true),'fixture directories');
    try{
        ck(copy(__DIR__.'/../scripts/ops/anex_local_offer_demand_fill.php',$ops.'/anex_local_offer_demand_fill.php'),'copy real worker');
        ck(copy(__DIR__.'/../app/integrations/anex-local-offer-demand.php',$root.'/app/integrations/anex-local-offer-demand.php'),'copy real normalizer');
        $queue=json_encode(['source'=>'offline-fixture','scopes'=>$scopes],JSON_THROW_ON_ERROR);
        file_put_contents($ops.'/anex_local_offer_demand_queue.php',"<?php\n".$queueBody.'echo '.var_export($queue,true).';');
        $trace='file_put_contents(__DIR__."/calls.jsonl",json_encode(array_slice($argv,1))."\n",FILE_APPEND);';
        file_put_contents($ops.'/anex_local_offer_collect.php',"<?php\n".$trace.$collectorBody);
        // GNU timeout bounds only these disposable test processes, including their
        // children. Files avoid recreating the production pipe deadlock in the test.
        $pipes=[];
        $process=proc_open(['timeout','--kill-after=1s','5s',PHP_BINARY,$ops.'/anex_local_offer_demand_fill.php','--limit='.$limit],
            [0=>['file','/dev/null','r'],1=>['file',$root.'/stdout','w'],2=>['file',$root.'/stderr','w']],$pipes,null,null,['bypass_shell'=>true]);
        ck(is_resource($process),'fixture process');
        $code=proc_close($process);$process=null;
        $calls=is_file($ops.'/calls.jsonl')?file($ops.'/calls.jsonl',FILE_IGNORE_NEW_LINES):[];
        return ['code'=>$code,'stdout'=>file_get_contents($root.'/stdout'),'stderr'=>file_get_contents($root.'/stderr'),
            'calls'=>array_map(static fn(string $line):array=>json_decode($line,true,64,JSON_THROW_ON_ERROR),$calls)];
    }finally{
        if(is_resource($process)){proc_terminate($process);proc_close($process);}
        $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file){if($file->isDir())rmdir($file->getPathname());else unlink($file->getPathname());}
        rmdir($root);
    }
}
$childReceipt=['browser_supplier_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'final_price_ready_offers'=>7,
    'discovered_set_drained'=>true,'snapshot_finalize'=>['published'=>true]];
$receiptJson=json_encode($childReceipt,JSON_THROW_ON_ERROR);
$emit='echo '.var_export($receiptJson,true).';';
$flood='fwrite(STDERR,str_repeat("diagnostic-",100000));';
$success=static function(array $run,string $label,int $count=1):array{
    ck($run['code']===0,$label.' exits without deadlock');
    ck($run['stderr']==='',$label.' child diagnostics stay out of parent stderr');
    $r=json_decode($run['stdout'],true,64,JSON_THROW_ON_ERROR);
    ck($r['status']==='complete'&&$r['completedScopes']===$count&&$r['readyOffersAcrossScopes']===7*$count,$label.' exact receipt');
    ck(count($run['calls'])===$count,$label.' no replay');
    ck($r['browserSupplierCalls']===0&&$r['bookingCalls']===0&&$r['leadCalls']===0,$label.' authority');
    return $r;
};
$success(cliFixture([$scope],'',$emit),'quiet');
$success(cliFixture([$scope],$flood,$emit),'queue stderr');
$success(cliFixture([$scope],'',$flood.$emit),'collector stderr');
$split=31;
$interleaved='echo '.var_export(substr($receiptJson,0,$split),true).';'.$flood.'echo '.var_export(substr($receiptJson,$split),true).';';
$success(cliFixture([$scope],'',$interleaved),'interleaved streams');
$closedOut=$emit.'fclose(STDOUT);'.$flood;
$success(cliFixture([$scope],'',$closedOut),'stderr after stdout closes');

$third=array_replace($scope,['nights'=>9]);
$secondFailure='if(in_array("--generation=261900001",$argv,true)){fclose(STDOUT);fwrite(STDERR,str_repeat("ошибка",200000));exit(23);}'.$emit;
$failed=cliFixture([$family,$scope,$third],'',$secondFailure);
ck($failed['code']===1,'child failure propagates after large diagnostics');
$failedReceipt=json_decode($failed['stdout'],true,64,JSON_THROW_ON_ERROR);
ck($failedReceipt['status']==='stopped_on_error'&&$failedReceipt['completedScopes']===1&&$failedReceipt['readyOffersAcrossScopes']===7,'partial success preserved');
ck($failedReceipt['error']['index']===1&&$failedReceipt['error']['code']===23,'exact failing child code');
ck($failedReceipt['error']['stderr']===mb_substr(str_repeat('ошибка',200000),0,500),'existing unicode error bound preserved');
ck(count($failed['calls'])===2,'no retry and no third scope after failure');
ck(in_array('--child-ages=3,7',$failed['calls'][0],true)&&in_array('--generation=261900000',$failed['calls'][0],true),'family context and first generation');
ck(in_array('--generation=261900001',$failed['calls'][1],true),'sequential next generation');
$queueFailed=cliFixture([$scope],$flood.'exit(19);',$emit);
ck($queueFailed['code']!==0&&$queueFailed['code']!==124&&$queueFailed['code']!==137,'failed queue exits rather than hangs');
ck(str_contains($queueFailed['stderr'],'ANEX_DEMAND_FILL_QUEUE')&&$queueFailed['calls']===[],'queue failure never launches collector');
echo "ANEX_LOCAL_OFFER_DEMAND_FILL_OK command=1 children=1 summary=1 cli_stream_cases=7\n";
