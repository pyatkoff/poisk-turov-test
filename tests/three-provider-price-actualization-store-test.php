<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/three-provider-price-actualization-store.php';

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

// Bounded summary uses the newest rows only; append order remains durable evidence.
$lastTwo = AnyTourThreeProviderPriceActualizationStore::summarize($path, 2);
store_check($lastTwo['total'] === 2 && $lastTwo['exact'] === 2 && $lastTwo['accuracy'] === 1.0);
store_check(array_keys($lastTwo['providers']) === ['anex', 'tourvisor']);

$bad = store_row('andromeda', 'ANEX', '185125', '199390', 1);
$bad['quote_final_price_verified'] = false;
store_reject(static fn() => AnyTourThreeProviderPriceActualizationStore::append($path, $bad), 'THREE_PROVIDER_ACTUALIZATION_OBSERVATION');

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

@unlink($path);
@rmdir($dir);
echo 'Three-provider natural actualization store: ' . $checks . " checks passed; supplier/DB/price-write/booking=0.\n";
