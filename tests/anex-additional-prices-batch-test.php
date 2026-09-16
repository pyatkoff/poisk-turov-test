<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-normalizer.php';
require_once __DIR__ . '/../app/integrations/anex-additional-prices-client.php';
require_once __DIR__ . '/../app/integrations/anex-preview-gateway.php';
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';

$checks = 0;
$assert = static function ($condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    ++$checks;
};

$ref1 = 'anex_online:' . str_repeat('a', 64);
$ref2 = 'anex_online:' . str_repeat('b', 64);
$ref3 = 'anex_online:' . str_repeat('c', 64);
$offer = static function (string $ref, int $localId, string $externalId, string $checkin, int $nights,
    string $price = '100000'): array {
    return [
        'offer_key' => $ref,
        'provider' => 'anex',
        'supplier_namespace' => 'anex_online',
        'kind' => 'concrete',
        'hotel' => [
            'external_id' => $externalId,
            'local_id' => $localId,
            'mapping_status' => 'resolved',
            'name' => 'TEST HOTEL ' . $localId,
            'star' => '4',
            'country' => null,
            'region' => null,
            'town' => 'Side',
            'external_town_id' => '12',
        ],
        'checkin' => $checkin,
        'checkout' => (new DateTimeImmutable($checkin))->modify('+' . $nights . ' days')->format('Y-m-d'),
        'nights' => $nights,
        'adults' => 2,
        'children' => 0,
        'infants' => null,
        'meal' => 'AI',
        'external_meal_id' => '7',
        'room' => 'STANDARD',
        'external_room_id' => '10',
        'hotel_place' => 'DBL',
        'external_hotel_place_id' => '2',
        'price' => ['amount' => $price, 'currency' => 'RUB'],
        'converted_price' => null,
        'availability' => ['hotel' => 'Y', 'flight_outbound_economy' => 'Y', 'flight_return_economy' => 'Y'],
        'supplier_booking_flag' => true,
        'final_price_verified' => false,
    ];
};

$state = [
    'gateway' => [
        'saved_offers' => ['offers' => [
            $ref1 => ['offer' => $offer($ref1, 101, '8101', '2026-10-05', 7),
                'supplier_tour_program_id' => '2637', 'supplier_currency_id' => '1'],
            $ref2 => ['offer' => $offer($ref2, 102, '8102', '2026-10-05', 7),
                'supplier_tour_program_id' => '2637', 'supplier_currency_id' => '1'],
            $ref3 => ['offer' => $offer($ref3, 103, '8103', '2026-10-06', 7),
                'supplier_tour_program_id' => '1797', 'supplier_currency_id' => '1'],
        ]],
        'search' => ['offers' => [
            ['offer_key' => $ref1, 'kind' => 'concrete', 'hotel_external_id' => '8101'],
            ['offer_key' => $ref2, 'kind' => 'concrete', 'hotel_external_id' => '8102'],
            ['offer_key' => $ref3, 'kind' => 'concrete', 'hotel_external_id' => '8103'],
        ]],
    ],
    'additional_prices' => [],
];

$plan = anytour_anex_additional_prices_batch_plan([
    ['offer_ref' => $ref1, 'local_hotel_id' => 101],
    ['offer_ref' => $ref2, 'local_hotel_id' => 102],
    ['offer_ref' => $ref3, 'local_hotel_id' => 103],
], $state);
$assert($plan['requested_offers'] === 3, 'three visible offers retained');
$assert($plan['unique_contexts'] === 2, 'shared program/date context deduplicated');
$assert($plan['offers'][0]['context_digest'] === $plan['offers'][1]['context_digest'], 'same APD context shares digest');
$assert($plan['offers'][2]['context_digest'] !== $plan['offers'][0]['context_digest'], 'different program/date gets another digest');
$assert($plan['contexts'][0]['supplier_tour_program_id'] === '2637'
    && $plan['contexts'][0]['supplier_currency_id'] === '1'
    && $plan['contexts'][0]['checkin'] === '2026-10-05'
    && $plan['contexts'][0]['nights'] === 7, 'private supplier context preserved server-side');

$reads = [];
$checkpoints = [];
$reader = static function (array $context) use (&$reads): array {
    $reads[] = $context['context_digest'];
    return ['source' => 'test', 'marker' => $context['checkin']];
};
$checkpoint = static function (array &$current, string $digest) use (&$checkpoints, $assert): void {
    $checkpoints[] = $digest;
    $assert(($current['additional_prices'][$digest]['status'] ?? null) === 'unknown', 'unknown persisted before reader');
};
$result = anytour_anex_additional_prices_batch_execute($plan, $state, $reader, $checkpoint);
$assert(count($reads) === 2 && count($checkpoints) === 2, 'one reader call per unique context');
$assert($result['requested_offers'] === 3 && $result['unique_contexts'] === 2, 'batch result keeps offer/context counts');
$assert($result['offers'][0]['additional_prices']['marker'] === '2026-10-05'
    && $result['offers'][1]['additional_prices']['marker'] === '2026-10-05', 'shared evidence fans out to both offers');
$assert($result['offers'][2]['additional_prices']['marker'] === '2026-10-06', 'second context keeps its evidence');
$assert(count($state['additional_prices']) === 2, 'completed evidence stored once per context');

$cachedReads = 0;
$cached = anytour_anex_additional_prices_batch_execute($plan, $state,
    static function () use (&$cachedReads): array { ++$cachedReads; return ['unexpected' => true]; },
    static function (): void { throw new RuntimeException('CHECKPOINT_MUST_NOT_RUN'); });
$assert($cachedReads === 0, 'completed batch is supplier-free cache read');
$assert($cached['offers'][0]['status'] === 'complete' && $cached['offers'][1]['status'] === 'complete'
    && $cached['offers'][2]['status'] === 'complete', 'cached statuses stay complete');

$unknownState = $state;
$unknownDigest = $plan['offers'][0]['context_digest'];
$unknownState['additional_prices'][$unknownDigest] = ['status' => 'unknown'];
$unknownReads = 0;
$unknown = anytour_anex_additional_prices_batch_execute($plan, $unknownState,
    static function () use (&$unknownReads): array { ++$unknownReads; return ['unexpected' => true]; },
    static function (): void { throw new RuntimeException('UNKNOWN_REPLAY_CHECKPOINT_FORBIDDEN'); });
$assert($unknownReads === 0, 'unknown context is never replayed');
$assert($unknown['offers'][0]['status'] === 'unknown' && $unknown['offers'][1]['status'] === 'unknown'
    && $unknown['offers'][2]['status'] === 'complete', 'unknown only affects offers sharing that context');

$failureState = [
    'gateway' => $state['gateway'],
    'additional_prices' => [],
];
$failurePlan = anytour_anex_additional_prices_batch_plan([['offer_ref' => $ref1, 'local_hotel_id' => 101]], $failureState);
$failureDigest = $failurePlan['offers'][0]['context_digest'];
$failed = false;
try {
    anytour_anex_additional_prices_batch_execute($failurePlan, $failureState,
        static function () { throw new RuntimeException('SUPPLIER_TIMEOUT'); },
        static function (): void {});
} catch (RuntimeException $e) {
    $failed = $e->getMessage() === 'SUPPLIER_TIMEOUT';
}
$assert($failed && ($failureState['additional_prices'][$failureDigest]['status'] ?? null) === 'unknown',
    'reader failure leaves durable unknown no-replay state');

$tooMany = [];
for ($i = 0; $i < 7; ++$i) $tooMany[] = ['offer_ref' => $ref1, 'local_hotel_id' => 101];
$failed = false;
try { anytour_anex_additional_prices_batch_plan($tooMany, $state); }
catch (InvalidArgumentException $e) { $failed = $e->getMessage() === 'ANEX_INVALID_ADDITIONAL_BATCH'; }
$assert($failed, 'batch hard-bounded to six offers');

$badState = $state;
$badState['gateway']['saved_offers']['offers'][$ref1]['offer']['kind'] = 'group_minimum';
$failed = false;
try { anytour_anex_additional_prices_batch_plan([['offer_ref' => $ref1, 'local_hotel_id' => 101]], $badState); }
catch (InvalidArgumentException $e) { $failed = $e->getMessage() === 'ANEX_INVALID_SESSION'; }
$assert($failed, 'group minimum cannot enter APD batch');

$badState = $state;
$badState['gateway']['saved_offers']['offers'][$ref1]['supplier_tour_program_id'] = null;
$failed = false;
try { anytour_anex_additional_prices_batch_plan([['offer_ref' => $ref1, 'local_hotel_id' => 101]], $badState); }
catch (InvalidArgumentException $e) { $failed = $e->getMessage() === 'ANEX_ADDITIONAL_CONTEXT_UNAVAILABLE'; }
$assert($failed, 'missing retained private context fails closed');

$failed = false;
try { anytour_anex_additional_prices_batch_plan([['offer_ref' => $ref1, 'local_hotel_id' => 999]], $state); }
catch (InvalidArgumentException $e) { $failed = $e->getMessage() === 'ANEX_INVALID_SESSION'; }
$assert($failed, 'local hotel identity mismatch fails closed');

// Real runtime consumer: the existing Search3 endpoint batches visible retained offers.
$searchRef = str_repeat('d', 32);
$runtimeState = [
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
            'offers' => [
                $ref1 => ['offer' => $offer($ref1, 101, '8101', '2026-10-05', 7, '100000'),
                    'supplier_tour_program_id' => '2637', 'supplier_currency_id' => '1'],
                $ref2 => ['offer' => $offer($ref2, 102, '8102', '2026-10-05', 7, '110000'),
                    'supplier_tour_program_id' => '2637', 'supplier_currency_id' => '1'],
                $ref3 => ['offer' => $offer($ref3, 103, '8103', '2026-10-06', 7, '120000'),
                    'supplier_tour_program_id' => '1797', 'supplier_currency_id' => '1'],
            ],
        ],
        'search' => ['offers' => [
            ['offer_key' => $ref1, 'kind' => 'concrete', 'hotel_external_id' => '8101'],
            ['offer_key' => $ref2, 'kind' => 'concrete', 'hotel_external_id' => '8102'],
            ['offer_key' => $ref3, 'kind' => 'concrete', 'hotel_external_id' => '8103'],
        ]],
    ],
    'expansions' => [],
    'additional_prices' => [],
];
$runtimeRequest = [
    'action' => 'additional_prices_batch',
    'generation' => 11,
    'search_ref' => $searchRef,
    'items' => [
        ['offer_ref' => $ref1, 'local_hotel_id' => 101],
        ['offer_ref' => $ref2, 'local_hotel_id' => 102],
        ['offer_ref' => $ref3, 'local_hotel_id' => 103],
    ],
];
$resolver = static function (string $namespace, $external): ?int {
    if ($namespace !== 'anex_online') return null;
    return ['8101' => 101, '8102' => 102, '8103' => 103][(string) $external] ?? null;
};
$metadata = static function (array $offers): array {
    $rows = [];
    foreach ($offers as $entry) {
        $id = $entry['hotel']['local_id'];
        $rows[$id] = [
            'id' => $id,
            'name' => 'TEST HOTEL ' . $id,
            'country_id' => 1,
            'country_name' => 'Turkey',
            'region_id' => 2,
            'region_name' => 'Side',
            'subregion_id' => 3,
            'subregion_name' => 'Kizilagac',
            'category' => 4,
            'rating' => 4.5,
        ];
    }
    return $rows;
};
$clock = static function (): int { return 1789220100; };
$runtimeCheckpoints = 0;
$runtimeCheckpoint = static function (array &$current) use (&$runtimeCheckpoints, $assert): void {
    ++$runtimeCheckpoints;
    $unknown = array_filter($current['additional_prices'] ?? [], static function ($attempt): bool {
        return is_array($attempt) && ($attempt['status'] ?? null) === 'unknown';
    });
    $assert(count($unknown) >= 1, 'endpoint checkpoints unknown context before B2B read');
};
$factoryCalls = 0;
$transportCalls = [];
$additionalFactory = static function () use (&$factoryCalls, &$transportCalls): AnyTourAnexAdditionalPricesClient {
    ++$factoryCalls;
    return new AnyTourAnexAdditionalPricesClient('test-token',
        static function (string $url, array $headers, array $options) use (&$transportCalls): array {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $transportCalls[] = $query;
            $tour = (int) ($query['tour'] ?? 0);
            $rate = $tour === 2637 ? '1000' : ($tour === 1797 ? '2000' : null);
            if ($rate === null) throw new RuntimeException('UNEXPECTED_TOUR');
            return ['status' => 200, 'body' => json_encode([
                'data' => [[
                    'price_adult' => $rate,
                    'price_chd' => '500',
                    'cashrate' => '1',
                    'price_converted_adult' => $rate,
                    'price_converted_chd' => '500',
                    'tour' => $tour,
                    'currency' => (int) $query['currency'],
                    'dateBeg' => $query['dateBeg'],
                    'nights' => (int) $query['nights'],
                ]],
                'totalCount' => 1,
                'totalPages' => 1,
            ], JSON_THROW_ON_ERROR)];
        });
};

$runtime = anytour_anex_search3_additional_batch($runtimeRequest, $runtimeState, $resolver, $metadata,
    $clock, $runtimeCheckpoint, $additionalFactory);
$assert($runtime['status'] === 'additional_prices_batch' && count($runtime['offers']) === 3,
    'existing endpoint returns one bounded batch response');
$assert($factoryCalls === 2 && count($transportCalls) === 2 && $runtimeCheckpoints === 2,
    'three visible offers use two deduplicated B2B contexts');
$assert($runtime['offers'][0]['additional_prices']['party_surcharge']['amount'] === '2000'
    && $runtime['offers'][0]['additional_prices']['search_plus_additional']['amount'] === '102000',
    'first offer applies its retained party surcharge');
$assert($runtime['offers'][1]['additional_prices']['party_surcharge']['amount'] === '2000'
    && $runtime['offers'][1]['additional_prices']['search_plus_additional']['amount'] === '112000',
    'shared APD context is applied to second offer base price independently');
$assert($runtime['offers'][2]['additional_prices']['party_surcharge']['amount'] === '4000'
    && $runtime['offers'][2]['additional_prices']['search_plus_additional']['amount'] === '124000',
    'second APD context is applied to its retained offer');
$assert($runtime['offers'][0]['finalPriceReady'] === true
    && $runtime['offers'][0]['finalPrice'] === '102000'
    && $runtime['offers'][0]['price'] === '102000'
    && $runtime['offers'][1]['finalPrice'] === '112000'
    && $runtime['offers'][2]['finalPrice'] === '124000',
    'applied direct-ANEX surcharge is exposed as the customer-ready listing price');
$assert($runtime['offers'][0]['additional_prices']['fuel_equivalence_verified'] === false
    && $runtime['offers'][0]['additional_prices']['final_price_verified'] === false,
    'batch does not claim Tourvisor fuel equivalence or final quote');
$publicJson = json_encode($runtime, JSON_THROW_ON_ERROR);
$assert(strpos($publicJson, '2637') === false && strpos($publicJson, '1797') === false
    && strpos($publicJson, 'context_digest') === false && strpos($publicJson, 'supplier_currency_id') === false,
    'private supplier context never crosses batch response');

$cachedRuntime = anytour_anex_search3_additional_batch($runtimeRequest, $runtimeState, $resolver, $metadata,
    $clock, $runtimeCheckpoint, $additionalFactory);
$assert($factoryCalls === 2 && count($transportCalls) === 2 && $runtimeCheckpoints === 2,
    'repeating completed visible batch is fully supplier-free');
$assert($cachedRuntime['offers'][0]['additional_prices']['search_plus_additional']['amount'] === '102000'
    && $cachedRuntime['offers'][2]['additional_prices']['search_plus_additional']['amount'] === '124000'
    && $cachedRuntime['offers'][0]['finalPriceReady'] === true
    && $cachedRuntime['offers'][0]['price'] === '102000',
    'cached endpoint response reapplies the same ready per-offer price without supplier replay');

// The exact saved-offer read must retain that completed APD listing amount without a new supplier call.
$exactTemplate = $runtimeState;
$exactTemplate['gateway']['expires_at'] = 1789220900;
$exactTemplate['gateway']['window_started_at'] = 1789220000;
$exactTemplate['gateway']['request_count'] = 0;
$exactTemplate['gateway']['burst_started_at'] = 1789220000;
$exactTemplate['gateway']['burst_request_count'] = 0;
$exactTemplate['gateway']['saved_offers']['search'] = [
    'checkin_begin' => '2026-10-05', 'checkin_end' => '2026-10-06',
    'nights_from' => 7, 'nights_till' => 7, 'adults' => 2, 'children' => 0, 'child_ages' => [],
];
foreach ($exactTemplate['gateway']['saved_offers']['offers'] as &$savedOffer) $savedOffer['observed_at'] = 1789220000;
unset($savedOffer);
$exactRequest = ['action' => 'offer', 'generation' => 11, 'search_ref' => $searchRef,
    'offer_ref' => $ref1, 'local_hotel_id' => 101];
$searchFactoryCalls = 0;
$forbiddenClientFactory = static function () use (&$searchFactoryCalls): AnyTourAnexClient {
    ++$searchFactoryCalls;
    throw new RuntimeException('EXACT_OFFER_MUST_NOT_CALL_SEARCH');
};
$exactAdditionalCalls = 0;
$forbiddenAdditionalFactory = static function () use (&$exactAdditionalCalls): AnyTourAnexAdditionalPricesClient {
    ++$exactAdditionalCalls;
    throw new RuntimeException('EXACT_OFFER_MUST_NOT_CALL_APD');
};
$beforeFactory = $factoryCalls;
$beforeTransport = count($transportCalls);
$exactState = $exactTemplate;
$exact = anytour_anex_search3_followup($exactRequest, $exactState, $resolver, $forbiddenClientFactory,
    $metadata, $clock, null, $forbiddenAdditionalFactory);
$assert($exact['status'] === 'current'
    && $exact['finalPriceReady'] === true && $exact['finalPrice'] === '102000' && $exact['price'] === '102000'
    && $exact['additional_prices']['search_plus_additional']['amount'] === '102000',
    'exact saved offer preserves the completed APD customer-ready listing amount');
$assert($exact['offer']['money']['search_price']['amount'] === '100000'
    && $exact['offer']['money']['fuel_charge_reported'] === null
    && $exact['offer']['final_price_verified'] === false
    && $exact['additional_prices']['final_price_verified'] === false,
    'exact APD continuity keeps search money and final quote verification separate');
$assert($searchFactoryCalls === 0 && $exactAdditionalCalls === 0
    && $factoryCalls === $beforeFactory && count($transportCalls) === $beforeTransport,
    'exact saved offer reuses APD state without supplier transport');

$unknownExactState = $exactTemplate;
$unknownExactState['additional_prices'][$sharedDigest] = ['status' => 'unknown'];
$unknownExact = anytour_anex_search3_followup($exactRequest, $unknownExactState, $resolver, $forbiddenClientFactory,
    $metadata, $clock, null, $forbiddenAdditionalFactory);
$assert($unknownExact['status'] === 'current' && $unknownExact['finalPriceReady'] === false
    && $unknownExact['finalPrice'] === null && $unknownExact['price'] === null
    && $unknownExact['additional_prices'] === null,
    'durable-unknown APD stays fail-closed on exact offer read');
$missingExactState = $exactTemplate;
unset($missingExactState['additional_prices'][$sharedDigest]);
$missingExact = anytour_anex_search3_followup($exactRequest, $missingExactState, $resolver, $forbiddenClientFactory,
    $metadata, $clock, null, $forbiddenAdditionalFactory);
$assert($missingExact['status'] === 'current' && $missingExact['finalPriceReady'] === false
    && $missingExact['finalPrice'] === null && $missingExact['price'] === null
    && $missingExact['additional_prices'] === null
    && $searchFactoryCalls === 0 && $exactAdditionalCalls === 0,
    'missing APD stays supplier-free and never falls back to base search price');

$runtimeUnknown = $runtimeState;
$sharedDigest = hash('sha256', implode("\0", ['2637', '1', '2026-10-05', '7']));
$runtimeUnknown['additional_prices'][$sharedDigest] = ['status' => 'unknown'];
$beforeFactory = $factoryCalls;
$beforeTransport = count($transportCalls);
$unknownRuntime = anytour_anex_search3_additional_batch($runtimeRequest, $runtimeUnknown, $resolver, $metadata,
    $clock, $runtimeCheckpoint, $additionalFactory);
$assert($factoryCalls === $beforeFactory && count($transportCalls) === $beforeTransport,
    'unknown APD context is not replayed through endpoint');
$assert($unknownRuntime['offers'][0]['status'] === 'additional_prices_unknown'
    && $unknownRuntime['offers'][1]['status'] === 'additional_prices_unknown'
    && $unknownRuntime['offers'][2]['status'] === 'additional_prices',
    'unknown shared context affects only its visible offers');
$assert($unknownRuntime['offers'][0]['finalPriceReady'] === false
    && $unknownRuntime['offers'][0]['finalPrice'] === null
    && $unknownRuntime['offers'][0]['price'] === null
    && $unknownRuntime['offers'][1]['finalPriceReady'] === false
    && $unknownRuntime['offers'][1]['price'] === null
    && $unknownRuntime['offers'][2]['finalPriceReady'] === true
    && $unknownRuntime['offers'][2]['price'] === '124000',
    'unknown APD stays fail-closed while independently completed offers remain price-ready');

$changedResolver = static function (string $namespace, $external): ?int {
    if ((string) $external === '8102') return 999;
    return ['8101' => 101, '8103' => 103][(string) $external] ?? null;
};
$beforeFactory = $factoryCalls;
$changed = anytour_anex_search3_additional_batch($runtimeRequest, $runtimeState, $changedResolver, $metadata,
    $clock, $runtimeCheckpoint, $additionalFactory);
$assert($changed['status'] === 'identity_changed' && $factoryCalls === $beforeFactory,
    'current identity drift blocks batch before B2B transport');

$missingMetadata = static function (array $offers): array {
    return [101 => ['id' => 101, 'name' => 'TEST HOTEL 101', 'country_id' => 1, 'country_name' => 'Turkey',
        'region_id' => 2, 'region_name' => 'Side', 'subregion_id' => 3, 'subregion_name' => 'Kizilagac',
        'category' => 4, 'rating' => 4.5]];
};
$beforeFactory = $factoryCalls;
$notAvailable = anytour_anex_search3_additional_batch($runtimeRequest, $runtimeState, $resolver, $missingMetadata,
    $clock, $runtimeCheckpoint, $additionalFactory);
$assert($notAvailable['status'] === 'not_available' && $factoryCalls === $beforeFactory,
    'current catalog/filter loss blocks batch before B2B transport');

$tooManyRequest = $runtimeRequest;
$tooManyRequest['items'] = array_fill(0, 7, ['offer_ref' => $ref1, 'local_hotel_id' => 101]);
$failed = false;
try {
    anytour_anex_search3_additional_batch($tooManyRequest, $runtimeState, $resolver, $metadata,
        $clock, $runtimeCheckpoint, $additionalFactory);
} catch (InvalidArgumentException $e) {
    $failed = $e->getMessage() === 'ANEX_INVALID_ADDITIONAL_BATCH';
}
$assert($failed, 'real endpoint preserves hard max-six batch bound');

echo "ANEX additional-prices batch endpoint: {$checks} checks passed; live_network=0\n";
