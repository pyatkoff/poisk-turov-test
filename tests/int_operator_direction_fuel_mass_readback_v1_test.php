<?php
declare(strict_types=1);
putenv('INT_DIRECTION_FUEL_READBACK_LIBRARY_ONLY=1');
require_once __DIR__ . '/../scripts/diagnostics/int_operator_direction_fuel_mass_readback_v1.php';

function tassert(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function payload(array $changes=[]): array {
    $rule = [
        'schema_version'=>2,
        'kind'=>'fuel',
        'unit'=>'per_person_one_way',
        'direction'=>['operator_family'=>'fun_and_sun','market'=>'departure:1','destination'=>'country:4'],
        'applicable_party'=>['adults'=>2,'children'=>0,'child_ages'=>[]],
        'amount'=>'140.00','currency'=>'EUR','base_relation'=>'excluded',
        'evidence_period_from'=>'2026-09-23','evidence_period_to'=>'2026-10-31','expires_at'=>2100000000,
        'independent_offer_count'=>2,'evidence_count'=>2,'evidence_sha256'=>str_repeat('b',64),
        'direction_count'=>2,'passenger_count'=>2,'applied_native_total'=>'560.00',
        'rule_sha256'=>str_repeat('a',64),'price_evidence_expires_at'=>2100000000,
        'exchange'=>['from'=>'EUR','to'=>'RUB','rate'=>'102.7','observed_at'=>1900000000,'expires_at'=>2100000000,'evidence_sha256'=>str_repeat('c',64)],
    ];
    $p = [
        'listingPrice'=>'100000.00',
        'listingPriceState'=>'final_ready_estimate','listingPriceReady'=>true,'finalPriceVerified'=>false,
        'quoteState'=>'unknown','quoteEvidenceDigest'=>null,'selection_state'=>'refresh_required','booking_enabled'=>false,
        'money'=>[
            'search_price'=>['amount'=>'100000.00','currency'=>'RUB','source'=>'supplier_search'],
            'fuel_charge_reported'=>['amount'=>'57512.00','currency'=>'RUB','source'=>'operator_fuel_direction_rule'],
            'operator_fuel_rule'=>$rule,
            'search_price_fuel_relation'=>'excluded',
            'search_price_with_surcharge'=>['amount'=>'157512.00','currency'=>'RUB','source'=>'derived_search_estimate'],
            'arithmetic_applied'=>true,
        ],
    ];
    foreach ($changes as $k=>$v) {
        if ($k === 'direction') $p['money']['operator_fuel_rule']['direction']=$v;
        elseif ($k === 'finalPriceVerified') $p['finalPriceVerified']=$v;
        else $p[$k]=$v;
    }
    return $p;
}
function row(string $seed, array $payload, int $ready=1, int $verified=0, string $display='157512.00'): array {
    $raw=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    return [
        'offer_ref_digest'=>hash('sha256',$seed),
        'display_price'=>$display,'currency'=>'RUB','final_price_ready'=>$ready,'final_price_verified'=>$verified,
        'payload_json'=>$raw,'payload_sha256'=>hash('sha256',$raw),
    ];
}

$p1=payload();
$p2=payload(['direction'=>['operator_family'=>'fun_and_sun','market'=>'departure:1','destination'=>'country:7'], 'finalPriceVerified'=>true]);
$p3=payload();
$p3['listingPriceState']='search_price_confirmation_required';
$p3['listingPriceReady']=false;
$p3['finalPriceVerified']=false;
$p3['money']=['search_price'=>['amount'=>'120000.00','currency'=>'RUB','source'=>'supplier_search']];
$p4=payload();
$rows=[
    row('one',$p1),
    row('two',$p2,1,1),
    row('three',$p3,0,0,'120000.00'),
    row('four',$p4),
];
$retained=[
    hash('sha256','one')=>'fun_and_sun',
    hash('sha256','two')=>'fun_and_sun',
    hash('sha256','three')=>'fun_and_sun',
    hash('sha256','four')=>'intourist',
];
$r=odfr_evaluate_rows($rows,$retained,'fun_and_sun','1','4');
tassert($r['stored_target_count']===3,'stored target');
tassert($r['ready_target_count']===2,'ready target');
tassert($r['verified_target_count']===1,'verified target');
tassert($r['confirmation_target_count']===1,'confirmation target');
tassert($r['direction_rule_rows']===2,'direction rows');
tassert($r['direction_rule_valid_rows']===1,'valid direction row');
tassert($r['ready_non_target_count']===1,'non-target contamination surfaced');
tassert($r['bad_ready_boundary_count']===1,'verified estimate boundary surfaced');
tassert(($r['validation_failure_counts']['rule_direction']??0)===1,'wrong direction classified');
tassert($r['fuel_charges_rub']===['57512.00'],'2 adults x 2 directions x 140 EUR x 102.7');
tassert($r['rule_native_totals']===['560.00'],'native total');
tassert($r['fx_rates']===['102.7'],'fresh fx retained');
tassert($r['payload_listing_vs_total_mismatch_count']===2,'listing projection mismatch classified, not arithmetic failure');
tassert(($r['validation_failure_counts']['display_total']??0)===0,'base+fuel stored once');

$bad=$rows[0];
$bad['payload_sha256']=str_repeat('0',64);
$r2=odfr_evaluate_rows([$bad],[hash('sha256','one')=>'fun_and_sun'],'fun_and_sun','1','4');
tassert($r2['payload_hash_invalid_count']===1,'payload hash guard');
tassert($r2['direction_rule_rows']===0,'bad hash not trusted');

echo "int operator direction fuel mass readback: PASS\n";
