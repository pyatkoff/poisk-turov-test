<?php
declare(strict_types=1);
require_once __DIR__.'/../app/integrations/andromeda-local-offer-collector.php';

function ok(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$request=['generation'=>7,'params'=>['departureId'=>'1','countryId'=>'4']];
$searchCalls=0;$captureCalls=0;$autosaveCalls=0;
$search=static function(array $r)use(&$searchCalls):array{
    ++$searchCalls;
    return ['provider'=>'andromeda','search_ref'=>str_repeat('a',64),'pages_count'=>3,'status'=>'complete'];
};
$offer=static function(string $suffix,string $operator,int $local,string $opRef,?bool $freight=null):array{
    $row=['offer_ref'=>'offer_'.hash('sha256',$suffix),'operator'=>$operator,'operator_ref'=>$opRef,'local_hotel_id'=>$local];
    if($freight!==null)$row['transport_context']=['freight_external'=>$freight];
    return $row;
};
$cohort=static fn(string $ref,int $generation):array=>[
    ['page'=>1,'offer'=>$offer('a','ANEX',11,'5')],
    ['page'=>1,'offer'=>$offer('b','FUN&SUN',12,'7')],
    ['page'=>2,'offer'=>$offer('c','Библио-Глобус',13,'8')],
    ['page'=>3,'offer'=>$offer('d','Coral Travel',14,'9')],
    ['page'=>3,'offer'=>$offer('e','Интурист',15,'10')],
];
$allow=static fn(array $selection,array $offer):bool=>$selection['local_id']!==13;
$capture=static function(array $selection)use(&$captureCalls):array{
    ++$captureCalls;
    return ['status'=>'captured','surcharge'=>$selection['local_id']===12
        ? ['status'=>'complete','fact'=>['state'=>'estimated']]
        : ['status'=>'unavailable','fact'=>null]];
};
$autosave=static function(array $r,string $ref,int $generation)use(&$autosaveCalls):array{
    ++$autosaveCalls;
    return ['published'=>true,'reason'=>null,'readyOfferCount'=>1];
};
$result=AnyTourAndromedaLocalOfferCollectorV1::collect(
    $request,$search,$cohort,$allow,$capture,$autosave,2
);
ok($searchCalls===1,'search once');
ok($result['pages']===3,'pages');
ok($result['received_offers']===5,'received');
ok($result['owned_operator_offers']===3,'routing');
ok($result['eligible_offers']===2,'candidate gate');
ok($captureCalls===2 && $result['surcharge_capture_attempts']===2,'capture bound');
ok($result['surcharge_ready']===1,'surcharge ready');
ok($autosaveCalls===1 && $result['autosave_published']===true && $result['ready_offer_count']===1,'autosave');

$priorityCohort=static fn(string $ref,int $generation):array=>[
    ['page'=>1,'offer'=>$offer('p-false','FUN&SUN',21,'21',false)],
    ['page'=>1,'offer'=>$offer('p-unknown','Библио-Глобус',22,'22',null)],
    ['page'=>1,'offer'=>$offer('p-true-a','Интурист',23,'23',true)],
    ['page'=>1,'offer'=>$offer('p-true-b','FUN&SUN',24,'24',true)],
];
$priorityOrder=[];
$priorityCapture=static function(array $selection)use(&$priorityOrder):array{
    $priorityOrder[]=$selection['local_id'];
    return ['status'=>'captured','surcharge'=>['status'=>'unavailable','fact'=>null]];
};
$allAllowed=static fn(array $selection,array $row):bool=>true;
$priorityAutosave=static fn(array $r,string $ref,int $generation):array=>['published'=>false,'reason'=>'no_final_price_ready_resolved_offers','readyOfferCount'=>0];
$priorityResult=AnyTourAndromedaLocalOfferCollectorV1::collect(
    $request,
    static fn(array $r):array=>['provider'=>'andromeda','search_ref'=>str_repeat('b',64),'pages_count'=>1,'status'=>'complete'],
    $priorityCohort,$allAllowed,$priorityCapture,$priorityAutosave,3
);
ok($priorityOrder===[23,24,22],'external freight must consume capture budget before unknown/false');
ok($priorityResult['surcharge_capture_attempts']===3,'priority capture bound');
ok($priorityResult['eligible_offers']===4,'priority keeps all candidates eligible');

$terminalOrder=[];$terminalAutosaveCalls=0;
$terminalCapture=static function(array $selection)use(&$terminalOrder):array{
    $terminalOrder[]=$selection['local_id'];
    if($selection['local_id']===23)throw new RuntimeException('ANDROMEDA_PACKAGE_OUTCOME_UNKNOWN');
    return ['status'=>'captured','surcharge'=>['status'=>'unavailable','fact'=>null]];
};
$terminalAutosave=static function(array $r,string $ref,int $generation)use(&$terminalAutosaveCalls):array{
    ++$terminalAutosaveCalls;
    return ['published'=>false,'reason'=>'no_final_price_ready_resolved_offers','readyOfferCount'=>0];
};
$terminalResult=AnyTourAndromedaLocalOfferCollectorV1::collect(
    $request,
    static fn(array $r):array=>['provider'=>'andromeda','search_ref'=>str_repeat('c',64),'pages_count'=>1,'status'=>'complete'],
    $priorityCohort,$allAllowed,$terminalCapture,$terminalAutosave,3
);
ok($terminalOrder===[23,24,22],'sealed package outcome must continue with disjoint candidates');
ok($terminalResult['surcharge_capture_attempts']===3,'terminal package outcome still consumes capture budget');
ok($terminalAutosaveCalls===1,'terminal per-offer package outcome must not skip autosave');

$invariantAutosaveCalls=0;$invariantThrown=false;
try{
    AnyTourAndromedaLocalOfferCollectorV1::collect(
        $request,
        static fn(array $r):array=>['provider'=>'andromeda','search_ref'=>str_repeat('d',64),'pages_count'=>1,'status'=>'complete'],
        $priorityCohort,$allAllowed,
        static function(array $selection):array{throw new RuntimeException('ANDROMEDA_PACKAGE_CHECKPOINT_FAILED');},
        static function(array $r,string $ref,int $generation)use(&$invariantAutosaveCalls):array{++$invariantAutosaveCalls;return ['published'=>false,'readyOfferCount'=>0];},
        1
    );
}catch(RuntimeException $error){$invariantThrown=$error->getMessage()==='ANDROMEDA_PACKAGE_CHECKPOINT_FAILED';}
ok($invariantThrown,'unexpected capture invariant must fail closed');
ok($invariantAutosaveCalls===0,'unexpected capture invariant must not autosave');

ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('ANEX')===false,'ANEX excluded');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('PEGAS Touristik')===false,'PEGAS excluded');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('Coral Travel')===false,'Coral excluded');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('Sunmar')===false,'Sunmar excluded');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('FUN&SUN')===true,'FUNSUN owned');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('Библио-Глобус')===true,'BG owned');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('Интурист')===true,'Intourist owned');

echo "ANDROMEDA_LOCAL_OFFER_COLLECTOR_OK pages=3 routing=1 capture_bound=2 ready=1 priority=1 terminal_continue=1 invariant_fail_closed=1 autosave=1\n";
