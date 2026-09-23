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

$defaultParty = v2_price_calendar_party(2, []);
pc_assert($defaultParty === [
    'adults' => 2,
    'childAges' => [],
    'childrenCount' => 0,
    'childAgesSignature' => '',
], 'default party preserves legacy 2-adult contract');

$familyParty = v2_price_calendar_party('2', ['7', '2']);
pc_assert($familyParty === [
    'adults' => 2,
    'childAges' => [2, 7],
    'childrenCount' => 2,
    'childAgesSignature' => '2,7',
], 'child ages canonicalize to exact sorted observation signature');

$commaParty = v2_price_calendar_party('1', '10,4,0');
pc_assert(($commaParty['childAges'] ?? null) === [0, 4, 10], 'comma-separated child ages canonicalize');
pc_assert(($commaParty['childAgesSignature'] ?? null) === '0,4,10', 'comma-separated child signature exact');

foreach ([
    [0, []],
    [7, []],
    [2, ['', 4]],
    [2, [18]],
    [2, [1, 2, 3, 4]],
    [2, [['age' => 4]]],
] as [$adults, $childs]) {
    try {
        v2_price_calendar_party($adults, $childs);
        pc_assert(false, 'invalid party must fail closed');
    } catch (InvalidArgumentException) {
        // expected
    }
}

$readerSource = (string)file_get_contents(__DIR__ . '/../v2/data/price-calendar-read-v1.php');
pc_assert(str_contains($readerSource, "v2_price_calendar_party(\$_GET['adults'] ?? 2, \$_GET['childs'] ?? [])"), 'reader resolves exact party');
pc_assert(str_contains($readerSource, 'AND o.adults=:adults'), 'reader filters exact adults');
pc_assert(str_contains($readerSource, 'AND o.children_count=:children_count'), 'reader filters exact child count');
pc_assert(str_contains($readerSource, 'AND o.child_ages_signature=:child_ages_signature'), 'reader filters exact child ages');
pc_assert(!str_contains($readerSource, 'AND o.adults=2 AND o.children_count=0'), 'reader must not hard-code 2 adults/no children');

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
