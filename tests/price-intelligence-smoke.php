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
        // A deliberately huge raw max must NOT become the crossed-out reference price.
        'max_price' => $min + 100000,
        'observation_count' => 5,
        'independent_search_count' => 3,
    ];
}
$summary = v2_price_intelligence_summary($rows, 120000);
assert_true(($summary['ok'] ?? false) === true, 'summary ok');
assert_true(($summary['observedDays'] ?? 0) === 7, '7 observed days');
assert_true(($summary['observationCount'] ?? 0) === 35, '35 raw observations');
assert_true(($summary['independentSearchCount'] ?? 0) === 21, '21 independent searches');
assert_true((float)($summary['referencePrice'] ?? 0) === 151000.0, 'reference uses max of daily minima');
assert_true(($summary['referenceMethod'] ?? '') === 'max_of_daily_min_exact_comparable_segment', 'reference method');
assert_true(($summary['historicalDropPercent'] ?? 0) === 21, 'rounded historical drop');
assert_true(($summary['showPromoDrop'] ?? false) === true, 'eligible promo drop');
assert_true(($summary['showHistoricalDrop'] ?? false) === true, 'eligible historical drop');

$fastPromo = v2_price_intelligence_summary(array_slice($rows, 0, 2), 120000);
assert_true(($fastPromo['observedDays'] ?? 0) === 2, 'promo uses two distinct days');
assert_true(($fastPromo['independentSearchCount'] ?? 0) === 6, 'promo has five-plus independent searches');
assert_true(($fastPromo['showPromoDrop'] ?? false) === true, 'two-day tourism promo may display');
assert_true(($fastPromo['showHistoricalDrop'] ?? true) === false, 'two-day promo must not claim stronger history');

$guardedRows = array_slice($rows, 0, 3);
foreach ($guardedRows as &$row) {
    $row['observation_count'] = 5;
    $row['independent_search_count'] = 5;
}
unset($row);
$guarded = v2_price_intelligence_summary($guardedRows, 120000);
assert_true(($guarded['independentSearchCount'] ?? 0) === 15, 'guarded delta has 15 searches');
assert_true(($guarded['showHistoricalDrop'] ?? false) === true, 'three-day guarded historical drop may display');
assert_true(($guarded['historyReady'] ?? true) === false, 'three days is not full history readiness');

$oneDay = v2_price_intelligence_summary(array_slice($rows, 0, 1), 120000);
assert_true(($oneDay['showPromoDrop'] ?? true) === false, 'one day must not authorize promo');

$smallDrop = v2_price_intelligence_summary($rows, 147000);
assert_true(($smallDrop['showPromoDrop'] ?? true) === false, 'sub-5-percent promo drop must be suppressed');
assert_true(($smallDrop['showHistoricalDrop'] ?? true) === false, 'sub-5-percent historical drop must be suppressed');

$manyRawFewSearches = $rows;
foreach ($manyRawFewSearches as $i => &$row) {
    $row['observation_count'] = 20;
    $row['independent_search_count'] = $i < 4 ? 1 : 0;
}
unset($row);
$notIndependent = v2_price_intelligence_summary($manyRawFewSearches, 120000);
assert_true(($notIndependent['observationCount'] ?? 0) === 140, 'raw observations retained');
assert_true(($notIndependent['independentSearchCount'] ?? -1) === 4, 'independent searches counted separately');
assert_true(($notIndependent['showPromoDrop'] ?? true) === false, 'raw rows cannot replace five independent searches');
assert_true(($notIndependent['showHistoricalDrop'] ?? true) === false, 'raw rows cannot authorize historical drop');

$duplicate = $rows;
$duplicate[] = $rows[0];
$invalid = v2_price_intelligence_summary($duplicate, 120000);
assert_true(($invalid['ok'] ?? true) === false, 'duplicate day fail closed');

fwrite(STDOUT, "PRICE_INTELLIGENCE_SMOKE_OK\n");
