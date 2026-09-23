<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/integrations/operator-fuel-rule-store.php';
require_once dirname(__DIR__) . '/app/integrations/three-provider-fuel-evidence.php';

function owner_ok(bool $value, string $message): void
{
    if (!$value) throw new RuntimeException('OWNER_NO_SEED:' . $message);
}
function owner_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('OWNER_NO_SEED:' . $message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

$root = sys_get_temp_dir() . '/anytour-funsun-owner-no-seed-' . bin2hex(random_bytes(6));
$searches = $root . '/searches';
if (!mkdir($searches, 0700, true) && !is_dir($searches)) throw new RuntimeException('mkdir');

$now = 1790182800;
$party = ['adults'=>2,'children'=>1,'child_ages'=>[5]];
$canonicalDirection = ['operator_family'=>'fun_and_sun','market'=>'departure:1','destination'=>'country:4'];
$target = [
    'operator'=>'FUN&SUN',
    'direction'=>['market'=>'departure:1','destination'=>'country:4'],
    'party'=>$party,
    'offer_ref_digest'=>hash('sha256','owner-no-seed-target'),
];
$fx = [
    'from'=>'EUR','to'=>'RUB','rate'=>'100',
    'scope_sha256'=>AnyTourOperatorFuelRuleEvidenceV1::directionDigest($canonicalDirection),
    'evidence_sha256'=>hash('sha256','owner-no-seed-fx'),
    'observed_at'=>$now-30,'expires_at'=>$now+300,
];

try {
    owner_same([], glob($searches . '/operator-fuel-rule-v2-*.json') ?: [], 'store starts empty');
    owner_same(null, AnyTourOperatorFuelRuleStoreV1::inputForTarget($searches, $target, $now, $fx), 'no supplier direction seed is confirmed');

    $pricing = AnyTourOperatorFuelRuleStoreV1::pricingEnvelopeForTarget($searches, $target, $now, $fx);
    owner_ok(is_array($pricing), 'owner fallback envelope missing on empty store');
    owner_same('operator_fuel', $pricing['state'] ?? null, 'fallback envelope state');
    $input = $pricing['operator_fuel'] ?? null;
    owner_ok(is_array($input), 'fallback input missing');
    owner_same([], $input['observations'] ?? null, 'owner fallback must not synthesize supplier observations');
    owner_same($canonicalDirection, $input['direction'] ?? null, 'canonical direction');
    owner_same($party, $input['party'] ?? null, 'party binding');
    owner_same('70.00', $input['owner_policy']['amount'] ?? null, 'owner native rate');
    owner_same('EUR', $input['owner_policy']['currency'] ?? null, 'owner native currency');
    owner_same('per_person_one_way', $input['owner_policy']['unit'] ?? null, 'owner native unit');
    owner_same([], glob($searches . '/operator-fuel-rule-v2-*.json') ?: [], 'owner fallback wrote a fake direction store');

    $dto = [
        'provider'=>'andromeda','operator'=>['raw'=>'FUN&SUN'],
        'identity'=>['offer_ref_digest'=>$target['offer_ref_digest']],
        'tour'=>['checkin'=>'2026-10-05','nights'=>7,'party'=>$party],
        'money'=>[
            'search_price'=>['amount'=>'100000','currency'=>'RUB','source'=>'andromeda_search'],
            'fuel_charge_reported'=>null,'additional_prices_reported'=>[],
            'arithmetic_applied'=>false,'final_price_verified'=>false,'search_price_fuel_relation'=>'unknown',
        ],
        'price'=>'100000','currency'=>'RUB','finalPriceReady'=>false,'finalPrice'=>null,
        'quote_state'=>'unknown','final_price_verified'=>false,'quote_evidence_digest'=>null,
        'selection_state'=>'disabled','booking_enabled'=>false,
    ];
    $applied = AnyTourThreeProviderFuelEvidenceV1::apply($dto, $input, $now);
    owner_same(true, $applied['applied'] ?? null, 'owner fallback not applied without supplier seed');
    owner_same(0, $applied['matched_observations'] ?? null, 'owner fallback depended on supplier observations');
    $out = $applied['dto'];
    owner_same('100000', $out['money']['search_price']['amount'] ?? null, 'supplier base mutated');
    owner_same(['amount'=>'42000.00','currency'=>'RUB','source'=>'operator_fuel_owner_policy'], $out['money']['fuel_charge_reported'] ?? null, '70 EUR x 3 eligible x 2 directions at FX100');
    owner_same('142000.00', $out['price'] ?? null, 'derived listing total');
    owner_same(true, $out['finalPriceReady'] ?? null, 'listing estimate not ready');
    owner_same(false, $out['final_price_verified'] ?? null, 'owner estimate promoted final verification');
    owner_same(0, $out['money']['operator_fuel_rule']['independent_offer_count'] ?? null, 'fake independent offers');
    owner_same(0, $out['money']['operator_fuel_rule']['evidence_count'] ?? null, 'fake supplier evidence');
    owner_same('2026-09-23', $out['money']['operator_fuel_rule']['evidence_period_from'] ?? null, 'owner source date missing');
    owner_same(PHP_INT_MAX, $out['money']['operator_fuel_rule']['expires_at'] ?? null, 'owner native policy tied to supplier expiry');
    owner_same($now+300, $out['money']['operator_fuel_rule']['price_evidence_expires_at'] ?? null, 'FX freshness not bounding displayed estimate');
    $ruleSha = $out['money']['operator_fuel_rule']['rule_sha256'] ?? null;

    $nextFx = $fx;
    $nextFx['rate'] = '101';
    $nextFx['evidence_sha256'] = hash('sha256','owner-no-seed-fx-next');
    $nextFx['expires_at'] = $now + 240;
    $nextPricing = AnyTourOperatorFuelRuleStoreV1::pricingEnvelopeForTarget($searches, $target, $now, $nextFx);
    $nextApplied = AnyTourThreeProviderFuelEvidenceV1::apply($dto, $nextPricing['operator_fuel'], $now);
    owner_same(true, $nextApplied['applied'] ?? null, 'fresh FX owner fallback failed');
    owner_same('142420.00', $nextApplied['dto']['price'] ?? null, 'fresh FX did not update RUB estimate');
    owner_same($ruleSha, $nextApplied['dto']['money']['operator_fuel_rule']['rule_sha256'] ?? null, 'FX changed native owner rule identity');

    $expiredFx = $fx; $expiredFx['expires_at'] = $now;
    owner_same(null, AnyTourOperatorFuelRuleStoreV1::pricingEnvelopeForTarget($searches, $target, $now, $expiredFx), 'expired FX accepted');
    owner_same(null, AnyTourOperatorFuelRuleStoreV1::pricingEnvelopeForTarget($searches, $target, $now, null), 'missing FX accepted');
    $wrongCountry = $target; $wrongCountry['direction']['destination'] = 'country:8';
    owner_same(null, AnyTourOperatorFuelRuleStoreV1::pricingEnvelopeForTarget($searches, $wrongCountry, $now, $fx), 'owner fallback leaked outside Turkey');
    $wrongOperator = $target; $wrongOperator['operator'] = 'Интурист';
    owner_same(null, AnyTourOperatorFuelRuleStoreV1::pricingEnvelopeForTarget($searches, $wrongOperator, $now, $fx), 'owner fallback leaked to another operator');
    $infant = $target; $infant['party'] = ['adults'=>2,'children'=>1,'child_ages'=>[1]];
    owner_same(null, AnyTourOperatorFuelRuleStoreV1::pricingEnvelopeForTarget($searches, $infant, $now, $fx), 'infant entered generic owner fallback');

    $ordinary = $input;
    unset($ordinary['owner_policy']);
    $ordinaryResult = AnyTourThreeProviderFuelEvidenceV1::apply($dto, $ordinary, $now);
    owner_same(false, $ordinaryResult['applied'] ?? null, 'empty ordinary direction rule applied');
    owner_same('fuel_independent_evidence_missing', $ordinaryResult['reason'] ?? null, 'ordinary two-evidence guard weakened');

    owner_same([], glob($searches . '/operator-fuel-rule-v2-*.json') ?: [], 'test path created supplier direction store');
    fwrite(STDOUT, "PASS FUN&SUN owner fallback works with empty direction store; 70 EUR native + fresh FX; supplier evidence 0; final_verified=false\n");
} finally {
    foreach (glob($searches . '/*') ?: [] as $path) @unlink($path);
    @rmdir($searches);
    @rmdir($root);
}
