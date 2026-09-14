<?php
declare(strict_types=1);
define('ANYTOUR_ANDROMEDA_SURCHARGE_E2E_TEST_MODE', true);
require __DIR__ . '/../scripts/diagnostics/andromeda_search_surcharge_e2e.php';

$request = anytour_andromeda_surcharge_e2e_request();
if (($request['params']['countryId'] ?? null) !== '1'
    || ($request['params']['departureId'] ?? null) !== '1'
    || ($request['params']['dateFrom'] ?? null) !== '2026-12-10'
    || ($request['params']['dateTo'] ?? null) !== '2026-12-10'
    || ($request['params']['nightsFrom'] ?? null) !== 10
    || ($request['params']['nightsTo'] ?? null) !== 10
    || ($request['params']['adults'] ?? null) !== 2
    || ($request['params']['childs'] ?? null) !== []
    || ($request['params']['meal'] ?? null) !== '7'
    || ($request['andromeda_operator_ids'] ?? null) !== ['5']) {
    throw new RuntimeException('scenario changed');
}

$offerRef = 'offer_' . str_repeat('a', 64);
$before = [
    'provider' => 'andromeda',
    'offer_ref' => $offerRef,
    'offer_context' => ['provider' => 'andromeda', 'offer_ref' => $offerRef],
    'price' => ['amount' => '100000.00', 'currency' => 'RUB'],
];
$projection = ['hotels' => [['local_id' => 9365, 'tours' => [$before]]]];
$picked = anytour_andromeda_surcharge_e2e_pick($projection);
if (($picked['local_id'] ?? null) !== 9365 || ($picked['tour']['offer_ref'] ?? null) !== $offerRef) {
    throw new RuntimeException('mapped offer not selected');
}
if (anytour_andromeda_surcharge_e2e_find($projection, $offerRef) !== $before) {
    throw new RuntimeException('retained offer not found');
}

$after = $before;
$after['base_search_price'] = $before['price'];
$after['price'] = ['amount' => '114356.00', 'currency' => 'RUB'];
$after['search_surcharge'] = [
    'state' => 'estimated',
    'arithmetic_applied' => true,
    'final_price_verified' => false,
    'search_price' => $before['price'],
    'party_surcharge' => ['amount' => '14356.00', 'currency' => 'RUB', 'source' => 'andromeda_get_flights_transport'],
    'search_price_with_surcharge' => $after['price'],
];
$money = anytour_andromeda_surcharge_e2e_verify($before, $after);
if (($money['base']['amount'] ?? null) !== '100000.00'
    || ($money['surcharge']['amount'] ?? null) !== '14356.00'
    || ($money['display_estimate']['amount'] ?? null) !== '114356.00'
    || ($money['arithmetic_applied'] ?? null) !== true
    || ($money['final_price_verified'] ?? null) !== false) {
    throw new RuntimeException('listing money contract changed');
}

$broken = $after;
unset($broken['base_search_price']);
try {
    anytour_andromeda_surcharge_e2e_verify($before, $broken);
    throw new RuntimeException('missing base was accepted');
} catch (RuntimeException $expected) {
    if ($expected->getMessage() !== 'listing_surcharge_not_applied') throw $expected;
}

if (anytour_andromeda_surcharge_e2e_reason(new RuntimeException('supplier_unavailable')) !== 'supplier_unavailable'
    || anytour_andromeda_surcharge_e2e_reason(new RuntimeException("secret value\nleak")) !== 'operation_unconfirmed') {
    throw new RuntimeException('reason sanitization failed');
}

echo "Andromeda surcharge E2E: fixed scenario + retained listing money contract passed\n";
