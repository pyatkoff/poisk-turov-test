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
function fact(array $offer,string $surcharge='5000',string $aggregation='single_distinct_party_markup'):array{
    $base=$offer['price']['amount'];$sum=(string)((int)$base+(int)$surcharge);
    return ['schema_version'=>1,'provider'=>'andromeda','state'=>'estimated',
        'search_price'=>['amount'=>$base,'currency'=>$offer['price']['currency']],
        'transport_markup_reported'=>['amount'=>$surcharge,'currency'=>$offer['price']['currency'],
            'source'=>'andromeda_get_flights_transport','aggregation'=>$aggregation],
        'party_surcharge'=>['amount'=>$surcharge,'currency'=>$offer['price']['currency'],'source'=>'andromeda_get_flights_transport'],
        'search_price_with_surcharge'=>['amount'=>$sum,'currency'=>$offer['price']['currency'],'source'=>'derived_search_estimate'],
        'surcharge_scope'=>'party','arithmetic_applied'=>true,'final_price_verified'=>false];
}
$request=['params'=>['departureId'=>'1','countryId'=>'4']];
$source=offer('source','100000');
$evidence=AnyTourAndromedaSurchargeEvidenceV1::capture($source,$request,fact($source),1000,1300);
ok(is_array($evidence),'valid source fact captured');
ok(($evidence['schema_version']??null)===2,'evidence schema v2');
ok(($evidence['reuse_basis']??null)===['class'=>'program_party_markup','aggregation'=>'single_distinct_party_markup'],'program reuse basis retained');
ok(str_starts_with($evidence['group_key'],'andromeda-surcharge-v3:'),'v3 group key');

$target=offer('target','110000');
$target['local_hotel_id']=99;$target['room_raw']='FAMILY';$target['meal']['raw_label']='UAI';
$applied=AnyTourAndromedaSurchargeEvidenceV1::apply($target,$request,$evidence,1100);
ok(is_array($applied),'same transport group reused across presentation variants');
ok($applied['search_price']===['amount'=>'110000','currency'=>'RUB'],'target base retained');
ok($applied['party_surcharge']['amount']==='5000','party surcharge reused once');
ok($applied['search_price_with_surcharge']['amount']==='115000','target total rebased');
ok($applied['final_price_verified']===false,'group evidence stays estimate only');
ok(($applied['reuse_basis']['aggregation']??null)==='single_distinct_party_markup','applied fact exposes reuse basis');

// Owner rule: one program surcharge record covers 7/10/14 nights while each target keeps its own base.
foreach ([7=>'110000',10=>'120000',14=>'130000'] as $nights=>$base) {
    $nightTarget=offer('n'.$nights,$base);$nightTarget['nights']=$nights;
    $nightApplied=AnyTourAndromedaSurchargeEvidenceV1::apply($nightTarget,$request,$evidence,1100);
    ok(is_array($nightApplied),'night '.$nights.' reuses group evidence');
    ok($nightApplied['search_price']['amount']===$base,'night '.$nights.' keeps own base');
    ok($nightApplied['search_price_with_surcharge']['amount']===(string)((int)$base+5000),'night '.$nights.' adds once');
    ok($nightApplied['final_price_verified']===false,'night '.$nights.' stays estimate');
}

$spoOnly=$target;$spoOnly['transport_context']['spo_ref']='totally-different-spo';
ok(AnyTourAndromedaSurchargeEvidenceV1::apply($spoOnly,$request,$evidence,1100)!==null,'SPO outside group key');

foreach([
    'program'=>function(array $o){$o['transport_context']['program_ref']='102';return $o;},
    'tour'=>function(array $o){$o['transport_context']['tour_ref']='203';return $o;},
    'date'=>function(array $o){$o['check_in']='2026-10-31';return $o;},
    'party'=>function(array $o){$o['adults']=3;return $o;},
    'currency'=>function(array $o){$o['price']['currency']='USD';return $o;},
] as $name=>$change){
    ok(AnyTourAndromedaSurchargeEvidenceV1::apply($change($target),$request,$evidence,1100)===null,$name.' mismatch fail closed');
}
ok(AnyTourAndromedaSurchargeEvidenceV1::apply($target,['params'=>['departureId'=>'2','countryId'=>'4']],$evidence,1100)===null,'route mismatch fail closed');
ok(AnyTourAndromedaSurchargeEvidenceV1::apply($target,$request,$evidence,999)===null,'future evidence rejected');
ok(AnyTourAndromedaSurchargeEvidenceV1::apply($target,$request,$evidence,1300)===null,'expired evidence rejected');

$choiceDependent=fact($source,'5000','minimum_complete_required_roundtrip_markup');
ok(AnyTourAndromedaSurchargeEvidenceV1::capture($source,$request,$choiceDependent,1000,1300)===null,'flight-choice markup cannot seed shared cache');
$missingBasis=fact($source);unset($missingBasis['transport_markup_reported']);
ok(AnyTourAndromedaSurchargeEvidenceV1::capture($source,$request,$missingBasis,1000,1300)===null,'unclassified markup cannot seed shared cache');
$badFact=fact($source);$badFact['search_price_with_surcharge']['amount']='105001';
ok(AnyTourAndromedaSurchargeEvidenceV1::capture($source,$request,$badFact,1000,1300)===null,'tampered arithmetic rejected');
$wrongBase=fact($source);$wrongBase['search_price']['amount']='99999';
ok(AnyTourAndromedaSurchargeEvidenceV1::capture($source,$request,$wrongBase,1000,1300)===null,'source fact bound to source PRICE money');
$malformed=$source;unset($malformed['transport_context']['program_ref']);
ok(AnyTourAndromedaSurchargeEvidenceV1::capture($malformed,$request,fact($source),1000,1300)===null,'unkeyable source rejected');
ok(AnyTourAndromedaSurchargeEvidenceV1::capture($source,$request,fact($source),1000,1301)===null,'evidence TTL bounded');

echo "ANDROMEDA_SURCHARGE_EVIDENCE_OK reuse=1 cross_night=3 rebase=1 reusable_basis=1 choice_rejected=2 strict_mismatch=6 stale=2 tamper=3\n";
