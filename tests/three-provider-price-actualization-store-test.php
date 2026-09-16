<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/three-provider-price-actualization-store.php';
require_once __DIR__ . '/../app/integrations/three-provider-price-actualization-observation.php';

$checks = 0;
function store_check(bool $ok): void { global $checks; ++$checks; if (!$ok) throw new RuntimeException('store_check_' . $checks); }
function store_row(string $provider, string $operator, string $listing, string $quote, int $hotel): array {
    return [
        'schema_version' => 1,
        'provider' => $provider,
        'operator' => $operator,
        'local_hotel_id' => $hotel,
        'tour' => ['checkin' => '2026-12-28', 'nights' => 7, 'party' => ['adults' => 2, 'children' => 0, 'child_ages' => []]],
        'listing_price' => ['amount' => $listing, 'currency' => 'RUB'],
        'verified_quote_price' => ['amount' => $quote, 'currency' => 'RUB'],
        'exact_match' => $listing === $quote,
        'listing_final_price_ready' => true,
        'quote_final_price_verified' => true,
        'context' => ['generation' => 44, 'page' => 1],
    ];
}
function store_reject(callable $case, string $message): void {
    try { $case(); store_check(false); } catch (InvalidArgumentException $e) { store_check($e->getMessage() === $message); }
}

$dir = sys_get_temp_dir() . '/anytour-natural-actualization-' . bin2hex(random_bytes(6));
mkdir($dir, 0700, true);
$path = $dir . '/observations.ndjson';

$empty = AnyTourThreeProviderPriceActualizationStore::summarize($path);
store_check($empty === ['total' => 0, 'exact' => 0, 'accuracy' => null, 'providers' => []]);

AnyTourThreeProviderPriceActualizationStore::append($path, store_row('andromeda', 'ANEX', '185125', '199390', 17449));
AnyTourThreeProviderPriceActualizationStore::append($path, store_row('andromeda', 'ANEX', '210000', '210000', 17450));
AnyTourThreeProviderPriceActualizationStore::append($path, store_row('anex', 'ANEX', '180000', '180000', 17451));
AnyTourThreeProviderPriceActualizationStore::append($path, store_row('tourvisor', 'PEGAS', '190000', '190000', 17452));

$summary = AnyTourThreeProviderPriceActualizationStore::summarize($path);
store_check($summary['total'] === 4 && $summary['exact'] === 3 && $summary['accuracy'] === 0.75);
store_check($summary['providers']['andromeda']['total'] === 2);
store_check($summary['providers']['andromeda']['exact'] === 1);
store_check($summary['providers']['andromeda']['accuracy'] === 0.5);
store_check($summary['providers']['andromeda']['operators']['ANEX']['accuracy'] === 0.5);
store_check($summary['providers']['anex']['accuracy'] === 1.0);
store_check($summary['providers']['tourvisor']['operators']['PEGAS']['accuracy'] === 1.0);

$lastTwo = AnyTourThreeProviderPriceActualizationStore::summarize($path, 2);
store_check($lastTwo['total'] === 2 && $lastTwo['exact'] === 2 && $lastTwo['accuracy'] === 1.0);
store_check(array_keys($lastTwo['providers']) === ['anex', 'tourvisor']);

$bad = store_row('andromeda', 'ANEX', '185125', '199390', 1);
$bad['quote_final_price_verified'] = false;
store_reject(static fn() => AnyTourThreeProviderPriceActualizationStore::append($path, $bad), 'THREE_PROVIDER_ACTUALIZATION_OBSERVATION');

$forgedExact = store_row('andromeda', 'ANEX', '185125', '199390', 1);
$forgedExact['exact_match'] = true;
store_reject(static fn() => AnyTourThreeProviderPriceActualizationStore::append($path, $forgedExact), 'THREE_PROVIDER_ACTUALIZATION_EXACT_MATCH');

$private = store_row('andromeda', 'ANEX', '185125', '199390', 1);
$private['tour']['offer_ref'] = 'private';
store_reject(static fn() => AnyTourThreeProviderPriceActualizationStore::append($path, $private), 'THREE_PROVIDER_ACTUALIZATION_PRIVATE_REF');

$eur = store_row('andromeda', 'ANEX', '185125', '199390', 1);
$eur['verified_quote_price']['currency'] = 'EUR';
store_reject(static fn() => AnyTourThreeProviderPriceActualizationStore::append($path, $eur), 'THREE_PROVIDER_ACTUALIZATION_OBSERVATION_MONEY');

store_reject(static fn() => AnyTourThreeProviderPriceActualizationStore::summarize($path, 0), 'THREE_PROVIDER_ACTUALIZATION_SUMMARY_LIMIT');

$lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
store_check(count($lines) === 4);
store_check(strpos(implode("\n", $lines), 'offer_ref') === false);

// Consume an actual producer result, not an independently fabricated exact flag.
$listing = [
    'provider' => 'andromeda', 'operator' => 'ANEX', 'local_hotel_id' => 17449,
    'identity' => ['namespace' => 'andromeda', 'key' => 'safe-identity'],
    'tour' => ['checkin' => '2026-12-28', 'nights' => 7,
        'party' => ['adults' => 2, 'children' => 0, 'child_ages' => []],
        'meal' => ['raw' => 'AI'], 'room' => ['raw' => 'Standard'], 'placement' => null],
    'context' => ['generation' => 44, 'page' => 1, 'current_context_verified' => true],
    'finalPriceReady' => true, 'finalPrice' => '199390', 'price' => '199390', 'currency' => 'RUB',
    'final_price_verified' => false, 'quote_state' => 'unknown',
    'selection_state' => 'disabled', 'booking_enabled' => false,
];
$quote = $listing;
$quote['final_price_verified'] = true;
$quote['quote_state'] = 'verified';
$quote['money'] = ['quote_price' => ['amount' => '199390.00', 'currency' => 'RUB']];
$produced = AnyTourThreeProviderPriceActualizationObservation::fromListingAndVerifiedQuote($listing, $quote);
store_check($produced['exact_match'] === true);
$decimalPath = $dir . '/decimal.ndjson';
AnyTourThreeProviderPriceActualizationStore::append($decimalPath, $produced);
store_check(json_decode(file($decimalPath)[0], true, 32, JSON_THROW_ON_ERROR) === $produced);
$decimalSummary = AnyTourThreeProviderPriceActualizationStore::summarize($decimalPath);
store_check($decimalSummary['total'] === 1 && $decimalSummary['exact'] === 1 && $decimalSummary['accuracy'] === 1.0);
store_check($decimalSummary['providers']['andromeda']['operators']['ANEX']['accuracy'] === 1.0);
$falseMismatch = $produced;
$falseMismatch['exact_match'] = false;
store_reject(static fn() => AnyTourThreeProviderPriceActualizationStore::append($decimalPath, $falseMismatch), 'THREE_PROVIDER_ACTUALIZATION_EXACT_MATCH');

// Previously accepted representation-only mismatches stay readable; no history is rewritten.
$legacyPath = $dir . '/legacy.ndjson';
$legacy = store_row('andromeda', 'ANEX', '199390', '199390.00', 17449);
file_put_contents($legacyPath, json_encode($legacy, JSON_THROW_ON_ERROR) . "\n");
$beforeHash = hash_file('sha256', $legacyPath);
$legacySummary = AnyTourThreeProviderPriceActualizationStore::summarize($legacyPath);
store_check($legacySummary['total'] === 1 && $legacySummary['exact'] === 1 && $legacySummary['accuracy'] === 1.0);
store_check(hash_file('sha256', $legacyPath) === $beforeHash);
AnyTourThreeProviderPriceActualizationStore::append($legacyPath, $produced);
$both = AnyTourThreeProviderPriceActualizationStore::summarize($legacyPath);
store_check($both['total'] === 2 && $both['exact'] === 2);
store_check(AnyTourThreeProviderPriceActualizationStore::summarize($legacyPath, 1)['exact'] === 1);

// Legacy read compatibility does not legalize flags the old store would have rejected.
$invalidPath = $dir . '/invalid.ndjson';
foreach ([$forgedExact, array_replace(store_row('andromeda', 'ANEX', '199390', '199390', 1), ['exact_match' => false])] as $invalid) {
    file_put_contents($invalidPath, json_encode($invalid, JSON_THROW_ON_ERROR) . "\n");
    store_reject(static fn() => AnyTourThreeProviderPriceActualizationStore::summarize($invalidPath), 'THREE_PROVIDER_ACTUALIZATION_EXACT_MATCH');
}
@unlink($decimalPath);
@unlink($legacyPath);
@unlink($invalidPath);

@unlink($path);
@rmdir($dir);
echo 'Three-provider natural actualization store: ' . $checks . " checks passed; supplier/DB/price-write/booking=0.\n";
