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

$mins = [151000, 148000, 145000, 140000, 136000, 130000, 125000];
$rows = [];
foreach ($mins as $i => $min) {
    $rows[] = [
        'price_date' => sprintf('2026-08-%02d', 25 + $i),
        'min_price' => $min,
        'median_price' => $min + 3000,
        // A deliberately huge raw max must NOT become the crossed-out reference price.
        'max_price' => $min + 100000,
        'observation_count' => 3,
    ];
}
$summary = v2_price_intelligence_summary($rows, 120000);
assert_true(($summary['ok'] ?? false) === true, 'summary ok');
assert_true(($summary['observedDays'] ?? 0) === 7, '7 observed days');
assert_true(($summary['observationCount'] ?? 0) === 21, '21 observations');
assert_true((float)($summary['referencePrice'] ?? 0) === 151000.0, 'reference uses max of daily minima');
assert_true(($summary['referenceMethod'] ?? '') === 'max_of_daily_min_exact_comparable_segment', 'reference method');
assert_true(($summary['historicalDropPercent'] ?? 0) === 21, 'rounded historical drop');
assert_true(($summary['showHistoricalDrop'] ?? false) === true, 'eligible historical drop');

$thin = v2_price_intelligence_summary(array_slice($rows, 0, 2), 120000);
assert_true(($thin['showHistoricalDrop'] ?? true) === false, 'insufficient days must suppress drop');

$smallDrop = v2_price_intelligence_summary($rows, 147000);
assert_true(($smallDrop['showHistoricalDrop'] ?? true) === false, 'sub-5-percent drop must be suppressed');

$duplicate = $rows;
$duplicate[] = $rows[0];
$invalid = v2_price_intelligence_summary($duplicate, 120000);
assert_true(($invalid['ok'] ?? true) === false, 'duplicate day fail closed');

fwrite(STDOUT, "PRICE_INTELLIGENCE_SMOKE_OK\n");
