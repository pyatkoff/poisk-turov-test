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
ck($result['grouped_drained']===true && $result['concrete_drained']===true && $result['discovered_set_drained']===true,'small-drained');

$state=[];
$expandCalls=0;
$result2=AnyTourAnexLocalOfferCollectorV1::collect($request,$state,$search,$expand,$record,$batch,0,6);
ck($result2['expand_calls']===0,'zero-expand');
ck($result2['apd_batch_items']===0,'no-charter-no-batch');
ck($result2['grouped_drained']===false && $result2['discovered_set_drained']===false,'zero-expand-not-drained');


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
ck($mass['discovered_set_drained']===true,'mass-drained');

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
ck($default['discovered_set_drained']===true,'default-drains-retained-set');
ck($defaultBatchCalls===22,'default-chunks');

echo "ANEX_LOCAL_OFFER_COLLECTOR_OK search=1 explicit_bound=1 chunking=1 mass60=1 default130_drained=1 ready=1\n";
