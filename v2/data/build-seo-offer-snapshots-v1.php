<?php
/** Materialize fresh SEO/feed-ready offer snapshots from first-party price observations. No Tourvisor calls. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

function seo_snapshot_arg(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) return substr($arg, strlen($name) + 3);
    }
    return null;
}

function seo_snapshot_int(?string $value, int $fallback, int $min, int $max): int
{
    $parsed = filter_var($value, FILTER_VALIDATE_INT);
    if ($parsed === false) return $fallback;
    return max($min, min($max, (int)$parsed));
}

function seo_snapshot_offer(array $row): array
{
    return [
        'tourId' => (string)($row['tour_id'] ?? ''),
        'hotelId' => (int)$row['hotel_id'],
        'hotelName' => (string)$row['hotel_name'],
        'hotelCategory' => $row['hotel_category'] !== null ? (int)$row['hotel_category'] : null,
        'regionId' => $row['region_id'] !== null ? (int)$row['region_id'] : null,
        'regionName' => (string)($row['region_name'] ?? ''),
        'departureDate' => (string)$row['departure_date'],
        'nights' => (int)$row['nights'],
        'mealId' => $row['meal_id'] !== null ? (int)$row['meal_id'] : null,
        'operatorId' => $row['operator_id'] !== null ? (int)$row['operator_id'] : null,
        'price' => (float)$row['price'],
        'currency' => (string)$row['currency'],
        'observedAt' => (string)$row['observed_at'],
    ];
}

function seo_snapshot_add(array &$groups, string $key, string $type, array $dims, array $row, int $maxOffers): void
{
    if (!isset($groups[$key])) {
        $groups[$key] = [
            'page_key' => $key,
            'page_type' => $type,
            'dimensions' => $dims,
            'offers' => [],
            'seen_hotels' => [],
            'min_price' => null,
            'currency' => (string)$row['currency'],
            'observed_at' => (string)$row['observed_at'],
        ];
    }

    $g =& $groups[$key];
    $price = (float)$row['price'];
    if ($g['min_price'] === null || $price < $g['min_price']) $g['min_price'] = $price;
    if ((string)$row['observed_at'] > $g['observed_at']) $g['observed_at'] = (string)$row['observed_at'];

    $hotelId = (int)$row['hotel_id'];
    if (isset($g['seen_hotels'][$hotelId]) || count($g['offers']) >= $maxOffers) return;
    $g['offers'][] = seo_snapshot_offer($row);
    $g['seen_hotels'][$hotelId] = true;
}

$freshHours = seo_snapshot_int(seo_snapshot_arg($argv, 'fresh-hours'), 48, 1, 168);
$expiresHours = seo_snapshot_int(seo_snapshot_arg($argv, 'expires-hours'), 8, 1, 48);
$maxOffers = seo_snapshot_int(seo_snapshot_arg($argv, 'offers-per-page'), 10, 1, 30);
$rowLimit = seo_snapshot_int(seo_snapshot_arg($argv, 'row-limit'), 100000, 1000, 250000);

$pdo = v2_data_db();
$sql = "WITH ranked AS (
    SELECT o.*,
           ROW_NUMBER() OVER (
             PARTITION BY o.departure_id,o.hotel_id,o.departure_date,o.nights,COALESCE(o.meal_id,0),o.currency
             ORDER BY o.observed_at DESC,o.price ASC,o.id DESC
           ) AS rn
      FROM tour_price_observations o
     WHERE o.observed_at >= DATE_SUB(NOW(), INTERVAL :fresh_hours HOUR)
       AND o.departure_date >= CURDATE()
       AND o.adults=2 AND o.children_count=0
       AND o.price>0
       AND o.currency='RUB'
)
SELECT r.*,h.name AS hotel_name,h.category AS hotel_category,
       COALESCE(c.slug,CAST(r.country_id AS CHAR)) AS country_slug,
       COALESCE(c.name,h.country_name,'') AS country_name,
       COALESCE(cr.slug,CAST(r.region_id AS CHAR)) AS region_slug,
       COALESCE(cr.name,h.region_name,'') AS region_name
  FROM ranked r
  JOIN catalog_hotels h ON h.id=r.hotel_id AND h.is_active=1
  LEFT JOIN catalog_countries c ON c.id=r.country_id
  LEFT JOIN catalog_regions cr ON cr.id=r.region_id
 WHERE r.rn=1
 ORDER BY r.price ASC,r.observed_at DESC
 LIMIT {$rowLimit}";

$select = $pdo->prepare($sql);
$select->bindValue(':fresh_hours', $freshHours, PDO::PARAM_INT);
$select->execute();
$rows = $select->fetchAll(PDO::FETCH_ASSOC) ?: [];

$groups = [];
foreach ($rows as $row) {
    $departureId = (int)$row['departure_id'];
    $countryId = (int)$row['country_id'];
    $regionId = $row['region_id'] !== null ? (int)$row['region_id'] : null;
    $hotelId = (int)$row['hotel_id'];
    $year = (int)$row['departure_year'];
    $month = (int)$row['departure_month'];
    if ($departureId <= 0 || $countryId <= 0 || $hotelId <= 0 || $year <= 0 || $month <= 0) continue;

    $monthKey = sprintf('%04d-%02d', $year, $month);
    $baseDims = [
        'departureId' => $departureId,
        'countryId' => $countryId,
        'countryName' => (string)$row['country_name'],
        'countrySlug' => (string)$row['country_slug'],
    ];

    seo_snapshot_add($groups, "country:{$departureId}:{$countryId}", 'country', $baseDims, $row, $maxOffers);
    seo_snapshot_add($groups, "month:{$departureId}:{$countryId}:{$monthKey}", 'month', $baseDims + ['year'=>$year,'month'=>$month,'monthKey'=>$monthKey], $row, $maxOffers);
    seo_snapshot_add($groups, "hotel:{$departureId}:{$hotelId}", 'hotel', $baseDims + ['hotelId'=>$hotelId,'hotelName'=>(string)$row['hotel_name']], $row, $maxOffers);

    if ($regionId !== null && $regionId > 0) {
        $regionDims = $baseDims + [
            'regionId' => $regionId,
            'regionName' => (string)$row['region_name'],
            'regionSlug' => (string)$row['region_slug'],
        ];
        seo_snapshot_add($groups, "resort:{$departureId}:{$countryId}:{$regionId}", 'resort', $regionDims, $row, $maxOffers);
        seo_snapshot_add($groups, "resort_month:{$departureId}:{$countryId}:{$regionId}:{$monthKey}", 'resort_month', $regionDims + ['year'=>$year,'month'=>$month,'monthKey'=>$monthKey], $row, $maxOffers);
    }
}

$insert = $pdo->prepare("INSERT INTO seo_offer_snapshots (
    page_key,page_type,country_id,region_id,hotel_id,departure_id,departure_year,departure_month,month_start,
    dimensions_json,offers_json,min_price,currency,offer_count,observed_at,expires_at
) VALUES (
    :page_key,:page_type,:country_id,:region_id,:hotel_id,:departure_id,:departure_year,:departure_month,:month_start,
    :dimensions_json,:offers_json,:min_price,:currency,:offer_count,:observed_at,:expires_at
) ON DUPLICATE KEY UPDATE
    page_type=VALUES(page_type),country_id=VALUES(country_id),region_id=VALUES(region_id),hotel_id=VALUES(hotel_id),
    departure_id=VALUES(departure_id),departure_year=VALUES(departure_year),departure_month=VALUES(departure_month),
    month_start=VALUES(month_start),dimensions_json=VALUES(dimensions_json),offers_json=VALUES(offers_json),
    min_price=VALUES(min_price),currency=VALUES(currency),offer_count=VALUES(offer_count),
    observed_at=VALUES(observed_at),expires_at=VALUES(expires_at)");

$expiresAt = (new DateTimeImmutable('now'))->modify('+' . $expiresHours . ' hours')->format('Y-m-d H:i:s');
$written = 0;
$pdo->beginTransaction();
try {
    foreach ($groups as $g) {
        usort($g['offers'], static fn(array $a, array $b): int => $a['price'] <=> $b['price']);
        $d = $g['dimensions'];
        $year = isset($d['year']) ? (int)$d['year'] : null;
        $month = isset($d['month']) ? (int)$d['month'] : null;
        $insert->execute([
            'page_key' => $g['page_key'],
            'page_type' => $g['page_type'],
            'country_id' => isset($d['countryId']) ? (int)$d['countryId'] : null,
            'region_id' => isset($d['regionId']) ? (int)$d['regionId'] : null,
            'hotel_id' => isset($d['hotelId']) ? (int)$d['hotelId'] : null,
            'departure_id' => isset($d['departureId']) ? (int)$d['departureId'] : null,
            'departure_year' => $year,
            'departure_month' => $month,
            'month_start' => ($year && $month) ? sprintf('%04d-%02d-01', $year, $month) : null,
            'dimensions_json' => json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'offers_json' => json_encode($g['offers'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'min_price' => $g['min_price'],
            'currency' => $g['currency'],
            'offer_count' => count($g['offers']),
            'observed_at' => $g['observed_at'],
            'expires_at' => $expiresAt,
        ]);
        $written++;
    }
    $pdo->prepare('DELETE FROM seo_offer_snapshots WHERE expires_at < :now_value')->execute(['now_value'=>(new DateTimeImmutable('now'))->format('Y-m-d H:i:s')]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'SEO_OFFER_SNAPSHOTS_FAILED ' . mb_substr($e->getMessage(), 0, 1000) . "\n");
    exit(1);
}

echo "SEO_OFFER_SNAPSHOTS_OK rows=" . count($rows) . " snapshots={$written} fresh_hours={$freshHours} expires_hours={$expiresHours}\n";
