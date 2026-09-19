<?php
declare(strict_types=1);

// Reuse the existing offline client and quote fixtures; their verified-price
// regressions run as well. All amounts below are SYNTHETIC, not live prices.
require __DIR__ . '/andromeda-selected-quote-smoke.php';

$fuelChecks = 0;
function fuel_need(bool $ok, string $why): void
{
    global $fuelChecks;
    ++$fuelChecks;
    if (!$ok) throw new RuntimeException($why);
}

$pending = $ambiguous;
foreach ($pending['variants'][0]['transports'][0]['transport'] as &$transport) {
    if (isset($transport['details'][0]['detail'][0])) {
        $transport['details'][0]['detail'][0]['markup'] = '0';
    }
}
unset($transport);
$service = static fn(mixed $price, string $currency, string $route): array => [
    'type' => 'stOther', 'servicetype' => '8',
    'servicecategoryName' => 'Топливный сбор',
    'price' => $price, 'currencyAlias' => $currency, 'routeIndex' => $route,
    'uid' => 'private_fuel_uid_' . $route,
    'required' => 'true', 'packet' => 'false',
    'clients' => [['client' => [['peopleKey' => 'private_people_key', 'common' => 'private_common']]]],
];
$fact = static fn(string $amount, string $currency, ?string $route): array => [
    'amount' => $amount, 'currency' => $currency, 'route_index' => $route,
    'source' => 'andromeda_claim_service',
];

$runPending = static function(array $reply) use ($resolved, $package): array {
    $before = json_encode($reply, JSON_THROW_ON_ERROR);
    $calls = []; $reservations = 0;
    $actions = new AnyTourAndromedaClaimActions('SID_precalc_fixture',
        static function() use (&$reservations): void { ++$reservations; },
        static function(string $url, string $post) use (&$calls, $reply): array {
            parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
            $calls[] = $query['action'] ?? null;
            if ($query['action'] !== 'get_flights') throw new RuntimeException('PRECALC_UNEXPECTED_ACTION');
            return ['status' => 200, 'body' => json_encode($reply, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
        }
    );
    $result = AnyTourAndromedaSelectedQuote::run($resolved, new AnyTourAndromedaClient($package), $actions);
    fuel_need($calls === ['get_flights'] && $reservations === 1, 'extra_calc_or_changeservice');
    fuel_need($result['state'] === 'flight_selection_required'
        && $result['quote_state'] === 'unverified' && $result['final_price'] === null
        && $result['final_price_verified'] === false && $result['booking_enabled'] === false,
        'fuel_evidence_created_final_authority');
    fuel_need($result['calc_money_facts_reported'] === [], 'invented_calc_money');
    fuel_need($result['search_price'] === ['amount' => '119114', 'currency' => 'RUB']
        && $result['search_price_estimate'] === [
            'amount' => '119114.00', 'currency' => 'RUB', 'source' => 'derived_search_estimate',
        ], 'fuel_was_added_to_flight_estimate');
    foreach (['fuel_total', 'price_with_fuel', 'fuel_included', 'finalPriceReady'] as $unsupported) {
        fuel_need(!array_key_exists($unsupported, $result), 'invented_fuel_basis_or_readiness');
    }
    $encoded = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    foreach (['private_fuel_uid_', 'private_people_key', 'private_common', 'SID_precalc_fixture',
        'opaque-claiminc', 'catalog-reduced', 'out_two'] as $private) {
        fuel_need(!str_contains($encoded, $private), 'private_fuel_detail_leaked');
    }
    fuel_need(json_encode($reply, JSON_THROW_ON_ERROR) === $before, 'supplier_claim_mutated');
    return $result;
};

$withFuel = $pending;
$withFuel['claimDocument'][0]['services'] = [['service' => [
    $service('17', 'USD', '0'), $service('23', 'EUR', '1'),
    ['servicetype' => '9', 'servicecategoryName' => 'Не топливо', 'price' => '999', 'currencyAlias' => 'RUB'],
]]];
$beforeChoice = $runPending($withFuel);
fuel_need($beforeChoice['fuel_surcharges_reported'] === [$fact('17', 'USD', '0'), $fact('23', 'EUR', '1')],
    'PRECALC_FUEL_EVIDENCE_LOST');

// Alternative services must never be mistaken for services in the current claim.
$alternativesOnly = $pending;
$alternativesOnly['variants'][0]['services'] = [['service' => [$service('200', 'USD', '0')]]];
fuel_need($runPending($alternativesOnly)['fuel_surcharges_reported'] === [], 'alternative_fuel_admitted');
$withFuel['variants'][0]['services'] = $alternativesOnly['variants'][0]['services'];
fuel_need($runPending($withFuel)['fuel_surcharges_reported'] === $beforeChoice['fuel_surcharges_reported'],
    'variant_fuel_summed_into_current');

// A missing/invalid amount stays absent; an explicit real zero remains a fact.
foreach ([true, false, null, [], -1, 'not-money', '1e3', '1.234'] as $invalid) {
    $bad = $pending;
    $bad['claimDocument'][0]['services'] = [['service' => [$service($invalid, 'USD', '0')]]];
    fuel_need($runPending($bad)['fuel_surcharges_reported'] === [], 'invalid_fuel_became_zero');
}
$zero = $pending;
$zero['claimDocument'][0]['services'] = [['service' => [$service('0', 'USD', '0')]]];
fuel_need($runPending($zero)['fuel_surcharges_reported'] === [$fact('0', 'USD', '0')], 'explicit_fuel_zero_lost');
$unknownRoute = $pending;
$unknownRoute['claimDocument'][0]['services'] = [['service' => [$service('9', 'USD', 'unknown')]]];
fuel_need($runPending($unknownRoute)['fuel_surcharges_reported'] === [$fact('9', 'USD', null)], 'route_was_invented');

// Real retained packages use multiple supplier service types for the exact fuel category.
$observedKind = $pending;
$observedService = $service('17', 'USD', '0'); $observedService['servicetype'] = '9';
$observedKind['claimDocument'][0]['services'] = [['service' => [$observedService]]];
fuel_need($runPending($observedKind)['fuel_surcharges_reported'] === [[
    'amount' => '17', 'currency' => 'USD', 'route_index' => '0',
    'source' => 'andromeda_claim_service', 'service_type' => '9',
]], 'observed_fuel_service_type_lost');

$wrongKind = $pending;
$wrongService = $service('17', 'USD', '0'); $wrongService['servicecategoryName'] = 'Доплата за рейс';
$wrongKind['claimDocument'][0]['services'] = [['service' => [$wrongService]]];
fuel_need($runPending($wrongKind)['fuel_surcharges_reported'] === [], 'flight_markup_relabeled_as_fuel');
fuel_need($runPending($pending)['fuel_surcharges_reported'] === [], 'missing_fuel_invented');

print("ANDROMEDA_PRECALC_FUEL_EVIDENCE_OK checks={$fuelChecks} live_supplier_calls=0 new_cases_calc_calls=0 live_db_writes=0\n");
