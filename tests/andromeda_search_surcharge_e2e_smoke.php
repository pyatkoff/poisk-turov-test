<?php
declare(strict_types=1);
define('ANYTOUR_ANDROMEDA_SURCHARGE_E2E_TEST_MODE', true);
require __DIR__ . '/../scripts/diagnostics/andromeda_search_surcharge_e2e.php';
require __DIR__ . '/../app/integrations/andromeda-price-observation.php';
require __DIR__ . '/../app/integrations/andromeda-search-surcharge.php';

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
    // Actual normalizer price shape: metadata stays on the original search fact.
    'price' => ['amount' => '100000.00', 'currency' => 'RUB', 'currency_id' => '1',
        'kind' => 'offer', 'fees' => 'unknown', 'final' => false],
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
// Use the real calculator, not a hand-built fact that copies extra base metadata.
$claim = ['claimDocument' => [[]], 'variants' => []];
$claim['variants'][0]['transports'][0]['transport'][0] = [
    'type' => 'ttAvia', 'details' => [['detail' => [['markup' => '14356.00', 'currency' => 'RUB']]]],
];
$after['search_surcharge'] = AnyTourAndromedaSearchSurcharge::estimate($claim, $before['price']);
$after['price'] = $after['search_surcharge']['search_price_with_surcharge'];
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
];
// The actual recorder intentionally stores money without the listing provenance tag.
$quote['served_price_observation'] = AnyTourAndromedaPriceObservation::compareServed([
    'served_price' => ['amount' => '114356.00', 'currency' => 'RUB'],
    'basis' => 'transport_surcharge_estimate', 'issued_at' => 1,
], $quote, 2);
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

// Provenance is validated separately, not discarded from the input or guessed.
foreach ([['source','unexpected'], ['currency','USD'], ['amount','140000.00'], ['extra',true]] as [$key,$value]) {
    $wrong = $listed; $wrong['price'][$key] = $value;
    try {
        anytour_andromeda_surcharge_e2e_verify_quote($wrong, $quote);
        throw new RuntimeException('foreign money accepted');
    } catch (RuntimeException $expected) {
        if ($expected->getMessage() !== 'served_quote_observation_invalid') throw $expected;
    }
}
if ($listed['price']['source'] !== 'derived_search_estimate') throw new RuntimeException('listing mutated');

$broken = $after;
unset($broken['base_search_price']);
try {
    anytour_andromeda_surcharge_e2e_verify($before, $broken);
    throw new RuntimeException('missing base was accepted');
} catch (RuntimeException $expected) {
    if ($expected->getMessage() !== 'listing_surcharge_not_applied') throw $expected;
}

// Original provenance must survive unchanged; a foreign base/fact is still refused.
foreach ([['base_search_price','amount','99000.00'], ['base_search_price','fees','included'],
    ['search_price','amount','99000.00'], ['search_price','currency','USD']] as [$where,$key,$value]) {
    $wrong = $after;
    if ($where === 'base_search_price') $wrong[$where][$key] = $value;
    else $wrong['search_surcharge'][$where][$key] = $value;
    try {
        anytour_andromeda_surcharge_e2e_verify($before, $wrong);
        throw new RuntimeException('foreign base accepted');
    } catch (RuntimeException $expected) {
        if ($expected->getMessage() !== 'listing_surcharge_not_applied') throw $expected;
    }
}
if ($after['base_search_price'] !== $before['price'] || $before['price']['fees'] !== 'unknown') {
    throw new RuntimeException('base metadata changed');
}

// The same normalized base shape is served when no surcharge is known.
$baseListed = $before; $baseListed['listing_price_ref'] = $listed['listing_price_ref'];
$baseQuote = $quote;
$baseQuote['served_price_observation'] = AnyTourAndromedaPriceObservation::compareServed([
    'served_price' => ['amount' => '100000.00', 'currency' => 'RUB'],
    'basis' => 'search_base', 'issued_at' => 1,
], $baseQuote, 2);
$baseEvidence = anytour_andromeda_surcharge_e2e_verify_quote($baseListed, $baseQuote);
if ($baseEvidence['price_basis'] !== 'search_base' || $baseEvidence['signed_delta_amount'] !== '20000.00') {
    throw new RuntimeException('base-only quote comparison failed');
}
foreach ([['amount','99999.00'], ['kind','other'], ['fees','included'], ['final',true],
    ['currency_id',true], ['extra',true]] as [$key,$value]) {
    $wrong = $baseListed; $wrong['price'][$key] = $value;
    try {
        anytour_andromeda_surcharge_e2e_verify_quote($wrong, $baseQuote);
        throw new RuntimeException('foreign base-only money accepted');
    } catch (RuntimeException $expected) {
        if ($expected->getMessage() !== 'served_quote_observation_invalid') throw $expected;
    }
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

// A normal structured rejection must survive the pinned SSH helper's exit contract.
$process = proc_open([PHP_BINARY, '-d', 'allow_url_fopen=0',
    __DIR__ . '/../scripts/diagnostics/andromeda_search_surcharge_e2e.php'],
    [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, sys_get_temp_dir());
if (!is_resource($process)) throw new RuntimeException('CLI fixture unavailable');
$stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
$reply = json_decode($stdout, true, 16, JSON_THROW_ON_ERROR);
if ($exit !== 0 || $stderr !== '' || $reply['status'] !== 'blocked'
    || $reply['phase'] !== 'preflight' || $reply['reason'] !== 'project_invalid'
    || $reply['calc_calls'] !== 0 || $reply['booking_calls'] !== 0) {
    throw new RuntimeException('structured rejection lost on CLI wire');
}

echo "Andromeda surcharge E2E: new cohort + served listing to verified quote contract passed\n";
