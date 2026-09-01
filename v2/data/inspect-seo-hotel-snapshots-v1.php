<?php
/** Read-only inspection of fresh hotel SEO snapshots. No Tourvisor calls or writes. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

function hotel_snapshot_arg(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) return trim(substr($arg, strlen($name) + 3));
    }
    return null;
}

$country = filter_var(hotel_snapshot_arg($argv, 'country') ?? '', FILTER_VALIDATE_INT);
$limit = filter_var(hotel_snapshot_arg($argv, 'limit') ?? '20', FILTER_VALIDATE_INT);
if ($country === false || (int)$country <= 0) {
    fwrite(STDERR, "Usage: php v2/data/inspect-seo-hotel-snapshots-v1.php --country=8 [--limit=20]\n");
    exit(2);
}
$country = (int)$country;
$limit = $limit === false ? 20 : max(1, min(100, (int)$limit));

$pdo = v2_data_db();
$sql = "SELECT s.hotel_id,h.name AS hotel_name,h.slug AS hotel_slug,h.category,h.rating,
               h.region_id,h.region_name,h.subregion_id,h.subregion_name,
               COUNT(*) AS snapshot_count,SUM(s.offer_count) AS offer_count,
               MIN(s.min_price) AS min_price,MAX(s.observed_at) AS observed_at,MAX(s.expires_at) AS expires_at
          FROM seo_offer_snapshots s
          JOIN catalog_hotels h ON h.id=s.hotel_id AND h.is_active=1
         WHERE s.country_id=:country_id
           AND s.page_type='hotel'
           AND s.expires_at>=NOW()
           AND s.offer_count>0
           AND s.min_price>0
           AND s.currency='RUB'
         GROUP BY s.hotel_id,h.name,h.slug,h.category,h.rating,h.region_id,h.region_name,h.subregion_id,h.subregion_name
         ORDER BY offer_count DESC,min_price ASC,hotel_name ASC
         LIMIT {$limit}";
$stmt = $pdo->prepare($sql);
$stmt->execute(['country_id'=>$country]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($rows as &$row) {
    $row['hotel_id'] = (int)$row['hotel_id'];
    $row['category'] = $row['category'] !== null ? (int)$row['category'] : null;
    $row['rating'] = $row['rating'] !== null ? (float)$row['rating'] : null;
    $row['region_id'] = $row['region_id'] !== null ? (int)$row['region_id'] : null;
    $row['subregion_id'] = $row['subregion_id'] !== null ? (int)$row['subregion_id'] : null;
    $row['snapshot_count'] = (int)$row['snapshot_count'];
    $row['offer_count'] = (int)$row['offer_count'];
    $row['min_price'] = (float)$row['min_price'];
}
unset($row);

echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
