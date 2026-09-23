<?php
declare(strict_types=1);

require_once __DIR__ . '/../v2/data/price-segment-v1.php';
require_once __DIR__ . '/../v2/data/price-consumer-intelligence-v1.php';

function consumer_price_check(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "PRICE_CONSUMER_INTELLIGENCE_FAILED {$message}\n");
        exit(1);
    }
}

$segment = [
    'departure_id' => 1,
    'hotel_id' => 22879,
    'departure_date' => '2026-10-20',
    'nights' => 7,
    'adults' => 2,
    'children_count' => 0,
    'child_ages_signature' => '',
    'meal_id' => 7,
    'room_id' => 11,
    'room_type' => 'Standard Sea View',
    'operator_id' => 9,
    'currency' => 'RUB',
];

$strictA = v2_price_segment_fingerprint($segment);
$consumerA = v2_price_consumer_segment_fingerprint($segment);
$otherOperator = $segment;
$otherOperator['operator_id'] = 25;
consumer_price_check(
    $strictA !== v2_price_segment_fingerprint($otherOperator),
    'strict identity must keep operator'
);
consumer_price_check(
    $consumerA === v2_price_consumer_segment_fingerprint($otherOperator),
    'consumer identity must ignore operator'
);

$otherDate = $segment;
$otherDate['departure_date'] = '2026-10-21';
consumer_price_check(
    $consumerA !== v2_price_consumer_segment_fingerprint($otherDate),
    'consumer identity must keep exact departure date'
);
$otherRoom = $segment;
$otherRoom['room_type'] = 'Standard Garden View';
consumer_price_check(
    $consumerA !== v2_price_consumer_segment_fingerprint($otherRoom),
    'consumer identity must keep exact room'
);
$otherMeal = $segment;
$otherMeal['meal_id'] = 3;
consumer_price_check(
    $consumerA !== v2_price_consumer_segment_fingerprint($otherMeal),
    'consumer identity must keep meal'
);

$strictRows = [
    [
        'price_date' => '2026-09-20',
        'operator_id' => 9,
        'min_price' => 240000,
        'observation_count' => 1,
        'independent_search_count' => 1,
    ],
    [
        'price_date' => '2026-09-20',
        'operator_id' => 25,
        'min_price' => 300000,
        'observation_count' => 1,
        'independent_search_count' => 1,
    ],
    [
        'price_date' => '2026-09-21',
        'operator_id' => 9,
        'min_price' => 225000,
        'observation_count' => 2,
        'independent_search_count' => 2,
    ],
    [
        'price_date' => '2026-09-21',
        'operator_id' => 25,
        'min_price' => 210000,
        'observation_count' => 1,
        'independent_search_count' => 1,
    ],
];

$daily = v2_price_consumer_daily_best_rows($strictRows);
consumer_price_check(count($daily) === 2, 'two consumer days');
consumer_price_check((float)$daily[0]['min_price'] === 240000.0, 'day one uses cheapest operator');
consumer_price_check((float)$daily[0]['max_price'] === 240000.0, 'expensive operator cannot become reference');
consumer_price_check((int)$daily[0]['operator_count'] === 2, 'operator diversity retained as evidence');
consumer_price_check((int)$daily[0]['independent_search_count'] === 1, 'search evidence is not summed across operators');
consumer_price_check((float)$daily[1]['min_price'] === 210000.0, 'day two uses cheapest operator');
consumer_price_check((int)$daily[1]['independent_search_count'] === 2, 'conservative daily search evidence');

$summary = v2_price_consumer_intelligence_summary($strictRows, 190000);
consumer_price_check(($summary['ok'] ?? false) === true, 'consumer summary ok');
consumer_price_check(
    ($summary['comparisonMode'] ?? '') === 'consumer_equivalent_operator_independent',
    'consumer comparison mode'
);
consumer_price_check(
    ($summary['hotelIdentity'] ?? '') === 'tourvisor_legacy',
    'current history identity is explicit'
);
consumer_price_check((float)($summary['referencePrice'] ?? 0) === 240000.0, 'reference is max daily best, not expensive operator');
consumer_price_check(
    ($summary['referenceMethod'] ?? '') === 'max_daily_best_price_consumer_comparable_segment',
    'consumer reference method'
);
consumer_price_check(($summary['historicalDropPercent'] ?? 0) === 21, 'consumer drop percent');
consumer_price_check(($summary['showPromoDrop'] ?? false) === true, 'consumer promo drop');
consumer_price_check(($summary['series'][0]['operatorCount'] ?? 0) === 2, 'series exposes operator count');

$endpoint = (string)file_get_contents(__DIR__ . '/../v2/data/price-consumer-intelligence-read-v1.php');
consumer_price_check(str_contains($endpoint, 'FROM tour_price_daily_exact'), 'reader uses existing exact history');
consumer_price_check(str_contains($endpoint, 'SELECT price_date,operator_id,min_price'), 'reader retains operator rows for collapse');
consumer_price_check(!preg_match('/\bAND\s+operator_id\s*=/i', $endpoint), 'reader must not filter by operator');
consumer_price_check(!preg_match('/\b(?:INSERT|UPDATE|DELETE|ALTER|CREATE)\b/i', $endpoint), 'reader is read only');
consumer_price_check(str_contains($endpoint, "'anytour-first-party-consumer-price-history'"), 'reader source is explicit');

fwrite(STDOUT, "PRICE_CONSUMER_INTELLIGENCE_OK\n");
