<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/integrations/anex-anytour-offer-autosave.php';

$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) throw new RuntimeException('ANEX_AUTOSAVE_CHECK_FAILED:' . $label);
};

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE anytour_hotels (id INTEGER PRIMARY KEY, is_active INTEGER NOT NULL)');
$db->exec('CREATE TABLE anytour_hotel_sources (anytour_hotel_id INTEGER NOT NULL, namespace TEXT NOT NULL, external_key TEXT NOT NULL)');
$db->exec("INSERT INTO anytour_hotels(id,is_active) VALUES(501,1),(502,1)");
$db->exec("INSERT INTO anytour_hotel_sources(anytour_hotel_id,namespace,external_key) VALUES(501,'legacy_catalog','3417'),(502,'legacy_catalog','3418')");

$now = new DateTimeImmutable('2026-09-17T10:00:00Z');
$created = $now->getTimestamp() - 60;
$searchRef = str_repeat('d', 32);
$offerRef = 'anex_online:' . str_repeat('a', 64);
$digest = hash('sha256', "2637\0" . "1\0" . "2026-10-05\0" . "7");

$offer = [
    'offer_key' => $offerRef,
    'provider' => 'anex',
    'supplier_namespace' => 'anex_online',
    'kind' => 'concrete',
    'hotel' => [
        'external_id' => '8101',
        'local_id' => 3417,
        'mapping_status' => 'resolved',
        'name' => 'TEST HOTEL',
        'star' => '4',
        'country' => null,
        'region' => null,
        'town' => 'Side',
        'external_town_id' => '12',
    ],
    'checkin' => '2026-10-05',
    'checkout' => '2026-10-12',
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

$params = [
    'departureId' => '1', 'countryId' => '4', 'dateFrom' => '2026-10-05', 'dateTo' => '2026-10-05',
    'nightsFrom' => 7, 'nightsTo' => 7, 'adults' => 2, 'childs' => [], 'meal' => '',
    'hotelCategory' => '', 'hotelRating' => '', 'hotelTypes' => [], 'hotelIds' => [], 'hotelServices' => [],
    'arrivalId' => '', 'regionIds' => [], 'subregionIds' => [], 'operatorIds' => [], 'priceFrom' => '', 'priceTo' => '',
    'currency' => 'RUB', 'onlyCharter' => false, 'onlyDirect' => false,
];
$search = [
    'checkin_begin' => '2026-10-05', 'checkin_end' => '2026-10-05',
    'nights_from' => 7, 'nights_till' => 7,
    'adults' => 2, 'children' => 0, 'child_ages' => [],
];
$state = [
    'generation' => 41,
    'params' => $params,
    'gateway' => [
        'saved_offers' => [
            'search_ref' => $searchRef,
            'created_at' => $created,
            'expires_at' => $created + 900,
            'search' => $search,
            'offers' => [
                $offerRef => [
                    'offer' => $offer,
                    'observed_at' => $created + 10,
                    'supplier_tour_program_id' => '2637',
                    'supplier_currency_id' => '1',
                ],
            ],
        ],
        'search' => ['offers' => [['offer_key' => $offerRef, 'kind' => 'concrete', 'hotel_external_id' => '8101']],],
    ],
    'additional_prices' => [
        $digest => ['status' => 'complete', 'evidence' => ['marker' => 'terminal-apd']],
    ],
];
$plan = [
    'requested_offers' => 1,
    'unique_contexts' => 1,
    'offers' => [['offer_ref' => $offerRef, 'local_hotel_id' => 3417, 'context_digest' => $digest]],
    'contexts' => [[
        'context_digest' => $digest,
        'supplier_tour_program_id' => '2637',
        'supplier_currency_id' => '1',
        'checkin' => '2026-10-05',
        'nights' => 7,
    ]],
];
$terminal = [$digest => ['status' => 'complete', 'cached' => false, 'evidence' => ['marker' => 'terminal-apd'], 'retryable' => false, 'retry_reason' => null]];

$apply = static function (array $evidence, array $rawOffer): array {
    if (($evidence['marker'] ?? null) !== 'terminal-apd') throw new RuntimeException('BAD_EVIDENCE');
    return [
        'application_state' => 'applied',
        'search_plus_additional' => ['amount' => '110000', 'currency' => 'RUB'],
        'rates' => ['adult' => ['amount' => '5000', 'currency' => 'RUB'], 'child' => null],
    ];
};
$resolver = static function (string $namespace, string $external): ?int {
    return $namespace === 'anex_online' && $external === '8101' ? 3417 : null;
};
$ingestCalls = [];
$ingest = static function (string $provider, array $searchParams, array $rows, DateTimeImmutable $at) use (&$ingestCalls): array {
    $ingestCalls[] = compact('provider', 'searchParams', 'rows', 'at');
    return ['provider' => $provider, 'offerCount' => count($rows), 'selectionAuthority' => false];
};

$result = AnyTourAnexOfferAutosaveV1::consume($db, $plan, $state, $terminal, $now, $apply, $resolver, $ingest);
$check($result['published'] === true && $result['readyOfferCount'] === 1, 'published-one-ready');
$check($result['accumulatedOfferCount'] === 1 && $result['selectionAuthority'] === false, 'bounded-non-authoritative');
$check(count($ingestCalls) === 1 && $ingestCalls[0]['provider'] === 'anex', 'ingest-once');
$row = $ingestCalls[0]['rows'][0] ?? null;
$check(is_array($row) && $row['anytour_hotel_id'] === 501, 'canonical-anytour-id');
$check(($row['dto']['finalPrice'] ?? null) === '110000' && ($row['dto']['price'] ?? null) === '110000', 'protected-final-price');
$check(($row['dto']['money']['search_price']['amount'] ?? null) === '100000'
    && ($row['dto']['money']['search_price_with_surcharge']['amount'] ?? null) === '110000', 'money-provenance');
$check(is_string($state['anytour_offer_autosave']['last_published_digest'] ?? null), 'publish-digest-retained');

$again = AnyTourAnexOfferAutosaveV1::consume($db, $plan, $state, $terminal, $now, $apply, $resolver, $ingest);
$check($again['published'] === false && $again['reason'] === 'already_published', 'idempotent-same-cohort');
$check(count($ingestCalls) === 1, 'no-duplicate-ingest');

$incompleteState = $state;
$incompleteState['anytour_offer_autosave']['last_published_digest'] = null;
$incomplete = AnyTourAnexOfferAutosaveV1::consume($db, $plan, $incompleteState,
    [$digest => ['status' => 'unknown', 'cached' => false, 'evidence' => null]], $now, $apply, $resolver, $ingest);
$check($incomplete['published'] === false && $incomplete['reason'] === 'batch_not_terminal', 'incomplete-preserves-old');
$check(count($ingestCalls) === 1, 'incomplete-no-ingest');

$mismatchState = $state;
$mismatchState['anytour_offer_autosave']['last_published_digest'] = null;
$mismatch = AnyTourAnexOfferAutosaveV1::consume($db, $plan, $mismatchState, $terminal, $now,
    static function (): array {
        return [
            'application_state' => 'applied',
            'search_plus_additional' => ['amount' => '109999', 'currency' => 'RUB'],
            'rates' => ['adult' => ['amount' => '5000', 'currency' => 'RUB'], 'child' => null],
        ];
    }, $resolver, $ingest);
$check($mismatch['published'] === false && $mismatch['reason'] === 'protected_price_mismatch', 'price-mismatch-fails-closed');
$check(count($ingestCalls) === 1, 'price-mismatch-no-ingest');

$identityState = $state;
$identityState['anytour_offer_autosave']['last_published_digest'] = null;
$identity = AnyTourAnexOfferAutosaveV1::consume($db, $plan, $identityState, $terminal, $now, $apply,
    static fn(string $namespace, string $external): ?int => 3418, $ingest);
$check($identity['published'] === false && $identity['reason'] === 'supplier_identity_changed', 'identity-revalidated');
$check(count($ingestCalls) === 1, 'identity-change-no-ingest');

$helperSource = file_get_contents($root . '/app/integrations/anex-anytour-offer-autosave.php');
$batchSource = file_get_contents($root . '/app/integrations/anex-additional-prices-batch.php');
$endpointSource = file_get_contents($root . '/v2/api-anex-search3-preview.php');
$check(is_string($helperSource) && !preg_match('/\b(?:curl_|fsockopen|stream_socket_client)\b/i', $helperSource), 'autosave-no-supplier-transport');
$check(is_string($batchSource) && str_contains($batchSource, 'anytour_anex_anytour_offer_autosave_runtime($plan, $state, $results);'), 'batch-runtime-hook');
$check(is_string($endpointSource) && str_contains($endpointSource, "require_once \$additionalBatchFile;"), 'endpoint-loads-hooked-batch');

echo 'ANEX AnyTour offer autosave: ' . $checks . " checks passed; supplier=0 booking=0 lead=0 mapping_write=0.\n";
