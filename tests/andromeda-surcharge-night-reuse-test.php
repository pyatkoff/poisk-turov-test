<?php
declare(strict_types=1);

require_once __DIR__.'/../app/integrations/andromeda-local-offer-collector.php';
require_once __DIR__.'/../app/integrations/andromeda-surcharge-evidence-store.php';

function night_ok(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
function night_offer(int $nights,int $id,int $base):array{return [
    'provider'=>'andromeda','offer_ref'=>'offer_'.hash('sha256','night-'.$id),'operator'=>'FUN&SUN','operator_ref'=>'315',
    'local_hotel_id'=>1000+$id,'check_in'=>'2026-10-07','nights'=>$nights,'adults'=>2,'children'=>0,
    'hotel'=>'Hotel '.$id,'room'=>'Room '.$id,'meal'=>$id%2?'AI':'UAI','price'=>['amount'=>(string)$base,'currency'=>'RUB'],
    'transport_context'=>['freight_external'=>true,'program_ref'=>'5','tour_ref'=>'3005','spo_ref'=>'spo-'.$id],
];}
function night_fact(array $offer):array{
    $base=$offer['price']['amount'];
    return ['schema_version'=>1,'provider'=>'andromeda','state'=>'estimated','search_price'=>$offer['price'],
        'transport_markup_reported'=>['amount'=>'15000','currency'=>'RUB','source'=>'andromeda_get_flights_transport','aggregation'=>'single_distinct_party_markup'],
        'party_surcharge'=>['amount'=>'15000','currency'=>'RUB','source'=>'andromeda_get_flights_transport'],
        'search_price_with_surcharge'=>['amount'=>(string)((int)$base+15000),'currency'=>'RUB','source'=>'derived_search_estimate'],
        'surcharge_scope'=>'party','arithmetic_applied'=>true,'final_price_verified'=>false];
}
function night_rows(array $offers):array{return array_map(static fn(array $offer):array=>['page'=>1,'offer'=>$offer],$offers);}
function night_collect(array $request,array $offers,callable $capture,callable $autosave,?callable $cache=null):array{
    return AnyTourAndromedaLocalOfferCollectorV1::collect($request,
        static fn(array $r):array=>['provider'=>'andromeda','search_ref'=>str_repeat('b',64),'pages_count'=>1,'status'=>'complete'],
        static fn(string $ref,int $generation):array=>night_rows($offers),
        static fn(array $selection,array $offer):bool=>true,
        $capture,$autosave,10,'all',0,null,$cache);
}

$request=['generation'=>91,'params'=>['departureId'=>'1','countryId'=>'4','childs'=>[]]];
$offers=[night_offer(7,1,100000),night_offer(10,2,120000),night_offer(14,3,140000)];
$input=$offers;$captures=0;$autosaves=0;
$capture=static function(array $selection)use(&$captures):array{++$captures;return ['status'=>'captured','surcharge'=>['status'=>'unavailable','fact'=>null]];};
$autosave=static function(array $r,string $ref,int $generation)use(&$autosaves,$request,$offers):array{
    ++$autosaves;night_ok($r===$request&&$generation===91,'autosave scope unchanged');
    return ['published'=>true,'readyOfferCount'=>0,'confirmationRequiredOfferCount'=>count($offers)];
};
$first=night_collect($request,$offers,$capture,$autosave);
night_ok($captures===1,'7/10/14 must consume one capture representative');
night_ok($first['reusable_surcharge_groups']===1&&$first['surcharge_group_duplicate_skips']===2,'7/10/14 one reusable group');
night_ok($first['capture_queue_offers']===1&&$first['surcharge_capture_attempts']===1,'one capture queue entry');
night_ok($offers===$input&&$autosaves===1,'tour facts and full autosave cohort preserved');

$root=sys_get_temp_dir().'/andromeda-night-reuse-'.bin2hex(random_bytes(5));$dir=$root.'/searches';mkdir($dir,0700,true);
$now=1800000000;
$write=static function(string $path,array $value):bool{return file_put_contents($path,json_encode($value,JSON_THROW_ON_ERROR))!==false;};
try{
    $evidence=AnyTourAndromedaSurchargeEvidenceV1::capture($offers[0],$request,night_fact($offers[0]),$now,$now+300);
    night_ok(is_array($evidence),'source evidence captured');
    night_ok(($evidence['reuse_basis']['aggregation']??null)==='single_distinct_party_markup','program reuse basis captured');
    AnyTourAndromedaSurchargeEvidenceStoreV1::save($dir,$evidence,[
        'source_sha'=>str_repeat('a',40),'source_search_ref'=>str_repeat('b',64),'source_offer_ref'=>$offers[0]['offer_ref']],$write);

    $cacheChecks=0;$captures=0;$applied=0;$autosaves=0;
    $cache=static function(array $selection,array $offer,array $r)use(&$cacheChecks,$dir,$now):bool{
        ++$cacheChecks;return AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($dir,$offer,$r,$now)!==null;
    };
    $saveAll=static function(array $r,string $ref,int $generation)use(&$autosaves,&$applied,$offers,$dir,$now):array{
        ++$autosaves;
        foreach($offers as $offer){
            $fact=AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($dir,$offer,$r,$now);
            night_ok(is_array($fact),'cached surcharge applies to '.$offer['nights'].' nights');
            night_ok($fact['search_price']===$offer['price'],'own base retained for '.$offer['nights'].' nights');
            night_ok($fact['search_price_with_surcharge']['amount']===(string)((int)$offer['price']['amount']+15000),'surcharge added once');
            night_ok($fact['final_price_verified']===false&&!isset($fact['fuel_surcharge']),'estimate never promoted to fuel/final verified');
            ++$applied;
        }
        return ['published'=>true,'readyOfferCount'=>0,'confirmationRequiredOfferCount'=>count($offers)];
    };
    $second=night_collect($request,$offers,$capture,$saveAll,$cache);
    night_ok($cacheChecks===1&&$second['surcharge_cache_hits']===1,'one group cache hit');
    night_ok($captures===0&&$second['surcharge_capture_attempts']===0,'cache hit avoids all supplier captures');
    night_ok($applied===3&&$autosaves===1,'one cached surcharge reused for all durations');

    $different=[];
    $v=$offers[1];$v['operator_ref']='342';$different[]=$v;
    $v=$offers[1];$v['transport_context']['program_ref']='6';$different[]=$v;
    $v=$offers[1];$v['transport_context']['tour_ref']='3006';$different[]=$v;
    $v=$offers[1];$v['check_in']='2026-10-08';$different[]=$v;
    $v=$offers[1];$v['price']['currency']='USD';$different[]=$v;
    $v=$offers[1];$v['adults']=3;$different[]=$v;
    foreach($different as $variant)night_ok(AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($dir,$variant,$request,$now)===null,'strict non-night mismatch rejected');
    $route=$request;$route['params']['departureId']='2';
    night_ok(AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($dir,$offers[1],$route,$now)===null,'route mismatch rejected');
    night_ok(AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($dir,$offers[1],$request,$now+300)===null,'expired cache rejected');

    $family=night_offer(10,9,160000);$family['children']=2;$familyRequest=$request;$familyRequest['params']['childs']=[3,7];
    $familyEvidence=AnyTourAndromedaSurchargeEvidenceV1::capture($family,$familyRequest,night_fact($family),$now,$now+300);
    night_ok(is_array($familyEvidence),'family evidence captured');
    AnyTourAndromedaSurchargeEvidenceStoreV1::save($dir,$familyEvidence,[
        'source_sha'=>str_repeat('c',40),'source_search_ref'=>str_repeat('d',64),'source_offer_ref'=>$family['offer_ref']],$write);
    $otherAges=$familyRequest;$otherAges['params']['childs']=[3,8];
    night_ok(AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($dir,$family,$otherAges,$now)===null,'child ages remain strict');
}finally{
    foreach(glob($dir.'/*')?:[] as $path)@unlink($path);@rmdir($dir);@rmdir($root);
}

echo "ANDROMEDA_SURCHARGE_NIGHT_REUSE_OK durations=3 groups=1 uncached_capture=1 cached_capture=0 cache_hits=1 applications=3 reusable_basis=1 supplier_http=0 live_db=0\n";
