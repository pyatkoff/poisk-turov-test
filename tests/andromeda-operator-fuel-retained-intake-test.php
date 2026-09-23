<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/andromeda-operator-fuel-retained-intake.php';

function afi_ok(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function afi_hash(string $v):string{return hash('sha256',$v);}
function afi_reject(array $v,string $m):void{
    try{AnyTourAndromedaOperatorFuelRetainedIntakeV1::observation($v);}
    catch(InvalidArgumentException|DomainException $e){return;}
    throw new RuntimeException($m);
}
function afi_flight(string $uid,string $group,string $direction,string $from,string $to,string $flight):array{
    return [
        'uid'=>$uid,'groupId'=>$group,'direction'=>$direction,'type'=>'ttAvia',
        'departure'=>[['port'=>$from]],'arrival'=>[['port'=>$to]],
        'details'=>[['detail'=>[[
            'flight_number'=>$flight,
            'marketing_airline'=>'ZF',
            'departureAirportCode'=>$from,
            'arrivalAirportCode'=>$to,
        ]]]],
    ];
}
function afi_claim(bool $explicit=true):array{
    $fuel0=['servicecategoryName'=>'Топливный сбор','price'=>'170','currencyAlias'=>'EUR','routeIndex'=>'0','required'=>'true','packet'=>'false'];
    $fuel1=['servicecategoryName'=>'Топливный сбор','price'=>'170','currencyAlias'=>'EUR','routeIndex'=>'1','required'=>'true','packet'=>'false'];
    if($explicit){
        $fuel0['unit']=$fuel1['unit']='party';
        $fuel0['included']=$fuel1['included']=false;
    }
    return [
        'claimDocument'=>[0=>[
            'services'=>[['service'=>[$fuel0,$fuel1]]],
            'moneys'=>[['money'=>[
                ['currency'=>'EUR','rate'=>'1','isClaimCurrency'=>'true'],
                ['currency'=>'RUB','rate'=>'99.49','isClaimCurrency'=>'false'],
            ]]],
        ]],
        'groups'=>[['group'=>[
            ['id'=>'g0','required'=>'true','oneItem'=>'true'],
            ['id'=>'g1','required'=>'true','oneItem'=>'true'],
        ]]],
        'variants'=>[['transports'=>[['transport'=>[
            afi_flight('out1','g0','0','VKO','AYT','ZF1001'),
            afi_flight('back1','g1','1','AYT','VKO','ZF1002'),
        ]]]]],
    ];
}
function afi_retained(string $offerRef,string $response,bool $explicit=true):array{
    return [
        'offer'=>[
            'provider'=>'andromeda','offer_ref'=>$offerRef,'operator'=>'Интурист',
            'adults'=>2,'children'=>0,'hotel'=>'A','nights'=>7,
        ],
        'party'=>['adults'=>2,'children'=>0,'child_ages'=>[]],
        'market'=>'RU-MOW',
        'direction'=>['market'=>'departure:1','destination'=>'country:4'],
        'claim'=>afi_claim($explicit),
        'source_response_sha256'=>afi_hash($response),
        'observed_at'=>1000,'expires_at'=>5000,
        'valid_from'=>'2026-10-01','valid_to'=>'2026-10-31',
    ];
}

$r1=afi_retained('offer_'.str_repeat('a',64),'claim-1',true);
$o1=AnyTourAndromedaOperatorFuelRetainedIntakeV1::observation($r1);
afi_ok($o1['operator_family']==='intourist','operator');
afi_ok($o1['amount']==='340'&&$o1['currency']==='EUR','native total');
afi_ok($o1['unit']==='party_roundtrip'&&$o1['base_relation']==='excluded','explicit arithmetic semantics');
afi_ok($o1['base_includes_other_required_charges']===true,'other required clear');
afi_ok($o1['scope']['outbound']===['origin'=>'VKO','destination'=>'AYT','carrier'=>'ZF','flight'=>'ZF1001'],'outbound exact');
afi_ok($o1['scope']['return']===['origin'=>'AYT','destination'=>'VKO','carrier'=>'ZF','flight'=>'ZF1002'],'return exact');
afi_ok($o1['direction']===['operator_family'=>'intourist','market'=>'departure:1','destination'=>'country:4'],'canonical mass direction retained');
afi_ok(($o1['exchange']['rate']??null)==='99.49'&&($o1['exchange']['from']??null)==='EUR','supplier claim FX retained');
$legacy=$r1;unset($legacy['direction']);
$legacyObs=AnyTourAndromedaOperatorFuelRetainedIntakeV1::observation($legacy);
afi_ok($legacyObs['direction']['destination']==='AYT','legacy no-direction observation remains airport-derived');

$r2=afi_retained('offer_'.str_repeat('b',64),'claim-2',true);
$r2['offer']['hotel']='Different hotel';
$r2['offer']['nights']=14;
$o2=AnyTourAndromedaOperatorFuelRetainedIntakeV1::observation($r2);
afi_ok($o2['scope']===$o1['scope'],'hotel nights do not split');
afi_ok($o2['offer_ref_digest']!==$o1['offer_ref_digest']&&$o2['evidence_sha256']!==$o1['evidence_sha256'],'independent evidence');

$target=[
    'provider'=>'andromeda','operator'=>'Интурист',
    'search_params'=>['departureId'=>1,'countryId'=>4],
    'party'=>$o1['scope']['party'],
    'offer_ref_digest'=>afi_hash('target'),
];
$confirmed=AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target,[$o1,$o2],2000);
afi_ok($confirmed!==null,'two independent SAMO observations confirm');
afi_ok(($confirmed['exchange']['rate']??null)==='99.49','confirmed direction carries current supplier FX');

$perPerson=afi_retained('offer_'.str_repeat('9',64),'claim-person',true);
foreach($perPerson['claim']['claimDocument'][0]['services'][0]['service'] as &$fuelRow){
    $fuelRow['price']='85';$fuelRow['unit']='person';
}
unset($fuelRow);
$perPersonObs=AnyTourAndromedaOperatorFuelRetainedIntakeV1::observation($perPerson);
afi_ok($perPersonObs['unit']==='per_person_one_way','per-person unit retained');
afi_ok($perPersonObs['amount']==='85'&&$perPersonObs['currency']==='EUR','per-person stores one-leg rate, not two-leg sum');
afi_ok($perPersonObs['base_relation']==='excluded','per-person relation retained');
afi_ok(($perPersonObs['exchange']['rate']??null)==='99.49','per-person observation carries typed FX');

$noFx=afi_retained('offer_'.str_repeat('8',64),'claim-no-fx',true);
unset($noFx['claim']['claimDocument'][0]['moneys']);
$noFxObs=AnyTourAndromedaOperatorFuelRetainedIntakeV1::observation($noFx);
afi_ok(!array_key_exists('exchange',$noFxObs),'missing supplier FX stays absent and does not invent rate');

$unknown=AnyTourAndromedaOperatorFuelRetainedIntakeV1::observation(
    afi_retained('offer_'.str_repeat('c',64),'claim-3',false)
);
afi_ok($unknown['unit']==='route_reported_unknown'&&$unknown['base_relation']==='unknown','unknown semantics preserved');
afi_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target,[$unknown,$o2],2000)===null,'unknown cannot confirm');

$ambiguous=afi_retained('offer_'.str_repeat('d',64),'claim-4',true);
$ambiguous['claim']['variants'][0]['transports'][0]['transport'][]=afi_flight('out2','g0','0','VKO','AYT','ZF9999');
afi_reject($ambiguous,'ambiguous flight accepted');

$otherCharge=afi_retained('offer_'.str_repeat('e',64),'claim-5',true);
$otherCharge['claim']['claimDocument'][0]['services'][0]['service'][]=[
    'servicecategoryName'=>'Страховка','price'=>'10','currencyAlias'=>'EUR','required'=>'true','packet'=>'false'
];
$otherObs=AnyTourAndromedaOperatorFuelRetainedIntakeV1::observation($otherCharge);
afi_ok($otherObs['base_includes_other_required_charges']===false,'other required not hidden');
afi_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target,[$o1,$otherObs],2000)===null,'other required must not confirm');

$different=afi_retained('offer_'.str_repeat('f',64),'claim-6',true);
$different['claim']['variants'][0]['transports'][0]['transport'][0]['details'][0]['detail'][0]['flight_number']='ZF9999';
$o3=AnyTourAndromedaOperatorFuelRetainedIntakeV1::observation($different);
afi_ok($o3['scope']!==$o1['scope'],'different flight separate scope');

$dir=sys_get_temp_dir().'/andromeda-fuel-store-'.bin2hex(random_bytes(5)).'/searches';
mkdir($dir,0700,true);
$write=static function(string $path,array $value):bool{
    return file_put_contents($path,json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX)!==false;
};
$a=AnyTourAndromedaOperatorFuelRetainedIntakeV1::append($dir,$r1,$write);
$b=AnyTourAndromedaOperatorFuelRetainedIntakeV1::append($dir,$r2,$write);
afi_ok($a['written']===true&&$b['written']===true&&$b['observationCount']===2,'existing store append');
$input=AnyTourOperatorFuelRuleStoreV1::inputForTarget($dir,$target,2000);
afi_ok($input!==null&&count($input['observations'])===2,'existing store emits confirmed input');
foreach(glob($dir.'/*')?:[] as $p)unlink($p);rmdir($dir);rmdir(dirname($dir));

echo "PASS retained Andromeda operator fuel intake\n";