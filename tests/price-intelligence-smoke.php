<?php
declare(strict_types=1);

require_once __DIR__ . '/../v2/data/price-segment-v1.php';
require_once __DIR__ . '/../v2/data/price-intelligence-v1.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "PRICE_INTELLIGENCE_SMOKE_FAILED {$message}\n");
        exit(1);
    }
}

$segment = [
    'departure_id' => 1,
    'hotel_id' => 123,
    'departure_date' => '2026-09-20',
    'nights' => 7,
    'adults' => 2,
    'children_count' => 0,
    'child_ages_signature' => '',
    'meal_id' => 5,
    'room_id' => 11,
    'room_type' => 'Standard Room',
    'operator_id' => 9,
    'currency' => 'RUB',
];
$fingerprint = v2_price_segment_fingerprint($segment);
assert_true(strlen($fingerprint) === 64, 'fingerprint length');
assert_true($fingerprint === v2_price_segment_fingerprint($segment), 'fingerprint stability');
$otherDate = $segment;
$otherDate['departure_date'] = '2026-09-21';
assert_true($fingerprint !== v2_price_segment_fingerprint($otherDate), 'departure date must affect fingerprint');
$otherRoom = $segment;
$otherRoom['room_type'] = 'standard room';
assert_true($fingerprint !== v2_price_segment_fingerprint($otherRoom), 'room label differences must stay conservative');
try {
    $badDate = $segment;
    $badDate['departure_date'] = '2026-02-31';
    v2_price_segment_fingerprint($badDate);
    assert_true(false, 'impossible departure date must fail');
} catch (InvalidArgumentException) {
    // expected
}

$mins = [151000, 148000, 145000, 140000, 136000, 130000, 125000];
$rows = [];
foreach ($mins as $i => $min) {
    $rows[] = [
        'price_date' => sprintf('2026-08-%02d', 25 + $i),
        'min_price' => $min,
        'median_price' => $min + 3000,
        // This is an exact-comparable segment, so the daily maximum is a real
        // observed price for the same tour and may be used as the reference.
        'max_price' => $min + 3000,
        'observation_count' => 5,
        'independent_search_count' => 3,
    ];
}
$summary = v2_price_intelligence_summary($rows, 120000);
assert_true(($summary['ok'] ?? false) === true, 'summary ok');
assert_true(($summary['observedDays'] ?? 0) === 7, '7 observed days');
assert_true(($summary['observationCount'] ?? 0) === 35, '35 raw observations');
assert_true(($summary['independentSearchCount'] ?? 0) === 21, '21 independent searches');
assert_true((float)($summary['referencePrice'] ?? 0) === 154000.0, 'reference uses maximum exact observed price');
assert_true(($summary['referenceMethod'] ?? '') === 'max_observed_price_exact_comparable_segment', 'reference method');
assert_true(($summary['historicalDropPercent'] ?? 0) === 22, 'rounded historical drop');
assert_true(($summary['showPromoDrop'] ?? false) === true, 'eligible promo drop');
assert_true(($summary['showHistoricalDrop'] ?? false) === true, 'eligible crossed reference price');

// Two real observations of the same exact segment are enough, even on the
// same day. This is the commercial rule; long history is only for stronger
// analytics such as weekly lows and normal-price claims.
$sameDayTwoPrices = [[
    'price_date' => '2026-09-03',
    'min_price' => 120000,
    'median_price' => 135500,
    'max_price' => 151000,
    'observation_count' => 2,
    'independent_search_count' => 1,
]];
$immediate = v2_price_intelligence_summary($sameDayTwoPrices, 120000);
assert_true(($immediate['observedDays'] ?? 0) === 1, 'same-day drop uses one observed day');
assert_true(($immediate['observationCount'] ?? 0) === 2, 'same-day drop has two real observations');
assert_true(($immediate['showPromoDrop'] ?? false) === true, 'two exact observations may display promo immediately');
assert_true(($immediate['showHistoricalDrop'] ?? false) === true, 'two exact observations may display crossed observed price');
assert_true((float)($immediate['referencePrice'] ?? 0) === 151000.0, 'same-day exact maximum is the observed reference');
assert_true(($immediate['historicalDropPercent'] ?? 0) === 21, 'same-day drop percent');
assert_true(($immediate['historyReady'] ?? true) === false, 'immediate promo is not full history readiness');

$fastPromo = v2_price_intelligence_summary(array_slice($rows, 0, 2), 120000);
assert_true(($fastPromo['observedDays'] ?? 0) === 2, 'two-day history retained');
assert_true(($fastPromo['showPromoDrop'] ?? false) === true, 'two-day exact promo displays');
assert_true(($fastPromo['showHistoricalDrop'] ?? false) === true, 'two-day exact crossed reference displays');

$smallDrop = v2_price_intelligence_summary($rows, 149000);
assert_true(($smallDrop['showPromoDrop'] ?? true) === false, 'sub-5-percent promo drop must be suppressed');
assert_true(($smallDrop['showHistoricalDrop'] ?? true) === false, 'sub-5-percent crossed reference must be suppressed');

$oneObservation = [[
    'price_date' => '2026-09-03',
    'min_price' => 151000,
    'median_price' => 151000,
    'max_price' => 151000,
    'observation_count' => 1,
    'independent_search_count' => 1,
]];
$notEnough = v2_price_intelligence_summary($oneObservation, 120000);
assert_true(($notEnough['showPromoDrop'] ?? true) === false, 'one observation cannot establish a price change');
assert_true(($notEnough['showHistoricalDrop'] ?? true) === false, 'one observation cannot establish a crossed reference');

$duplicate = $rows;
$duplicate[] = $rows[0];
$invalid = v2_price_intelligence_summary($duplicate, 120000);
assert_true(($invalid['ok'] ?? true) === false, 'duplicate day fail closed');

fwrite(STDOUT, "PRICE_INTELLIGENCE_SMOKE_OK\n");
