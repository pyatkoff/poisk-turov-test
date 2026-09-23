<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/integrations/three-provider-fuel-evidence.php';

$checks = 0;
function fuel_check(bool $value, string $message): void {
    global $checks; ++$checks;
    if (!$value) throw new RuntimeException('FUEL_CHECK:' . $message);
}
function fuel_dto(string $operator = 'FUN&SUN', string $base = '100000', string $salt = 'target'): array {
    return ['provider'=>'andromeda', 'operator'=>['raw'=>$operator],
        'identity'=>['offer_ref_digest'=>hash('sha256',$salt)],
        'tour'=>['checkin'=>'2026-10-05','nights'=>7,
            'party'=>['adults'=>2,'children'=>0,'child_ages'=>[]]],
        'money'=>['search_price'=>['amount'=>$base,'currency'=>'RUB','source'=>'andromeda_search'],
            'fuel_charge_reported'=>null,'additional_prices_reported'=>[],
            'arithmetic_applied'=>false,'final_price_verified'=>false,'search_price_fuel_relation'=>'unknown'],
        'price'=>$base,'currency'=>'RUB','finalPriceReady'=>false,'finalPrice'=>null,
        'quote_state'=>'unknown','final_price_verified'=>false,'quote_evidence_digest'=>null,
        'selection_state'=>'disabled','booking_enabled'=>false];
}
function fuel_input(array $dto, int $now, string $amount = '280', string $relation = 'excluded'): array {
    $family=AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($dto['operator']['raw']);
    if ($family===null) throw new RuntimeException('fixture operator');
    $direction=['operator_family'=>$family,'market'=>'departure:1','destination'=>'country:4'];
    $party=$dto['tour']['party'];
    $sample=['direction'=>$direction,'party'=>$party,'kind'=>'fuel','unit'=>'party_roundtrip',
        'amount'=>$amount,'currency'=>'EUR','base_relation'=>$relation,
        'base_includes_other_required_charges'=>true,
        'observed_at'=>$now-60,'expires_at'=>$now+3600,
        'evidence_valid_from'=>'2026-09-01','evidence_valid_to'=>'2026-09-30'];
    $rows=[];
    foreach ([['a','tourvisor'],['b','andromeda']] as [$id,$provider]) {
        $rows[]=$sample+['provider'=>$provider,'offer_ref_digest'=>hash('sha256','sample-'.$family.'-'.$id),
            'evidence_sha256'=>hash('sha256','independent-fuel-row-'.$family.'-'.$id),
            'provenance_scope'=>['different_flight'=>$id]];
    }
    // Deliberately a different evidence period for the second row. Dates are
    // provenance, not rule identity/application gates.
    $rows[1]['evidence_valid_from']='2026-10-01';
    $rows[1]['evidence_valid_to']='2026-10-31';
    return ['offer_ref_digest'=>$dto['identity']['offer_ref_digest'],'direction'=>$direction,'party'=>$party,
        'observations'=>$rows,
        'exchange'=>['from'=>'EUR','to'=>'RUB','rate'=>'104.23',
            'scope_sha256'=>AnyTourThreeProviderFuelEvidenceV1::hash($direction),
            'evidence_sha256'=>hash('sha256','fx'),'observed_at'=>$now-10,'expires_at'=>$now+300]];
}
function fuel_per_person_input(array $dto, int $now, string $rate = '85'): array {
    $family=AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($dto['operator']['raw']);
    if ($family===null) throw new RuntimeException('fixture operator');
    $direction=['operator_family'=>$family,'market'=>'departure:1','destination'=>'country:4'];
    $targetParty=$dto['tour']['party'];
    $sourceParties=[
        ['adults'=>1,'children'=>0,'child_ages'=>[]],
        ['adults'=>2,'children'=>0,'child_ages'=>[]],
    ];
    $rows=[];
    foreach ([['a','andromeda'],['b','andromeda']] as $idx=>[$id,$provider]) {
        $rows[]=[
            'direction'=>$direction,'party'=>$sourceParties[$idx],'kind'=>'fuel','unit'=>'per_person_one_way',
            'amount'=>$rate,'currency'=>'EUR','base_relation'=>'excluded',
            'base_includes_other_required_charges'=>true,
            'observed_at'=>$now-60,'expires_at'=>$now+3600,
            'evidence_valid_from'=>'2026-09-01','evidence_valid_to'=>'2026-10-31',
            'provider'=>$provider,'offer_ref_digest'=>hash('sha256','pp-sample-'.$family.'-'.$id),
            'evidence_sha256'=>hash('sha256','pp-evidence-'.$family.'-'.$id),
            'provenance_scope'=>['source_party'=>$sourceParties[$idx]],
        ];
    }
    return ['offer_ref_digest'=>$dto['identity']['offer_ref_digest'],'direction'=>$direction,'party'=>$targetParty,
        'observations'=>$rows,
        'exchange'=>['from'=>'EUR','to'=>'RUB','rate'=>'100',
            'scope_sha256'=>AnyTourThreeProviderFuelEvidenceV1::hash($direction),
            'evidence_sha256'=>hash('sha256','pp-fx'),'observed_at'=>$now-10,'expires_at'=>$now+300]];
}
function fuel_hold(array $dto, array $input, int $now, string $reason): void {
    $r=AnyTourThreeProviderFuelEvidenceV1::apply($dto,$input,$now);
    fuel_check(!$r['applied'] && $r['reason']===$reason,'hold-'.$reason);
    fuel_check($r['dto']===$dto,'hold-preserves-base-'.$reason);
}

$now=1790078400;
$d=fuel_dto(); $i=fuel_input($d,$now);
$r=AnyTourThreeProviderFuelEvidenceV1::apply($d,$i,$now);
fuel_check($r['applied'] && $r['dto']['price']==='129184.40','native-fuel-current-fx');
fuel_check($r['dto']['money']['search_price']['amount']==='100000','own-base');
fuel_check($r['dto']['finalPriceReady'] && !$r['dto']['final_price_verified']
    && $r['dto']['quote_evidence_digest']===null && !$r['dto']['booking_enabled'],'estimate-not-quote');
fuel_check($r['dto']['money']['operator_fuel_rule']['amount']==='280.00','retain-native-party-total');
fuel_check($r['dto']['money']['operator_fuel_rule']['currency']==='EUR','native-currency-retained');
fuel_check($r['dto']['money']['operator_fuel_rule']['direction']===[
    'operator_family'=>'fun_and_sun','market'=>'departure:1','destination'=>'country:4'
], 'direction-rule-emitted');
fuel_check(!array_key_exists('scope',$r['dto']['money']['operator_fuel_rule']),'exact-flight-scope-not-rule');
$reverse=$i;$reverse['observations']=array_reverse($i['observations']);
fuel_check(AnyTourThreeProviderFuelEvidenceV1::apply($d,$reverse,$now)===$r,'evidence-order-idempotent');
fuel_hold($r['dto'],$i,$now,'fuel_overlap_or_nonbase_state');

// Fresh FX changes displayed RUB while the native direction rule remains identical.
$nextFx=$i;$nextFx['exchange']['rate']='105';$nextFx['exchange']['expires_at']=$now+200;
$nextFx['exchange']['evidence_sha256']=hash('sha256','next-fx');
$fxResult=AnyTourThreeProviderFuelEvidenceV1::apply($d,$nextFx,$now);
fuel_check($fxResult['applied'] && $fxResult['dto']['price']==='129400.00', 'fx-updates-rubles');
fuel_check($fxResult['dto']['money']['operator_fuel_rule']['rule_sha256']
    ===$r['dto']['money']['operator_fuel_rule']['rule_sha256'], 'fx-not-native-rule-version');
fuel_check($fxResult['dto']['money']['operator_fuel_rule']['expires_at']===$now+3600
    && $fxResult['dto']['money']['operator_fuel_rule']['price_evidence_expires_at']===$now+200, 'separate-freshness');

// Dates/nights/hotel are intentionally absent from the application key.
$groups=[];$covered=0;
foreach (['FUN&SUN','Интурист'] as $op) {
    foreach ([7,10,14] as $nights) {
        $dto=fuel_dto($op,(string)(100000+$nights*1000),$op.'-'.$nights);
        $dto['tour']['nights']=$nights;
        $dto['tour']['checkin']='2026-10-'.str_pad((string)(5+$nights),2,'0',STR_PAD_LEFT);
        $input=fuel_input($dto,$now);
        $got=AnyTourThreeProviderFuelEvidenceV1::apply($dto,$input,$now);
        fuel_check($got['applied'] && $got['dto']['price']===(string)(129184+$nights*1000).'.40','operator-night-base');
        $groups[$got['dto']['money']['operator_fuel_rule']['rule_sha256']]=true;++$covered;
    }
}
fuel_check(count($groups)===2 && $covered===6,'two-operators-two-direction-rules-six-cross-night-offers');

// One confirmed per-person one-way rate scales to the target party, including children 2+.
$ppDto=fuel_dto('FUN&SUN','100000','pp-target');
$ppDto['tour']['party']=['adults'=>2,'children'=>1,'child_ages'=>[5]];
$ppInput=fuel_per_person_input($ppDto,$now,'85');
$ppResult=AnyTourThreeProviderFuelEvidenceV1::apply($ppDto,$ppInput,$now);
fuel_check($ppResult['applied']&&$ppResult['dto']['price']==='151000.00','per-person-85-x-3-x-2');
fuel_check(($ppResult['dto']['money']['fuel_charge_reported']['amount']??null)==='51000.00','per-person-party-rub-total');
fuel_check(($ppResult['dto']['money']['operator_fuel_rule']['unit']??null)==='per_person_one_way','per-person-rule-unit');
fuel_check(($ppResult['dto']['money']['operator_fuel_rule']['amount']??null)==='85.00','per-person-native-rate-retained');
fuel_check(($ppResult['dto']['money']['operator_fuel_rule']['passenger_count']??null)===3
    &&($ppResult['dto']['money']['operator_fuel_rule']['direction_count']??null)===2
    &&($ppResult['dto']['money']['operator_fuel_rule']['applied_native_total']??null)==='510.00','per-person-native-total-metadata');
fuel_check($ppResult['dto']['finalPriceReady']===true&&$ppResult['dto']['final_price_verified']===false,'per-person-estimate-not-verified');

// Owner policy can replace the broad FUN&SUN Turkey listing rate without rewriting
// the retained supplier observations that provide fresh FX/provenance.
$policyDto=fuel_dto('FUN&SUN','100000','policy-target');
$policyDto['tour']['party']=['adults'=>2,'children'=>1,'child_ages'=>[5]];
$policyInput=fuel_per_person_input($policyDto,$now,'140');
$policyInput['owner_policy']=[
    'schema_version'=>1,'source'=>'owner_policy','policy_date'=>'2026-09-23',
    'operator_family'=>'fun_and_sun','destination'=>'country:4',
    'amount'=>'70.00','currency'=>'EUR','unit'=>'per_person_one_way','base_relation'=>'excluded',
];
$policyResult=AnyTourThreeProviderFuelEvidenceV1::apply($policyDto,$policyInput,$now);
fuel_check($policyResult['applied']&&$policyResult['dto']['price']==='142000.00','funsun-turkey-policy-70-x-3-x-2');
fuel_check(($policyResult['dto']['money']['fuel_charge_reported']??null)=== [
    'amount'=>'42000.00','currency'=>'RUB','source'=>'operator_fuel_owner_policy'
],'funsun-owner-policy-rub-surcharge');
fuel_check(($policyResult['dto']['money']['operator_fuel_rule']['amount']??null)==='70.00'
    &&($policyResult['dto']['money']['operator_fuel_rule']['unit']??null)==='per_person_one_way'
    &&($policyResult['dto']['money']['operator_fuel_rule']['applied_native_total']??null)==='420.00'
    &&($policyResult['dto']['money']['operator_fuel_rule']['owner_policy']??null)===$policyInput['owner_policy'],
    'funsun-owner-policy-rule-metadata');
fuel_check(($policyInput['observations'][0]['amount']??null)==='140'
    &&($policyInput['observations'][1]['amount']??null)==='140','supplier-observations-not-rewritten');
fuel_check($policyResult['dto']['finalPriceReady']===true&&$policyResult['dto']['final_price_verified']===false,
    'funsun-owner-policy-estimate-not-verified');
$badPolicy=$policyInput;$badPolicy['owner_policy']['amount']='80.00';
fuel_hold($policyDto,$badPolicy,$now,'fuel_evidence_invalid');

$ppInfant=fuel_dto('FUN&SUN','100000','pp-infant');
$ppInfant['tour']['party']=['adults'=>2,'children'=>1,'child_ages'=>[1]];
$ppInfantInput=fuel_per_person_input($ppInfant,$now,'85');
$ppInfantInput['owner_policy']=$policyInput['owner_policy'];
fuel_hold($ppInfant,$ppInfantInput,$now,'fuel_infant_separate');
$ppConflict=$ppInput;$ppConflict['observations'][1]['amount']='90';
fuel_hold($ppDto,$ppConflict,$now,'fuel_rule_conflict');

$included=fuel_input($d,$now,'280','included');
$got=AnyTourThreeProviderFuelEvidenceV1::apply($d,$included,$now);
fuel_check($got['applied'] && $got['dto']['price']==='100000.00'
    && !$got['dto']['money']['arithmetic_applied'],'included-no-double-charge');
$zero=fuel_input($d,$now,'0');
fuel_check(AnyTourThreeProviderFuelEvidenceV1::apply($d,$zero,$now)['dto']['price']==='100000.00','explicit-zero');

$bad=$i;$bad['direction']['operator_family']='intourist';fuel_hold($d,$bad,$now,'fuel_direction_binding');
$bad=$i;$bad['party']=['adults'=>1,'children'=>1,'child_ages'=>[5]];fuel_hold($d,$bad,$now,'fuel_party_binding');
$bad=$i;$bad['observations'][1]['direction']['destination']='DLM';fuel_hold($d,$bad,$now,'fuel_independent_evidence_missing');
foreach (['amount','currency','base_relation'] as $key) {
    $bad=$i;$bad['observations'][1][$key]=['amount'=>'300','currency'=>'USD','base_relation'=>'included'][$key];
    fuel_hold($d,$bad,$now,'fuel_rule_conflict');
}
foreach (['offer_ref_digest','evidence_sha256'] as $key) {
    $bad=$i;$bad['observations'][1][$key]=$bad['observations'][0][$key];
    fuel_hold($d,$bad,$now,'fuel_independent_evidence_missing');
}
foreach (['expiry','future','missing'] as $case) {
    $bad=$i;
    if ($case==='expiry') foreach ($bad['observations'] as &$x) $x['expires_at']=$now; unset($x);
    if ($case==='future') foreach ($bad['observations'] as &$x) $x['observed_at']=$now+1; unset($x);
    if ($case==='missing') $bad['observations']=[];
    fuel_hold($d,$bad,$now,'fuel_independent_evidence_missing');
}
foreach (['amount','unit','base_relation','base_includes_other_required_charges'] as $key) {
    $bad=$i;$bad['observations'][0][$key]=['amount'=>true,'unit'=>'route_reported_unknown',
        'base_relation'=>'unknown','base_includes_other_required_charges'=>false][$key];
    fuel_hold($d,$bad,$now,'fuel_evidence_invalid');
}
$bad=$i;$bad['exchange']['expires_at']=$now;fuel_hold($d,$bad,$now,'fuel_exchange_unavailable');
$bad=$i;$bad['exchange']['scope_sha256']=hash('sha256','wrong-direction');fuel_hold($d,$bad,$now,'fuel_exchange_unavailable');
$bad=$i;$bad['exchange']['rate']=true;fuel_hold($d,$bad,$now,'fuel_evidence_invalid');
$bad=$i;$bad['offer_ref_digest']=hash('sha256','other');fuel_hold($d,$bad,$now,'fuel_offer_binding');
$other=$d;$other['money']['additional_prices_reported']=[['kind'=>'party_transport_surcharge']];
fuel_hold($other,$i,$now,'fuel_overlap_or_nonbase_state');
$other=$d;$other['money']['fuel_charge_reported']=['amount'=>'100','currency'=>'RUB'];
fuel_hold($other,$included,$now,'fuel_existing_fact_conflict');

$infant=fuel_dto();$infant['tour']['party']=['adults'=>2,'children'=>1,'child_ages'=>[1]];
$infantInput=fuel_input($infant,$now);
fuel_hold($infant,$infantInput,$now,'fuel_infant_separate');
$child=fuel_dto();$child['tour']['party']=['adults'=>2,'children'=>2,'child_ages'=>[4,11]];
$ci=fuel_input($child,$now);$ci['observations'][0]['party']['child_ages']=[11,4];
fuel_check(AnyTourThreeProviderFuelEvidenceV1::apply($child,$ci,$now)['applied'],'ages-unordered');
$ci['observations'][1]['party']['child_ages']=[5,11];
fuel_hold($child,$ci,$now,'fuel_independent_evidence_missing');

echo "FUEL_DIRECTION_TYPED_CHECKS_OK checks=$checks rules=2 covered_offers=6 supplier_http=0\n";

if (($argv[1]??'')==='--pure') exit(0);
require_once __DIR__.'/anytour-offer-snapshot-producer-test.php';
require_once dirname(__DIR__).'/v2/data/anytour-offer-store-v1.php';
$validate=new ReflectionMethod(AnyTourOfferStoreV1::class,'validateDto');
$projection=new ReflectionMethod(AnyTourOfferStoreV1::class,'listingProjection');
$at=new DateTimeImmutable('2026-09-22T13:00:00Z');$now=$at->getTimestamp();
$entries=[];$rules=[];
foreach (['FUN&SUN','Интурист'] as $op) {
    foreach ([7,10,14] as $nights) {
        $raw=producer_raw('andromeda',5000+count($entries),2,[],(string)(100000+$nights*1000),$op.$nights);
        $raw['operator']=$op;$raw['nights']=$nights;
        $raw['observed_at']=$at->format('Y-m-d\TH:i:s\Z');
        $offer=AnyTourThreeProviderOfferContract::fromSearch($raw);
        $retained=AnyTourThreeProviderOfferContext::retain($offer,1,1,$now,900);
        $current=array_intersect_key($retained,array_flip(['provider','operator','local_hotel_id','identity','generation','page']));
        $base=AnyTourThreeProviderSearchHandoff::fromConfirmationRequiredSearchOffer($offer,$retained,$current,$now);
        $input=fuel_input($base,$now);
        $entries[]=['anytour_hotel_id'=>7000+count($entries),'offer'=>$offer,'retained'=>$retained,
            'current'=>$current,'priced_money'=>null,'operator_fuel'=>$input];
    }
}
$bad=$entries[0];$bad['operator_fuel']['observations'][1]['amount']='999';
$badRaw=producer_raw('andromeda',9000,2,[],'90000','conflict');$badRaw['operator']='FUN&SUN';
$badRaw['observed_at']=$at->format('Y-m-d\TH:i:s\Z');
$bad['offer']=AnyTourThreeProviderOfferContract::fromSearch($badRaw);
$bad['retained']=AnyTourThreeProviderOfferContext::retain($bad['offer'],1,1,$now,900);
$bad['current']=array_intersect_key($bad['retained'],array_flip(['provider','operator','local_hotel_id','identity','generation','page']));
$bad['operator_fuel']['offer_ref_digest']=$bad['offer']['identity']['offer_ref_digest'];$entries[]=$bad;
$before=$entries;$ingests=0;$passedRows=[];
$search=producer_params();$search['nightsTo']=14;
$result=AnyTourIntOfferSnapshotProducerV1::produce('andromeda',$search,[
    'complete'=>true,'authoritative_empty'=>false,'offers'=>$entries],$at,
    static function(string $provider,array $params,array $rows,DateTimeImmutable $time)use(&$ingests,&$passedRows,$validate,$projection,&$rules):array{
        ++$ingests;$passedRows=$rows;
        foreach ($rows as $row) {
            $v=$validate->invoke(null,$row['dto']);$p=$projection->invoke(null,$row['dto']);
            fuel_check(!$v['final_price_verified'] && !$p['finalPriceVerified'],'local-not-individual-quote');
            if ($row['dto']['finalPriceReady']) {
                fuel_check($v['listing_price_state']==='final_ready_estimate','local-ready-estimate');
                $rules[$row['dto']['money']['operator_fuel_rule']['rule_sha256']]=true;
            } else fuel_check($v['listing_price_state']==='search_price_confirmation_required','local-conflict-fallback');
        }
        return ['offerCount'=>count($rows)];
    });
fuel_check($entries===$before,'producer-does-not-mutate-source');
fuel_check($ingests===1 && count($passedRows)===7,'one-existing-ingest-all-rows');
fuel_check($result['readyOfferCount']===6 && $result['confirmationRequiredOfferCount']===1,'ready-plus-local-exception');
fuel_check(count($rules)===2,'rule-per-operator-direction-not-night');
fuel_check($result['operatorFuel']['appliedOfferCount']===6
    && $result['operatorFuel']['ruleCount']===2
    && $result['operatorFuel']['rejections']===['fuel_rule_conflict'=>1],'coverage-receipt');
fuel_check($passedRows[6]['dto']['price']==='90000','conflict-preserves-own-base');
echo "FUEL_DIRECTION_PRODUCER_LOCAL_OK rules=2 ready=6 confirmation=1 ingest_calls=1 supplier_http=0 live_db_writes=0\n";