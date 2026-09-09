<?php
/** Read-only inspection of fresh materialized SEO offer snapshots. No Tourvisor calls or writes. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

function snapshot_inspect_arg(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) return trim(substr($arg, strlen($name) + 3));
    }
    return null;
}

$raw = snapshot_inspect_arg($argv, 'countries') ?? snapshot_inspect_arg($argv, 'country') ?? '';
$countries = [];
foreach (preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $value) {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id !== false && (int)$id > 0) $countries[] = (int)$id;
}
$countries = array_values(array_unique($countries));
if ($countries === []) {
    fwrite(STDERR, "Usage: php v2/data/inspect-seo-offer-snapshots-v1.php --countries=12,8\n");
    exit(2);
}

$pdo = v2_data_db();
$placeholders = implode(',', array_fill(0, count($countries), '?'));
$sql = "SELECT s.country_id,COALESCE(c.name,'') AS country_name,s.page_type,s.region_id,
               COALESCE(r.name,'') AS region_name,COUNT(*) AS snapshot_count,
               SUM(s.offer_count) AS offer_count,MIN(s.min_price) AS min_price,
               MAX(s.observed_at) AS observed_at,MAX(s.expires_at) AS expires_at
          FROM seo_offer_snapshots s
          LEFT JOIN catalog_countries c ON c.id=s.country_id
          LEFT JOIN catalog_regions r ON r.id=s.region_id
         WHERE s.country_id IN ($placeholders)
           AND s.expires_at >= NOW()
           AND s.offer_count > 0
           AND s.min_price > 0
           AND s.currency='RUB'
           AND s.page_type IN ('country','resort')
         GROUP BY s.country_id,c.name,s.page_type,s.region_id,r.name
         ORDER BY s.country_id,s.page_type,s.region_id";
$stmt = $pdo->prepare($sql);
$stmt->execute($countries);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($rows as &$row) {
    $row['country_id'] = (int)$row['country_id'];
    $row['region_id'] = $row['region_id'] !== null ? (int)$row['region_id'] : null;
    $row['snapshot_count'] = (int)$row['snapshot_count'];
    $row['offer_count'] = (int)$row['offer_count'];
    $row['min_price'] = (float)$row['min_price'];
}
unset($row);

echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
