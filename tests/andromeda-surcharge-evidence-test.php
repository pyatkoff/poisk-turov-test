<?php
declare(strict_types=1);
require_once __DIR__.'/../app/integrations/andromeda-surcharge-evidence.php';

function ok(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
function offer(string $id,string $amount,string $currency='RUB'):array{return [
    'provider'=>'andromeda','offer_ref'=>'offer_'.hash('sha256',$id),'operator_ref'=>'7','local_hotel_id'=>1,
    'check_in'=>'2026-10-30','nights'=>7,'adults'=>2,'children'=>0,
    'price'=>['amount'=>$amount,'currency'=>$currency],
    'transport_context'=>['freight_external'=>true,'program_ref'=>'101','tour_ref'=>'202','spo_ref'=>'spo-'.$id],
    'room_raw'=>'STD '.$id,'meal'=>['raw_label'=>'AI'],
];}
function fact(array $offer,string $surcharge='5000'):array{
    $base=$offer['price']['amount'];$sum=(string)((int)$base+(int)$surcharge);
    return ['schema_version'=>1,'provider'=>'andromeda','state'=>'estimated',
        'search_price'=>['amount'=>$base,'currency'=>$offer['price']['currency']],
        'party_surcharge'=>['amount'=>$surcharge,'currency'=>$offer['price']['currency'],'source'=>'andromeda_get_flights_transport'],
        'search_price_with_surcharge'=>['amount'=>$sum,'currency'=>$offer['price']['currency'],'source'=>'derived_search_estimate'],
        'surcharge_scope'=>'party','arithmetic_applied'=>true,'final_price_verified'=>false];
}
function program_fact(array $offer,string $surcharge='5000',string $aggregation='single_distinct_party_markup'):array{
    $f=fact($offer,$surcharge);
    $f['transport_markup_reported']=[
        'amount'=>$surcharge,'currency'=>$offer['price']['currency'],
        'source'=>'andromeda_get_flights_transport','aggregation'=>$aggregation,
    ];
    return $f;
}
$request=['params'=>['departureId'=>'1','countryId'=>'4']];
$source=offer('source','100000');
$evidence=AnyTourAndromedaSurchargeEvidenceV1::capture($source,$request,fact($source),1000,1300);
ok(is_array($evidence),'valid source fact captured');

$target=offer('target','110000');
$target['local_hotel_id']=99;$target['room_raw']='FAMILY';$target['meal']['raw_label']='UAI';
$applied=AnyTourAndromedaSurchargeEvidenceV1::apply($target,$request,$evidence,1100);
ok(is_array($applied),'same strict transport group reused across presentation variants');
ok($applied['search_price']===['amount'=>'110000','currency'=>'RUB'],'target base retained');
ok($applied['party_surcharge']['amount']==='5000','party surcharge reused once');
ok($applied['search_price_with_surcharge']['amount']==='115000','target total rebased');
ok($applied['final_price_verified']===false,'group evidence stays estimate only');

$spoOnly=$target;$spoOnly['transport_context']['spo_ref']='totally-different-spo';
ok(AnyTourAndromedaSurchargeEvidenceV1::apply($spoOnly,$request,$evidence,1100)!==null,'SPO outside proven group key');

foreach([
    'program'=>function(array $o){$o['transport_context']['program_ref']='102';return $o;},
    'tour'=>function(array $o){$o['transport_context']['tour_ref']='203';return $o;},
    'date'=>function(array $o){$o['check_in']='2026-10-31';return $o;},
    'nights'=>function(array $o){$o['nights']=8;return $o;},
    'party'=>function(array $o){$o['adults']=3;return $o;},
    'currency'=>function(array $o){$o['price']['currency']='USD';return $o;},
] as $name=>$change){
    ok(AnyTourAndromedaSurchargeEvidenceV1::apply($change($target),$request,$evidence,1100)===null,$name.' mismatch fail closed');
}
ok(AnyTourAndromedaSurchargeEvidenceV1::apply($target,['params'=>['departureId'=>'2','countryId'=>'4']],$evidence,1100)===null,'route mismatch fail closed');
ok(AnyTourAndromedaSurchargeEvidenceV1::apply($target,$request,$evidence,999)===null,'future evidence rejected');
ok(AnyTourAndromedaSurchargeEvidenceV1::apply($target,$request,$evidence,1300)===null,'expired evidence rejected');

$badFact=fact($source);$badFact['search_price_with_surcharge']['amount']='105001';
ok(AnyTourAndromedaSurchargeEvidenceV1::capture($source,$request,$badFact,1000,1300)===null,'tampered arithmetic rejected');
$wrongBase=fact($source);$wrongBase['search_price']['amount']='99999';
ok(AnyTourAndromedaSurchargeEvidenceV1::capture($source,$request,$wrongBase,1000,1300)===null,'source fact bound to source PRICE money');
$malformed=$source;unset($malformed['transport_context']['program_ref']);
ok(AnyTourAndromedaSurchargeEvidenceV1::capture($malformed,$request,fact($source),1000,1300)===null,'unkeyable source rejected');
ok(AnyTourAndromedaSurchargeEvidenceV1::capture($source,$request,fact($source),1000,1301)===null,'evidence TTL bounded');

// Program-fixed evidence is a separate explicit class. It deliberately ignores
// only nights while keeping every other transport/route/party discriminator.
$programEvidence=AnyTourAndromedaSurchargeEvidenceV1::captureProgramFixed(
    $source,$request,program_fact($source),1000,1300
);
ok(is_array($programEvidence),'program-fixed evidence captured');
ok(($programEvidence['reuse_scope']??null)==='program_fixed','program reuse scope explicit');
ok(str_starts_with($programEvidence['group_key'],'andromeda-program-surcharge-v1:'),'program evidence versioned separately');
foreach([10,14] as $nights){
    $crossNight=offer('cross-'.$nights,'120000');$crossNight['nights']=$nights;
    $cross=AnyTourAndromedaSurchargeEvidenceV1::apply($crossNight,$request,$programEvidence,1100);
    ok(is_array($cross),'program fixed failed cross-night '.$nights);
    ok(($cross['reuse_scope']??null)==='program_fixed','program applied scope '.$nights);
    ok($cross['search_price']['amount']==='120000'&&$cross['search_price_with_surcharge']['amount']==='125000','program rebase '.$nights);
    ok($cross['final_price_verified']===false,'program cross-night remains estimate');
}

// Choice-dependent minimum is explicitly not sufficient proof for broader scope.
$choiceFact=program_fact($source,'5000','minimum_complete_required_roundtrip_markup');
ok(AnyTourAndromedaSurchargeEvidenceV1::captureProgramFixed($source,$request,$choiceFact,1000,1300)===null,
    'choice-dependent minimum must not seed program-fixed evidence');
$noAggregation=fact($source);
ok(AnyTourAndromedaSurchargeEvidenceV1::captureProgramFixed($source,$request,$noAggregation,1000,1300)===null,
    'unclassified estimate must not seed program-fixed evidence');

// Exact family composition remains part of the program-fixed key, including ages.
$familySource=offer('family-source','130000');$familySource['children']=2;
$familyRequest=['params'=>['departureId'=>'1','countryId'=>'4','childs'=>[3,7]]];
$familyEvidence=AnyTourAndromedaSurchargeEvidenceV1::captureProgramFixed(
    $familySource,$familyRequest,program_fact($familySource,'4000'),1000,1300
);
ok(is_array($familyEvidence),'family program evidence captured');
$familyTarget=offer('family-target','140000');$familyTarget['children']=2;$familyTarget['nights']=14;
ok(AnyTourAndromedaSurchargeEvidenceV1::apply($familyTarget,['params'=>['departureId'=>'1','countryId'=>'4','childs'=>[7,3]]],$familyEvidence,1100)!==null,
    'same child-age multiset reuses across nights');
ok(AnyTourAndromedaSurchargeEvidenceV1::apply($familyTarget,['params'=>['departureId'=>'1','countryId'=>'4','childs'=>[7,4]]],$familyEvidence,1100)===null,
    'different child ages split program evidence');
$familyTarget['adults']=3;
ok(AnyTourAndromedaSurchargeEvidenceV1::apply($familyTarget,$familyRequest,$familyEvidence,1100)===null,
    'different adults split program evidence');

// Program-fixed still rejects all non-night dimensions that strict evidence rejects.
$programTarget=offer('program-target','150000');$programTarget['nights']=10;
foreach([
    'operator'=>function(array $o){$o['operator_ref']='8';return $o;},
    'program'=>function(array $o){$o['transport_context']['program_ref']='102';return $o;},
    'tour'=>function(array $o){$o['transport_context']['tour_ref']='203';return $o;},
    'date'=>function(array $o){$o['check_in']='2026-10-31';return $o;},
    'currency'=>function(array $o){$o['price']['currency']='USD';return $o;},
] as $name=>$change){
    ok(AnyTourAndromedaSurchargeEvidenceV1::apply($change($programTarget),$request,$programEvidence,1100)===null,
        'program-fixed '.$name.' mismatch fail closed');
}
ok(AnyTourAndromedaSurchargeEvidenceV1::apply($programTarget,['params'=>['departureId'=>'2','countryId'=>'4']],$programEvidence,1100)===null,
    'program-fixed departure mismatch fail closed');
ok(AnyTourAndromedaSurchargeEvidenceV1::apply($programTarget,$request,$programEvidence,1300)===null,
    'program-fixed expiry fail closed');

echo "ANDROMEDA_SURCHARGE_EVIDENCE_OK reuse=1 rebase=1 spo_invariant=1 strict_mismatch=7 stale=2 tamper=3 program_cross_night=2 program_choice_rejected=2 family_age_guard=3 program_other_guard=7\n";
