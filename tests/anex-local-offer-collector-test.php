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

$state=[];
$expandCalls=0;
$result2=AnyTourAnexLocalOfferCollectorV1::collect($request,$state,$search,$expand,$record,$batch,0,6);
ck($result2['expand_calls']===0,'zero-expand');
ck($result2['apd_batch_items']===0,'no-charter-no-batch');

echo "ANEX_LOCAL_OFFER_COLLECTOR_OK search=1 expand_bound=1 dedup=1 regular_observed=1 ready=1\n";
