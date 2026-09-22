<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/integrations/andromeda-local-offer-collector.php';

function reuse_metrics_check(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

function reuse_metrics_offer(int $id, string $program, int $nights): array
{
    return [
        'provider' => 'andromeda',
        'offer_ref' => 'offer_' . hash('sha256', 'reuse-metrics-' . $id),
        'local_hotel_id' => 9000 + $id,
        'operator' => 'FUN&SUN',
        'operator_ref' => '315',
        'check_in' => '2026-10-07',
        'nights' => $nights,
        'adults' => 2,
        'children' => 0,
        'hotel' => 'Metrics hotel ' . $id,
        'room' => 'Room ' . $id,
        'meal' => $id % 2 ? 'AI' : 'UAI',
        'price' => ['amount' => (string)(120000 + $id * 1000), 'currency' => 'RUB'],
        'transport_context' => [
            'freight_external' => true,
            'program_ref' => $program,
            'tour_ref' => 'tour_' . $program,
            'spo_ref' => 'spo_' . $id,
        ],
    ];
}

function reuse_metrics_collect(array $offers, callable $cached, callable $capture, int $budget = 1): array
{
    $request = [
        'generation' => 77,
        'params' => ['departureId' => '1', 'countryId' => '4', 'childs' => []],
    ];
    return AnyTourAndromedaLocalOfferCollectorV1::collect(
        $request,
        static fn(array $r): array => [
            'provider' => 'andromeda',
            'search_ref' => str_repeat('c', 64),
            'pages_count' => 1,
            'status' => 'complete',
        ],
        static fn(string $ref, int $generation): array => array_map(
            static fn(array $offer): array => ['page' => 1, 'offer' => $offer],
            $offers
        ),
        static fn(array $selection, array $offer): bool => true,
        $capture,
        static fn(array $request, string $ref, int $generation): array => [
            'published' => true,
            'readyOfferCount' => 0,
            'confirmationRequiredOfferCount' => 5,
        ],
        $budget,
        'all',
        0,
        null,
        $cached
    );
}

$offers = [
    reuse_metrics_offer(1, 'P1', 7),
    reuse_metrics_offer(2, 'P1', 10),
    reuse_metrics_offer(3, 'P1', 14),
    reuse_metrics_offer(4, 'P2', 7),
    reuse_metrics_offer(5, 'P2', 14),
];

$cacheChecks = 0;
$captures = 0;
$result = reuse_metrics_collect(
    $offers,
    static function(array $selection, array $offer, array $request) use (&$cacheChecks): bool {
        ++$cacheChecks;
        return ($offer['transport_context']['program_ref'] ?? null) === 'P1';
    },
    static function(array $selection) use (&$captures): array {
        ++$captures;
        return ['status' => 'captured', 'surcharge' => ['status' => 'unavailable', 'fact' => null]];
    }
);

reuse_metrics_check($result['reusable_surcharge_groups'] === 2, 'expected two strict groups');
reuse_metrics_check($result['reusable_surcharge_offers'] === 5, 'all compatible offers must be counted once');
reuse_metrics_check($result['surcharge_group_duplicate_skips'] === 3, 'group sibling denominator');
reuse_metrics_check($cacheChecks === 2 && $result['surcharge_cache_checks'] === 2, 'one cache check per representative group');
reuse_metrics_check($result['surcharge_cache_hits'] === 1, 'one group cache hit');
reuse_metrics_check($result['surcharge_cache_covered_offers'] === 3, 'one cached 7/10/14 group covers exactly three offers');
reuse_metrics_check($captures === 1 && $result['surcharge_capture_attempts'] === 1, 'cached group must not spend capture budget');
reuse_metrics_check($result['eligible_offers'] === 5 && $result['autosave_published'] === true, 'metrics must not suppress cohort autosave');
reuse_metrics_check($result['selection_authority'] === false && $result['booking_calls'] === 0, 'metrics cannot grant selection authority');

$missCaptures = 0;
$miss = reuse_metrics_collect(
    $offers,
    static fn(array $selection, array $offer, array $request): bool => false,
    static function(array $selection) use (&$missCaptures): array {
        ++$missCaptures;
        return ['status' => 'captured', 'surcharge' => ['status' => 'unavailable', 'fact' => null]];
    }
);
reuse_metrics_check($miss['reusable_surcharge_offers'] === 5, 'candidate denominator survives cache miss');
reuse_metrics_check($miss['surcharge_cache_hits'] === 0 && $miss['surcharge_cache_covered_offers'] === 0,
    'cache miss must not claim covered offers');
reuse_metrics_check($missCaptures === 1 && $miss['surcharge_capture_attempts'] === 1,
    'miss keeps existing bounded capture behavior');

$zeroCaptures = 0;
$zero = reuse_metrics_collect(
    $offers,
    static fn(array $selection, array $offer, array $request): bool => true,
    static function(array $selection) use (&$zeroCaptures): array {
        ++$zeroCaptures;
        return ['status' => 'captured', 'surcharge' => ['status' => 'unavailable', 'fact' => null]];
    },
    0
);
reuse_metrics_check($zero['reusable_surcharge_offers'] === 5, 'zero-budget denominator remains observable');
reuse_metrics_check($zero['surcharge_cache_checks'] === 0 && $zero['surcharge_cache_covered_offers'] === 0,
    'zero capture budget performs no cache preflight');
reuse_metrics_check($zeroCaptures === 0 && $zero['surcharge_capture_attempts'] === 0,
    'zero capture budget remains supplier-free');

echo "ANDROMEDA_SURCHARGE_REUSE_METRICS_OK groups=2 offers=5 cache_hits=1 covered=3 captures=1 zero_budget=1\n";
