<?php
declare(strict_types=1);
// Bounded, read-only catalog audit. No supplier client, raw content or URLs leave the server.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(0);
ob_start();

function catalog_content_type(mixed $value): string
{
    if (is_array($value)) return $value === [] ? 'empty_array_or_object' : (array_is_list($value) ? 'array' : 'object');
    return get_debug_type($value);
}

function catalog_content_profile(array &$profile, string $path, mixed $value, int $hotelId, int $depth = 0): void
{
    if ($depth > 7) return;
    $key = $path . ':' . catalog_content_type($value);
    if (!isset($profile[$key]) && count($profile) >= 300) return;
    if (!isset($profile[$key])) $profile[$key] = [
        'path'=>$path, 'type'=>catalog_content_type($value), 'values'=>0,
        'http_values'=>0, 'https_values'=>0, 'protocol_relative_values'=>0,
        'example_hotel_ids'=>[],
    ];
    $bucket = &$profile[$key];
    $bucket['values']++;
    if (!in_array($hotelId, $bucket['example_hotel_ids'], true) && count($bucket['example_hotel_ids']) < 3) $bucket['example_hotel_ids'][] = $hotelId;
    if (is_string($value)) {
        $trimmed = trim($value);
        if (preg_match('~^https://~i', $trimmed)) $bucket['https_values']++;
        elseif (preg_match('~^http://~i', $trimmed)) $bucket['http_values']++;
        elseif (str_starts_with($trimmed, '//')) $bucket['protocol_relative_values']++;
    }
    unset($bucket);
    if (!is_array($value)) return;
    foreach (array_slice($value, 0, 200, true) as $field=>$child) {
        $segment = is_int($field) ? '[]' : (preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,39}$/D', (string)$field) ? '.' . $field : '.[field]');
        catalog_content_profile($profile, $path . $segment, $child, $hotelId, $depth + 1);
    }
}

function catalog_content_find_media(array &$profile, array $value, int $hotelId, string $path = '$', int $depth = 0): void
{
    if ($depth > 5) return;
    foreach (array_slice($value, 0, 200, true) as $field=>$child) {
        $segment = is_int($field) ? '[]' : (preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,39}$/D', (string)$field) ? '.' . $field : '.[field]');
        if (is_string($field) && preg_match('/image|photo|picture|gallery/i', $field)) {
            catalog_content_profile($profile, $path . $segment, $child, $hotelId);
        } elseif (is_array($child)) {
            catalog_content_find_media($profile, $child, $hotelId, $path . $segment, $depth + 1);
        }
    }
}

$phase = 'root';
$result = ['status'=>'source_error','reason'=>'catalog_content_audit_unavailable'];
try {
    $root = realpath((string)getenv('HOME') . '/www/anytoour.ru');
    if (!$root || realpath((string)getcwd()) !== $root) throw new RuntimeException();
    $phase = 'input';
    $input = json_decode((string)file_get_contents('php://stdin', false, null, 0, 1025), true);
    if (!is_array($input)) throw new RuntimeException();
    $rawLimit = $input['raw_limit'] ?? 2000;
    if (!is_int($rawLimit) || $rawLimit < 1 || $rawLimit > 2000) throw new RuntimeException();
    $phase = 'configuration';
    require_once (is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php');
    $phase = 'database';
    $pdo = v2_data_db();
    $pdo->exec('START TRANSACTION READ ONLY');

    $phase = 'catalog_totals';
    $catalog = $pdo->query("SELECT COUNT(*) AS hotels, SUM(is_active=1) AS active_hotels,
        SUM(primary_image_url IS NOT NULL AND TRIM(primary_image_url)<>'') AS hotels_with_primary_image
        FROM catalog_hotels")->fetch(PDO::FETCH_ASSOC);
    $phase = 'country_coverage';
    $countries = $pdo->query("SELECT c.id AS country_id,c.name AS country_name,c.is_active AS country_active,
        s.status AS sync_status,s.finished_at AS sync_finished_at,s.rows_seen,
        COALESCE(h.hotel_count,0) AS hotel_count,COALESCE(h.active_hotels,0) AS active_hotels,
        COALESCE(dc.departure_count,0) AS active_departure_count
        FROM catalog_countries c
        LEFT JOIN catalog_sync_state s ON s.sync_key=CONCAT('hotels:country:',c.id)
        LEFT JOIN (SELECT country_id,COUNT(*) AS hotel_count,SUM(is_active=1) AS active_hotels
            FROM catalog_hotels GROUP BY country_id) h ON h.country_id=c.id
        LEFT JOIN (SELECT country_id,COUNT(DISTINCT departure_id) AS departure_count
            FROM catalog_departure_countries WHERE is_active=1 GROUP BY country_id) dc ON dc.country_id=c.id
        ORDER BY c.id LIMIT 1001")->fetchAll(PDO::FETCH_ASSOC);
    if (count($countries) > 1000) throw new RuntimeException();
    $coverage = ['countries'=>count($countries),'missing_sync'=>0,'failed_sync'=>0,'running_sync'=>0,
        'success_sync'=>0,'successful_rows_seen_mismatch'=>0,'active_route_countries_missing_success'=>0];
    foreach ($countries as &$country) {
        foreach (['country_id','country_active','hotel_count','active_hotels','active_departure_count'] as $field) $country[$field] = (int)$country[$field];
        if ($country['rows_seen'] !== null) $country['rows_seen'] = (int)$country['rows_seen'];
        $state = $country['sync_status'];
        if ($state === null) $coverage['missing_sync']++;
        elseif ($state === 'failure') $coverage['failed_sync']++;
        elseif ($state === 'running') $coverage['running_sync']++;
        elseif ($state === 'success') $coverage['success_sync']++;
        else $country['sync_status'] = 'other';
        $country['successful_rows_seen_mismatch'] = $state === 'success' && $country['rows_seen'] !== $country['hotel_count'];
        if ($country['successful_rows_seen_mismatch']) $coverage['successful_rows_seen_mismatch']++;
        if ($country['country_active'] === 1 && $country['active_departure_count'] > 0 && $state !== 'success') $coverage['active_route_countries_missing_success']++;
    }
    unset($country);

    $phase = 'details_totals';
    $details = $pdo->query("SELECT COUNT(*) AS rows_total,SUM(status='success') AS successful_rows,
        SUM(status='success' AND description IS NOT NULL AND TRIM(description)<>'') AS successful_descriptions,
        SUM(status='success' AND primary_image_url IS NOT NULL AND TRIM(primary_image_url)<>'') AS successful_primary_images,
        SUM(status='success' AND raw_json IS NOT NULL AND TRIM(raw_json)<>'') AS successful_raw_rows,
        SUM(status='success' AND OCTET_LENGTH(raw_json)>2097152) AS raw_rows_over_byte_limit
        FROM catalog_hotel_details")->fetch(PDO::FETCH_ASSOC);
    foreach ($catalog as &$count) $count = (int)$count;
    unset($count);
    foreach ($details as &$count) $count = (int)$count;
    unset($count);

    $phase = 'raw_media_profile';
    $query = $pdo->query("SELECT hotel_id,raw_json FROM catalog_hotel_details
        WHERE status='success' AND raw_json IS NOT NULL AND OCTET_LENGTH(raw_json)<=2097152
        ORDER BY fetched_at DESC,hotel_id ASC LIMIT {$rawLimit}");
    $profile = [];
    $raw = ['limit'=>$rawLimit,'rows_examined'=>0,'invalid_json'=>0,'images_field_missing'=>0,
        'images_field_types'=>[],'normalizer_compatible_https_strings'=>0,'rows_with_compatible_images'=>0];
    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $raw['rows_examined']++;
        $hotel = json_decode((string)$row['raw_json'], true);
        if (!is_array($hotel)) { $raw['invalid_json']++; continue; }
        if (!array_key_exists('images', $hotel)) $raw['images_field_missing']++;
        else {
            $type = catalog_content_type($hotel['images']);
            $raw['images_field_types'][$type] = ($raw['images_field_types'][$type] ?? 0) + 1;
        }
        $accepted = [];
        foreach ((array)($hotel['images'] ?? []) as $image) {
            if (!is_scalar($image)) continue;
            $url = trim((string)$image);
            $parts = strlen($url) <= 2048 ? parse_url($url) : false;
            if (is_array($parts) && strtolower((string)($parts['scheme'] ?? '')) === 'https' && trim((string)($parts['host'] ?? '')) !== '') $accepted[$url] = true;
            if (count($accepted) >= 100) break;
        }
        $raw['normalizer_compatible_https_strings'] += count($accepted);
        if ($accepted !== []) $raw['rows_with_compatible_images']++;
        catalog_content_find_media($profile, $hotel, (int)$row['hotel_id']);
    }
    ksort($profile);
    ksort($raw['images_field_types']);
    $raw['media_fields'] = array_values($profile);
    $raw['scan_bounds'] = ['raw_bytes_per_row'=>2097152,'children_per_node'=>200,'profile_buckets'=>300,'profile_depth'=>7,'media_discovery_depth'=>5];
    $pdo->rollBack();
    $result = ['status'=>'ok','generated_at_utc'=>gmdate('c'),'supplier_requests'=>0,'database_writes'=>0,
        'catalog'=>$catalog,'coverage'=>$coverage,'countries'=>$countries,'details'=>$details,'raw_media_profile'=>$raw,
        'coverage_limit'=>'Stored sync history only; row-count equality is not a current supplier ID-set reconciliation.'];
} catch (Throwable $ignored) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $result = ['status'=>'source_error','reason'=>'catalog_content_audit_unavailable','phase'=>$phase];
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
