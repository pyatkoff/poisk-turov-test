<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/integrations/three-provider-fuel-evidence.php';

// Explicitly synthetic typed inputs. No supplier transport, saved live-data claim,
// production database, or external URL is used in these tests.
$checks = 0;
function fuel_check(bool $value, string $message): void {
    global $checks; ++$checks;
    if (!$value) throw new RuntimeException('FUEL_CHECK:' . $message);
}
function fuel_dto(string $operator = 'ANEX', string $base = '100000', string $salt = 'target'): array {
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
    $scope=['provider'=>$dto['provider'],'operator_sha256'=>AnyTourThreeProviderFuelEvidenceV1::hash($dto['operator']),
        'market'=>'RU-agency',
        'outbound'=>['origin'=>'VKO','destination'=>'BJV','carrier'=>'PC','flight'=>'PC1457'],
        'return'=>['origin'=>'BJV','destination'=>'VKO','carrier'=>'PC','flight'=>'PC1456'],
        'party'=>$dto['tour']['party']];
    $sample=['scope'=>$scope,'kind'=>'fuel','unit'=>'party_roundtrip','amount'=>$amount,'currency'=>'EUR',
        'base_relation'=>$relation,'base_includes_other_required_charges'=>true,
        'observed_at'=>$now-60,'expires_at'=>$now+3600,'valid_from'=>'2026-09-01','valid_to'=>'2026-11-01'];
    $rows=[];
    foreach (['a','b'] as $id) $rows[]=$sample+['offer_ref_digest'=>hash('sha256','sample-'.$id),
        'evidence_sha256'=>hash('sha256','independent-fuel-row-'.$id)];
    return ['offer_ref_digest'=>$dto['identity']['offer_ref_digest'],'scope'=>$scope,
        'flight_dates'=>['2026-10-05','2026-10-12'],'observations'=>$rows,
        'exchange'=>['from'=>'EUR','to'=>'RUB','rate'=>'104.23',
            'scope_sha256'=>AnyTourThreeProviderFuelEvidenceV1::hash($scope),
            'evidence_sha256'=>hash('sha256','fx'),'observed_at'=>$now-10,'expires_at'=>$now+300]];
}
function fuel_hold(array $dto, array $input, int $now, string $reason): void {
    $r=AnyTourThreeProviderFuelEvidenceV1::apply($dto,$input,$now);
    fuel_check(!$r['applied'] && $r['reason']===$reason,'hold-'.$reason);
    fuel_check($r['dto']===$dto,'hold-preserves-base-'.$reason);
}
$now=1790078400;
$d=fuel_dto(); $i=fuel_input($d,$now);
$r=AnyTourThreeProviderFuelEvidenceV1::apply($d,$i,$now);
fuel_check($r['applied'] && $r['dto']['price']==='129184.40','native-fuel-exact-fx');
fuel_check($r['dto']['money']['search_price']['amount']==='100000','own-base');
fuel_check($r['dto']['finalPriceReady'] && !$r['dto']['final_price_verified']
    && $r['dto']['quote_evidence_digest']===null && !$r['dto']['booking_enabled'],'estimate-not-quote');
fuel_check($r['dto']['money']['operator_fuel_rule']['amount']==='280.00','retain-party-unit-not-half');
$reverse=$i;$reverse['observations']=array_reverse($i['observations']);
fuel_check(AnyTourThreeProviderFuelEvidenceV1::apply($d,$reverse,$now)===$r,'evidence-order-idempotent');
fuel_hold($r['dto'],$i,$now,'fuel_overlap_or_nonbase_state');
$groups=[];$covered=0;
foreach (['ANEX','Библио-Глобус','FUN&SUN','Интурист'] as $op) {
    foreach ([7,10,14] as $nights) {
        $dto=fuel_dto($op,(string)(100000+$nights*1000),$op.'-'.$nights);
        $dto['tour']['nights']=$nights;
        $input=fuel_input($dto,$now);
        $input['flight_dates'][1]='2026-10-'.str_pad((string)(5+$nights),2,'0',STR_PAD_LEFT);
        $got=AnyTourThreeProviderFuelEvidenceV1::apply($dto,$input,$now);
        fuel_check($got['applied'] && $got['dto']['price']===(string)(129184+$nights*1000).'.40','operator-night-base');
        $groups[$got['dto']['money']['operator_fuel_rule']['rule_sha256']]=true;++$covered;
    }
}
fuel_check(count($groups)===4 && $covered===12,'four-operators-four-rules-twelve-offers');
$included=fuel_input($d,$now,'280','included');
$got=AnyTourThreeProviderFuelEvidenceV1::apply($d,$included,$now);
fuel_check($got['applied'] && $got['dto']['price']==='100000.00'
    && !$got['dto']['money']['arithmetic_applied'],'included-no-double-charge');
$zero=fuel_input($d,$now,'0');
fuel_check(AnyTourThreeProviderFuelEvidenceV1::apply($d,$zero,$now)['dto']['price']==='100000.00','explicit-zero');
foreach (['operator','market','flight','ages'] as $change) {
    $bad=$i;
    if ($change==='operator') $bad['scope']['operator_sha256']=hash('sha256','other');
    if ($change==='market') $bad['scope']['market']='BY';
    if ($change==='flight') $bad['scope']['return']['flight']='TK3155';
    if ($change==='ages') $bad['scope']['party']=['adults'=>2,'children'=>1,'child_ages'=>[5]];
    fuel_hold($d,$bad,$now,in_array($change,['operator','ages'],true)?'fuel_scope_binding':'fuel_independent_evidence_missing');
}
foreach (['amount','currency','base_relation'] as $key) {
    $bad=$i;$bad['observations'][1][$key]=['amount'=>'300','currency'=>'USD','base_relation'=>'included'][$key];
    fuel_hold($d,$bad,$now,'fuel_rule_conflict');
}
foreach (['offer_ref_digest','evidence_sha256'] as $key) {
    $bad=$i;$bad['observations'][1][$key]=$bad['observations'][0][$key];
    fuel_hold($d,$bad,$now,'fuel_independent_evidence_missing');
}
foreach (['expiry','future','period','missing'] as $case) {
    $bad=$i;
    if ($case==='expiry') foreach ($bad['observations'] as &$x) $x['expires_at']=$now; unset($x);
    if ($case==='future') foreach ($bad['observations'] as &$x) $x['observed_at']=$now+1; unset($x);
    if ($case==='period') $bad['flight_dates']=['2026-11-02','2026-11-09'];
    if ($case==='missing') $bad['observations']=[];
    fuel_hold($d,$bad,$now,'fuel_independent_evidence_missing');
}
foreach (['amount','unit','base_relation','base_includes_other_required_charges'] as $key) {
    $bad=$i;$bad['observations'][0][$key]=['amount'=>true,'unit'=>'transport_markup',
        'base_relation'=>'unknown','base_includes_other_required_charges'=>false][$key];
    fuel_hold($d,$bad,$now,'fuel_evidence_invalid');
}
$bad=$i;$bad['exchange']['expires_at']=$now;fuel_hold($d,$bad,$now,'fuel_exchange_unavailable');
$bad=$i;$bad['exchange']['scope_sha256']=hash('sha256','wrong-operator');fuel_hold($d,$bad,$now,'fuel_exchange_unavailable');
$bad=$i;$bad['exchange']['rate']=true;fuel_hold($d,$bad,$now,'fuel_evidence_invalid');
$bad=$i;$bad['offer_ref_digest']=hash('sha256','other');fuel_hold($d,$bad,$now,'fuel_offer_binding');
$other=$d;$other['money']['additional_prices_reported']=[['kind'=>'party_transport_surcharge']];
fuel_hold($other,$i,$now,'fuel_overlap_or_nonbase_state');
$other=$d;$other['money']['fuel_charge_reported']=['amount'=>'100','currency'=>'RUB'];
fuel_hold($other,$included,$now,'fuel_existing_fact_conflict');
$child=fuel_dto();$child['tour']['party']=['adults'=>2,'children'=>2,'child_ages'=>[4,11]];
$ci=fuel_input($child,$now);$ci['observations'][0]['scope']['party']['child_ages']=[11,4];
fuel_check(AnyTourThreeProviderFuelEvidenceV1::apply($child,$ci,$now)['applied'],'ages-unordered');
$ci['observations'][1]['scope']['party']['child_ages']=[5,11];
fuel_hold($child,$ci,$now,'fuel_independent_evidence_missing');
$foreign=$i;$row=$foreign['observations'][0];$row['scope']['market']='BY';$row['amount']='999';
$foreign['observations'][]=$row;
fuel_check(AnyTourThreeProviderFuelEvidenceV1::apply($d,$foreign,$now)['applied'],'other-group-conflict-isolated');
echo "FUEL_TYPED_CHECKS_OK checks=$checks groups=4 covered_offers=12 supplier_http=0\n";

// The default CI path MUST exercise real existing producer and LOCAL DTO validation.
// --pure is the explicit narrow local mode, not a substitute for integration CI.
if (($argv[1]??'')==='--pure') exit(0);
require_once __DIR__.'/anytour-offer-snapshot-producer-test.php';
require_once dirname(__DIR__).'/v2/data/anytour-offer-store-v1.php';
$validate=new ReflectionMethod(AnyTourOfferStoreV1::class,'validateDto');
$projection=new ReflectionMethod(AnyTourOfferStoreV1::class,'listingProjection');
$at=new DateTimeImmutable('2026-09-22T13:00:00Z');$now=$at->getTimestamp();
$entries=[];$rules=[];
foreach (['ANEX','Библио-Глобус','FUN&SUN','Интурист'] as $op) {
    foreach ([7,10,14] as $nights) {
        $raw=producer_raw('andromeda',5000+count($entries),2,[],(string)(100000+$nights*1000),$op.$nights);
        $raw['operator']=$op;$raw['nights']=$nights;
        $raw['observed_at']=$at->format('Y-m-d\TH:i:s\Z');
        $offer=AnyTourThreeProviderOfferContract::fromSearch($raw);
        $retained=AnyTourThreeProviderOfferContext::retain($offer,1,1,$now,900);
        $current=array_intersect_key($retained,array_flip(['provider','operator','local_hotel_id','identity','generation','page']));
        $base=AnyTourThreeProviderSearchHandoff::fromConfirmationRequiredSearchOffer($offer,$retained,$current,$now);
        $input=fuel_input($base,$now);$input['flight_dates'][1]='2026-10-'.(5+$nights);
        $entries[]=['anytour_hotel_id'=>7000+count($entries),'offer'=>$offer,'retained'=>$retained,
            'current'=>$current,'priced_money'=>null,'operator_fuel'=>$input];
    }
}
$bad=$entries[0];$bad['operator_fuel']['observations'][1]['amount']='999';
// Use a new real fixture identity rather than inserting a duplicate entry.
$badRaw=producer_raw('andromeda',9000,2,[],'90000','conflict');$badRaw['observed_at']=$at->format('Y-m-d\TH:i:s\Z');
$bad['offer']=AnyTourThreeProviderOfferContract::fromSearch($badRaw);
$bad['retained']=AnyTourThreeProviderOfferContext::retain($bad['offer'],1,1,$now,900);
$bad['current']=array_intersect_key($bad['retained'],array_flip(['provider','operator','local_hotel_id','identity','generation','page']));
$bad['operator_fuel']['offer_ref_digest']=$bad['offer']['identity']['offer_ref_digest'];$entries[]=$bad;
$before=$entries;$ingests=0;$passedRows=[];
$result=AnyTourIntOfferSnapshotProducerV1::produce('andromeda',producer_params(),[
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
fuel_check($ingests===1 && count($passedRows)===13,'one-existing-ingest-all-rows');
fuel_check($result['readyOfferCount']===12 && $result['confirmationRequiredOfferCount']===1,'ready-plus-local-exception');
fuel_check(count($rules)===4,'rule-per-operator-not-per-night');
fuel_check($result['operatorFuel']['appliedOfferCount']===12
    && $result['operatorFuel']['ruleCount']===4
    && $result['operatorFuel']['rejections']===['fuel_rule_conflict'=>1],'coverage-receipt');
fuel_check($passedRows[12]['dto']['price']==='90000','conflict-preserves-own-base');
echo "FUEL_PRODUCER_LOCAL_OK rules=4 ready=12 confirmation=1 ingest_calls=1 supplier_http=0 live_db_writes=0\n";
