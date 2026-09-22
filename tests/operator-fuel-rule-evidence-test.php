<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/operator-fuel-rule-evidence.php';
require_once __DIR__ . '/../app/integrations/operator-fuel-rule-store.php';

function fuel_ok(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function fuel_h(string $v): string { return hash('sha256', $v); }

$scope = [
    'market' => 'RU-MOW',
    'outbound' => ['origin'=>'VKO','destination'=>'AYT','carrier'=>'TK','flight'=>'TK212'],
    'return' => ['origin'=>'AYT','destination'=>'VKO','carrier'=>'TK','flight'=>'TK211'],
    'party' => ['adults'=>2,'children'=>0,'child_ages'=>[]],
];
$base = [
    'provider'=>'tourvisor', 'operator'=>'FUN&SUN', 'scope'=>$scope,
    'unit'=>'party_roundtrip', 'base_relation'=>'excluded', 'amount'=>'180.00', 'currency'=>'EUR',
    'source'=>'tourvisor_flights_fuel', 'observed_at'=>1000, 'expires_at'=>5000,
    'valid_from'=>'2026-09-01', 'valid_to'=>'2026-10-31',
    'base_includes_other_required_charges'=>true,
    'source_response_sha256'=>fuel_h('resp1'),
];
$a = $base + ['offer_ref_digest'=>fuel_h('offer1'),'evidence_sha256'=>fuel_h('ev1')];
$b = ($base + ['source_response_sha256'=>fuel_h('resp2')])
    + ['offer_ref_digest'=>fuel_h('offer2'),'evidence_sha256'=>fuel_h('ev2')];

fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::operatorFamily('Интурист') === 'intourist', 'intourist family');
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::operatorFamily('FUN & SUN') === 'fun_and_sun', 'fun family');
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::operatorFamily('Библио-Глобус') === 'biblio_globus', 'bg family');
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::operatorFamily('ANEX') === null, 'anex excluded from target compiler');

$obs = AnyTourOperatorFuelRuleEvidenceV1::observation($a);
fuel_ok($obs['unit'] === 'party_roundtrip' && $obs['scope']['outbound']['flight'] === 'TK212', 'observation normalized');
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::observation($obs) === $obs, 'observation idempotent');

$target = [
    'provider'=>'tourvisor', 'operator'=>'FUN&SUN', 'scope'=>$scope,
    'offer_ref_digest'=>fuel_h('target'), 'flight_dates'=>['2026-09-20','2026-09-27'],
];
$input = AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$b], 2000);
fuel_ok(is_array($input), 'two independent observations confirm');
fuel_ok(count($input['observations']) === 2, 'confirmed input retains two evidence rows');
fuel_ok(!array_key_exists('nights', $input['scope']), 'nights not discriminator');

fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a], 2000) === null, 'one sample does not confirm');
$conflict = $b; $conflict['amount'] = '200.00';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$conflict], 2000) === null, 'amount conflict blocks rule');
$wrongFlight = $b; $wrongFlight['scope']['outbound']['flight'] = 'TK999';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$wrongFlight], 2000) === null, 'different flight does not mix');
$wrongAge = $b; $wrongAge['scope']['party'] = ['adults'=>1,'children'=>1,'child_ages'=>[5]];
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$wrongAge], 2000) === null, 'different party does not mix');
$unknownUnit = $b; $unknownUnit['unit'] = 'route_reported_unknown';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$unknownUnit], 2000) === null, 'unknown unit never promoted');
$unknownRelation = $b; $unknownRelation['base_relation'] = 'unknown';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$unknownRelation], 2000) === null, 'unknown inclusion never promoted');

/* Exact shape retained in artifact10590488429: literal amounts but no operator/flight/unit binding.
 * It must not be promoted or assigned to any of the three operators. */
$unboundSavedSamo = [
    'provider'=>'andromeda', 'amount'=>'170', 'currency'=>'EUR', 'unit'=>'route_reported_unknown',
    'base_relation'=>'unknown', 'source'=>'andromeda_claim_service', 'observed_at'=>1000, 'expires_at'=>5000,
    'valid_from'=>'2026-09-01', 'valid_to'=>'2026-10-31', 'base_includes_other_required_charges'=>false,
    'offer_ref_digest'=>fuel_h('saved-offer'), 'evidence_sha256'=>fuel_h('saved-evidence'),
    'source_response_sha256'=>fuel_h('saved-response'),
];
$rejectedUnbound = false;
try { AnyTourOperatorFuelRuleEvidenceV1::observation($unboundSavedSamo); } catch (InvalidArgumentException $expected) { $rejectedUnbound = true; }
fuel_ok($rejectedUnbound, 'unbound saved SAMO amount must stay unassigned');

$root = sys_get_temp_dir() . '/operator-fuel-' . bin2hex(random_bytes(5));
$dir = $root . '/searches';
mkdir($dir, 0700, true);
$write = static function(string $path, array $value): bool {
    return file_put_contents($path, json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR), LOCK_EX) !== false;
};
$r1 = AnyTourOperatorFuelRuleStoreV1::append($dir, $a, $write);
$r2 = AnyTourOperatorFuelRuleStoreV1::append($dir, $b, $write);
$r3 = AnyTourOperatorFuelRuleStoreV1::append($dir, $b, $write);
fuel_ok($r1['status'] === 'created' && $r2['status'] === 'appended' && $r3['status'] === 'unchanged', 'store idempotency');
$stored = AnyTourOperatorFuelRuleStoreV1::inputForTarget($dir, $target, 2000);
fuel_ok(is_array($stored) && count($stored['observations']) === 2, 'store returns confirmed target input');
$envelope = AnyTourOperatorFuelRuleStoreV1::pricingEnvelopeForTarget($dir, $target, 2000);
fuel_ok(($envelope['state'] ?? null) === 'operator_fuel' && is_array($envelope['operator_fuel'] ?? null), 'autosave pricing envelope');

$bg = $a; $bg['operator'] = 'Библио-Глобус'; $bg['offer_ref_digest']=fuel_h('bg1'); $bg['evidence_sha256']=fuel_h('bgev1');
AnyTourOperatorFuelRuleStoreV1::append($dir, $bg, $write);
fuel_ok(AnyTourOperatorFuelRuleStoreV1::inputForTarget($dir, $target, 2000) !== null, 'other operator cannot poison target rule');

fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$b], 6000) === null, 'expired evidence not reused');
array_map('unlink', glob($dir.'/*') ?: []); rmdir($dir); rmdir($root);
echo "PASS operator fuel evidence/store\n";
