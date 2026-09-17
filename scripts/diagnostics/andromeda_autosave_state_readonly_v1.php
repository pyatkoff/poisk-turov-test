<?php
declare(strict_types=1);

/**
 * Read-only acceptance for the published Andromeda/SAMO -> AnyTour autosave path.
 * Local filesystem reads + SQL SELECT only. No supplier transport, no filesystem write,
 * no mapping mutation, no booking/lead action.
 */

const ANYTOUR_ANDROMEDA_AUTOSAVE_PUBLISHED_AT = 1789658123; // 2026-09-17T15:15:23Z
const ANYTOUR_ANDROMEDA_AUTOSAVE_SOURCE_SHA = 'ebc508e31e5294d0e0aa8733dbe271e68efa9502';
const ANYTOUR_ANDROMEDA_AUTOSAVE_RUNTIME_HASHES = [
    'app/integrations/andromeda-anytour-offer-autosave.php' => '3993506f75f3f91de33913edff2c3d216c08004a9c81a25d1d7e2c8ee38e0845',
    'app/integrations/three-provider-money-facts.php' => '355b746702bdbc1cb1134c149b3286106ac9b5ddf1c342571663b525f91ddc45',
    'api-andromeda-search3-preview.php' => 'e80d4ad490cc39f9d389ee7e3ad08259f1aa030c23d396d0a31d64a0814eaa0d',
];

function at_readonly_fail(string $reason): never
{
    fwrite(STDERR, 'ANDROMEDA_AUTOSAVE_READONLY_FAILED ' . preg_replace('/[^A-Za-z0-9_.:-]+/', '_', $reason) . "\n");
    exit(2);
}

function at_readonly_json(string $path, int $maxBytes): ?array
{
    if (is_link($path) || !is_file($path)) return null;
    $size = filesize($path);
    if (!is_int($size) || $size < 2 || $size > $maxBytes) return null;
    try {
        $value = json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        return is_array($value) ? $value : null;
    } catch (Throwable $ignored) {
        return null;
    }
}

function at_readonly_operator_owned(string $raw): bool
{
    $value = str_replace(['Ё', 'ё'], 'е', trim($raw));
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $compact = preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
    if ($compact === '') return false;
    foreach (['anex', 'анекс', 'pegas', 'пегас', 'coral', 'корал', 'sunmar', 'санмар'] as $elsewhere) {
        if (str_contains($compact, $elsewhere)) return false;
    }
    return true;
}

function at_readonly_snapshot(array $state, string $ref, int $generation, int $page): ?array
{
    if (!in_array($state['status'] ?? null, ['complete', 'partial'], true)
        || ($state['search_ref'] ?? null) !== $ref || ($state['generation'] ?? null) !== $generation
        || !is_array($state['store'] ?? null)) return null;
    $store = $state['store'];
    if (($store['version'] ?? null) !== 1 || ($store['search_ref'] ?? null) !== $ref
        || ($store['generation'] ?? null) !== $generation || !is_int($store['created_at'] ?? null)
        || !is_int($store['expires_at'] ?? null) || $store['expires_at'] !== $store['created_at'] + 900
        || !is_array($store['snapshot'] ?? null)) return null;
    $snapshot = $store['snapshot'];
    if (($snapshot['provider'] ?? null) !== 'andromeda' || ($snapshot['search_ref'] ?? null) !== $ref
        || ($snapshot['generation'] ?? null) !== $generation || ($snapshot['page'] ?? null) !== $page
        || !is_int($snapshot['pages_count'] ?? null) || $snapshot['pages_count'] < 0 || $snapshot['pages_count'] > 1000
        || !is_array($snapshot['offers'] ?? null) || !array_is_list($snapshot['offers'])
        || !is_array($snapshot['rejected'] ?? null) || !array_is_list($snapshot['rejected'])
        || ($snapshot['selection_enabled'] ?? null) !== false) return null;
    return $snapshot;
}

function at_readonly_complete_surcharge(string $path, array $offer): bool
{
    $row = at_readonly_json($path, 16384);
    if (!is_array($row) || ($row['version'] ?? null) !== 1 || ($row['status'] ?? null) !== 'complete'
        || !is_array($row['fact'] ?? null)) return false;
    $fact = $row['fact'];
    $price = $offer['price'] ?? null;
    return ($fact['schema_version'] ?? null) === 1
        && ($fact['provider'] ?? null) === 'andromeda'
        && ($fact['state'] ?? null) === 'estimated'
        && ($fact['surcharge_scope'] ?? null) === 'party'
        && ($fact['arithmetic_applied'] ?? null) === true
        && ($fact['final_price_verified'] ?? null) === false
        && is_array($price)
        && ($fact['search_price'] ?? null) === [
            'amount' => (string)($price['amount'] ?? ''),
            'currency' => $price['currency'] ?? null,
        ]
        && is_array($fact['party_surcharge'] ?? null)
        && is_array($fact['search_price_with_surcharge'] ?? null);
}

if (PHP_SAPI !== 'cli') at_readonly_fail('cli_required');
$home = rtrim((string)getenv('HOME'), '/');
if ($home === '') at_readonly_fail('home_missing');
$root = $home . '/www/anytoour.ru';
$preview = $root . '/_preview/search3-anex-candidate';
if (!is_dir($root) || is_link($root) || !is_dir($preview) || is_link($preview)) at_readonly_fail('preview_root_invalid');

foreach (ANYTOUR_ANDROMEDA_AUTOSAVE_RUNTIME_HASHES as $relative => $expected) {
    $path = $preview . '/' . $relative;
    if (!is_file($path) || is_link($path) || hash_file('sha256', $path) !== $expected) {
        at_readonly_fail('runtime_hash_mismatch:' . $relative);
    }
}

$searches = $home . '/.anytoour-andromeda/searches';
if (!is_dir($searches) || is_link($searches)) at_readonly_fail('searches_directory_invalid');
$firstPages = [];
$checkpointPaths = [];
$fileCount = 0;
foreach (new DirectoryIterator($searches) as $entry) {
    if ($entry->isDot()) continue;
    ++$fileCount;
    if ($fileCount > 100000) at_readonly_fail('searches_inventory_too_large');
    if ($entry->isLink() || !$entry->isFile()) continue;
    $name = $entry->getFilename();
    if (preg_match('/\A([a-f0-9]{64})-1\.json\z/D', $name, $m)) {
        $firstPages[$m[1]] = $entry->getPathname();
    } elseif (preg_match('/\A[a-f0-9]{64}-[0-9]+-anytour-offer-autosave-v1\.json\z/D', $name)) {
        $checkpointPaths[] = $entry->getPathname();
    }
}

$checkpoint = [
    'valid_total' => 0,
    'invalid_total' => 0,
    'post_publish_total' => 0,
    'post_publish_ready_offer_sum' => 0,
    'post_publish_ready_offer_max' => 0,
    'post_publish_latest_at' => null,
];
foreach ($checkpointPaths as $path) {
    $row = at_readonly_json($path, 65536);
    if (!is_array($row) || ($row['version'] ?? null) !== 1 || ($row['provider'] ?? null) !== 'andromeda'
        || !is_string($row['search_ref'] ?? null) || !preg_match('/\A[a-f0-9]{64}\z/D', $row['search_ref'])
        || !is_int($row['generation'] ?? null) || $row['generation'] < 1
        || !is_string($row['cohort_digest'] ?? null) || !preg_match('/\A[a-f0-9]{64}\z/D', $row['cohort_digest'])
        || !is_int($row['published_at'] ?? null) || !is_int($row['ready_offer_count'] ?? null)
        || $row['ready_offer_count'] < 0 || $row['ready_offer_count'] > 5000) {
        ++$checkpoint['invalid_total'];
        continue;
    }
    ++$checkpoint['valid_total'];
    if ($row['published_at'] >= ANYTOUR_ANDROMEDA_AUTOSAVE_PUBLISHED_AT) {
        ++$checkpoint['post_publish_total'];
        $checkpoint['post_publish_ready_offer_sum'] += $row['ready_offer_count'];
        $checkpoint['post_publish_ready_offer_max'] = max($checkpoint['post_publish_ready_offer_max'], $row['ready_offer_count']);
        $checkpoint['post_publish_latest_at'] = max((int)($checkpoint['post_publish_latest_at'] ?? 0), $row['published_at']);
    }
}

$now = time();
$cohorts = [
    'first_page_total' => count($firstPages),
    'post_publish_first_page_total' => 0,
    'structurally_complete_total' => 0,
    'current_complete_total' => 0,
    'post_publish_complete_total' => 0,
    'post_publish_rejected_total' => 0,
    'post_publish_owned_offer_total' => 0,
    'post_publish_retained_mapped_owned_offer_total' => 0,
    'post_publish_owned_offer_with_complete_surcharge_total' => 0,
    'post_publish_complete_cohorts_with_ready_surcharge' => 0,
];
foreach ($firstPages as $ref => $firstPath) {
    $first = at_readonly_json($firstPath, 3000000);
    if (!is_array($first) || !is_int($first['generation'] ?? null)) continue;
    $generation = $first['generation'];
    $firstSnapshot = at_readonly_snapshot($first, $ref, $generation, 1);
    if ($firstSnapshot === null || !is_int($first['store']['created_at'] ?? null) || !is_int($first['store']['expires_at'] ?? null)) continue;
    $created = $first['store']['created_at'];
    $postPublish = $created >= ANYTOUR_ANDROMEDA_AUTOSAVE_PUBLISHED_AT;
    if ($postPublish) ++$cohorts['post_publish_first_page_total'];
    $target = $firstSnapshot['pages_count'];
    $states = [1 => $first];
    $snapshots = [1 => $firstSnapshot];
    $complete = $firstSnapshot['rejected'] === [] && !($target === 0 && $firstSnapshot['offers'] !== []);
    $current = $complete && $created <= $now && $now < $first['store']['expires_at'];
    if ($postPublish && $firstSnapshot['rejected'] !== []) ++$cohorts['post_publish_rejected_total'];
    for ($page = 2; $complete && $page <= $target; ++$page) {
        $path = $searches . '/' . $ref . '-' . $created . '-' . $page . '.json';
        $state = at_readonly_json($path, 3000000);
        $snapshot = is_array($state) ? at_readonly_snapshot($state, $ref, $generation, $page) : null;
        if ($snapshot === null || $snapshot['rejected'] !== [] || $snapshot['pages_count'] < $page) {
            $complete = false;
            if ($postPublish && is_array($snapshot) && $snapshot['rejected'] !== []) ++$cohorts['post_publish_rejected_total'];
            break;
        }
        $states[$page] = $state;
        $snapshots[$page] = $snapshot;
        $target = max($target, $snapshot['pages_count']);
        $current = $current && $state['store']['created_at'] <= $now && $now < $state['store']['expires_at'];
    }
    if (!$complete) continue;
    ++$cohorts['structurally_complete_total'];
    if ($current) ++$cohorts['current_complete_total'];
    if (!$postPublish) continue;
    ++$cohorts['post_publish_complete_total'];
    $cohortReady = 0;
    foreach ($snapshots as $page => $snapshot) {
        foreach ($snapshot['offers'] as $offer) {
            if (!is_array($offer) || !at_readonly_operator_owned((string)($offer['operator'] ?? ''))) continue;
            ++$cohorts['post_publish_owned_offer_total'];
            if (is_int($offer['local_hotel_id'] ?? null) && $offer['local_hotel_id'] > 0) {
                ++$cohorts['post_publish_retained_mapped_owned_offer_total'];
            }
            $offerRef = $offer['offer_ref'] ?? null;
            if (!is_string($offerRef) || !preg_match('/\Aoffer_[a-f0-9]{64}\z/D', $offerRef)) continue;
            $surchargePath = $searches . '/' . $ref . '-' . $created . '-' . $page . '-' . $offerRef . '-surcharge-v1.json';
            if (at_readonly_complete_surcharge($surchargePath, $offer)) {
                ++$cohorts['post_publish_owned_offer_with_complete_surcharge_total'];
                ++$cohortReady;
            }
        }
    }
    if ($cohortReady > 0) ++$cohorts['post_publish_complete_cohorts_with_ready_surcharge'];
}

$_SERVER['DOCUMENT_ROOT'] = $root;
$dbFile = is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php';
if (!is_file($dbFile) || is_link($dbFile)) at_readonly_fail('db_runtime_missing');
require_once $dbFile;
if (!function_exists('v2_data_db')) at_readonly_fail('db_helper_missing');
$db = v2_data_db();
if (!$db instanceof PDO) at_readonly_fail('db_unavailable');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$publishedSql = gmdate('Y-m-d H:i:s', ANYTOUR_ANDROMEDA_AUTOSAVE_PUBLISHED_AT);

$scalar = static function (PDO $db, string $sql, array $params = []): mixed {
    $q = $db->prepare($sql);
    $q->execute($params);
    return $q->fetchColumn();
};
$schema = (int)$scalar($db, 'SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1 LIMIT 1');
$dbState = [
    'schema_version' => $schema,
    'andromeda_offer_rows_total' => (int)$scalar($db, "SELECT COUNT(*) FROM anytour_offers WHERE provider='andromeda'"),
    'andromeda_active_rows_total' => (int)$scalar($db, "SELECT COUNT(*) FROM anytour_offers WHERE provider='andromeda' AND is_active=1"),
    'andromeda_current_ready_rows' => (int)$scalar($db, "SELECT COUNT(*) FROM anytour_offers WHERE provider='andromeda' AND is_active=1 AND final_price_ready=1 AND expires_at>UTC_TIMESTAMP()"),
    'andromeda_current_ready_hotels' => (int)$scalar($db, "SELECT COUNT(DISTINCT anytour_hotel_id) FROM anytour_offers WHERE provider='andromeda' AND is_active=1 AND final_price_ready=1 AND expires_at>UTC_TIMESTAMP()"),
    'andromeda_post_publish_seen_rows' => (int)$scalar($db, "SELECT COUNT(*) FROM anytour_offers WHERE provider='andromeda' AND last_seen_at>=:published", ['published' => $publishedSql]),
    'andromeda_post_publish_active_ready_rows' => (int)$scalar($db, "SELECT COUNT(*) FROM anytour_offers WHERE provider='andromeda' AND is_active=1 AND final_price_ready=1 AND last_seen_at>=:published", ['published' => $publishedSql]),
    'andromeda_completed_refreshes_total' => (int)$scalar($db, "SELECT COUNT(*) FROM anytour_offer_refreshes WHERE provider='andromeda' AND status='completed'"),
    'andromeda_post_publish_completed_refreshes' => (int)$scalar($db, "SELECT COUNT(*) FROM anytour_offer_refreshes WHERE provider='andromeda' AND status='completed' AND completed_at>=:published", ['published' => $publishedSql]),
    'andromeda_latest_completed_at' => $scalar($db, "SELECT MAX(completed_at) FROM anytour_offer_refreshes WHERE provider='andromeda' AND status='completed'"),
];

$result = [
    'schema_version' => 1,
    'source' => 'andromeda-autosave-state-readonly-v1',
    'published_source_sha' => ANYTOUR_ANDROMEDA_AUTOSAVE_SOURCE_SHA,
    'published_at' => ANYTOUR_ANDROMEDA_AUTOSAVE_PUBLISHED_AT,
    'observed_at' => $now,
    'runtime_hashes_verified' => true,
    'filesystem_writes' => 0,
    'supplier_calls' => 0,
    'db_writes' => 0,
    'booking_calls' => 0,
    'lead_calls' => 0,
    'checkpoints' => $checkpoint,
    'cohorts' => $cohorts,
    'database' => $dbState,
];
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
