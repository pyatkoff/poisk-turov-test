<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/andromeda-local-offer-collector.php';

$checks = 0;
function inrun_ok(bool $condition, string $message): void
{
    global $checks;
    ++$checks;
    if (!$condition) throw new RuntimeException($message);
}

function inrun_offer(int $id, int $nights): array
{
    return [
        'provider' => 'andromeda',
        'offer_ref' => 'offer_' . hash('sha256', 'inrun-' . $id),
        'local_hotel_id' => 5000 + $id,
        'operator' => 'FUN&SUN',
        'operator_ref' => '315',
        'check_in' => '2026-10-07',
        'nights' => $nights,
        'adults' => 2,
        'children' => 0,
        'price' => ['amount' => (string)(100000 + $id), 'currency' => 'RUB'],
        'transport_context' => [
            'freight_external' => true,
            'program_ref' => '5',
            'tour_ref' => '3005',
            'spo_ref' => 'spo-' . $id,
        ],
    ];
}

function inrun_fact(array $offer, ?string $aggregation = 'single_distinct_party_markup'): array
{
    $price = $offer['price'];
    $amount = '15000.50';
    $total = ((int)$price['amount'] + 15000) . '.50';
    $fact = [
        'schema_version' => 1,
        'provider' => 'andromeda',
        'state' => 'estimated',
        'search_price' => $price,
        'party_surcharge' => [
            'amount' => $amount,
            'currency' => $price['currency'],
            'source' => 'andromeda_get_flights_transport',
        ],
        'search_price_with_surcharge' => [
            'amount' => $total,
            'currency' => $price['currency'],
            'source' => 'derived_search_estimate',
        ],
        'surcharge_scope' => 'party',
        'arithmetic_applied' => true,
        'final_price_verified' => false,
    ];
    if ($aggregation !== null) {
        $fact['transport_markup_reported'] = [
            'amount' => $amount,
            'currency' => $price['currency'],
            'source' => 'andromeda_get_flights_transport',
            'aggregation' => $aggregation,
        ];
    }
    return $fact;
}

function inrun_collect(array $request, array $offers, callable $capture, int $budget = 20): array
{
    $rows = array_map(static fn(array $offer): array => ['page' => 1, 'offer' => $offer], $offers);
    return AnyTourAndromedaLocalOfferCollectorV1::collect(
        $request,
        static fn(array $r): array => [
            'provider' => 'andromeda',
            'search_ref' => str_repeat('c', 64),
            'pages_count' => 1,
            'status' => 'complete',
        ],
        static fn(string $ref, int $generation): array => $rows,
        static fn(array $selection, array $offer): bool => true,
        $capture,
        static fn(array $r, string $ref, int $generation): array => [
            'published' => true,
            'readyOfferCount' => 0,
            'confirmationRequiredOfferCount' => count($offers),
        ],
        $budget,
        'all',
        0,
        null,
        null
    );
}

function inrun_offer_map(array $offers): array
{
    $map = [];
    foreach ($offers as $offer) $map[$offer['offer_ref']] = $offer;
    return $map;
}

$request = ['generation' => 41, 'params' => ['departureId' => '1', 'countryId' => '4', 'childs' => []]];
$crossNight = [inrun_offer(1, 7), inrun_offer(2, 10), inrun_offer(3, 14)];

// A fixed program fact is learned only after the first strict per-night capture.
$map = inrun_offer_map($crossNight);
$calls = [];
$fixed = inrun_collect($request, $crossNight, static function(array $selection) use (&$calls, $map): array {
    $offer = $map[$selection['offer_ref']];
    $calls[] = $offer['nights'];
    return ['status' => 'captured', 'surcharge' => ['status' => 'complete', 'fact' => inrun_fact($offer)]];
});
inrun_ok($calls === [7], 'fixed program 7/10/14 must use one capture after proof');
inrun_ok($fixed['capture_queue_offers'] === 3 && $fixed['surcharge_capture_attempts'] === 1,
    'initial queue remains strict while supplier attempts collapse dynamically');
inrun_ok($fixed['program_fixed_groups_proven'] === 1 && $fixed['program_fixed_dynamic_skips'] === 2,
    'one exact program proof skips only later cross-night siblings');
inrun_ok($fixed['surcharge_ready'] === 1 && $fixed['ready_offer_count'] === 0,
    'in-run reuse never invents final-price readiness');

// Choice-dependent minimum is useful evidence but cannot broaden across nights.
$calls = [];
$choice = inrun_collect($request, $crossNight, static function(array $selection) use (&$calls, $map): array {
    $offer = $map[$selection['offer_ref']];
    $calls[] = $offer['nights'];
    return ['status' => 'captured', 'surcharge' => [
        'status' => 'complete',
        'fact' => inrun_fact($offer, 'minimum_complete_required_roundtrip_markup'),
    ]];
});
inrun_ok($calls === [7, 10, 14] && $choice['surcharge_capture_attempts'] === 3,
    'choice-dependent markup stays strict per night');
inrun_ok($choice['program_fixed_groups_proven'] === 0 && $choice['program_fixed_dynamic_skips'] === 0,
    'choice-dependent evidence grants no program-fixed authority');

// Unclassified estimate also stays strict.
$calls = [];
$unclassified = inrun_collect($request, $crossNight, static function(array $selection) use (&$calls, $map): array {
    $offer = $map[$selection['offer_ref']];
    $calls[] = $offer['nights'];
    return ['status' => 'captured', 'surcharge' => ['status' => 'complete', 'fact' => inrun_fact($offer, null)]];
});
inrun_ok($calls === [7, 10, 14] && $unclassified['program_fixed_groups_proven'] === 0,
    'unclassified estimate cannot skip cross-night capture');

// Verified final quote is per offer and cannot seed common program evidence.
$calls = [];
$verified = inrun_collect($request, $crossNight, static function(array $selection) use (&$calls, $map): array {
    $offer = $map[$selection['offer_ref']];
    $calls[] = $offer['nights'];
    return ['status' => 'captured', 'surcharge' => [
        'status' => 'complete', 'fact' => null, 'final_price_verified' => true,
    ]];
});
inrun_ok($calls === [7, 10, 14] && $verified['program_fixed_groups_proven'] === 0
    && $verified['program_fixed_dynamic_skips'] === 0, 'verified final quote is not reusable program surcharge');

// An unavailable first night cannot authorize skipping the next; once the second
// night returns an explicitly fixed fact, only the third sibling is skipped.
$calls = [];
$unavailable = inrun_collect($request, $crossNight, static function(array $selection) use (&$calls, $map): array {
    $offer = $map[$selection['offer_ref']];
    $calls[] = $offer['nights'];
    if ($offer['nights'] === 7) return ['status' => 'captured', 'surcharge' => ['status' => 'unavailable', 'fact' => null]];
    return ['status' => 'captured', 'surcharge' => ['status' => 'complete', 'fact' => inrun_fact($offer)]];
});
inrun_ok($calls === [7, 10] && $unavailable['surcharge_capture_attempts'] === 2,
    'unavailable first result cannot authorize sibling skip');
inrun_ok($unavailable['program_fixed_groups_proven'] === 1 && $unavailable['program_fixed_dynamic_skips'] === 1,
    'later exact proof may skip only following sibling');

// A sealed unknown outcome has the same no-replay boundary: do not retry that night,
// do not broaden it, but continue to the next disjoint strict group.
$calls = [];
$unknown = inrun_collect($request, $crossNight, static function(array $selection) use (&$calls, $map): array {
    $offer = $map[$selection['offer_ref']];
    $calls[] = $offer['nights'];
    if ($offer['nights'] === 7) throw new RuntimeException('ANDROMEDA_PACKAGE_OUTCOME_UNKNOWN');
    return ['status' => 'captured', 'surcharge' => ['status' => 'complete', 'fact' => inrun_fact($offer)]];
});
inrun_ok($calls === [7, 10] && $unknown['surcharge_capture_attempts'] === 2,
    'terminal unknown first result cannot authorize cross-night skip');
inrun_ok($unknown['program_fixed_groups_proven'] === 1 && $unknown['program_fixed_dynamic_skips'] === 1,
    'unknown group remains sealed while later exact proof controls following sibling only');

// Material program boundaries remain distinct even after the first fixed proof.
$variants = [];
$operator = inrun_offer(10, 10); $operator['operator_ref'] = '342'; $variants['operator'] = $operator;
$program = inrun_offer(11, 10); $program['transport_context']['program_ref'] = '6'; $variants['program'] = $program;
$tour = inrun_offer(12, 10); $tour['transport_context']['tour_ref'] = '3006'; $variants['tour'] = $tour;
$date = inrun_offer(13, 10); $date['check_in'] = '2026-10-08'; $variants['date'] = $date;
$party = inrun_offer(14, 10); $party['adults'] = 3; $variants['party'] = $party;
$currency = inrun_offer(15, 10); $currency['price']['currency'] = 'USD'; $variants['currency'] = $currency;
foreach ($variants as $label => $variant) {
    $pair = [$crossNight[0], $variant];
    $pairMap = inrun_offer_map($pair);
    $pairCalls = [];
    $result = inrun_collect($request, $pair, static function(array $selection) use (&$pairCalls, $pairMap): array {
        $offer = $pairMap[$selection['offer_ref']];
        $pairCalls[] = $offer['offer_ref'];
        return ['status' => 'captured', 'surcharge' => ['status' => 'complete', 'fact' => inrun_fact($offer)]];
    });
    inrun_ok(count($pairCalls) === 2 && $result['program_fixed_dynamic_skips'] === 0,
        $label . ' mismatch must remain a separate program-fixed group');
}

$baseKey = AndromedaSurchargeGroupKey::buildProgramFixed($crossNight[0], $request);
$route = $request; $route['params']['departureId'] = '2';
$country = $request; $country['params']['countryId'] = '5';
inrun_ok(is_string($baseKey) && $baseKey !== AndromedaSurchargeGroupKey::buildProgramFixed($crossNight[0], $route),
    'departure remains part of program-fixed identity');
inrun_ok($baseKey !== AndromedaSurchargeGroupKey::buildProgramFixed($crossNight[0], $country),
    'country remains part of program-fixed identity');

// Explicit zero budget preserves background supplier-free behavior.
$calls = [];
$zero = inrun_collect($request, $crossNight, static function(array $selection) use (&$calls): array {
    $calls[] = $selection['offer_ref'];
    return ['status' => 'captured'];
}, 0);
inrun_ok($calls === [] && $zero['surcharge_capture_attempts'] === 0
    && $zero['program_fixed_groups_proven'] === 0 && $zero['program_fixed_dynamic_skips'] === 0,
    'zero capture budget remains supplier-free');

echo 'ANDROMEDA_COLLECTOR_INRUN_PROGRAM_REUSE_OK checks=' . $checks
    . ' fixed_7_10_14=1 choice_strict=1 unavailable_guard=1 unknown_guard=1'
    . ' mismatch_guard=' . count($variants) . ' route_guard=2 zero_budget=1 supplier_http=0 live_db=0' . PHP_EOL;
