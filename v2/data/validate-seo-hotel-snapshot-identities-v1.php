<?php
/**
 * Compare review-only hotel-tour editorial identities with a fresh snapshot JSON.
 * Read-only CLI utility: no DB/API calls and no writes.
 *
 * Usage:
 *   php v2/data/validate-seo-hotel-snapshot-identities-v1.php \
 *     --catalog-file=v2/seo-content-pilot-maldives-catalog-v1.php \
 *     --catalog-function=v2_seo_content_pilot_maldives_catalog \
 *     [--only=65108,82538] [--require-all] < fresh-hotels.json
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function seo_identity_arg(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        $prefix = '--' . $name . '=';
        if (str_starts_with($arg, $prefix)) return trim(substr($arg, strlen($prefix)));
    }
    return null;
}

function seo_identity_flag(array $argv, string $name): bool
{
    return in_array('--' . $name, $argv, true);
}

function seo_identity_fail(string $message): never
{
    fwrite(STDERR, "SEO_HOTEL_IDENTITY_FAIL:" . $message . "\n");
    exit(1);
}

$catalogFile = seo_identity_arg($argv, 'catalog-file') ?: dirname(__DIR__) . '/seo-content-pilot-maldives-catalog-v1.php';
$catalogFunction = seo_identity_arg($argv, 'catalog-function') ?: 'v2_seo_content_pilot_maldives_catalog';
$onlyRaw = seo_identity_arg($argv, 'only');
$requireAll = seo_identity_flag($argv, 'require-all');

if (!is_file($catalogFile)) seo_identity_fail('catalog_file_missing');
require_once $catalogFile;
if (!function_exists($catalogFunction)) seo_identity_fail('catalog_function_missing');

$raw = stream_get_contents(STDIN);
if (!is_string($raw) || trim($raw) === '') seo_identity_fail('snapshot_json_missing');
$snapshot = json_decode($raw, true);
if (!is_array($snapshot) || !array_is_list($snapshot)) seo_identity_fail('snapshot_json_invalid');

$snapshotById = [];
$slugToId = [];
foreach ($snapshot as $row) {
    if (!is_array($row)) seo_identity_fail('snapshot_row_invalid');
    $hotelId = (int)($row['hotel_id'] ?? 0);
    $slug = trim((string)($row['hotel_slug'] ?? ''));
    if ($hotelId <= 0 || $slug === '') seo_identity_fail('snapshot_identity_missing');
    if (isset($snapshotById[$hotelId])) seo_identity_fail('snapshot_duplicate_hotel_id_' . $hotelId);
    if (isset($slugToId[$slug]) && $slugToId[$slug] !== $hotelId) seo_identity_fail('snapshot_duplicate_slug_' . $slug);
    $snapshotById[$hotelId] = $slug;
    $slugToId[$slug] = $hotelId;
}

$catalog = $catalogFunction();
$registry = is_array($catalog['registry'] ?? null) ? $catalog['registry'] : [];
$catalogById = [];
foreach ($registry as $path => $entry) {
    if (($entry['type'] ?? '') !== 'hotel_tours') continue;
    $page = is_array($entry['page'] ?? null) ? $entry['page'] : [];
    $state = is_array($page['search_state'] ?? null) ? $page['search_state'] : [];
    $hotelId = (int)($state['hotel'] ?? 0);
    if ($hotelId <= 0) seo_identity_fail('catalog_hotel_id_missing_' . $path);
    if (isset($catalogById[$hotelId])) seo_identity_fail('catalog_duplicate_hotel_id_' . $hotelId);
    if (!preg_match('~/hotel/([^/]+)/$~', (string)$path, $match)) seo_identity_fail('catalog_hotel_path_invalid_' . $hotelId);
    $catalogById[$hotelId] = ['slug' => $match[1], 'path' => (string)$path];
}

$only = [];
if ($onlyRaw !== null && $onlyRaw !== '') {
    foreach (explode(',', $onlyRaw) as $piece) {
        $id = filter_var(trim($piece), FILTER_VALIDATE_INT);
        if ($id === false || (int)$id <= 0) seo_identity_fail('only_hotel_id_invalid');
        $only[(int)$id] = true;
    }
}

$checked = 0;
$skipped = 0;
foreach ($catalogById as $hotelId => $record) {
    if ($only && !isset($only[$hotelId])) continue;
    if (!isset($snapshotById[$hotelId])) {
        if ($requireAll || isset($only[$hotelId])) seo_identity_fail('snapshot_missing_hotel_' . $hotelId);
        $skipped++;
        continue;
    }
    $snapshotSlug = $snapshotById[$hotelId];
    if ($record['slug'] !== $snapshotSlug) {
        seo_identity_fail('slug_mismatch_hotel_' . $hotelId . '_catalog_' . $record['slug'] . '_snapshot_' . $snapshotSlug);
    }
    $checked++;
}

if ($only) {
    foreach (array_keys($only) as $hotelId) {
        if (!isset($catalogById[$hotelId])) seo_identity_fail('catalog_missing_hotel_' . $hotelId);
        if (!isset($snapshotById[$hotelId])) seo_identity_fail('snapshot_missing_hotel_' . $hotelId);
    }
}
if ($requireAll && $checked !== count($catalogById)) seo_identity_fail('catalog_snapshot_not_complete');
if ($checked === 0) seo_identity_fail('nothing_checked');

echo 'SEO_HOTEL_IDENTITY_OK checked=' . $checked . ' skipped=' . $skipped . ' catalog=' . count($catalogById) . ' snapshot=' . count($snapshotById) . "\n";
