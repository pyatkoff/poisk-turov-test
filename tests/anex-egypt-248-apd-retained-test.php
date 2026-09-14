<?php
declare(strict_types=1);

define('ANYTOUR_ANEX_EGYPT_248_APD_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/anex_egypt_248_apd_retained.php';
define('ANYTOUR_ANEX_EGYPT_248_APD_PREFLIGHT_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/anex_egypt_248_apd_preflight.php';

$checks = 0;
$assert = static function ($condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    ++$checks;
};

$offer = static function (string $program, string $currency, string $price, string $kind = 'concrete'): array {
    return [
        'kind' => $kind,
        'supplier_tour_program_id' => $program,
        'supplier_currency_id' => $currency,
        'hotel' => ['external_id' => '10449', 'local_id' => 248],
        'checkin' => '2026-12-07', 'nights' => 10, 'adults' => 3, 'children' => 0,
        'meal' => 'AI', 'room' => 'Standard Room With Garden View',
        'price' => ['amount' => $price, 'currency' => 'RUB'],
    ];
};

$selected = anex_egypt_248_apd_select_concrete([
    $offer('7654', '3', '171000'),
    $offer('4321', '3', '169970'),
    $offer('4321', '3', '169970'),
]);
$assert($selected['program'] === '4321', 'SearchTour program remains retained evidence only');
$assert($selected['currency'] === '3', 'SearchTour currency remains retained evidence only');
$assert($selected['price'] === '169970', 'current search price remains separate');

$failed = false;
try {
    anex_egypt_248_apd_select_concrete([$offer('4321', '3', '169970'), $offer('9999', '3', '169970')]);
} catch (RuntimeException $error) {
    $failed = $error->getMessage() === 'ANEX_EGYPT_APD_CONCRETE_AMBIGUOUS';
}
$assert($failed, 'same-price different SearchTour contexts remain ambiguous');

$application = anex_egypt_248_apd_application([
    'totalCount' => 1,
    'data' => [[
        'price_adult' => '100', 'price_chd' => '50', 'cashrate' => '1',
        'price_converted_adult' => '7178.3333', 'price_converted_chd' => '3000',
    ]],
], '169970');
$assert($application['application_state'] === 'applied', 'pure retained APD calculator still parses one exact row');
$assert($application['party_surcharge']['amount'] === '21534.9999', 'pure calculator preserves fixed-point party multiplication');
$assert($application['search_plus_additional']['amount'] === '191504.9999', 'pure calculator preserves reproducible arithmetic evidence');
$assert($application['arithmetic_applied'] === true, 'pure calculator labels its local evidence arithmetic');

$unknown = anex_egypt_248_apd_application(['totalCount' => 2, 'data' => [[], []]], '169970');
$assert($unknown['application_state'] === 'unknown' && $unknown['party_surcharge'] === null
    && $unknown['arithmetic_applied'] === false, 'ambiguous APD rows never become zero or arithmetic');

$assert(anex_egypt_248_apd_b2b_binding() === null, 'no authoritative B2B tour binding is invented');
$validInput = ['operation_id' => ANEX_EGYPT_248_APD_OPERATION, 'source_sha' => str_repeat('a', 40)];
$blocked = anex_egypt_248_apd_main($validInput);
$assert($blocked['status'] === 'blocked' && $blocked['reason'] === 'ANEX_EGYPT_APD_B2B_TOUR_BINDING_REQUIRED',
    'targeted operation fails closed on the real binding blocker');
$assert($blocked['supplier_calls'] === 0 && $blocked['additional_prices_calls'] === 0
    && $blocked['semantic_reservation_written'] === false, 'binding blocker occurs before supplier access or durable reservation');
$assert($blocked['b2b_tour_binding']['searchtour_program_is_b2b_tour'] === false,
    'SearchTour program id is explicitly not promoted into B2B tour authority');

$source = file_get_contents(__DIR__ . '/../scripts/diagnostics/anex_egypt_248_apd_retained.php');
$assert(is_string($source), 'retained runner source readable');
foreach (['AnyTourAnexClient', 'AnyTourAnexAdditionalPricesClient', 'additionalPricesDaily', "'tour' => (int) \$selected['program']", '->bron(', 'bron_ticket'] as $forbidden) {
    $assert(strpos($source, $forbidden) === false, 'blocked retained runner contains no live supplier/booking path: ' . $forbidden);
}
$assert(strpos($source, 'supplier_dictionary_or_supplier_issued_binding') !== false,
    'binding authority requirement is explicit in the concrete runner');

$invalidPreflight = anex_egypt_248_apd_preflight_main([]);
$assert($invalidPreflight['status'] === 'blocked' && $invalidPreflight['blocker_stage'] === 'input'
    && $invalidPreflight['blocker_category'] === 'invalid_input', 'preflight safely classifies invalid input');
$preflight = anex_egypt_248_apd_preflight_main([
    'operation_id' => ANEX_EGYPT_248_APD_PREFLIGHT_OPERATION,
    'source_sha' => str_repeat('b', 40),
]);
$assert($preflight['status'] === 'blocked' && $preflight['blocker_stage'] === 'binding_contract'
    && $preflight['blocker_category'] === 'b2b_tour_binding_unverified', 'preflight exposes authoritative binding as the first blocker');
$assert($preflight['supplier_calls'] === 0 && $preflight['additional_prices_calls'] === 0
    && $preflight['tourvisor_calls'] === 0 && $preflight['andromeda_calls'] === 0
    && $preflight['semantic_reservation_written'] === false, 'preflight remains supplier-free and never reserves semantic operation');
$assert(anex_egypt_248_apd_preflight_category('binding_contract') === 'b2b_tour_binding_unverified',
    'binding-contract category is bounded and stable');

$preflightSource = file_get_contents(__DIR__ . '/../scripts/diagnostics/anex_egypt_248_apd_preflight.php');
$assert(is_string($preflightSource), 'preflight source readable');
foreach (['AnyTourAnexClient', 'AnyTourAnexAdditionalPricesClient', 'SearchTour_', 'additionalPricesDaily', 'curl_', '->bron(', 'bron_ticket'] as $forbidden) {
    $assert(strpos($preflightSource, $forbidden) === false, 'preflight contains no supplier/booking token: ' . $forbidden);
}

echo "ANEX Egypt retained APD binding guard: {$checks} checks passed; network=0\n";
