<?php
declare(strict_types=1);
putenv('INT_DIRECTION_FUEL_READBACK_LIBRARY_ONLY=1');
require_once __DIR__ . '/../scripts/diagnostics/int_operator_direction_fuel_mass_readback_v1.php';

function tassert(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function ruleOwner(string $country='4'): array {
    return [
        'schema_version'=>2,
        'kind'=>'fuel',
        'unit'=>'per_person_one_way',
        'direction'=>['operator_family'=>'fun_and_sun','market'=>'departure:1','destination'=>'country:'.$country],
        'applicable_party'=>['adults'=>2,'children'=>0,'child_ages'=>[]],
        'amount'=>'70.00','currency'=>'EUR','base_relation'=>'excluded',
        'evidence_period_from'=>'2026-09-23','evidence_period_to'=>'2026-09-23','expires_at'=>PHP_INT_MAX,
        'independent_offer_count'=>0,'evidence_count'=>0,'evidence_sha256'=>str_repeat('b',64),
        'owner_policy'=>[
            'schema_version'=>1,'source'=>'owner_policy','policy_date'=>'2026-09-23',
            'operator_family'=>'fun_and_sun','destination'=>'country:4',
            'amount'=>'70.00','currency'=>'EUR','unit'=>'per_person_one_way','base_relation'=>'excluded',
        ],
        'direction_count'=>2,'passenger_count'=>2,'applied_native_total'=>'280.00',
        'rule_sha256'=>str_repeat('a',64),'price_evidence_expires_at'=>2100000000,
        'exchange'=>['from'=>'EUR','to'=>'RUB','rate'=>'102.7','observed_at'=>1900000000,'expires_at'=>2100000000,'evidence_sha256'=>str_repeat('c',64)],
    ];
}
function ruleSupplier(): array {
    return [
        'schema_version'=>2,
        'kind'=>'fuel',
        'unit'=>'per_person_one_way',
        'direction'=>['operator_family'=>'fun_and_sun','market'=>'departure:1','destination'=>'country:4'],
        'applicable_party'=>['adults'=>2,'children'=>0,'child_ages'=>[]],
        'amount'=>'140.00','currency'=>'EUR','base_relation'=>'excluded',
        'evidence_period_from'=>'2026-09-23','evidence_period_to'=>'2026-10-31','expires_at'=>2100000000,
        'independent_offer_count'=>2,'evidence_count'=>2,'evidence_sha256'=>str_repeat('d',64),
        'direction_count'=>2,'passenger_count'=>2,'applied_native_total'=>'560.00',
        'rule_sha256'=>str_repeat('e',64),'price_evidence_expires_at'=>2100000000,
        'exchange'=>['from'=>'EUR','to'=>'RUB','rate'=>'102.7','observed_at'=>1900000000,'expires_at'=>2100000000,'evidence_sha256'=>str_repeat('f',64)],
    ];
}
function readyPayload(array $rule, string $fuel, string $total, string $fuelSource, bool $verified=false): array {
    return [
        'listingPrice'=>$total,
        'listingPriceState'=>'final_ready_estimate','listingPriceReady'=>true,'finalPriceVerified'=>$verified,
        'quoteState'=>'unknown','quoteEvidenceDigest'=>null,'selection_state'=>'refresh_required','booking_enabled'=>false,
        'money'=>[
            'search_price'=>['amount'=>'100000.00','currency'=>'RUB','source'=>'supplier_search'],
            'fuel_charge_reported'=>['amount'=>$fuel,'currency'=>'RUB','source'=>$fuelSource],
            'operator_fuel_rule'=>$rule,
            'search_price_fuel_relation'=>'excluded',
            'search_price_with_surcharge'=>['amount'=>$total,'currency'=>'RUB','source'=>'derived_search_estimate'],
            'arithmetic_applied'=>true,
        ],
    ];
}
function confirmationPayload(): array {
    return [
        'listingPrice'=>'120000.00',
        'listingPriceState'=>'search_price_confirmation_required','listingPriceReady'=>false,'finalPriceVerified'=>false,
        'quoteState'=>'unknown','quoteEvidenceDigest'=>null,'selection_state'=>'refresh_required','booking_enabled'=>false,
        'money'=>['search_price'=>['amount'=>'120000.00','currency'=>'RUB','source'=>'supplier_search']],
    ];
}
function row(string $seed, array $payload, int $ready=1, int $verified=0, ?string $display=null): array {
    $raw=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    return [
        'offer_ref_digest'=>hash('sha256',$seed),
        'display_price'=>$display ?? (string)$payload['listingPrice'],
        'currency'=>'RUB','final_price_ready'=>$ready,'final_price_verified'=>$verified,
        'payload_json'=>$raw,'payload_sha256'=>hash('sha256',$raw),
    ];
}

$ownerRule=ruleOwner();
$ownerRule['direction']=['destination'=>'country:4','market'=>'departure:1','operator_family'=>'fun_and_sun'];
$owner=readyPayload($ownerRule,'28756.00','128756.00','operator_fuel_owner_policy');
$supplier=readyPayload(ruleSupplier(),'57512.00','157512.00','operator_fuel_direction_rule');
$badOwner=readyPayload(ruleOwner('7'),'28756.00','128756.00','operator_fuel_owner_policy',true);
$rows=[
    row('owner',$owner),
    row('supplier',$supplier),
    row('confirmation',confirmationPayload(),0,0),
    row('other',$owner),
    row('bad-owner',$badOwner,1,1),
];
$retained=[
    hash('sha256','owner')=>'fun_and_sun',
    hash('sha256','supplier')=>'fun_and_sun',
    hash('sha256','confirmation')=>'fun_and_sun',
    hash('sha256','other')=>'intourist',
    hash('sha256','bad-owner')=>'fun_and_sun',
];
$r=odfr_evaluate_rows($rows,$retained,'fun_and_sun','1','4');
tassert($r['stored_target_count']===4,'stored target');
tassert($r['ready_target_count']===3,'ready target');
tassert($r['verified_target_count']===1,'verified target surfaced');
tassert($r['confirmation_target_count']===1,'confirmation target');
tassert($r['direction_rule_rows']===3,'direction rows');
tassert($r['direction_rule_valid_rows']===2,'owner and supplier authorities valid');
tassert($r['ready_non_target_count']===1,'non-target contamination surfaced');
tassert($r['bad_ready_boundary_count']===1,'verified estimate boundary surfaced');
tassert(($r['validation_failure_counts']['rule_direction']??0)===1,'wrong direction classified');
tassert($r['rule_authorities']===['owner_policy','supplier_direction_evidence'],'authority surfaced');
tassert($r['fuel_sources']===['operator_fuel_direction_rule','operator_fuel_owner_policy'],'fuel source surfaced');
tassert($r['rule_amounts']===['140.00','70.00'],'native authority rates retained');
tassert($r['fuel_charges_rub']===['28756.00','57512.00'],'owner/supplier fuel arithmetic');
tassert($r['rule_native_totals']===['280.00','560.00'],'native totals');
tassert($r['fx_rates']===['102.7'],'typed fx retained');
tassert($r['payload_listing_vs_total_mismatch_count']===0,'listing total persisted');
tassert($r['payload_listing_equals_total_count']===3,'ready listings equal totals');
tassert($r['payload_listing_equals_base_count']===0,'no base listing mismatch');
tassert(count($r['actual_rule_direction_summaries'])===2,'direction summaries sanitized');
tassert(count($r['actual_rule_direction_summary_sha256'])===2,'direction summary digests surfaced');
tassert(($r['validation_failure_counts']['display_total']??0)===0,'base+fuel stored once');

$liveLike=readyPayload(ruleOwner(),'28756.00','128756.00','operator_fuel_owner_policy');
$liveLike['listingPrice']='100000.00';
$rLive=odfr_evaluate_rows(
    [row('live-like',$liveLike,1,0,'128756.00')],
    [hash('sha256','live-like')=>'fun_and_sun'],
    'fun_and_sun','1','4'
);
tassert($rLive['direction_rule_valid_rows']===1,'semantic direction valid');
tassert($rLive['payload_listing_vs_total_mismatch_count']===1,'listing mismatch surfaced');
tassert($rLive['payload_listing_equals_base_count']===1,'base listing mismatch classified');
tassert($rLive['payload_listing_equals_total_count']===0,'mismatch not total');
tassert($rLive['payload_listing_invalid_count']===0,'listing remains valid money');
tassert($rLive['payload_listing_other_count']===0,'mismatch class exact');

$invalidPolicy=ruleOwner();
$invalidPolicy['owner_policy']['amount']='80.00';
$rInvalid=odfr_evaluate_rows(
    [row('invalid-policy',readyPayload($invalidPolicy,'28756.00','128756.00','operator_fuel_owner_policy'))],
    [hash('sha256','invalid-policy')=>'fun_and_sun'],
    'fun_and_sun','1','4'
);
tassert(($rInvalid['validation_failure_counts']['rule_authority']??0)===1,'invalid owner policy rejected');

$bad=$rows[0];
$bad['payload_sha256']=str_repeat('0',64);
$r2=odfr_evaluate_rows([$bad],[hash('sha256','owner')=>'fun_and_sun'],'fun_and_sun','1','4');
tassert($r2['payload_hash_invalid_count']===1,'payload hash guard');
tassert($r2['direction_rule_rows']===0,'bad hash not trusted');

echo "int operator direction fuel mass readback: PASS\n";
