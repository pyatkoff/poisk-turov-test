<?php
declare(strict_types=1);

require_once __DIR__ . '/../v2/data/anytour-offer-price-history-v1.php';

function price_history_check(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "ANYTOUR_OFFER_PRICE_HISTORY_FAILED {$message}\n");
        exit(1);
    }
}

$dsn = getenv('TEST_MYSQL_DSN') ?: '';
$user = getenv('TEST_MYSQL_USER') ?: 'root';
$pass = getenv('TEST_MYSQL_PASSWORD') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "ANYTOUR_OFFER_PRICE_HISTORY_FAILED missing TEST_MYSQL_DSN\n");
    exit(1);
}

$db = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$db->exec('DROP TABLE IF EXISTS anytour_offer_price_observations');
$db->exec('DROP TABLE IF EXISTS anytour_hotels');
$db->exec('CREATE TABLE anytour_hotels (id BIGINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
$db->exec((string)file_get_contents(__DIR__ . '/../v2/data/migrations/20260923-anytour-offer-price-history-v1.sql'));
$db->exec('INSERT INTO anytour_hotels(id) VALUES (42)');

price_history_check(AnyTourOfferPriceHistoryV1::installed($db), 'history table installed');

$columns = $db->query('SHOW COLUMNS FROM anytour_offer_price_observations')->fetchAll(PDO::FETCH_COLUMN);
foreach ([
    'observation_sha256','provider','anytour_hotel_id','provider_local_hotel_id',
    'offer_ref_digest','consumer_segment_sha256','departure_id','country_id',
    'checkin','display_price','final_price_ready','final_price_verified','observed_at'
] as $column) {
    price_history_check(in_array($column, $columns, true), 'missing column ' . $column);
}

$scope = [
    'departureId' => '1',
    'countryId' => '4',
    'dateFrom' => '2026-10-20',
    'dateTo' => '2026-10-20',
    'nightsFrom' => 7,
    'nightsTo' => 7,
    'adults' => 2,
    'childs' => [],
    'meal' => '',
    'hotelCategory' => '',
    'hotelRating' => '',
    'hotelTypes' => [],
    'hotelIds' => [],
    'hotelServices' => [],
    'arrivalId' => '',
    'regionIds' => [],
    'subregionIds' => [],
    'operatorIds' => [],
    'priceFrom' => '',
    'priceTo' => '',
    'currency' => 'RUB',
    'onlyCharter' => false,
    'onlyDirect' => false,
];

function history_dto(
    string $provider,
    int $providerHotelId,
    string $operator,
    string $offerKey,
    string $price,
    string $observedAt,
    string $room = 'standard sea view'
): array {
    return [
        'provider' => $provider,
        'operator' => [
            'raw' => $operator,
            'canonical_name' => $operator,
            'canonical_verified' => false,
            'identity_source' => 'test',
            'filter_status' => 'unknown',
            'cross_provider_equivalence_verified' => false,
            'supplier_code_exposed' => false,
        ],
        'local_hotel_id' => $providerHotelId,
        'identity' => [
            'search_ref_digest' => hash('sha256', $provider . ':search'),
            'offer_ref_digest' => hash('sha256', $provider . ':' . $offerKey),
            'provider_hotel_ref_digest' => hash('sha256', $provider . ':hotel:' . $providerHotelId),
        ],
        'tour' => [
            'checkin' => '2026-10-20',
            'nights' => 7,
            'party' => ['adults' => 2, 'children' => 0, 'child_ages' => []],
            'meal' => [
                'raw' => 'All Inclusive',
                'family' => 'ai',
                'qualifiers' => ['plus' => false, 'without_alcohol' => false],
                'family_verified' => true,
            ],
            'room' => ['raw' => strtoupper($room), 'normalized' => $room],
            'placement' => null,
            'availability' => [],
            'flight_details' => [],
            'observed_at' => $observedAt,
        ],
        'price' => $price,
        'currency' => 'RUB',
        'finalPriceReady' => true,
        'final_price_verified' => false,
    ];
}

$anex = history_dto('anex', 7001, 'ANEX', 'offer-a', '240000', '2026-09-23T09:00:00Z');
$funsun = history_dto('andromeda', 315001, 'FUN&SUN', 'offer-b', '220000', '2026-09-23T09:05:00Z');

$consumerAnex = AnyTourOfferPriceHistoryV1::consumerSegmentDigest($scope, 42, $anex);
$consumerFunsun = AnyTourOfferPriceHistoryV1::consumerSegmentDigest($scope, 42, $funsun);
price_history_check($consumerAnex === $consumerFunsun, 'provider/operator must not split consumer segment');

$differentOperator = $anex;
$differentOperator['operator']['raw'] = 'Other Operator';
$differentOperator['operator']['canonical_name'] = 'Other Operator';
price_history_check(
    $consumerAnex === AnyTourOfferPriceHistoryV1::consumerSegmentDigest($scope, 42, $differentOperator),
    'operator must not affect consumer segment'
);

$differentRoom = history_dto('anex', 7001, 'ANEX', 'offer-c', '240000', '2026-09-23T09:00:00Z', 'garden view');
price_history_check(
    $consumerAnex !== AnyTourOfferPriceHistoryV1::consumerSegmentDigest($scope, 42, $differentRoom),
    'room must affect consumer segment'
);

$recorded = new DateTimeImmutable('2026-09-23T09:10:00Z');
$receipt = AnyTourOfferPriceHistoryV1::recordIfInstalled($db, $scope, [
    ['anytour_hotel_id' => 42, 'dto' => $anex],
    ['anytour_hotel_id' => 42, 'dto' => $funsun],
], $recorded);
price_history_check(($receipt['installed'] ?? false) === true, 'installed receipt');
price_history_check(($receipt['written'] ?? -1) === 2, 'first snapshot writes two observations');
price_history_check(($receipt['duplicates'] ?? -1) === 0, 'first snapshot no duplicates');

$repeat = AnyTourOfferPriceHistoryV1::recordIfInstalled($db, $scope, [
    ['anytour_hotel_id' => 42, 'dto' => $anex],
    ['anytour_hotel_id' => 42, 'dto' => $funsun],
], $recorded->modify('+1 minute'));
price_history_check(($repeat['written'] ?? -1) === 0, 'same supplier observations deduplicate');
price_history_check(($repeat['duplicates'] ?? -1) === 2, 'duplicate count');

$anexLater = history_dto('anex', 7001, 'ANEX', 'offer-a', '195000', '2026-09-23T15:00:00Z');
$later = AnyTourOfferPriceHistoryV1::recordIfInstalled(
    $db,
    $scope,
    [['anytour_hotel_id' => 42, 'dto' => $anexLater]],
    new DateTimeImmutable('2026-09-23T15:01:00Z')
);
price_history_check(($later['written'] ?? -1) === 1, 'later price creates new observation');

$total = (int)$db->query('SELECT COUNT(*) FROM anytour_offer_price_observations')->fetchColumn();
price_history_check($total === 3, 'append-only row count');

$stmt = $db->prepare('SELECT COUNT(*) FROM anytour_offer_price_observations WHERE consumer_segment_sha256=?');
$stmt->execute([$consumerAnex]);
price_history_check((int)$stmt->fetchColumn() === 3, 'cross-provider rows share consumer series');

$offerDigest = hash('sha256', 'anex:offer-a');
$stmt = $db->prepare("SELECT COUNT(*) FROM anytour_offer_price_observations WHERE provider='anex' AND offer_ref_digest=?");
$stmt->execute([$offerDigest]);
price_history_check((int)$stmt->fetchColumn() === 2, 'exact offer series keeps two price points');

$prices = $db->query('SELECT display_price FROM anytour_offer_price_observations ORDER BY observed_at')->fetchAll(PDO::FETCH_COLUMN);
price_history_check(array_map('floatval', $prices) === [240000.0, 220000.0, 195000.0], 'prices retained exactly');

fwrite(STDOUT, "ANYTOUR_OFFER_PRICE_HISTORY_OK rows=3 consumer_series=3 exact_offer_points=2\n");
