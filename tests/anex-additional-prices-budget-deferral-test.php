<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-normalizer.php';
require_once __DIR__ . '/../app/integrations/anex-additional-prices-client.php';
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$digest = hash('sha256', "778\0" . "3\0" . "2026-10-18\0" . "7");
$plan = [
    'requested_offers' => 1,
    'unique_contexts' => 1,
    'contexts' => [[
        'context_digest' => $digest,
        'supplier_tour_program_id' => '778',
        'supplier_currency_id' => '3',
        'checkin' => '2026-10-18',
        'nights' => 7,
    ]],
    'offers' => [[
        'offer_ref' => 'anex_online:' . str_repeat('a', 64),
        'local_hotel_id' => 6319,
        'context_digest' => $digest,
    ]],
];

$state = [];
$checkpoints = [];
$readerCalls = 0;
$result = anytour_anex_additional_prices_batch_execute(
    $plan,
    $state,
    static function(array $context) use (&$readerCalls): array {
        ++$readerCalls;
        throw new RuntimeException('ANEX_RATE_LIMIT');
    },
    static function(array $next, string $contextDigest) use (&$checkpoints): void {
        $checkpoints[] = [$contextDigest, $next['additional_prices'][$contextDigest] ?? null];
    }
);
expect($readerCalls === 1, 'budget deferral must reach the local pre-transport guard once');
expect(($result['offers'][0]['status'] ?? null) === 'unknown', 'public status remains fail-closed');
expect(array_key_exists('additional_prices', $result['offers'][0])
    && $result['offers'][0]['additional_prices'] === null, 'deferred APD must expose no money');
expect(($result['offers'][0]['retryable'] ?? null) === true, 'unsent budget deferral must be classified retryable');
expect(($result['offers'][0]['retry_reason'] ?? null) === 'session_budget_deferred',
    'unsent budget deferral must expose only the bounded local reason');
expect(!isset($state['additional_prices'][$digest]), 'unsent budget deferral must not become durable unknown');
expect(count($checkpoints) === 2, 'budget deferral must checkpoint reservation and rollback');
expect(($checkpoints[0][1]['status'] ?? null) === 'unknown' && $checkpoints[1][1] === null,
    'rollback checkpoint must remove only the never-sent context');

$evidence = [
    'source' => 'anex_b2b_additional_prices_daily',
    'application' => ['state' => 'applied'],
    'search_plus_additional' => ['amount' => '120000', 'currency' => 'RUB'],
];
$result = anytour_anex_additional_prices_batch_execute(
    $plan,
    $state,
    static function(array $context) use (&$readerCalls, $evidence): array {
        ++$readerCalls;
        return $evidence;
    },
    static function(array $next, string $contextDigest): void {}
);
expect($readerCalls === 2, 'later batch must retry the context after local budget recovery');
expect(($result['offers'][0]['status'] ?? null) === 'complete', 'retried APD context must complete');
expect(($result['offers'][0]['additional_prices'] ?? null) === $evidence, 'completed evidence must be returned unchanged');
expect(($result['offers'][0]['retryable'] ?? null) === false
    && array_key_exists('retry_reason', $result['offers'][0]) && $result['offers'][0]['retry_reason'] === null,
    'completed APD context must not request another retry');
expect(($state['additional_prices'][$digest]['status'] ?? null) === 'complete', 'successful retry must become durable complete');

$unknownDigest = hash('sha256', "779\0" . "3\0" . "2026-10-18\0" . "7");
$unknownPlan = $plan;
$unknownPlan['contexts'][0]['context_digest'] = $unknownDigest;
$unknownPlan['contexts'][0]['supplier_tour_program_id'] = '779';
$unknownPlan['offers'][0]['context_digest'] = $unknownDigest;
$unknownState = [];
$unknownCalls = 0;
$unknownResult = anytour_anex_additional_prices_batch_execute(
    $unknownPlan,
    $unknownState,
    static function(array $context) use (&$unknownCalls): array {
        ++$unknownCalls;
        throw new RuntimeException('ANEX_B2B_DAILY_UNKNOWN');
    },
    static function(array $next, string $contextDigest): void {}
);
expect(($unknownState['additional_prices'][$unknownDigest]['status'] ?? null) === 'unknown',
    'supplier-observed APD unknown must remain durable');
expect(($unknownResult['offers'][0]['retryable'] ?? null) === false
    && ($unknownResult['offers'][0]['retry_reason'] ?? null) === 'durable_unknown',
    'supplier-observed APD unknown must be explicitly non-retryable');
$unknownResult = anytour_anex_additional_prices_batch_execute(
    $unknownPlan,
    $unknownState,
    static function(array $context) use (&$unknownCalls): array {
        ++$unknownCalls;
        return ['unexpected' => true];
    },
    static function(array $next, string $contextDigest): void {}
);
expect($unknownCalls === 1, 'durable supplier unknown must remain no-replay');
expect(($unknownResult['offers'][0]['retryable'] ?? null) === false
    && ($unknownResult['offers'][0]['retry_reason'] ?? null) === 'durable_unknown',
    'cached durable unknown must remain non-retryable');

// Real INT HTTP projection: expose only the safe pre-transport deferral hint.
$offerRef = 'anex_online:' . str_repeat('b', 64);
$searchRef = str_repeat('d', 32);
$offer = [
    'offer_key' => $offerRef,
    'provider' => 'anex',
    'supplier_namespace' => 'anex_online',
    'kind' => 'concrete',
    'hotel' => [
        'external_id' => '8121', 'local_id' => 6319, 'mapping_status' => 'resolved',
        'name' => 'APERION BEACH HOTEL', 'star' => '4', 'country' => null,
        'region' => null, 'town' => 'Side', 'external_town_id' => '12',
    ],
    'checkin' => '2026-10-18',
    'checkout' => '2026-10-25',
    'nights' => 7,
    'adults' => 2,
    'children' => 0,
    'infants' => null,
    'meal' => 'AI',
    'external_meal_id' => '7',
    'room' => 'STANDARD',
    'external_room_id' => '10',
    'hotel_place' => 'DBL',
    'external_hotel_place_id' => '2',
    'price' => ['amount' => '100000', 'currency' => 'RUB'],
    'converted_price' => null,
    'availability' => ['hotel' => 'Y', 'flight_outbound_economy' => 'Y', 'flight_return_economy' => 'Y'],
    'supplier_booking_flag' => true,
    'final_price_verified' => false,
];
$endpointState = [
    'generation' => 11,
    'params' => [
        'countryId' => 1, 'hotelIds' => [], 'regionIds' => [], 'subregionIds' => [],
        'hotelRating' => '', 'hotelCategory' => '', 'meal' => '', 'priceFrom' => '', 'priceTo' => '',
    ],
    'gateway' => [
        'saved_offers' => [
            'search_ref' => $searchRef,
            'created_at' => 1789220000,
            'expires_at' => 1789220900,
            'offers' => [$offerRef => ['offer' => $offer, 'supplier_tour_program_id' => '778', 'supplier_currency_id' => '3']],
        ],
        'search' => ['offers' => [[
            'offer_key' => $offerRef, 'kind' => 'concrete', 'hotel_external_id' => '8121',
        ]]],
    ],
    'expansions' => [],
    'additional_prices' => [],
];
$endpointRequest = [
    'action' => 'additional_prices_batch', 'generation' => 11, 'search_ref' => $searchRef,
    'items' => [['offer_ref' => $offerRef, 'local_hotel_id' => 6319]],
];
$resolver = static function(string $namespace, $external): ?int {
    return $namespace === 'anex_online' && (string) $external === '8121' ? 6319 : null;
};
$metadata = static function(array $offers): array {
    return [6319 => [
        'id' => 6319, 'name' => 'APERION BEACH HOTEL', 'country_id' => 1, 'country_name' => 'Turkey',
        'region_id' => 2, 'region_name' => 'Side', 'subregion_id' => 3, 'subregion_name' => 'Kizilagac',
        'category' => 4, 'rating' => 4.5,
    ]];
};
$clock = static function(): int { return 1789220100; };
$endpointCheckpoints = 0;
$checkpoint = static function(array &$current) use (&$endpointCheckpoints): void { ++$endpointCheckpoints; };
$factoryCalls = 0;
$deferredFactory = static function() use (&$factoryCalls): AnyTourAnexAdditionalPricesClient {
    ++$factoryCalls;
    throw new RuntimeException('ANEX_RATE_LIMIT');
};
$endpointDeferred = anytour_anex_search3_additional_batch(
    $endpointRequest, $endpointState, $resolver, $metadata, $clock, $checkpoint, $deferredFactory
);
expect(($endpointDeferred['status'] ?? null) === 'additional_prices_batch', 'endpoint keeps bounded batch status on local deferral');
expect(($endpointDeferred['offers'][0]['status'] ?? null) === 'additional_prices_unknown'
    && ($endpointDeferred['offers'][0]['finalPriceReady'] ?? null) === false
    && ($endpointDeferred['offers'][0]['finalPrice'] ?? 'not-null') === null
    && ($endpointDeferred['offers'][0]['price'] ?? 'not-null') === null
    && ($endpointDeferred['offers'][0]['additional_prices'] ?? 'not-null') === null,
    'endpoint local deferral remains no-money and fail-closed');
expect(($endpointDeferred['offers'][0]['retryable'] ?? null) === true
    && ($endpointDeferred['offers'][0]['retry_reason'] ?? null) === 'session_budget_deferred',
    'endpoint exposes only the safe later-enrichment hint');
expect($factoryCalls === 1 && $endpointCheckpoints === 2 && ($endpointState['additional_prices'] ?? []) === [],
    'endpoint local deferral rolls reservation back without durable supplier state');

$endpointUnknownState = $endpointState;
$endpointUnknownState['additional_prices'][$digest] = ['status' => 'unknown'];
$blockedFactoryCalls = 0;
$blockedFactory = static function() use (&$blockedFactoryCalls): AnyTourAnexAdditionalPricesClient {
    ++$blockedFactoryCalls;
    throw new RuntimeException('MUST_NOT_REPLAY');
};
$endpointUnknown = anytour_anex_search3_additional_batch(
    $endpointRequest, $endpointUnknownState, $resolver, $metadata, $clock, $checkpoint, $blockedFactory
);
expect($blockedFactoryCalls === 0, 'endpoint durable unknown remains supplier no-replay');
expect(($endpointUnknown['offers'][0]['finalPriceReady'] ?? null) === false
    && ($endpointUnknown['offers'][0]['price'] ?? 'not-null') === null
    && ($endpointUnknown['offers'][0]['retryable'] ?? null) === false
    && array_key_exists('retry_reason', $endpointUnknown['offers'][0])
    && $endpointUnknown['offers'][0]['retry_reason'] === null,
    'endpoint durable unknown is public non-retryable without exposing internal reason');

$endpointCompleteState = $endpointState;
$endpointCompleteState['additional_prices'][$digest] = ['status' => 'complete', 'evidence' => [
    'source' => 'anex_b2b_additional_prices_daily',
    'rows' => [[
        'price_adult' => '1000', 'price_chd' => '500', 'cashrate' => '1',
        'price_converted_adult' => '1000', 'price_converted_chd' => '500',
    ]],
    'total_count' => 1, 'truncated' => false, 'scope' => 'tour_program_date_nights_currency',
    'offer_specific' => false, 'currency' => null, 'converted_currency' => null,
    'per_person_or_package' => 'unknown', 'fuel_equivalence_verified' => false,
    'included_in_search_price' => 'unknown', 'arithmetic_applied' => false, 'final_price_verified' => false,
    'observed_at' => '2026-09-16T08:00:00Z',
]];
$endpointComplete = anytour_anex_search3_additional_batch(
    $endpointRequest, $endpointCompleteState, $resolver, $metadata, $clock, $checkpoint, $blockedFactory
);
expect(($endpointComplete['offers'][0]['finalPriceReady'] ?? null) === true
    && ($endpointComplete['offers'][0]['price'] ?? null) === '102000'
    && ($endpointComplete['offers'][0]['retryable'] ?? null) === false
    && array_key_exists('retry_reason', $endpointComplete['offers'][0])
    && $endpointComplete['offers'][0]['retry_reason'] === null,
    'endpoint completed fuel-ready price remains unchanged and non-retryable');

echo "ANEX AdditionalPricesDaily budget deferral regression: OK\n";
