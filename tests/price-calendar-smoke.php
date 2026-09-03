<?php
declare(strict_types=1);

require_once __DIR__ . '/../v2/data/price-calendar-core-v1.php';

function pc_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "PRICE_CALENDAR_SMOKE_FAILED {$message}\n");
        exit(1);
    }
}

$rows = [
    [
        'departure_date' => '2026-09-10',
        'min_price' => 128400,
        'hotel_count' => 12,
        'independent_search_count' => 3,
        'latest_observed_at' => '2026-09-03 07:00:00',
    ],
    [
        'departure_date' => '2026-09-12',
        'min_price' => 116700,
        'hotel_count' => 9,
        'independent_search_count' => 2,
        'latest_observed_at' => '2026-09-03 08:00:00',
    ],
    [
        'departure_date' => '2026-09-13',
        'min_price' => 119300,
        'hotel_count' => 7,
        'independent_search_count' => 2,
        'latest_observed_at' => '2026-09-03 08:10:00',
    ],
];

$calendar = v2_price_calendar_build($rows, '2026-09-10', '2026-09-13');
pc_assert(($calendar['days'] ?? 0) === 4, 'four calendar days');
pc_assert(($calendar['observedDays'] ?? 0) === 3, 'three observed days');
pc_assert(($calendar['missingDays'] ?? 0) === 1, 'one missing day');
pc_assert(($calendar['bestDate'] ?? '') === '2026-09-12', 'best observed date');
pc_assert((float)($calendar['bestPrice'] ?? 0) === 116700.0, 'best observed price');
pc_assert(($calendar['missing_semantics'] ?? '') === 'unknown_not_zero', 'missing semantics');

$byDate = [];
foreach ($calendar['series'] as $point) $byDate[$point['date']] = $point;
pc_assert(($byDate['2026-09-11']['observed'] ?? true) === false, 'missing day is not observed');
pc_assert(array_key_exists('minPrice', $byDate['2026-09-11']) && $byDate['2026-09-11']['minPrice'] === null, 'missing day price is null, never zero');
pc_assert(($byDate['2026-09-12']['best'] ?? false) === true, 'best date marked');

$single = v2_price_calendar_build([$rows[0]], '2026-09-10', '2026-09-10');
pc_assert(array_key_exists('bestDate', $single) && $single['bestDate'] === null, 'one observed day must not manufacture a best badge');

try {
    v2_price_calendar_build([], '2026-02-31', '2026-03-01');
    pc_assert(false, 'impossible calendar date must fail');
} catch (InvalidArgumentException) {
    // expected
}

try {
    v2_price_calendar_build([], '2026-09-01', '2026-10-05');
    pc_assert(false, 'range over 31 days must fail');
} catch (InvalidArgumentException) {
    // expected
}

fwrite(STDOUT, "PRICE_CALENDAR_SMOKE_OK\n");
