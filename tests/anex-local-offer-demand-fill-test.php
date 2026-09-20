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
$queueMeta=AnyTourAnexDemandFillV1::queueReceipt([
    'selectionStatus'=>'source_exhausted','scannedRowCount'=>17,'scannedPageCount'=>1,'freshScopesSkipped'=>4,
]);
ck($queueMeta===['selectionStatus'=>'source_exhausted','scannedRowCount'=>17,'scannedPageCount'=>1,'freshScopesSkipped'=>4],'queue metadata');
// Exercise the unchanged CLI entrypoint, not a second implementation of its runner.
// All queue/collector programs below are local fixtures: no supplier, DB or credentials.
function cliFixture(array $scopes,string $queueBody,string $collectorBody,int $limit=3,array $queueMeta=[]):array
{
    $root=sys_get_temp_dir().'/anex demand worker '.bin2hex(random_bytes(8));
    $ops=$root.'/scripts/ops';$process=null;
    ck(mkdir($ops,0700,true)&&mkdir($root.'/app/integrations',0700,true),'fixture directories');
    try{
        ck(copy(__DIR__.'/../scripts/ops/anex_local_offer_demand_fill.php',$ops.'/anex_local_offer_demand_fill.php'),'copy real worker');
        ck(copy(__DIR__.'/../app/integrations/anex-local-offer-demand.php',$root.'/app/integrations/anex-local-offer-demand.php'),'copy real normalizer');
        $baseQueue=[
            'source'=>'offline-fixture','scopes'=>$scopes,'selectionStatus'=>'source_exhausted',
            'scannedRowCount'=>count($scopes),'scannedPageCount'=>1,'freshScopesSkipped'=>0,
        ];
        $queue=json_encode(array_replace($baseQueue,$queueMeta),JSON_THROW_ON_ERROR);
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
    ck(in_array($r['queueSelectionStatus'],['source_exhausted','limit_reached'],true),$label.' complete queue status');
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
$limitScopes=[$scope,$family,array_replace($scope,['nights'=>9])];
$success(cliFixture($limitScopes,'',$emit,3,[
    'selectionStatus'=>'limit_reached','scannedRowCount'=>57,'scannedPageCount'=>1,'freshScopesSkipped'=>12,
]),'queue limit reached',3);

// #3204 can intentionally stop after its bounded 1000-row metadata scan. The worker
// may process the stale scopes it did find, but must not advertise that partial frontier as complete.
$scanIncomplete=cliFixture([$scope],'',$emit,3,[
    'selectionStatus'=>'scan_limit_reached','scannedRowCount'=>1000,'scannedPageCount'=>10,'freshScopesSkipped'=>999,
]);
ck($scanIncomplete['code']===1&&count($scanIncomplete['calls'])===1,'scan-capped queue processes known scope once');
$scanReceipt=json_decode($scanIncomplete['stdout'],true,64,JSON_THROW_ON_ERROR);
ck($scanReceipt['status']==='queue_scan_incomplete'&&$scanReceipt['completedScopes']===1
    &&$scanReceipt['readyOffersAcrossScopes']===7&&$scanReceipt['error']===null,'scan cap cannot claim complete');
ck($scanReceipt['queueSelectionStatus']==='scan_limit_reached'&&$scanReceipt['queueScannedRowCount']===1000
    &&$scanReceipt['queueScannedPageCount']===10&&$scanReceipt['queueFreshScopesSkipped']===999,'scan cap evidence preserved');
$badQueueMeta=cliFixture([$scope],'',$emit,3,['selectionStatus'=>'unknown']);
ck($badQueueMeta['code']!==0&&$badQueueMeta['calls']===[]&&str_contains($badQueueMeta['stderr'],'ANEX_DEMAND_FILL_QUEUE'),
    'invalid queue completeness fails before supplier collector');

// An exit-zero collector may still carry an explicit persistence failure. Keep
// that exact evidence and do not count the failed scope or invoke its successor.
$persistenceFailures=[
    ['published'=>false,'reason'=>'autosave_failed'],
    ['published'=>false,'reason'=>'local_ingest_unavailable'],
    ['published'=>false,'reason'=>'runtime_dependency_unavailable'],
    ['published'=>false,'reason'=>'db_unavailable'],
    ['published'=>false,'reason'=>'supplier_identity_changed'],
    ['published'=>false,'reason'=>'protected_price_mismatch'],
    ['published'=>false,'reason'=>'unknown_future_reason'],
    ['published'=>'true','reason'=>null],
    ['published'=>0,'reason'=>'already_published'],
    ['published'=>false,'reason'=>'no_final_price_ready','readyOfferCount'=>0],
    [],
    null,
];
foreach($persistenceFailures as $index=>$finalize){
    $unpublished=array_replace($childReceipt,['snapshot_finalize'=>$finalize]);
    if($index===count($persistenceFailures)-1)unset($unpublished['snapshot_finalize']);
    $run=cliFixture([$scope,$family],'','echo '.var_export(json_encode($unpublished,JSON_THROW_ON_ERROR),true).';');
    ck($run['code']===1,'persistence failure '.$index.' exits nonzero');
    $r=json_decode($run['stdout'],true,64,JSON_THROW_ON_ERROR);
    ck($r['status']==='stopped_on_error'&&$r['completedScopes']===0&&$r['readyOffersAcrossScopes']===0&&$r['results']===[],'persistence failure '.$index.' not counted');
    ck($r['error']===['index'=>0,'code'=>0,'reason'=>'snapshot_not_published','collectorResult'=>$unpublished],'persistence failure '.$index.' exact receipt retained');
    ck(count($run['calls'])===1,'persistence failure '.$index.' no retry/no next scope');
}
$already=array_replace($childReceipt,['snapshot_finalize'=>['published'=>false,'reason'=>'already_published','readyOfferCount'=>7]]);
$success(cliFixture([$scope],'','echo '.var_export(json_encode($already,JSON_THROW_ON_ERROR),true).';'),'already published');
$zero=array_replace($childReceipt,['final_price_ready_offers'=>0,'snapshot_finalize'=>['published'=>false,'reason'=>'no_final_price_ready','readyOfferCount'=>0,'accumulatedOfferCount'=>0]]);
$zeroThenReady='if(in_array("--generation=261900000",$argv,true)){echo '.var_export(json_encode($zero,JSON_THROW_ON_ERROR),true).';}else{'.$emit.'}';
$zeroRun=cliFixture([$scope,$family],'',$zeroThenReady);
ck($zeroRun['code']===0&&count($zeroRun['calls'])===2,'legitimate zero yield continues without retry');
$zeroReceipt=json_decode($zeroRun['stdout'],true,64,JSON_THROW_ON_ERROR);
ck($zeroReceipt['status']==='complete'&&$zeroReceipt['completedScopes']===2&&$zeroReceipt['readyOffersAcrossScopes']===7,'zero yield not invented ready offers');
ck($zeroReceipt['results'][0]['snapshotFinalize']===$zero['snapshot_finalize']&&$zeroReceipt['results'][0]['finalPriceReadyOffers']===0,'zero no-write receipt unchanged');
$badSecond=array_replace($childReceipt,['snapshot_finalize'=>['published'=>false,'reason'=>'autosave_failed']]);
$secondPersistenceFailure='if(in_array("--generation=261900001",$argv,true)){echo '.var_export(json_encode($badSecond,JSON_THROW_ON_ERROR),true).';}else{'.$emit.'}';
$partialRun=cliFixture([$scope,$family,array_replace($scope,['nights'=>9])],'',$secondPersistenceFailure);
ck($partialRun['code']===1&&count($partialRun['calls'])===2,'persistence failure after success stops before third scope');
$partialReceipt=json_decode($partialRun['stdout'],true,64,JSON_THROW_ON_ERROR);
ck($partialReceipt['completedScopes']===1&&$partialReceipt['readyOffersAcrossScopes']===7&&count($partialReceipt['results'])===1,'earlier persisted result preserved');
ck($partialReceipt['error']['index']===1&&$partialReceipt['error']['collectorResult']===$badSecond,'second persistence failure retained exactly');
echo "ANEX_DEMAND_PERSISTENCE_CASES_OK failed=".count($persistenceFailures)." already=1 zero_yield=1 partial_success=1\n";

// New collector contract: failed/unknown persistence now exits nonzero but prints
// one structured receipt. The demand worker must preserve that exact receipt for
// reconciliation, stop immediately and never invoke a second scope.
$structuredFailure=[
    'source'=>'anex-local-offer-collector-v1','status'=>'incomplete','selection_authority'=>false,
    'autosave_failure'=>['phase'=>'batch','receipt'=>['published'=>false,'reason'=>'autosave_failed','writeOutcome'=>'unknown']],
    'browser_supplier_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,
];
$structuredBody='echo '.var_export(json_encode($structuredFailure,JSON_THROW_ON_ERROR),true).';fwrite(STDERR,"fixture persistence refused");exit(17);';
$structured=cliFixture([$scope,$family],'',$structuredBody);
ck($structured['code']===1&&count($structured['calls'])===1,'structured nonzero failure stops without replay');
$structuredReceipt=json_decode($structured['stdout'],true,64,JSON_THROW_ON_ERROR);
ck($structuredReceipt['status']==='stopped_on_error'&&$structuredReceipt['completedScopes']===0&&$structuredReceipt['results']===[],'structured failure not counted');
ck($structuredReceipt['error']['index']===0&&$structuredReceipt['error']['code']===17
    &&$structuredReceipt['error']['stderr']==='fixture persistence refused','structured failure code/stderr preserved');
ck($structuredReceipt['error']['collectorResult']===$structuredFailure,'structured collector receipt retained exactly');

$third=array_replace($scope,['nights'=>9]);
$secondFailure='if(in_array("--generation=261900001",$argv,true)){fclose(STDOUT);fwrite(STDERR,str_repeat("ошибка",200000));exit(23);}'.$emit;
$failed=cliFixture([$family,$scope,$third],'',$secondFailure);
ck($failed['code']===1,'child failure propagates after large diagnostics');
$failedReceipt=json_decode($failed['stdout'],true,64,JSON_THROW_ON_ERROR);
ck($failedReceipt['status']==='stopped_on_error'&&$failedReceipt['completedScopes']===1&&$failedReceipt['readyOffersAcrossScopes']===7,'partial success preserved');
ck($failedReceipt['error']['index']===1&&$failedReceipt['error']['code']===23,'exact failing child code');
ck($failedReceipt['error']['stderr']===mb_substr(str_repeat('ошибка',200000),0,500),'existing unicode error bound preserved');
ck(!array_key_exists('collectorResult',$failedReceipt['error']),'arbitrary/non-JSON stdout not surfaced');
ck(count($failed['calls'])===2,'no retry and no third scope after failure');
ck(in_array('--child-ages=3,7',$failed['calls'][0],true)&&in_array('--generation=261900000',$failed['calls'][0],true),'family context and first generation');
ck(in_array('--generation=261900001',$failed['calls'][1],true),'sequential next generation');
$queueFailed=cliFixture([$scope],$flood.'exit(19);',$emit);
ck($queueFailed['code']!==0&&$queueFailed['code']!==124&&$queueFailed['code']!==137,'failed queue exits rather than hangs');
ck(str_contains($queueFailed['stderr'],'ANEX_DEMAND_FILL_QUEUE')&&$queueFailed['calls']===[],'queue failure never launches collector');
echo "ANEX_LOCAL_OFFER_DEMAND_FILL_OK command=1 children=1 summary=1 cli_stream_cases=10 queue_completeness=1 structured_nonzero=1\n";