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
$offer=static function(string $suffix,string $operator,int $local,string $opRef,?bool $freight=null,?string $program=null,?string $tour=null,?string $spo=null):array{
    $row=[
        'offer_ref'=>'offer_'.hash('sha256',$suffix),
        'provider'=>'andromeda',
        'operator'=>$operator,
        'operator_ref'=>$opRef,
        'local_hotel_id'=>$local,
        'check_in'=>'2026-10-30',
        'nights'=>7,
        'adults'=>2,
        'children'=>0,
        'price'=>['final'=>100000+$local,'currency'=>'RUB'],
    ];
    if($freight!==null||$program!==null||$tour!==null||$spo!==null){
        $row['transport_context']=[];
        if($freight!==null)$row['transport_context']['freight_external']=$freight;
        if($program!==null)$row['transport_context']['program_ref']=$program;
        if($tour!==null)$row['transport_context']['tour_ref']=$tour;
        if($spo!==null)$row['transport_context']['spo_ref']=$spo;
    }
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
ok($result['status']==='complete'&&$result['autosave']===['published'=>true,'reason'=>null,'readyOfferCount'=>1],'published autosave completes collector');

$drainedCaptureCalls=0;$drainedAutosaveCalls=0;
$drained=AnyTourAndromedaLocalOfferCollectorV1::collect(
    $request,
    static fn(array $r):array=>[
        'provider'=>'andromeda','search_ref'=>str_repeat('f',64),
        'page'=>3,'pages_count'=>3,'status'=>'partial','grouped'=>true,
        'first_page_only'=>false,'external_search_pending'=>false,
        'received_offers'=>5,'mapped_offers'=>5,
    ],
    $cohort,
    static fn(array $selection,array $row):bool=>true,
    static function(array $selection)use(&$drainedCaptureCalls):array{
        ++$drainedCaptureCalls;
        return ['status'=>'captured','surcharge'=>['status'=>'unavailable','fact'=>null]];
    },
    static function(array $r,string $ref,int $generation)use(&$drainedAutosaveCalls):array{
        ++$drainedAutosaveCalls;
        return ['published'=>false,'reason'=>'no_final_price_ready_resolved_offers','readyOfferCount'=>0];
    },
    1
);
ok($drained['pages']===3 && $drainedCaptureCalls===1 && $drainedAutosaveCalls===1,'drained partial aggregate accepted');
ok($drained['status']==='incomplete','unpublished drained cohort must not report complete');

foreach ([
    ['page'=>2,'pages_count'=>3,'status'=>'partial','grouped'=>true,'first_page_only'=>false,'external_search_pending'=>false,'received_offers'=>5,'mapped_offers'=>5],
    ['page'=>1,'pages_count'=>1,'status'=>'partial','grouped'=>true,'first_page_only'=>false,'external_search_pending'=>false,'received_offers'=>1,'mapped_offers'=>1],
    ['page'=>3,'pages_count'=>3,'status'=>'partial','grouped'=>false,'first_page_only'=>false,'external_search_pending'=>false,'received_offers'=>5,'mapped_offers'=>5],
    ['page'=>3,'pages_count'=>3,'status'=>'partial','grouped'=>true,'first_page_only'=>false,'external_search_pending'=>true,'received_offers'=>5,'mapped_offers'=>5],
] as $shape) {
    $partialThrown=false;$shape['provider']='andromeda';$shape['search_ref']=str_repeat('e',64);
    try {
        AnyTourAndromedaLocalOfferCollectorV1::collect(
            $request,
            static fn(array $r):array=>$shape,
            static function(string $ref,int $generation):array{throw new RuntimeException('cohort_must_not_load');},
            static fn(array $selection,array $row):bool=>true,
            static function(array $selection):array{throw new RuntimeException('capture_must_not_run');},
            static function(array $r,string $ref,int $generation):array{throw new RuntimeException('autosave_must_not_run');},
            1
        );
    } catch (RuntimeException $error) {
        $partialThrown=$error->getMessage()==='ANDROMEDA_LOCAL_COLLECTOR_SEARCH';
    }
    ok($partialThrown,'ambiguous partial aggregate must fail closed');
}

$emptyPage=[
    'provider'=>'andromeda','search_ref'=>str_repeat('0',64),'generation'=>7,
    'page'=>1,'pages_count'=>0,'status'=>'complete','hotels'=>[],
    'grouped'=>true,'first_page_only'=>false,'external_search_pending'=>false,
    'received_offers'=>0,'mapped_offers'=>0,'selection_enabled'=>false,
];
$emptyPageBefore=$emptyPage;$emptyRequestBefore=$request;
$emptySaveReceipts=[
    ['published'=>true,'reason'=>null,'readyOfferCount'=>0],
    ['published'=>false,'reason'=>'already_published','readyOfferCount'=>0],
];
$emptyMustNotRun=static function():array{throw new RuntimeException('empty_offer_callback_must_not_run');};
foreach(['all','non_external_only','external_group_only'] as $mode)foreach($emptySaveReceipts as $saveReceipt){
    $emptyOrder=[];
    $emptyResult=AnyTourAndromedaLocalOfferCollectorV1::collect(
        $request,
        static function(array $r)use($request,$emptyPage,&$emptyOrder):array{
            ok($r===$request,'empty search preserves exact request');
            $emptyOrder[]='search';return $emptyPage;
        },
        static function(string $ref,int $generation)use($emptyPage,&$emptyOrder):array{
            ok($ref===$emptyPage['search_ref']&&$generation===$emptyPage['generation'],'empty cohort binding');
            $emptyOrder[]='load';return [];
        },
        $emptyMustNotRun,$emptyMustNotRun,
        static function(array $r,string $ref,int $generation)use($request,$emptyPage,$saveReceipt,&$emptyOrder):array{
            ok($r===$request&&$ref===$emptyPage['search_ref']&&$generation===7,'empty autosave binding');
            $emptyOrder[]='autosave';return $saveReceipt;
        },
        2,$mode
    );
    ok($emptyOrder===['search','load','autosave'],'terminal empty reaches autosave once without captures');
    ok($emptyResult['status']==='complete'&&$emptyResult['pages']===0,'terminal empty metadata');
    foreach(['received_offers','owned_operator_offers','eligible_offers','capture_queue_offers',
        'surcharge_capture_attempts','surcharge_ready','ready_offer_count','booking_calls'] as $key){
        ok($emptyResult[$key]===0,'terminal empty keeps zero '.$key);
    }
    ok($emptyResult['capture_mode']===$mode&&$emptyResult['selection_authority']===false,'empty no selection authority');
    ok($emptyResult['autosave_published']===$saveReceipt['published']
        &&$emptyResult['autosave_reason']===$saveReceipt['reason'],'empty never promotes an unpublished receipt');
    ok($emptyResult['autosave']===$saveReceipt,'empty preserves autosave receipt');
}
ok($emptyPage===$emptyPageBefore&&$request===$emptyRequestBefore,'empty input immutability');

$persistenceCases=[
    [['published'=>true,'reason'=>null,'readyOfferCount'=>3],'complete'],
    [['published'=>false,'reason'=>'already_published','readyOfferCount'=>3],'complete'],
    [['published'=>false,'reason'=>'autosave_failed','readyOfferCount'=>0,'failedOfferCount'=>2],'incomplete'],
    [['published'=>false,'reason'=>'runtime_dependency_unavailable','readyOfferCount'=>0],'incomplete'],
    [['published'=>false,'reason'=>'local_ingest_unavailable','readyOfferCount'=>0],'incomplete'],
    [['published'=>false,'readyOfferCount'=>0],'incomplete'],
    [[], 'incomplete'],
];
foreach($persistenceCases as [$saveReceipt,$expectedStatus]){
    $persistenceAutosaveCalls=0;
    $persistenceResult=AnyTourAndromedaLocalOfferCollectorV1::collect(
        $request,static fn(array $r):array=>$emptyPage,
        static fn(string $ref,int $generation):array=>[],
        $emptyMustNotRun,$emptyMustNotRun,
        static function(array $r,string $ref,int $generation)use($saveReceipt,&$persistenceAutosaveCalls):array{
            ++$persistenceAutosaveCalls;return $saveReceipt;
        },1
    );
    ok($persistenceAutosaveCalls===1,'persistence receipt autosave once');
    ok($persistenceResult['status']===$expectedStatus,'persistence receipt completion status');
    ok($persistenceResult['autosave']===$saveReceipt,'persistence receipt preserved exactly');
}

$invalidEmptyPages=[];
foreach([
    'provider'=>['anex',null], 'search_ref'=>['bad',null],
    'generation'=>[8,'7',null], 'page'=>[0,2,'1',null],
    'pages_count'=>[-1,'0',false,null], 'status'=>['partial','pending',null],
    'hotels'=>[[['local_id'=>1]],null], 'grouped'=>[false,null],
    'first_page_only'=>[true,null], 'external_search_pending'=>[true,null],
    'received_offers'=>[1,-1,'0',null], 'mapped_offers'=>[1,-1,'0',null],
] as $field=>$values){
    foreach($values as $value){$shape=$emptyPage;$shape[$field]=$value;$invalidEmptyPages[]=$shape;}
    $shape=$emptyPage;unset($shape[$field]);$invalidEmptyPages[]=$shape;
}
foreach($invalidEmptyPages as $shape){
    $invalidEmptyThrown=false;
    try{
        AnyTourAndromedaLocalOfferCollectorV1::collect(
            $request,static fn(array $r):array=>$shape,
            $emptyMustNotRun,$emptyMustNotRun,$emptyMustNotRun,$emptyMustNotRun,1
        );
    }catch(RuntimeException $error){$invalidEmptyThrown=$error->getMessage()==='ANDROMEDA_LOCAL_COLLECTOR_SEARCH';}
    ok($invalidEmptyThrown,'unproven zero-page result must fail before autosave');
}

foreach([
    [['page'=>1,'offer'=>$offer('empty-owned','FUN&SUN',12,'7')]],
    [['page'=>1,'offer'=>$offer('empty-excluded','ANEX',11,'5')]],
    [[]], ['malformed'], ['not_a_list'=>[]],
] as $rows){
    $nonemptyThrown=false;
    try{
        AnyTourAndromedaLocalOfferCollectorV1::collect(
            $request,static fn(array $r):array=>$emptyPage,
            static fn(string $ref,int $generation):array=>$rows,
            $emptyMustNotRun,$emptyMustNotRun,$emptyMustNotRun,1
        );
    }catch(RuntimeException $error){$nonemptyThrown=$error->getMessage()==='ANDROMEDA_LOCAL_COLLECTOR_COHORT';}
    ok($nonemptyThrown,'contradictory retained cohort must not autosave');
}

$priorityCohort=static fn(string $ref,int $generation):array=>[
    ['page'=>1,'offer'=>$offer('p-false','FUN&SUN',21,'21',false,'pf','tf')],
    ['page'=>1,'offer'=>$offer('p-unknown','Библио-Глобус',22,'22',null,'pu','tu')],
    ['page'=>1,'offer'=>$offer('p-true-a','Интурист',23,'23',true,'p1','t1','spo-a')],
    ['page'=>1,'offer'=>$offer('p-true-b','Интурист',24,'23',true,'p1','t1','spo-b')],
    ['page'=>1,'offer'=>$offer('p-true-c','Интурист',25,'23',true,'p2','t2','spo-c')],
    ['page'=>1,'offer'=>$offer('p-true-d','FUN&SUN',26,'24',true,'p3','t3','spo-d')],
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
ok($priorityOrder===[23,25,26],'strict external surcharge groups must consume capture budget before same-group SPO duplicate');
ok($priorityResult['surcharge_capture_attempts']===3,'priority capture bound');
ok($priorityResult['eligible_offers']===6,'priority keeps all candidates eligible');
ok($priorityResult['status']==='incomplete','unpublished populated cohort must not report complete');

$strictBase=$offer('strict-a','Интурист',30,'30',true,'p4','t4','spo-a');
$strictSpo=$offer('strict-b','Интурист',31,'30',true,'p4','t4','spo-b');
$strictNights=$offer('strict-c','Интурист',32,'30',true,'p4','t4','spo-c');$strictNights['nights']=8;
$strictDate=$offer('strict-d','Интурист',33,'30',true,'p4','t4','spo-d');$strictDate['check_in']='2026-10-31';
$strictCurrency=$offer('strict-e','Интурист',34,'30',true,'p4','t4','spo-e');$strictCurrency['price']['currency']='USD';
$strictCohort=static fn(string $ref,int $generation):array=>[
    ['page'=>1,'offer'=>$strictBase],['page'=>1,'offer'=>$strictSpo],
    ['page'=>1,'offer'=>$strictNights],['page'=>1,'offer'=>$strictDate],['page'=>1,'offer'=>$strictCurrency],
];
$strictOrder=[];
AnyTourAndromedaLocalOfferCollectorV1::collect(
    $request,
    static fn(array $r):array=>['provider'=>'andromeda','search_ref'=>str_repeat('7',64),'pages_count'=>1,'status'=>'complete'],
    $strictCohort,$allAllowed,
    static function(array $selection)use(&$strictOrder):array{
        $strictOrder[]=$selection['local_id'];
        return ['status'=>'captured','surcharge'=>['status'=>'unavailable','fact'=>null]];
    },
    $priorityAutosave,4
);
ok($strictOrder===[30,33,34],'nights and SPO share one group while date/currency remain distinct');

$externalProbeOrder=[];
$externalProbe=AnyTourAndromedaLocalOfferCollectorV1::collect(
    $request,
    static fn(array $r):array=>['provider'=>'andromeda','search_ref'=>str_repeat('5',64),'pages_count'=>1,'status'=>'complete'],
    $priorityCohort,$allAllowed,
    static function(array $selection)use(&$externalProbeOrder):array{
        $externalProbeOrder[]=$selection['local_id'];
        return ['status'=>'captured','surcharge'=>['status'=>'unavailable','fact'=>null]];
    },
    $priorityAutosave,30,'external_group_only'
);
ok($externalProbeOrder===[23],'external probe selects exactly one strict external group');
ok($externalProbe['capture_mode']==='external_group_only'
    &&$externalProbe['capture_queue_offers']===1
    &&$externalProbe['reusable_surcharge_groups']===1
    &&$externalProbe['surcharge_capture_attempts']===1,
    'external probe has one representative and one attempt even with a larger caller budget');

$externalProbeCacheChecks=0;$externalProbeCaptured=0;
$externalProbeHit=AnyTourAndromedaLocalOfferCollectorV1::collect(
    $request,
    static fn(array $r):array=>['provider'=>'andromeda','search_ref'=>str_repeat('4',64),'pages_count'=>1,'status'=>'complete'],
    $priorityCohort,$allAllowed,
    static function(array $selection)use(&$externalProbeCaptured):array{
        ++$externalProbeCaptured;
        return ['status'=>'captured','surcharge'=>['status'=>'unavailable','fact'=>null]];
    },
    $priorityAutosave,1,'external_group_only',0,null,
    static function(array $selection,array $row,array $req)use(&$externalProbeCacheChecks):bool{
        ++$externalProbeCacheChecks;return true;
    }
);
ok($externalProbeCacheChecks===1&&$externalProbeCaptured===0,'external probe cache hit prevents supplier capture');
ok($externalProbeHit['surcharge_cache_hits']===1
    &&$externalProbeHit['surcharge_cache_covered_offers']===2
    &&$externalProbeHit['surcharge_capture_attempts']===0,
    'external probe cache hit covers siblings and never falls through to another group');

$malformedA=$offer('malformed-a','Интурист',40,'40',true,'p5','t5','spo-a');unset($malformedA['check_in']);
$malformedB=$offer('malformed-b','Интурист',41,'40',true,'p5','t5','spo-b');unset($malformedB['check_in']);
$malformedDistinct=$offer('malformed-c','Интурист',42,'40',true,'p6','t6','spo-c');
$malformedCohort=static fn(string $ref,int $generation):array=>[
    ['page'=>1,'offer'=>$malformedA],['page'=>1,'offer'=>$malformedB],['page'=>1,'offer'=>$malformedDistinct],
];
$malformedOrder=[];
AnyTourAndromedaLocalOfferCollectorV1::collect(
    $request,
    static fn(array $r):array=>['provider'=>'andromeda','search_ref'=>str_repeat('6',64),'pages_count'=>1,'status'=>'complete'],
    $malformedCohort,$allAllowed,
    static function(array $selection)use(&$malformedOrder):array{
        $malformedOrder[]=$selection['local_id'];
        return ['status'=>'captured','surcharge'=>['status'=>'unavailable','fact'=>null]];
    },
    $priorityAutosave,3
);
ok($malformedOrder===[40,41,42],'unkeyable external rows must remain unique fail-closed buckets');

$nonExternalOrder=[];$nonExternalReady=0;
$massCohort=static function(string $ref,int $generation)use($offer):array{
    $rows=[];
    for($i=1;$i<=12;++$i){
        $rows[]=['page'=>1,'offer'=>$offer('mass-false-'.$i,'FUN&SUN',100+$i,'31',false,'pf'.($i%3),'tf'.($i%4))];
    }
    $rows[]=['page'=>1,'offer'=>$offer('mass-true','Интурист',200,'32',true,'px','tx')];
    $rows[]=['page'=>1,'offer'=>$offer('mass-unknown','Библио-Глобус',201,'33',null,'pu','tu')];
    return $rows;
};
$massCapture=static function(array $selection)use(&$nonExternalOrder,&$nonExternalReady):array{
    $nonExternalOrder[]=$selection['local_id'];++$nonExternalReady;
    return ['status'=>'captured','surcharge'=>[
        'status'=>'complete','fact'=>null,'final_price_verified'=>true
    ]];
};
$massAutosave=static fn(array $r,string $ref,int $generation):array=>[
    'published'=>true,'reason'=>null,'readyOfferCount'=>12
];
$massResult=AnyTourAndromedaLocalOfferCollectorV1::collect(
    $request,
    static fn(array $r):array=>['provider'=>'andromeda','search_ref'=>str_repeat('9',64),'pages_count'=>1,'status'=>'complete'],
    $massCohort,$allAllowed,$massCapture,$massAutosave,12,'non_external_only'
);
ok(count($nonExternalOrder)===12,'nonexternal mass captured all false rows');
ok(!in_array(200,$nonExternalOrder,true)&&!in_array(201,$nonExternalOrder,true),'nonexternal mode excluded external and unknown');
ok($massResult['capture_mode']==='non_external_only'&&$massResult['capture_queue_offers']===12,'nonexternal queue metadata');
ok($massResult['surcharge_ready']===12&&$massResult['ready_offer_count']===12,'verified calc counts as ready');

$budgetOrder=[];$budgetAutosaveCalls=0;$budgetTicks=[100.0,120.0,311.0];
$budgetClock=static function()use(&$budgetTicks):float{
    if($budgetTicks===[])throw new RuntimeException('unexpected_clock_read');
    return array_shift($budgetTicks);
};
$budgetResult=AnyTourAndromedaLocalOfferCollectorV1::collect(
    $request,
    static fn(array $r):array=>['provider'=>'andromeda','search_ref'=>str_repeat('8',64),'pages_count'=>1,'status'=>'complete'],
    $massCohort,$allAllowed,
    static function(array $selection)use(&$budgetOrder):array{
        $budgetOrder[]=$selection['local_id'];
        return ['status'=>'captured','surcharge'=>['status'=>'complete','fact'=>null,'final_price_verified'=>true]];
    },
    static function(array $r,string $ref,int $generation)use(&$budgetAutosaveCalls):array{
        ++$budgetAutosaveCalls;
        return ['published'=>true,'reason'=>null,'readyOfferCount'=>2];
    },
    12,'non_external_only',210,$budgetClock
);
ok(count($budgetOrder)===2,'time budget must stop before third supplier candidate');
ok($budgetAutosaveCalls===1,'time budget must still autosave once');
ok($budgetResult['surcharge_capture_attempts']===2&&$budgetResult['surcharge_ready']===2,'time budget counts only attempted captures');
ok($budgetResult['capture_time_budget_seconds']===210&&$budgetResult['capture_time_budget_exhausted']===true,'time budget metadata');

$budgetInputThrown=false;
try{
    AnyTourAndromedaLocalOfferCollectorV1::collect(
        $request,$search,$cohort,$allow,$capture,$autosave,2,'all',241
    );
}catch(InvalidArgumentException $error){$budgetInputThrown=$error->getMessage()==='ANDROMEDA_LOCAL_COLLECTOR_INPUT';}
ok($budgetInputThrown,'time budget above freshness-safe CLI contract rejected');

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
ok($terminalOrder===[23,25,26],'sealed package outcome must continue with distinct disjoint transport groups');
ok($terminalResult['surcharge_capture_attempts']===3,'terminal package outcome still consumes capture budget');
ok($terminalAutosaveCalls===1,'terminal per-offer package outcome must not skip autosave');
ok($terminalResult['status']==='incomplete','terminal captures do not override failed persistence');

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

$cliSource=file_get_contents(__DIR__.'/../scripts/ops/andromeda_local_offer_collect.php');
ok(is_string($cliSource)&&str_contains($cliSource,'if (($result[\'status\'] ?? null) !== \'complete\') exit(1);'),'CLI must propagate incomplete collector status after printing receipt');

ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('ANEX')===false,'ANEX excluded');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('PEGAS Touristik')===false,'PEGAS excluded');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('Coral Travel')===false,'Coral excluded');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('Sunmar')===false,'Sunmar excluded');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('FUN&SUN')===true,'FUNSUN owned');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('Библио-Глобус')===true,'BG owned');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('Интурист')===true,'Intourist owned');

echo "ANDROMEDA_LOCAL_OFFER_COLLECTOR_OK pages=3 routing=1 capture_bound=2 ready=1 drained_partial=1 partial_fail_closed=4 night_independent_grouping=1 malformed_unique=1 nonexternal_mass=1 time_budget=1 terminal_continue=1 invariant_fail_closed=1 autosave=1 persistence_fail_closed=7 cli_exit_guard=1 external_group_probe=2 terminal_empty=6 empty_fail_closed=51\n";
