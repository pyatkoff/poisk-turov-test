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
$offer=static function(string $suffix,string $operator,int $local,string $opRef):array{
    return ['offer_ref'=>'offer_'.hash('sha256',$suffix),'operator'=>$operator,'operator_ref'=>$opRef,'local_hotel_id'=>$local];
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

ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('ANEX')===false,'ANEX excluded');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('PEGAS Touristik')===false,'PEGAS excluded');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('Coral Travel')===false,'Coral excluded');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('Sunmar')===false,'Sunmar excluded');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('FUN&SUN')===true,'FUNSUN owned');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('Библио-Глобус')===true,'BG owned');
ok(AnyTourAndromedaLocalOfferCollectorV1::ownsOperator('Интурист')===true,'Intourist owned');

echo "ANDROMEDA_LOCAL_OFFER_COLLECTOR_OK pages=3 routing=1 capture_bound=2 ready=1 autosave=1\n";
