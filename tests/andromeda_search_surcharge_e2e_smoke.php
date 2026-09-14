<?php
declare(strict_types=1);
define('ANYTOUR_ANDROMEDA_SURCHARGE_E2E_TEST_MODE', true);
require __DIR__ . '/../scripts/diagnostics/andromeda_search_surcharge_e2e.php';

$request = anytour_andromeda_surcharge_e2e_request();
if (ANYTOUR_ANDROMEDA_SURCHARGE_E2E_OPERATION !== 'andromeda-search-surcharge-e2e-1717-v2-egypt-2026-12-15-2a1c8'
    || ANYTOUR_ANDROMEDA_SURCHARGE_E2E_RUNTIME_SOURCE !== '64706822fc54f4d4423ea6bbd0ad966144151387'
    || ($request['params']['countryId'] ?? null) !== '1'
    || ($request['params']['departureId'] ?? null) !== '1'
    || ($request['params']['dateFrom'] ?? null) !== '2026-12-15'
    || ($request['params']['dateTo'] ?? null) !== '2026-12-15'
    || ($request['params']['nightsFrom'] ?? null) !== 7
    || ($request['params']['nightsTo'] ?? null) !== 7
    || ($request['params']['adults'] ?? null) !== 2
    || ($request['params']['childs'] ?? null) !== [8]
    || ($request['params']['meal'] ?? null) !== ''
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
    || ($money['served_price']['amount'] ?? null) !== '114356.00'
    || ($money['price_basis'] ?? null) !== 'transport_surcharge_estimate'
    || ($money['arithmetic_applied'] ?? null) !== true
    || ($money['final_price_verified'] ?? null) !== false) {
    throw new RuntimeException('listing money contract changed');
}

$listed = $after;
$listed['listing_price_ref'] = 'listing_' . str_repeat('b', 64);
$quote = [
    'state' => 'quote_verified',
    'final_price_verified' => true,
    'final_price' => ['amount' => '120000.00', 'currency' => 'RUB'],
    'served_price_observation' => [
        'schema_version' => 1,
        'provider' => 'andromeda',
        'basis' => 'search_api_response',
        'state' => 'comparable',
        'price_basis' => 'transport_surcharge_estimate',
        'served_at' => 1,
        'actualized_at' => 2,
        'served_price' => ['amount' => '114356.00', 'currency' => 'RUB'],
        'final_price' => ['amount' => '120000.00', 'currency' => 'RUB'],
        'signed_delta_amount' => '5644.00',
        'absolute_delta_amount' => '5644.00',
        'relative_delta_bps' => 494,
        'final_price_verified' => true,
    ],
];
$quoteEvidence = anytour_andromeda_surcharge_e2e_verify_quote($listed, $quote);
if (($quoteEvidence['price_basis'] ?? null) !== 'transport_surcharge_estimate'
    || ($quoteEvidence['served_price']['amount'] ?? null) !== '114356.00'
    || ($quoteEvidence['final_price']['amount'] ?? null) !== '120000.00'
    || ($quoteEvidence['signed_delta_amount'] ?? null) !== '5644.00'
    || ($quoteEvidence['relative_delta_bps'] ?? null) !== 494
    || ($quoteEvidence['final_price_verified'] ?? null) !== true
    || ($quoteEvidence['listing_price_ref_sha256'] ?? null) !== hash('sha256', $listed['listing_price_ref'])) {
    throw new RuntimeException('served quote comparison changed');
}

$broken = $after;
unset($broken['base_search_price']);
try {
    anytour_andromeda_surcharge_e2e_verify($before, $broken);
    throw new RuntimeException('missing base was accepted');
} catch (RuntimeException $expected) {
    if ($expected->getMessage() !== 'listing_surcharge_not_applied') throw $expected;
}

$tmp = sys_get_temp_dir() . '/andromeda-surcharge-e2e-' . bin2hex(random_bytes(8));
if (!mkdir($tmp, 0700)) throw new RuntimeException('counter fixture mkdir');
try {
    $counter = anytour_andromeda_surcharge_e2e_counter($tmp);
    if (($counter['reserved_requests'] ?? null) !== 0 || ($counter['remaining'] ?? null) !== 5000000) {
        throw new RuntimeException('empty counter changed');
    }
    file_put_contents($tmp . '/monthly-requests.json', json_encode([
        'month' => gmdate('Y-m'), 'reserved_requests' => 42,
        'monthly_limit' => 5000000, 'scope' => 'this_integration',
    ], JSON_THROW_ON_ERROR));
    $counter = anytour_andromeda_surcharge_e2e_counter($tmp);
    if (($counter['reserved_requests'] ?? null) !== 42 || ($counter['remaining'] ?? null) !== 4999958) {
        throw new RuntimeException('counter read changed');
    }
} finally {
    @unlink($tmp . '/monthly-requests.json');
    @rmdir($tmp);
}

if (anytour_andromeda_surcharge_e2e_reason(new RuntimeException('supplier_unavailable')) !== 'supplier_unavailable'
    || anytour_andromeda_surcharge_e2e_reason(new RuntimeException("secret value\nleak")) !== 'operation_unconfirmed') {
    throw new RuntimeException('reason sanitization failed');
}

echo "Andromeda surcharge E2E: new cohort + served listing to verified quote contract passed\n";
