<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/v2/seo-offer-snapshot-v1.php';
require_once dirname(__DIR__) . '/v2/data/hot-tours-read-v1.php';

function freshness_check(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

// Deliberately disagree with the business timezone and cross Moscow midnight.
date_default_timezone_set('Pacific/Honolulu');
freshness_check(v2_offer_business_date(new DateTimeImmutable('2026-09-04T20:59:59Z')) === '2026-09-04', 'before midnight');
freshness_check(v2_offer_business_date(new DateTimeImmutable('2026-09-04T21:00:00Z')) === '2026-09-05', 'Moscow midnight');
freshness_check(v2_offer_business_date(new DateTimeImmutable('2026-12-31T21:00:00Z')) === '2027-01-01', 'year boundary');
foreach (['', '2026-09-04', '2026-02-30', '2027-02-29', '2026-13-01', '2026-9-06', '06.09.2026', '2026-09-06T12:00:00Z'] as $date) {
    freshness_check(!v2_offer_departure_is_current($date, '2026-09-05'), 'reject ' . $date);
}
foreach (['2026-09-05', '2026-09-06', '2028-02-29'] as $date) {
    freshness_check(v2_offer_departure_is_current($date, '2026-09-05'), 'keep ' . $date);
}

// Exercise the actual readers against an isolated in-memory DB, not live data.
putenv('ANYTOUR_DATA_DSN=sqlite::memory:');
putenv('ANYTOUR_DATA_DB_USER=fixture');
putenv('ANYTOUR_DATA_DB_PASSWORD=');
$pdo = v2_data_db();
$pdo->sqliteCreateFunction('NOW', static fn(): string => gmdate('Y-m-d H:i:s'));
$pdo->exec('CREATE TABLE catalog_departures (id INTEGER, name TEXT)');
$pdo->exec("INSERT INTO catalog_departures VALUES (1,'Москва')");
$pdo->exec('CREATE TABLE seo_offer_snapshots (departure_id INTEGER, country_id INTEGER, region_id INTEGER, hotel_id INTEGER, page_type TEXT, offers_json TEXT, observed_at TEXT, expires_at TEXT, offer_count INTEGER, currency TEXT, min_price REAL)');
$today = v2_offer_business_date();
$yesterday = (new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
$tomorrow = (new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d');
$fixture = [];
foreach ([$yesterday, '2099-02-30', $today, $tomorrow] as $i => $date) {
    $fixture[] = ['hotelId' => 99, 'price' => 100 + $i, 'departureDate' => $date, 'nights' => 7];
}
$stmt = $pdo->prepare('INSERT INTO seo_offer_snapshots VALUES (1,4,5,99,?,?,?, ?,4,\'RUB\',100)');
foreach (['country', 'resort', 'hotel'] as $type) {
    $stmt->execute([$type, json_encode($fixture), '2026-09-01 12:00:00', '9999-01-01']);
    $stmt->execute([$type, json_encode([['hotelId'=>99,'price'=>1,'departureDate'=>$tomorrow,'nights'=>7]]), '2026-09-01 12:00:00', '2000-01-01']);
}
foreach ([v2_seo_country_snapshot_offers(4, 1), v2_seo_resort_snapshot_offers(4, 5, 1), v2_seo_hotel_snapshot_offers(4, 99, 1)] as $offers) {
    freshness_check(count($offers) === 1, 'reader limit after filtering');
    freshness_check($offers[0]['departureDate'] === $today && $offers[0]['price'] === 102, 'stale cheap offer removed before limit');
    freshness_check($offers[0]['departureId'] === 1 && $offers[0]['departureName'] === 'Москва', 'city retained');
}
freshness_check(count(v2_seo_country_snapshot_offers(4, 6)) === 2, 'today and future retained');
$pdo->exec("UPDATE seo_offer_snapshots SET offers_json='[]'");
freshness_check(v2_seo_country_snapshot_offers(4) === [], 'empty snapshot');

$pdo->exec('CREATE TABLE hot_tours_current (snapshot_key TEXT, tour_id TEXT, departure_id INTEGER, departure_name TEXT, country_id INTEGER, country_name TEXT, region_id INTEGER, region_name TEXT, subregion_id INTEGER, subregion_name TEXT, hotel_id INTEGER, hotel_name TEXT, hotel_category INTEGER, hotel_rating REAL, picture_url TEXT, departure_date TEXT, nights INTEGER, meal_id INTEGER, meal_name TEXT, operator_id INTEGER, operator_name TEXT, price REAL, old_price REAL, currency TEXT, fetched_at TEXT, expires_at TEXT)');
$stmt = $pdo->prepare("INSERT INTO hot_tours_current (departure_id,country_id,hotel_id,departure_date,nights,price,fetched_at,expires_at) VALUES (1,4,99,?,7,?, '2026-09-01 12:00:00',?)");
$stmt->execute([$yesterday, 1, '9999-01-01']);
$stmt->execute([$today, 102, '9999-01-01']);
$stmt->execute([$tomorrow, 103, '9999-01-01']);
$stmt->execute([$tomorrow, 1, '2000-01-01']);
$offers = v2_data_hot_tours(['departureId'=>1, 'countryId'=>4, 'limit'=>1]);
freshness_check(count($offers) === 1 && $offers[0]['departure_date'] === $today && (float)$offers[0]['price'] === 102.0, 'hot dates, TTL and limit');
freshness_check(v2_data_hot_tours(['departureId'=>2]) === [], 'hot city filter preserved');
echo "OFFER_FRESHNESS_OK readers=country,resort,hotel,hot timezone=Europe/Moscow sameDay=preserved liveRequests=0\n";
