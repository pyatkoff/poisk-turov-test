<?php
/** Materialize fresh near-departure offers from first-party observations. No Tourvisor calls. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

function observed_hot_arg(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) return substr($arg, strlen($name) + 3);
    }
    return null;
}

function observed_hot_int(?string $value, int $fallback, int $min, int $max): int
{
    $parsed = filter_var($value, FILTER_VALIDATE_INT);
    if ($parsed === false) return $fallback;
    return max($min, min($max, (int)$parsed));
}

$freshHours = observed_hot_int(observed_hot_arg($argv, 'fresh-hours'), 6, 1, 24);
$departureDays = observed_hot_int(observed_hot_arg($argv, 'departure-days'), 21, 1, 45);
$limit = observed_hot_int(observed_hot_arg($argv, 'limit'), 500, 1, 1000);
$now = new DateTimeImmutable('now');
$expiresAt = $now->modify('+' . $freshHours . ' hours')->format('Y-m-d H:i:s');

$pdo = v2_data_db();
$sql = "WITH ranked AS (
    SELECT o.*,
           ROW_NUMBER() OVER (
             PARTITION BY o.departure_id,o.country_id,o.hotel_id,o.departure_date,o.nights,
                          COALESCE(o.meal_id,0),o.currency
             ORDER BY o.observed_at DESC,o.price ASC,o.id DESC
           ) AS rn
      FROM tour_price_observations o
     WHERE o.source='user_search'
       AND o.observed_at >= DATE_SUB(NOW(), INTERVAL :fresh_hours HOUR)
       AND o.departure_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :departure_days DAY)
       AND o.adults=2 AND o.children_count=0
       AND o.tour_id IS NOT NULL AND TRIM(o.tour_id)<>''
       AND o.price>0
)
SELECT r.*,
       d.name AS departure_name,
       COALESCE(c.name,h.country_name,'') AS country_name,
       COALESCE(cr.name,h.region_name,'') AS region_name,
       COALESCE(cs.name,h.subregion_name,'') AS subregion_name,
       h.name AS hotel_name,h.category AS hotel_category,h.rating AS hotel_rating
  FROM ranked r
  JOIN catalog_hotels h ON h.id=r.hotel_id AND h.is_active=1
  LEFT JOIN catalog_departures d ON d.id=r.departure_id
  LEFT JOIN catalog_countries c ON c.id=r.country_id
  LEFT JOIN catalog_regions cr ON cr.id=r.region_id
  LEFT JOIN catalog_subregions cs ON cs.id=r.subregion_id
 WHERE r.rn=1
 ORDER BY r.departure_date ASC,r.price ASC,r.observed_at DESC
 LIMIT {$limit}";

$select = $pdo->prepare($sql);
$insert = $pdo->prepare("INSERT INTO hot_tours_current (
    snapshot_key,tour_id,departure_id,departure_name,country_id,country_name,region_id,region_name,
    subregion_id,subregion_name,hotel_id,hotel_name,hotel_category,hotel_rating,picture_url,
    departure_date,nights,meal_id,meal_name,operator_id,operator_name,price,old_price,currency,fetched_at,expires_at
) VALUES (
    :snapshot_key,:tour_id,:departure_id,:departure_name,:country_id,:country_name,:region_id,:region_name,
    :subregion_id,:subregion_name,:hotel_id,:hotel_name,:hotel_category,:hotel_rating,NULL,
    :departure_date,:nights,:meal_id,NULL,:operator_id,NULL,:price,NULL,:currency,:fetched_at,:expires_at
) ON DUPLICATE KEY UPDATE
    departure_name=VALUES(departure_name),country_name=VALUES(country_name),region_id=VALUES(region_id),region_name=VALUES(region_name),
    subregion_id=VALUES(subregion_id),subregion_name=VALUES(subregion_name),hotel_name=VALUES(hotel_name),
    hotel_category=VALUES(hotel_category),hotel_rating=VALUES(hotel_rating),meal_id=VALUES(meal_id),
    operator_id=VALUES(operator_id),price=VALUES(price),currency=VALUES(currency),fetched_at=VALUES(fetched_at),expires_at=VALUES(expires_at)");

try {
    $select->bindValue(':fresh_hours', $freshHours, PDO::PARAM_INT);
    $select->bindValue(':departure_days', $departureDays, PDO::PARAM_INT);
    $select->execute();
    $rows = $select->fetchAll(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();
    $deleteExpired = $pdo->prepare('DELETE FROM hot_tours_current WHERE expires_at < :now_value');
    $deleteExpired->execute(['now_value' => $now->format('Y-m-d H:i:s')]);

    $written = 0;
    foreach ($rows as $row) {
        $tourId = trim((string)$row['tour_id']);
        $departureId = (int)$row['departure_id'];
        $countryId = (int)$row['country_id'];
        $hotelId = (int)$row['hotel_id'];
        $date = (string)$row['departure_date'];
        $nights = (int)$row['nights'];
        $price = (float)$row['price'];
        if ($tourId === '' || $departureId <= 0 || $countryId <= 0 || $hotelId <= 0 || $date === '' || $nights <= 0 || $price <= 0) continue;

        $snapshotKey = hash('sha256', implode('|', ['observed',$departureId,$countryId,$hotelId,$tourId,$date,$nights,(string)$row['currency']]));
        $observedAt = (string)$row['observed_at'];
        $rowExpires = (new DateTimeImmutable($observedAt))->modify('+' . $freshHours . ' hours');
        if ($rowExpires < $now) continue;

        $insert->execute([
            'snapshot_key' => $snapshotKey,
            'tour_id' => $tourId,
            'departure_id' => $departureId,
            'departure_name' => trim((string)($row['departure_name'] ?? '')),
            'country_id' => $countryId,
            'country_name' => trim((string)($row['country_name'] ?? '')),
            'region_id' => $row['region_id'] !== null ? (int)$row['region_id'] : null,
            'region_name' => trim((string)($row['region_name'] ?? '')) ?: null,
            'subregion_id' => $row['subregion_id'] !== null ? (int)$row['subregion_id'] : null,
            'subregion_name' => trim((string)($row['subregion_name'] ?? '')) ?: null,
            'hotel_id' => $hotelId,
            'hotel_name' => trim((string)$row['hotel_name']),
            'hotel_category' => $row['hotel_category'] !== null ? (int)$row['hotel_category'] : null,
            'hotel_rating' => $row['hotel_rating'] !== null ? (float)$row['hotel_rating'] : null,
            'departure_date' => $date,
            'nights' => $nights,
            'meal_id' => $row['meal_id'] !== null ? (int)$row['meal_id'] : null,
            'operator_id' => $row['operator_id'] !== null ? (int)$row['operator_id'] : null,
            'price' => $price,
            'currency' => strtoupper(trim((string)$row['currency'])) ?: 'RUB',
            'fetched_at' => $observedAt,
            'expires_at' => min($rowExpires, new DateTimeImmutable($expiresAt))->format('Y-m-d H:i:s'),
        ]);
        $written++;
    }

    $pdo->commit();
    fwrite(STDOUT, "OBSERVED_HOT_BUILD_OK candidates=" . count($rows) . " written={$written} fresh_hours={$freshHours} departure_days={$departureDays}\n");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'OBSERVED_HOT_BUILD_FAILED ' . mb_substr($e->getMessage(), 0, 1000) . "\n");
    exit(1);
}
