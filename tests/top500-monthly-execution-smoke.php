<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/top500-monthly-plan-v1.php';
function execution_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
function execution_target(int $n,array $ids):array{return ['criterion'=>'hotel_batch','target_key'=>'monthly:retained:'.$n,'month'=>'2026-12','departure_id'=>1,'country_id'=>4,'region_id'=>null,'hotel_ids'=>$ids,'date_from'=>'2026-12-01','date_to'=>'2026-12-07','resume_search_id'=>null,'resume_attempt_id'=>null];}
$fragmented=[];foreach(range(1,12) as $n)$fragmented[]=execution_target($n,[$n]);
$original=$fragmented;$packed=v2_top500_execution_targets($fragmented);
execution_check(count($packed)===1&&count($packed[0]['hotel_ids'])===12,'twelve sparse cohorts use one existing-cap request');
execution_check($packed[0]['_logical_targets']===$original&&$fragmented===$original,'all source keys and logical hotel sets unchanged');
$many=[];foreach(range(1,65) as $n)$many[]=execution_target($n,[$n]);
$packedMany=v2_top500_execution_targets($many);
execution_check(array_map(fn($t)=>count($t['hotel_ids']),$packedMany)===[30,30,5],'supplier cap unchanged');
execution_check(count(v2_seasonal_gap_pass_targets($packedMany,[],2))===2,'budget counts actual searches');
foreach(['country_id'=>8,'departure_id'=>3,'date_from'=>'2026-12-02','date_to'=>'2026-12-06'] as $field=>$value){
    $other=execution_target(90,[90]);$other[$field]=$value;
    execution_check(count(v2_top500_execution_targets([$fragmented[0],$other]))===2,'incompatible '.$field.' isolated');
}
$overlap=execution_target(90,[1]);execution_check(count(v2_top500_execution_targets([$fragmented[0],$overlap]))===2,'overlapping logical sets never share persistence');
$gap=execution_target(91,[]);$gap['criterion']='resort';$gap['region_id']=22;$gap['page_key']='resort_month:1:4:22:2026-12';
$gap2=$gap;$gap2['target_key'].=':second';
$gaps=v2_top500_execution_targets([$gap,$gap2]);execution_check(count($gaps)===2,'seasonal page searches standalone');
$reserved=v2_seasonal_gap_pass_targets($packedMany,$gaps,2,8);
execution_check(count($reserved)===2&&isset($reserved[0]['page_key'])&&!isset($reserved[1]['page_key']),'SEO reserve remains inside request budget with hotel progress');
execution_check(v2_seasonal_gap_pass_targets($packedMany,[],2,8,'2027-01')===[],'month filter remains exact');
$r1=$fragmented[0];$r1['resume_search_id']=77;$r1['resume_attempt_id']=501;
$r2=$fragmented[1];$r2['resume_search_id']=77;$r2['resume_attempt_id']=502;
$r3=$fragmented[2];$r3['resume_search_id']=78;$r3['resume_attempt_id']=503;
$resumed=v2_top500_execution_targets([$r1,$r2,$r3,$fragmented[3]]);
execution_check(count($resumed)===3&&$resumed[0]['_logical_targets']===[$r1,$r2],'one shared retained search resumes once; other/new searches isolated');
$wrong=$r2;$wrong['country_id']=8;
try{v2_top500_execution_targets([$r1,$wrong]);throw new LogicException('conflicting retained scope accepted');}catch(InvalidArgumentException $e){}
try{v2_top500_execution_targets([$r1,$r1]);throw new LogicException('duplicate key accepted');}catch(InvalidArgumentException $e){}
$rows=[['id'=>1,'country'=>4,'tours'=>[['price'=>100]]],['id'=>2,'country'=>8,'tours'=>[['price'=>50]]],['id'=>99,'country'=>4,'tours'=>[['price'=>1]]]];
$out=v2_top500_execution_outcomes($packed[0],$rows,true);
execution_check($out[0]['status']==='success'&&count($out[0]['rows'])===1&&$out[1]['status']==='empty','foreign and sibling results do not confer success');
execution_check(array_unique(array_column(v2_top500_execution_outcomes($packed[0],$rows,false),'status'))===['timeout'],'partial search cannot confer completed coverage');

// Execute the actual collector loop with deterministic fake storage/provider I/O.
// No production test switch or alternative implementation is used.
$source=file_get_contents(__DIR__.'/../v2/data/top500-daily-collector-v1.php');
$begin=strpos($source,'    $processed=0;');$end=strpos($source,'    $after=v2_top500_monthly_queue(', $begin);
execution_check($begin!==false&&$end!==false,'actual loop boundaries');$loop=substr($source,$begin,$end-$begin);
class ExecutionFakeDb{public function prepare(string $sql):ExecutionFakeStatement{return new ExecutionFakeStatement;}}
class ExecutionFakeStatement{public function execute(array $p):void{$GLOBALS['execution_io']['bindings'][$p['id']]=$p['search_id'];}}
function top500_daily_attempt_start($pdo,array $t):int{$id=count($GLOBALS['execution_io']['starts'])+1;$GLOBALS['execution_io']['starts'][$id]=$t;return $id;}
function top500_daily_attempt_finish($pdo,int $id,string $status,?int $searchId,int $rows,int $written,?string $error):void{
    if(($GLOBALS['execution_io']['fail_finish']??0)===$id)throw new RuntimeException('completion unavailable');
    $GLOBALS['execution_io']['finishes'][]=compact('id','status','searchId','rows','written');
}
function top500_daily_get(string $path,array $params=[]):array{
    $GLOBALS['execution_io']['requests'][]=[$path,$params];
    if($GLOBALS['execution_io']['fail_fetch']??false)throw new RuntimeException('provider failed');
    return $path==='/tours/search'?['searchId'=>900]:['progress'=>($GLOBALS['execution_io']['complete']?100:50)];
}
function top500_daily_search_id(array $p):?int{return $p['searchId']??null;}
function top500_daily_complete(array $p):bool{return ($p['progress']??0)>=100;}
function top500_daily_fetch_results(int $id):array{$GLOBALS['execution_io']['result_reads'][]=$id;return $GLOBALS['execution_io']['rows'];}
function v2_data_observe_search_results(array $rows,array $context):array{
    $GLOBALS['execution_io']['observations'][]=['ids'=>array_column($rows,'id'),'context'=>$context];
    $seen=min($context['maxTours'],array_sum(array_map(fn($h)=>count($h['tours']??[]),$rows)));
    return ['seen'=>$seen,'written'=>$seen];
}
function execution_run(string $loop,array $passTargets,array $rows,bool $complete=true,array $options=[]):array{
    $GLOBALS['execution_io']=['starts'=>[],'bindings'=>[],'finishes'=>[],'requests'=>[],'result_reads'=>[],'observations'=>[],'rows'=>$rows,'complete'=>$complete]+$options;
    $pdo=new ExecutionFakeDb;$budget=64;$deadline=microtime(true)+10;$gapError=0;$pollAttempts=1;$pollSeconds=0;
    ob_start();try{eval($loop);}finally{ob_end_clean();}
    return $GLOBALS['execution_io']+compact('processed','logicalProcessed','writtenTotal','failed','partial');
}
$sample=[['id'=>1,'country'=>4,'tours'=>[['price'=>100],['price'=>110]]],['id'=>2,'country'=>4,'tours'=>[['price'=>80]]]];
$io=execution_run($loop,$packed,$sample);
execution_check(count($io['requests'])===2&&count($io['result_reads'])===1&&count($io['starts'])===12,'one start/status/results chain for twelve exact attempts');
execution_check(count($io['bindings'])===12&&array_values(array_unique($io['bindings']))===[900],'all logical attempts retain same actual search ID');
execution_check(array_column($io['finishes'],'written')===[2,1,0,0,0,0,0,0,0,0,0,0]&&$io['writtenTotal']===3,'per-member accounting exact; no duplicate observations');
execution_check(array_column($io['finishes'],'status')===['success','success','empty','empty','empty','empty','empty','empty','empty','empty','empty','empty'],'logical empties stay empty');
$io=execution_run($loop,[$resumed[0]],$sample,false);
execution_check(count($io['starts'])===0&&count($io['requests'])===1&&$io['requests'][0][0]==='/tours/search/77/status'&&$io['result_reads']===[77],'resume reads existing shared search without a new one');
execution_check(array_column($io['finishes'],'id')===[501,502]&&array_column($io['finishes'],'status')===['timeout','timeout'],'original attempt IDs and partial states retained');
$io=execution_run($loop,[$resumed[0]],[],true,['fail_fetch'=>true]);
execution_check($io['failed']===1&&array_column($io['finishes'],'status')===['failure','failure']&&$io['observations']===[],'provider failure leaves all logical targets incomplete');
try{execution_run($loop,$packed,$sample,true,['fail_finish'=>2]);throw new LogicException('completion failure ignored');}catch(RuntimeException $e){execution_check($e->getMessage()==='completion unavailable','expected storage failure');}
execution_check(count($GLOBALS['execution_io']['finishes'])===1&&$GLOBALS['execution_io']['finishes'][0]['written']===2,'unknown completion not retried or recounted');
$large=$sample;$large[0]['tours']=array_fill(0,50000,['price'=>100]);
$io=execution_run($loop,$packed,$large);
execution_check($io['writtenTotal']===50000&&count($io['observations'])===1,'shared request retains total 50k observer cap');
echo "TOP500_EXECUTION_OK sparse12_to1=1 cap30=1 logical_receipts=1 resume_once=1 partial_empty_failure=1 observer_cap=1\n";
