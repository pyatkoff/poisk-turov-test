<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/operator-fuel-rule-evidence.php';
require_once __DIR__ . '/../app/integrations/operator-fuel-rule-store.php';

function fuel_ok(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function fuel_h(string $v): string { return hash('sha256', $v); }

$party = ['adults'=>2,'children'=>0,'child_ages'=>[]];
$scopeA = [
    'market' => 'tourvisor:departure:1',
    'outbound' => ['origin'=>'VKO','destination'=>'AYT','carrier'=>'TK','flight'=>'TK212'],
    'return' => ['origin'=>'AYT','destination'=>'VKO','carrier'=>'TK','flight'=>'TK211'],
    'party' => $party,
];
$scopeB = [
    'market' => 'andromeda:departure:1',
    'outbound' => ['origin'=>'SVO','destination'=>'AYT','carrier'=>'SU','flight'=>'SU2142'],
    'return' => ['origin'=>'AYT','destination'=>'SVO','carrier'=>'SU','flight'=>'SU2143'],
    'party' => $party,
];
$base = [
    'operator'=>'FUN&SUN',
    'unit'=>'party_roundtrip', 'base_relation'=>'excluded', 'amount'=>'180.00', 'currency'=>'EUR',
    'observed_at'=>1000, 'expires_at'=>5000,
    'base_includes_other_required_charges'=>true,
];
$a = $base + [
    'provider'=>'tourvisor','scope'=>$scopeA,'direction'=>['market'=>'departure:1','destination'=>'country:4'],'source'=>'tourvisor_flights_fuel',
    'valid_from'=>'2026-09-01','valid_to'=>'2026-09-30',
    'offer_ref_digest'=>fuel_h('offer1'),'evidence_sha256'=>fuel_h('ev1'),
    'source_response_sha256'=>fuel_h('resp1'),
];
$b = $base + [
    'provider'=>'andromeda','scope'=>$scopeB,'direction'=>['market'=>'departure:1','destination'=>'country:4'],'source'=>'andromeda_claim_service',
    // A different evidence month is deliberately compatible: date is provenance,
    // not a reusable direction discriminator.
    'valid_from'=>'2026-10-01','valid_to'=>'2026-10-31',
    'offer_ref_digest'=>fuel_h('offer2'),'evidence_sha256'=>fuel_h('ev2'),
    'source_response_sha256'=>fuel_h('resp2'),
];

fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::operatorFamily('Интурист') === 'intourist', 'intourist family');
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::operatorFamily('FUN & SUN') === 'fun_and_sun', 'fun family');
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::operatorFamily('Библио-Глобус') === 'biblio_globus', 'bg family');
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::operatorFamily('ANEX') === null, 'anex excluded from generic rule');

$obsA = AnyTourOperatorFuelRuleEvidenceV1::observation($a);
$obsB = AnyTourOperatorFuelRuleEvidenceV1::observation($b);
fuel_ok($obsA['schema_version'] === 2, 'v2 observation');
fuel_ok($obsA['scope']['outbound']['flight'] === 'TK212', 'exact flight retained as provenance');
fuel_ok($obsB['scope']['outbound']['flight'] === 'SU2142', 'different flight retained as provenance');
fuel_ok($obsA['direction'] === $obsB['direction'], 'different providers/flights collapse to one direction');
fuel_ok($obsA['direction'] === [
    'operator_family'=>'fun_and_sun','market'=>'departure:1','destination'=>'country:4'
], 'canonical direction');
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::observation($obsA) === $obsA, 'observation idempotent');

$target = [
    'operator'=>'FUN&SUN',
    'search_params'=>['departureId'=>1,'countryId'=>4],
    'party'=>$party,
    'offer_ref_digest'=>fuel_h('target'),
];
$input = AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$b], 2000);
fuel_ok(is_array($input), 'two independent direction observations confirm');
fuel_ok(count($input['observations']) === 2, 'confirmed input retains two provenance rows');
fuel_ok(!array_key_exists('flight_dates', $input), 'target dates removed from reusable rule');
fuel_ok(!array_key_exists('scope', $input), 'exact flight scope removed from reusable rule input');
fuel_ok($input['direction'] === $obsA['direction'], 'direction emitted');

fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a], 2000) === null, 'one sample still not enough corroboration');
$conflict = $b; $conflict['amount'] = '200.00';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$conflict], 2000) === null, 'amount conflict blocks rule');
$wrongFlight = $b; $wrongFlight['scope']['outbound']['flight'] = 'SU999';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$wrongFlight], 2000) !== null, 'different flight no longer splits rule');
$wrongCarrier = $b; $wrongCarrier['scope']['outbound']['carrier'] = 'PC';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$wrongCarrier], 2000) !== null, 'different carrier no longer splits rule');
$wrongDestination = $b;
$wrongDestination['direction']['destination'] = 'country:5';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$wrongDestination], 2000) === null, 'different destination remains different rule');
$wrongMarket = $b; $wrongMarket['direction']['market'] = 'departure:2';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$wrongMarket], 2000) === null, 'different origin market remains different rule');
$wrongParty = $b; $wrongParty['scope']['party'] = ['adults'=>1,'children'=>1,'child_ages'=>[5]];
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$wrongParty], 2000) === null, 'party total not divided/reinterpreted');
$infantTarget = $target; $infantTarget['party']=['adults'=>2,'children'=>1,'child_ages'=>[1]];
$infantA=$a; $infantA['scope']['party']=$infantTarget['party'];
$infantB=$b; $infantB['scope']['party']=$infantTarget['party'];
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($infantTarget, [$infantA,$infantB], 2000) === null, 'infant kept outside generic fuel');
$unknownUnit = $b; $unknownUnit['unit'] = 'route_reported_unknown';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$unknownUnit], 2000) === null, 'unknown unit never promoted');
$unknownRelation = $b; $unknownRelation['base_relation'] = 'unknown';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$unknownRelation], 2000) === null, 'unknown inclusion never promoted');

// Per-person one-way rates are reusable across non-infant party composition.
$ppPartyA=['adults'=>1,'children'=>0,'child_ages'=>[]];
$ppPartyB=['adults'=>2,'children'=>0,'child_ages'=>[]];
$ppTarget=$target;
$ppTarget['party']=['adults'=>2,'children'=>1,'child_ages'=>[5]];
$ppA=$a;
$ppA['provider']='andromeda';$ppA['source']='andromeda_claim_service';
$ppA['unit']='per_person_one_way';$ppA['amount']='85.00';
$ppA['scope']['party']=$ppPartyA;
$ppA['offer_ref_digest']=fuel_h('pp-offer-a');$ppA['evidence_sha256']=fuel_h('pp-ev-a');
$ppA['source_response_sha256']=fuel_h('pp-resp-a');
$ppB=$b;
$ppB['provider']='andromeda';$ppB['source']='andromeda_claim_service';
$ppB['unit']='per_person_one_way';$ppB['amount']='85.00';
$ppB['scope']['party']=$ppPartyB;
$ppB['offer_ref_digest']=fuel_h('pp-offer-b');$ppB['evidence_sha256']=fuel_h('pp-ev-b');
$ppB['source_response_sha256']=fuel_h('pp-resp-b');
$ppInput=AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($ppTarget,[$ppA,$ppB],2000);
fuel_ok(is_array($ppInput),'per-person independent observations confirm');
fuel_ok(($ppInput['observations'][0]['unit']??null)==='per_person_one_way','per-person unit retained');
fuel_ok(($ppInput['party']??null)===$ppTarget['party'],'target party retained for later arithmetic');
fuel_ok(($ppInput['observations'][0]['party']??null)!==$ppTarget['party'],'source party remains provenance, not applicability key');
$ppConflict=$ppB;$ppConflict['amount']='90.00';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($ppTarget,[$ppA,$ppConflict],2000)===null,'per-person rate conflict blocks rule');
$ppInfant=$ppTarget;$ppInfant['party']=['adults'=>2,'children'=>1,'child_ages'=>[1]];
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($ppInfant,[$ppA,$ppB],2000)===null,'per-person infant target stays separate');

$root = sys_get_temp_dir() . '/operator-fuel-v2-' . bin2hex(random_bytes(5));
$dir = $root . '/searches';
mkdir($dir, 0700, true);
$write = static function(string $path, array $value): bool {
    return file_put_contents($path, json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR), LOCK_EX) !== false;
};
$r1 = AnyTourOperatorFuelRuleStoreV1::append($dir, $a, $write);
$r2 = AnyTourOperatorFuelRuleStoreV1::append($dir, $b, $write);
$r3 = AnyTourOperatorFuelRuleStoreV1::append($dir, $b, $write);
fuel_ok($r1['status'] === 'created' && $r2['status'] === 'appended' && $r3['status'] === 'unchanged', 'direction store idempotency');
fuel_ok($r1['path'] === $r2['path'], 'different provider/flight stored under same direction key');
fuel_ok(str_contains(basename($r1['path']), 'operator-fuel-rule-v2-'), 'v2 namespace isolates old flight-key files');
$stored = AnyTourOperatorFuelRuleStoreV1::inputForTarget($dir, $target, 2000);
fuel_ok(is_array($stored) && count($stored['observations']) === 2, 'store returns confirmed direction input');
$envelope = AnyTourOperatorFuelRuleStoreV1::pricingEnvelopeForTarget($dir, $target, 2000);
fuel_ok(($envelope['state'] ?? null) === 'operator_fuel' && is_array($envelope['operator_fuel'] ?? null), 'autosave pricing envelope');

$otherDirection = $b;
$otherDirection['direction']['market']='departure:2';
$otherDirection['offer_ref_digest']=fuel_h('offer-other');
$otherDirection['evidence_sha256']=fuel_h('ev-other');
AnyTourOperatorFuelRuleStoreV1::append($dir,$otherDirection,$write);
fuel_ok(count(glob($dir.'/operator-fuel-rule-v2-*.json') ?: []) === 2, 'different origin produces second file');

fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$b], 6000) === null, 'expired evidence not reused');
array_map('unlink', glob($dir.'/*') ?: []); rmdir($dir); rmdir($root);
echo "PASS operator fuel direction evidence/store\n";