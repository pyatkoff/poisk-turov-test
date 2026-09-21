<?php
declare(strict_types=1);
require_once __DIR__.'/../app/integrations/anex-local-offer-collector.php';

function ck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
$state=[];
$searchCalls=0;$expandCalls=0;$programCalls=0;$batchCalls=0;
$search=static function(array $request,array &$state)use(&$searchCalls):array{
    ++$searchCalls;
    $state=['seen'=>'search'];
    return [
        'provider'=>'anex','search_ref'=>str_repeat('a',32),
        'hotels'=>[
            ['local_id'=>101,'tours'=>[
                ['kind'=>'group_minimum','offer_ref'=>'anex_online:'.str_repeat('1',64),'flight_type'=>null],
                ['kind'=>'concrete','offer_ref'=>'anex_online:'.str_repeat('2',64),'flight_type'=>'regular'],
            ]],
            ['local_id'=>102,'tours'=>[
                ['kind'=>'group_minimum','offer_ref'=>'anex_online:'.str_repeat('3',64),'flight_type'=>null],
            ]],
        ],
    ];
};
$expand=static function(array $request,array &$state)use(&$expandCalls):array{
    ++$expandCalls;
    $state['expand']=$expandCalls;
    return [
        'status'=>'expanded',
        'hotels'=>[[
            'local_id'=>$request['local_hotel_id'],
            'tours'=>[
                ['kind'=>'concrete','offer_ref'=>'anex_online:'.str_repeat('4',64),'flight_type'=>'charter'],
                ['kind'=>'concrete','offer_ref'=>'anex_online:'.str_repeat('4',64),'flight_type'=>'charter'],
                ['kind'=>'concrete','offer_ref'=>'anex_online:'.str_repeat('5',64),'flight_type'=>'regular'],
            ],
        ]],
    ];
};
$record=static function(array &$state)use(&$programCalls):array{++$programCalls;return ['status'=>'complete'];};
$batch=static function(array $request,array &$state)use(&$batchCalls):array{
    ++$batchCalls;
    ck(count($request['items'])===2,'batch-identity');
    return ['status'=>'additional_prices_batch','offers'=>[
        ['status'=>'additional_prices','finalPriceReady'=>true,'retryable'=>false],
        ['status'=>'additional_prices','finalPriceReady'=>true,'retryable'=>false],
    ]];
};
$request=['action'=>'search','generation'=>42,'params'=>['departureId'=>'1','countryId'=>'4']];
$result=AnyTourAnexLocalOfferCollectorV1::collect($request,$state,$search,$expand,$record,$batch,2,6);
ck($searchCalls===1,'search-once');
ck($expandCalls===2,'expand-bounded-actual');
ck($programCalls===3,'program-after-search-and-expands');
ck($batchCalls===1,'batch-once');
ck($result['charter_concrete_candidates']===2,'charter-per-hotel-identity');
ck($result['regular_concrete_candidates']===3,'regular-observed');
ck($result['final_price_ready_offers']===2,'ready-count');
ck($result['status']==='complete'&&$result['grouped_drained']===true && $result['concrete_drained']===true && $result['discovered_set_drained']===true,'small-drained');

$state=[];
$expandCalls=0;
$result2=AnyTourAnexLocalOfferCollectorV1::collect($request,$state,$search,$expand,$record,$batch,0,6);
ck($result2['expand_calls']===0,'zero-expand');
ck($result2['apd_batch_items']===0,'no-charter-no-batch');
ck($result2['status']==='incomplete'&&$result2['grouped_drained']===false && $result2['discovered_set_drained']===false,'zero-expand-not-drained');


$massState=[];
$massBatchCalls=0;
$massSearch=static function(array $request,array &$state):array{
    $state=['mass'=>true];
    $hotels=[];
    for($i=1;$i<=15;++$i){
        $hotels[]=['local_id'=>1000+$i,'tours'=>[
            ['kind'=>'group_minimum','offer_ref'=>'anex_online:'.str_pad(dechex($i),64,'0',STR_PAD_LEFT),'flight_type'=>null],
        ]];
    }
    return ['provider'=>'anex','search_ref'=>str_repeat('b',32),'hotels'=>$hotels];
};
$massExpand=static function(array $request,array &$state):array{
    $tours=[];
    for($j=1;$j<=4;++$j){
        $seed=hash('sha256',$request['offer_ref'].':'.$j);
        $tours[]=['kind'=>'concrete','offer_ref'=>'anex_online:'.$seed,'flight_type'=>'charter'];
    }
    return ['status'=>'expanded','hotels'=>[['local_id'=>$request['local_hotel_id'],'tours'=>$tours]]];
};
$massBatch=static function(array $request,array &$state)use(&$massBatchCalls):array{
    ++$massBatchCalls;
    ck(count($request['items'])>=1 && count($request['items'])<=6,'mass-chunk-size');
    return ['status'=>'additional_prices_batch','offers'=>array_map(
        static fn(array $item):array=>['status'=>'additional_prices','finalPriceReady'=>true,'retryable'=>false],
        $request['items']
    )];
};
$massRecord=static fn(array &$state):array=>['status'=>'complete'];
$mass=AnyTourAnexLocalOfferCollectorV1::collect($request,$massState,$massSearch,$massExpand,$massRecord,$massBatch,15,60);
ck($mass['expand_calls']===15,'mass-expands');
ck($mass['charter_concrete_candidates']===60,'mass-charters');
ck($mass['apd_batch_items']===60 && $mass['apd_batch_calls']===10,'mass-apd-chunks');
ck($mass['final_price_ready_offers']===60,'mass-ready');
ck($massBatchCalls===10,'mass-batch-call-count');
ck($mass['status']==='complete'&&$mass['discovered_set_drained']===true,'mass-drained');

$defaultState=[];$defaultBatchCalls=0;
$defaultSearch=static function(array $request,array &$state):array{
    $state=['default'=>true];$hotels=[];
    for($i=1;$i<=130;++$i)$hotels[]=['local_id'=>2000+$i,'tours'=>[[
        'kind'=>'group_minimum','offer_ref'=>'anex_online:'.hash('sha256','group:'.$i),'flight_type'=>null,
    ]]];
    return ['provider'=>'anex','search_ref'=>str_repeat('c',32),'hotels'=>$hotels];
};
$defaultExpand=static function(array $request,array &$state):array{
    return ['status'=>'expanded','hotels'=>[['local_id'=>$request['local_hotel_id'],'tours'=>[[
        'kind'=>'concrete','offer_ref'=>'anex_online:'.hash('sha256','concrete:'.$request['offer_ref']),'flight_type'=>'charter',
    ]]]]];
};
$defaultBatch=static function(array $request,array &$state)use(&$defaultBatchCalls):array{
    ++$defaultBatchCalls;return ['status'=>'additional_prices_batch','offers'=>array_map(
        static fn(array $item):array=>['status'=>'additional_prices','finalPriceReady'=>true,'retryable'=>false],$request['items'])];
};
$default=AnyTourAnexLocalOfferCollectorV1::collect($request,$defaultState,$defaultSearch,$defaultExpand,$massRecord,$defaultBatch);
ck($default['expand_calls']===130 && $default['apd_batch_items']===130,'default-not-old-60-300-cap');
ck($default['status']==='complete'&&$default['discovered_set_drained']===true,'default-drains-retained-set');
ck($defaultBatchCalls===22,'default-chunks');

echo "ANEX_LOCAL_OFFER_COLLECTOR_OK search=1 explicit_bound=1 chunking=1 mass60=1 default130_drained=1 incomplete_status=1 ready=1\n";

/** Supplier-free orchestration fixture; "persisted" is a local callback log, not a DB. */
function incrementalCase(int $initial,int $groups,int $perGroup,int $maxExpands=600,int $maxItems=600,
    int $failExpand=0,int $failBatch=0,bool $duplicates=false,bool $ready=true):array
{
    $state=[];$events=[];$batches=[];$expands=0;$error=null;$receipt=null;
    $ref=static fn(int $i):string=>'anex_online:'.hash('sha256','stream:'.$i);
    $tour=static fn(int $i):array=>['kind'=>'concrete','offer_ref'=>$ref($i),'flight_type'=>'charter'];
    $search=static function(array $r,array &$s)use($initial,$groups,$tour,&$events):array{
        $events[]='search';$s=['programs'=>0,'persisted'=>[]];$tours=[];
        for($i=0;$i<$initial;++$i)$tours[]=$tour($i);
        for($i=0;$i<$groups;++$i)$tours[]=['kind'=>'group_minimum',
            'offer_ref'=>'anex_online:'.hash('sha256','group:'.$i),'flight_type'=>null];
        return ['provider'=>'anex','search_ref'=>str_repeat('d',32),'hotels'=>[['local_id'=>501,'tours'=>$tours]]];
    };
    $expand=static function(array $r,array &$s)use($initial,$perGroup,$failExpand,$duplicates,$tour,&$events,&$expands):array{
        ++$expands;$events[]='expand:'.$expands;
        if($expands===$failExpand)throw new RuntimeException('SIMULATED_LATE_EXPAND_FAILURE');
        $tours=[];$start=$initial+($expands-1)*$perGroup;
        for($i=0;$i<$perGroup;++$i)$tours[]=$tour($start+$i);
        // Repeated data within/across expansions must not trigger another APD item.
        if($duplicates&&$start+$perGroup>0){$tours[]=$tour(0);$tours[]=$tour(0);}
        return ['status'=>'expanded','hotels'=>[['local_id'=>501,'tours'=>$tours]]];
    };
    $record=static function(array &$s):array{++$s['programs'];return ['status'=>'complete'];};
    $batch=static function(array $r,array &$s)use($failBatch,$ready,&$events,&$batches,&$expands):array{
        $batches[]=$r;$events[]='apd:'.count($r['items']).'@'.$expands;
        ck($r['action']==='additional_prices_batch'&&$r['generation']===73
            &&$r['search_ref']===str_repeat('d',32),'incremental exact context');
        ck($s['programs']===$expands+1,'program evidence before APD');
        if(count($batches)===$failBatch)throw new RuntimeException('SIMULATED_APD_FAILURE');
        $offers=[];
        foreach($r['items'] as $item){
            ck($item['local_hotel_id']===501,'incremental exact local identity');
            if($ready)$s['persisted'][]=$item;
            $offers[]=['status'=>'additional_prices','finalPriceReady'=>$ready,'retryable'=>!$ready];
        }
        return ['status'=>'additional_prices_batch','offers'=>$offers];
    };
    try{
        $receipt=AnyTourAnexLocalOfferCollectorV1::collect(['action'=>'search','generation'=>73,'params'=>[]],
            $state,$search,$expand,$record,$batch,$maxExpands,$maxItems);
    }catch(RuntimeException $e){$error=$e->getMessage();}
    return compact('receipt','state','events','batches','expands','error');
}

$stream=incrementalCase(0,130,1);
ck($stream['error']===null&&$stream['receipt']['status']==='complete'&&$stream['receipt']['apd_batch_calls']===22,'stream unchanged batch total');
ck(array_search('apd:6@6',$stream['events'],true)<array_search('expand:7',$stream['events'],true)
    &&in_array('apd:6@6',$stream['events'],true),'first full batch before later expansions');
ck(end($stream['events'])==='apd:4@130'&&count($stream['state']['persisted'])===130,'one terminal tail');

$late=incrementalCase(0,10,1,600,600,7);
ck($late['error']==='SIMULATED_LATE_EXPAND_FAILURE'&&$late['receipt']===null,'late exception unchanged');
ck(count($late['state']['persisted'])===6&&count($late['batches'])===1&&$late['expands']===7,'earlier full batch survives late failure');
ck(end($late['events'])==='expand:7','no retry or APD after failed expansion');

$failedApd=incrementalCase(0,20,1,600,600,0,2);
ck($failedApd['error']==='SIMULATED_APD_FAILURE'&&$failedApd['receipt']===null,'APD failure unchanged');
ck($failedApd['expands']===12&&count($failedApd['batches'])===2
    &&count($failedApd['state']['persisted'])===6,'APD failure stops before next expansion without replay');

$initial=incrementalCase(6,10,1);
ck($initial['events'][1]==='apd:6@0','initial full batch before any expansion');
$pending=incrementalCase(0,10,1,600,600,8);
ck(count($pending['state']['persisted'])===6&&end($pending['events'])==='expand:8','no forced tail after failure');
$unknown=incrementalCase(0,13,1,600,600,0,0,false,false);
ck($unknown['receipt']['status']==='complete'&&$unknown['receipt']['final_price_ready_offers']===0&&$unknown['receipt']['retryable_offers']===13
    &&count($unknown['batches'])===3&&$unknown['state']['persisted']===[],'unknown prices never made ready or retried');

$bounds=0;
foreach([[0,5,4,600,8],[0,5,4,2,600],[0,5,4,600,5],[13,5,4,0,7],[3,0,0,0,600],
    [0,5,0,600,600],[0,3,7,600,13],[0,3,7,0,600]] as [$initialCount,$groups,$perGroup,$expandLimit,$itemLimit]){
    $r=incrementalCase($initialCount,$groups,$perGroup,$expandLimit,$itemLimit,0,0,true);
    $total=$initialCount;$expectedExpands=0;
    while($expectedExpands<min($groups,$expandLimit)&&$total<$itemLimit){++$expectedExpands;$total+=$perGroup;}
    $n=min($total,$itemLimit);$expected=[];
    for($i=0;$i<$n;++$i)$expected[]=['offer_ref'=>'anex_online:'.hash('sha256','stream:'.$i),'local_hotel_id'=>501];
    $actual=[];foreach($r['batches'] as $b)array_push($actual,...$b['items']);
    ck($r['error']===null&&$r['expands']===$expectedExpands&&$actual===$expected,'same bounded ordered unique items');
    ck($r['receipt']['apd_batch_items']===$n&&$r['receipt']['apd_batch_calls']===intdiv($n+5,6),'same bounded batch count');
    $expectedDrained=$expectedExpands===$groups&&$total<=$itemLimit;
    ck($r['receipt']['grouped_drained']===($expectedExpands===$groups)
        &&$r['receipt']['concrete_drained']===($total<=$itemLimit)
        &&$r['receipt']['status']===($expectedDrained?'complete':'incomplete'),'same discovery flags/status');
    ++$bounds;
}
echo "ANEX_INCREMENTAL_APD_OK first_batch_at_expand=6 total130_batches=22 late_failure_saved=6 bounds=".$bounds." incomplete_status=1\n";