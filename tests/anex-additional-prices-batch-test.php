<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-normalizer.php';
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
            'external_id' => $externalId, 'local_id' => $localId, 'mapping_status' => 'resolved',
            'name' => 'TEST HOTEL ' . $localId, 'star' => '4', 'country' => null, 'region' => null,
            'town' => 'Side', 'external_town_id' => '12',
        ],
        'checkin' => $checkin,
        'checkout' => (new DateTimeImmutable($checkin))->modify('+' . $nights . ' days')->format('Y-m-d'),
        'nights' => $nights, 'adults' => 2, 'children' => 0, 'infants' => null,
        'meal' => 'AI', 'external_meal_id' => '7', 'room' => 'STANDARD', 'external_room_id' => '10',
        'hotel_place' => 'DBL', 'external_hotel_place_id' => '2',
        'price' => ['amount' => $price, 'currency' => 'RUB'], 'converted_price' => null,
        'availability' => ['hotel' => 'Y', 'flight_outbound_economy' => 'Y', 'flight_return_economy' => 'Y'],
        'supplier_booking_flag' => true, 'final_price_verified' => false,
    ];
};

$baseState = [
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

// Keep the pure batch planner/executor contract available for future supplier-authoritative B2B contexts.
$state = $baseState;
$plan = anytour_anex_additional_prices_batch_plan([
    ['offer_ref' => $ref1, 'local_hotel_id' => 101],
    ['offer_ref' => $ref2, 'local_hotel_id' => 102],
    ['offer_ref' => $ref3, 'local_hotel_id' => 103],
], $state);
$assert($plan['requested_offers'] === 3 && $plan['unique_contexts'] === 2, 'planner deduplicates identical private contexts');
$assert($plan['offers'][0]['context_digest'] === $plan['offers'][1]['context_digest']
    && $plan['offers'][2]['context_digest'] !== $plan['offers'][0]['context_digest'], 'context digests remain deterministic');

$reads = [];
$checkpoints = [];
$executed = anytour_anex_additional_prices_batch_execute($plan, $state,
    static function (array $context) use (&$reads): array {
        $reads[] = $context['context_digest'];
        return ['source' => 'authoritative-fixture', 'marker' => $context['checkin']];
    },
    static function (array &$current, string $digest) use (&$checkpoints, $assert): void {
        $checkpoints[] = $digest;
        $assert(($current['additional_prices'][$digest]['status'] ?? null) === 'unknown', 'executor persists unknown before reader');
    });
$assert(count($reads) === 2 && count($checkpoints) === 2, 'executor reads once per unique context');
$assert($executed['offers'][0]['status'] === 'complete' && $executed['offers'][2]['status'] === 'complete',
    'pure executor retains completed evidence');
$cachedReads = 0;
$cached = anytour_anex_additional_prices_batch_execute($plan, $state,
    static function () use (&$cachedReads): array { ++$cachedReads; return ['unexpected' => true]; },
    static function (): void { throw new RuntimeException('CHECKPOINT_MUST_NOT_RUN'); });
$assert($cachedReads === 0 && $cached['offers'][0]['status'] === 'complete', 'pure completed context remains supplier-free');
$unknownState = $state;
$unknownDigest = $plan['offers'][0]['context_digest'];
$unknownState['additional_prices'][$unknownDigest] = ['status' => 'unknown'];
$unknownReads = 0;
$unknown = anytour_anex_additional_prices_batch_execute($plan, $unknownState,
    static function () use (&$unknownReads): array { ++$unknownReads; return ['unexpected' => true]; },
    static function (): void { throw new RuntimeException('UNKNOWN_REPLAY_FORBIDDEN'); });
$assert($unknownReads === 0 && $unknown['offers'][0]['status'] === 'unknown'
    && $unknown['offers'][1]['status'] === 'unknown' && $unknown['offers'][2]['status'] === 'complete',
    'pure unknown context is never replayed and affects only its shared context');

$tooMany = array_fill(0, 7, ['offer_ref' => $ref1, 'local_hotel_id' => 101]);
$failed = false;
try { anytour_anex_additional_prices_batch_plan($tooMany, $baseState); }
catch (InvalidArgumentException $e) { $failed = $e->getMessage() === 'ANEX_INVALID_ADDITIONAL_BATCH'; }
$assert($failed, 'planner remains hard-bounded to six visible offers');
$badState = $baseState;
$badState['gateway']['saved_offers']['offers'][$ref1]['offer']['kind'] = 'group_minimum';
$failed = false;
try { anytour_anex_additional_prices_batch_plan([['offer_ref' => $ref1, 'local_hotel_id' => 101]], $badState); }
catch (InvalidArgumentException $e) { $failed = $e->getMessage() === 'ANEX_INVALID_SESSION'; }
$assert($failed, 'group minimum cannot enter APD plan');

// Real runtime consumer: validate current search/identity/catalog but never treat SearchTour IDs as B2B tour authority.
$searchRef = str_repeat('d', 32);
$runtimeState = $baseState;
$runtimeState['generation'] = 11;
$runtimeState['params'] = ['countryId' => 1, 'hotelIds' => [], 'regionIds' => [], 'subregionIds' => [],
    'hotelRating' => '', 'hotelCategory' => '', 'meal' => '', 'priceFrom' => '', 'priceTo' => ''];
$runtimeState['gateway']['saved_offers']['search_ref'] = $searchRef;
$runtimeState['gateway']['saved_offers']['created_at'] = 1789220000;
$runtimeState['gateway']['saved_offers']['expires_at'] = 1789220900;
$runtimeState['expansions'] = [];
$runtimeRequest = [
    'action' => 'additional_prices_batch', 'generation' => 11, 'search_ref' => $searchRef,
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
        $rows[$id] = ['id' => $id, 'name' => 'TEST HOTEL ' . $id, 'country_id' => 1, 'country_name' => 'Turkey',
            'region_id' => 2, 'region_name' => 'Side', 'subregion_id' => 3, 'subregion_name' => 'Kizilagac',
            'category' => 4, 'rating' => 4.5];
    }
    return $rows;
};
$clock = static function (): int { return 1789220100; };
$runtimeCheckpoints = 0;
$checkpoint = static function (array &$current) use (&$runtimeCheckpoints): void { ++$runtimeCheckpoints; };
$factoryCalls = 0;
$additionalFactory = static function () use (&$factoryCalls) {
    ++$factoryCalls;
    throw new RuntimeException('B2B_CLIENT_MUST_NOT_RUN');
};

$runtime = anytour_anex_search3_additional_batch($runtimeRequest, $runtimeState, $resolver, $metadata,
    $clock, $checkpoint, $additionalFactory);
$assert($runtime['status'] === 'additional_prices_batch' && count($runtime['offers']) === 3,
    'runtime preserves bounded batch response shape');
$assert(($runtime['additional_prices_reason'] ?? null) === 'b2b_tour_binding_unverified',
    'runtime exposes exact binding blocker');
$assert($factoryCalls === 0 && $runtimeCheckpoints === 0 && $runtimeState['additional_prices'] === [],
    'runtime binding blocker stops before cache reservation or B2B client');
foreach ($runtime['offers'] as $item) {
    $assert($item['status'] === 'additional_prices_unknown' && $item['additional_prices'] === null
        && ($item['additional_prices_reason'] ?? null) === 'b2b_tour_binding_unverified',
        'each current visible offer remains unknown until authoritative binding exists');
}
$publicJson = json_encode($runtime, JSON_THROW_ON_ERROR);
$assert(strpos($publicJson, '2637') === false && strpos($publicJson, '1797') === false
    && strpos($publicJson, 'context_digest') === false && strpos($publicJson, 'supplier_currency_id') === false,
    'private SearchTour diagnostics never cross runtime response');

// A legacy completed APD cache based on the unverified SearchTour IDs is deliberately not surfaced.
$legacy = $runtimeState;
$legacyDigest = hash('sha256', implode("\0", ['2637', '1', '2026-10-05', '7']));
$legacy['additional_prices'][$legacyDigest] = ['status' => 'complete', 'evidence' => ['source' => 'legacy-unverified']];
$legacyResult = anytour_anex_search3_additional_batch($runtimeRequest, $legacy, $resolver, $metadata,
    $clock, $checkpoint, $additionalFactory);
$assert($factoryCalls === 0 && $runtimeCheckpoints === 0
    && $legacyResult['offers'][0]['status'] === 'additional_prices_unknown',
    'legacy unverified cache is neither replayed nor exposed');

$changedResolver = static function (string $namespace, $external): ?int {
    if ((string) $external === '8102') return 999;
    return ['8101' => 101, '8103' => 103][(string) $external] ?? null;
};
$changed = anytour_anex_search3_additional_batch($runtimeRequest, $runtimeState, $changedResolver, $metadata,
    $clock, $checkpoint, $additionalFactory);
$assert($changed['status'] === 'identity_changed' && $factoryCalls === 0, 'identity drift still blocks before binding status');
$missingMetadata = static function (array $offers): array {
    return [101 => ['id' => 101, 'name' => 'TEST HOTEL 101', 'country_id' => 1, 'country_name' => 'Turkey',
        'region_id' => 2, 'region_name' => 'Side', 'subregion_id' => 3, 'subregion_name' => 'Kizilagac',
        'category' => 4, 'rating' => 4.5]];
};
$notAvailable = anytour_anex_search3_additional_batch($runtimeRequest, $runtimeState, $resolver, $missingMetadata,
    $clock, $checkpoint, $additionalFactory);
$assert($notAvailable['status'] === 'not_available' && $factoryCalls === 0, 'catalog/filter loss still blocks before binding status');

$tooManyRequest = $runtimeRequest;
$tooManyRequest['items'] = array_fill(0, 7, ['offer_ref' => $ref1, 'local_hotel_id' => 101]);
$failed = false;
try { anytour_anex_search3_additional_batch($tooManyRequest, $runtimeState, $resolver, $metadata,
    $clock, $checkpoint, $additionalFactory); }
catch (InvalidArgumentException $e) { $failed = $e->getMessage() === 'ANEX_INVALID_ADDITIONAL_BATCH'; }
$assert($failed, 'runtime preserves max-six batch bound');

echo "ANEX additional-prices batch runtime binding: {$checks} checks passed; live_network=0\n";
