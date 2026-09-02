<?php
/**
 * Read-only aggregate inspector for currently unexpired month/resort-month SEO snapshots.
 * No Tourvisor calls, writes, prices, hotel names or offer payloads are emitted.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/db-v1.php';
require_once dirname(__DIR__) . '/seo-data-readiness-v1.php';

function data_readiness_arg(array $argv, string $name): ?string
{
    foreach ($argv as $arg) if (str_starts_with($arg, '--'.$name.'=')) return trim(substr($arg, strlen($name)+3));
    return null;
}
$raw = data_readiness_arg($argv, 'countries') ?? data_readiness_arg($argv, 'country') ?? '';
$countries = [];
foreach (preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $value) {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id !== false && (int)$id > 0) $countries[] = (int)$id;
}
$countries = array_values(array_unique($countries));
if ($countries === []) { fwrite(STDERR, "Usage: php v2/data/inspect-seo-data-readiness-v1.php --countries=1,4,8\n"); exit(2); }

$pdo = v2_data_db();
$placeholders = implode(',', array_fill(0, count($countries), '?'));
$sql = "SELECT s.country_id,COALESCE(c.name,'') AS country_name,s.page_type,
               COUNT(*) AS snapshot_count,
               COUNT(DISTINCT CONCAT_WS(':',s.departure_id,s.country_id,COALESCE(s.region_id,0),COALESCE(s.departure_year,0),COALESCE(s.departure_month,0))) AS identity_count,
               SUM(s.offer_count) AS offer_count,MIN(s.offer_count) AS min_offer_count,
               MAX(s.observed_at) AS newest_observed_at,MIN(s.observed_at) AS oldest_observed_at,
               MIN(s.expires_at) AS earliest_expires_at,MAX(s.expires_at) AS latest_expires_at,
               MIN(TIMESTAMPDIFF(SECOND,NOW(),s.expires_at)) AS min_freshness_seconds,
               MAX(TIMESTAMPDIFF(SECOND,NOW(),s.expires_at)) AS max_freshness_seconds,
               SUM(CASE WHEN s.expires_at > NOW() AND s.offer_count > 0 AND s.currency='RUB' THEN 1 ELSE 0 END) AS usable_snapshot_count
          FROM seo_offer_snapshots s
          LEFT JOIN catalog_countries c ON c.id=s.country_id
         WHERE s.country_id IN ($placeholders)
           AND s.page_type IN ('month','resort_month')
           AND s.expires_at > NOW()
         GROUP BY s.country_id,c.name,s.page_type
         ORDER BY s.country_id,s.page_type";
$stmt = $pdo->prepare($sql);
$stmt->execute($countries);
$out = v2_seo_data_readiness_summary($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $countries);
echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR), "\n";

$requireCountries = (int)(data_readiness_arg($argv, 'require-countries') ?? '0');
$requireSnapshots = (int)(data_readiness_arg($argv, 'require-snapshots') ?? '0');
if ($requireCountries > 0 && ($out['country_count'] ?? 0) < $requireCountries) {
    fwrite(STDERR, "SEO_DATA_READINESS_FAIL:require_countries expected={$requireCountries} actual=".($out['country_count'] ?? 0)."\n"); exit(3);
}
if ($requireSnapshots > 0 && ($out['usable_snapshot_count'] ?? 0) < $requireSnapshots) {
    fwrite(STDERR, "SEO_DATA_READINESS_FAIL:require_snapshots expected={$requireSnapshots} actual=".($out['usable_snapshot_count'] ?? 0)."\n"); exit(4);
}
