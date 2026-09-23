<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/integrations/andromeda-surcharge-cache-autosave.php';
require_once dirname(__DIR__) . '/app/integrations/three-provider-fuel-evidence.php';

$checks = 0;
$ok = static function(bool $value, string $label) use (&$checks): void {
    ++$checks;
    if (!$value) throw new RuntimeException('FUNSUN_AUTOSAVE:' . $label);
};
$same = static function(mixed $expected, mixed $actual, string $label) use (&$checks): void {
    ++$checks;
    if ($expected !== $actual) {
        throw new RuntimeException('FUNSUN_AUTOSAVE:' . $label
            . ' expected=' . var_export($expected, true)
            . ' actual=' . var_export($actual, true));
    }
};

$root = sys_get_temp_dir() . '/anytour-funsun-owner-autosave-' . bin2hex(random_bytes(6));
$searches = $root . '/searches';
if (!mkdir($searches, 0700, true) && !is_dir($searches)) throw new RuntimeException('mkdir');

$cleanup = static function(string $path) use (&$cleanup): void {
    if (!file_exists($path) && !is_link($path)) return;
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $cleanup($path . '/' . $name);
        }
        rmdir($path);
        return;
    }
    unlink($path);
};

$now = 1790184000;
$party = ['adults'=>2,'children'=>1,'child_ages'=>[5]];
$request = ['departureId'=>1,'countryId'=>4,'adults'=>2,'childs'=>[5]];
$offerRef = 'offer_' . hash('sha256', 'funsun-autosave-target');
$offer = [
    'operator'=>'FUN&SUN',
    'offer_ref'=>$offerRef,
    'adults'=>2,
    'children'=>1,
    'price'=>['amount'=>'100000','currency'=>'RUB'],
];

$putFxObservation = static function(string $directory, int $observedAt, int $expiresAt, string $seed): void {
    $key = ['operator_family'=>'fun_and_sun','program_key'=>'114','tour_key'=>'78'];
    $row = AnyTourOperatorProgramFuelRegistryV1::observation([
        'key'=>$key,
        'unit'=>'per_person_one_way',
        'amount'=>'999.00', // Fuel fact is intentionally irrelevant: one observation cannot confirm an exact program rule.
        'currency'=>'EUR',
        'direction_count'=>2,
        'base_relation'=>'excluded',
        'flight_pair'=>null,
        'offer_ref_digest'=>hash('sha256', 'fx-offer-' . $seed),
        'evidence_sha256'=>hash('sha256', 'fuel-evidence-' . $seed),
        'source'=>'andromeda_get_flights',
        'observed_at'=>$observedAt - 10,
        'expires_at'=>$expiresAt + 10,
        'exchange'=>[
            'from'=>'EUR','to'=>'RUB','rate'=>'100',
            'observed_at'=>$observedAt,'expires_at'=>$expiresAt,
            'evidence_sha256'=>hash('sha256', 'fx-evidence-' . $seed),
        ],
    ]);
    $digest = AnyTourOperatorFuelRuleEvidenceV1::hash($key);
    $value = ['version'=>1,'key_sha256'=>$digest,'key'=>$key,'observations'=>[$row]];
    file_put_contents(
        $directory . '/operator-program-fuel-v1-' . $digest . '.json',
        json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)
    );
};

try {
    $putFxObservation($searches, $now - 30, $now + 300, 'fresh');
    $same([], glob($searches . '/operator-fuel-rule-v2-*.json') ?: [], 'no direction seed before resolve');

    // One exact-program observation is deliberately below the 2-offer confirmation threshold.
    $same(null, AnyTourOperatorProgramFuelRegistryV1::priceForOffer($searches, $offer, $party, $now), 'exact program remains unconfirmed');

    $pricing = AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(
        null,
        $offer,
        $request,
        $searches,
        $now
    );
    $ok(is_array($pricing), 'owner fallback missing from normal autosave bridge');
    $same('operator_fuel', $pricing['state'] ?? null, 'fallback state');
    $input = $pricing['operator_fuel'] ?? null;
    $ok(is_array($input), 'operator input missing');
    $same([], $input['observations'] ?? null, 'must not synthesize supplier direction evidence');
    $same('70.00', $input['owner_policy']['amount'] ?? null, 'owner native rate');
    $same('EUR', $input['owner_policy']['currency'] ?? null, 'owner native currency');
    $same('per_person_one_way', $input['owner_policy']['unit'] ?? null, 'owner native unit');
    $same('100', $input['exchange']['rate'] ?? null, 'retained supplier FX supplied');
    $same(
        AnyTourOperatorFuelRuleEvidenceV1::directionDigest([
            'operator_family'=>'fun_and_sun','market'=>'departure:1','destination'=>'country:4',
        ]),
        $input['exchange']['scope_sha256'] ?? null,
        'FX bound to target direction'
    );
    $same([], glob($searches . '/operator-fuel-rule-v2-*.json') ?: [], 'fallback created fake direction evidence');

    $dto = [
        'provider'=>'andromeda','operator'=>['raw'=>'FUN&SUN'],
        'identity'=>['offer_ref_digest'=>hash('sha256', $offerRef)],
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
    $same(true, $applied['applied'] ?? null, 'owner fallback not applicable downstream');
    $out = $applied['dto'];
    $same('100000', $out['money']['search_price']['amount'] ?? null, 'supplier base changed');
    $same(
        ['amount'=>'42000.00','currency'=>'RUB','source'=>'operator_fuel_owner_policy'],
        $out['money']['fuel_charge_reported'] ?? null,
        '70 EUR x 3 eligible x 2 directions not applied once'
    );
    $same('142000.00', $out['price'] ?? null, 'derived listing total');
    $same(true, $out['finalPriceReady'] ?? null, 'listing estimate not ready');
    $same(false, $out['final_price_verified'] ?? null, 'estimate became final verified');

    // Existing exact pricing is immutable and must still win before every fallback.
    $exact = ['state'=>'verified','verified_quote'=>['sentinel'=>'exact'],'fact'=>null];
    $same(
        $exact,
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve($exact, $offer, $request, $searches, $now),
        'exact pricing precedence changed'
    );

    // Owner policy is Turkey-only and generic fallback still excludes infants.
    $wrongCountry = $request;
    $wrongCountry['countryId'] = 8;
    $same(null, AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null, $offer, $wrongCountry, $searches, $now), 'fallback leaked outside Turkey');
    $infantRequest = $request;
    $infantRequest['childs'] = [1];
    $same(null, AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null, $offer, $infantRequest, $searches, $now), 'infant entered generic fallback');

    $stale = $root . '/stale/searches';
    if (!mkdir($stale, 0700, true) && !is_dir($stale)) throw new RuntimeException('mkdir stale');
    $putFxObservation($stale, $now - 600, $now - 1, 'stale');
    $same(null, AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null, $offer, $request, $stale, $now), 'stale FX activated owner fallback');

    fwrite(STDOUT, 'INT_FUNSUN_OWNER_AUTOSAVE_FALLBACK_OK checks=' . $checks
        . " supplier=0 db=0 direction_seed=0 final_verified=false\n");
} finally {
    $cleanup($root);
}
