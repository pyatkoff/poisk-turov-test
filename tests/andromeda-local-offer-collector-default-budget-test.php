<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/andromeda-local-offer-collector.php';

function expect_true(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$request = [
    'generation' => 20260922,
    'params' => ['departureId' => '1', 'countryId' => '4'],
];
$search = static fn(array $input): array => [
    'provider' => 'andromeda',
    'search_ref' => str_repeat('a', 64),
    'pages_count' => 1,
    'status' => 'complete',
];
$cohort = static fn(string $searchRef, int $generation): array => [[
    'page' => 1,
    'offer' => [
        'offer_ref' => 'offer_' . hash('sha256', 'default-budget'),
        'provider' => 'andromeda',
        'operator' => 'Интурист',
        'operator_ref' => '10',
        'local_hotel_id' => 42,
        'check_in' => '2026-10-30',
        'nights' => 7,
        'adults' => 2,
        'children' => 0,
        'price' => ['final' => 185125, 'currency' => 'RUB'],
    ],
]];
$allowed = static fn(array $selection, array $offer): bool => true;
$autosaveCalls = 0;
$autosave = static function(array $input, string $searchRef, int $generation) use (&$autosaveCalls): array {
    ++$autosaveCalls;
    return ['published' => true, 'reason' => null, 'readyOfferCount' => 0];
};

$defaultCaptureCalls = 0;
$defaultResult = AnyTourAndromedaLocalOfferCollectorV1::collect(
    $request,
    $search,
    $cohort,
    $allowed,
    static function(array $selection) use (&$defaultCaptureCalls): array {
        ++$defaultCaptureCalls;
        return ['status' => 'captured', 'surcharge' => ['status' => 'unavailable', 'fact' => null]];
    },
    $autosave
);

expect_true($defaultCaptureCalls === 0, 'omitted budget must not invoke package/get_flights capture');
expect_true($defaultResult['surcharge_capture_attempts'] === 0, 'omitted budget attempts must be zero');
expect_true($defaultResult['capture_queue_offers'] === 1, 'eligible work may remain queued without capture authority');
expect_true($defaultResult['status'] === 'complete', 'zero-capture collection must still autosave');
expect_true($autosaveCalls === 1, 'zero-capture collection must autosave once');

$explicitCaptureCalls = 0;
$explicitResult = AnyTourAndromedaLocalOfferCollectorV1::collect(
    $request,
    $search,
    $cohort,
    $allowed,
    static function(array $selection) use (&$explicitCaptureCalls): array {
        ++$explicitCaptureCalls;
        return ['status' => 'captured', 'surcharge' => ['status' => 'unavailable', 'fact' => null]];
    },
    $autosave,
    1
);

expect_true($explicitCaptureCalls === 1, 'explicit positive budget must retain bounded capture behavior');
expect_true($explicitResult['surcharge_capture_attempts'] === 1, 'explicit positive budget attempts');
expect_true($autosaveCalls === 2, 'explicit capture path must autosave once');

echo "andromeda-local-offer-collector-default-budget-test: ok\n";
