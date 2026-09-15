<?php
/**
 * MATCH #1971: read-only CURRENT reconciliation for sealed Tourvisor->ANEX native identities.
 * No supplier HTTP and no DB writes. The parent evidence operation is hash-pinned before DB access.
 */
declare(strict_types=1);

const HM_OP = 'hotel-match-tv-operatorlink-current-reconcile-1971-20260915-v1';

function hm_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function hm_write(string $path, array $value): string
{
    $raw = hm_json($value) . "\n";
    $fh = fopen($path, 'x');
    if ($fh === false) throw new RuntimeException('durable_create_failed');
    fwrite($fh, $raw);
    fflush($fh);
    fclose($fh);
    if ((string)file_get_contents($path) !== $raw) throw new RuntimeException('durable_readback_failed');
    return hash('sha256', $raw);
}

function hm_one(PDO $db, string $sql, array $params): ?array
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

function hm_many(PDO $db, string $sql, array $params): array
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function hm_first(array $row, array $keys): string
{
    foreach ($keys as $key) {
        if (isset($row[$key]) && is_scalar($row[$key]) && trim((string)$row[$key]) !== '') {
            return trim((string)$row[$key]);
        }
    }
    return '';
}

function hm_number(mixed $value): ?float
{
    if ($value === null || !is_scalar($value) || !is_numeric((string)$value)) return null;
    $number = (float)$value;
    return is_finite($number) ? $number : null;
}

function hm_coords(array $row): array
{
    foreach ([['latitude','longitude'], ['lat','lng'], ['lat','lon'], ['api_latitude','api_longitude'], ['hotelLatitude','hotelLongitude']] as [$latKey, $lonKey]) {
        $lat = hm_number($row[$latKey] ?? null);
        $lon = hm_number($row[$lonKey] ?? null);
        if ($lat !== null && $lon !== null && abs($lat) <= 90 && abs($lon) <= 180) return [$lat, $lon];
    }
    return [null, null];
}

function hm_haversine(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): ?float
{
    if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) return null;
    $radius = 6371.0088;
    $p1 = deg2rad($lat1);
    $p2 = deg2rad($lat2);
    $dp = deg2rad($lat2 - $lat1);
    $dl = deg2rad($lon2 - $lon1);
    $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return round(2 * $radius * asin(min(1, sqrt($a))), 3);
}

$sourceSha = (string)getenv('MATCH_SOURCE_SHA');
$parentOperation = (string)getenv('PARENT_OPERATION_ID');
$parentSourceSha = (string)getenv('PARENT_SOURCE_SHA');
$parentResultSha = (string)getenv('PARENT_RESULT_SHA256');
if (!preg_match('/^[0-9a-f]{40}$/', $sourceSha)) throw new RuntimeException('source_sha_required');
if (!preg_match('/^[0-9a-f]{40}$/', $parentSourceSha)) throw new RuntimeException('parent_source_sha_required');
if (!preg_match('/^[0-9a-f]{64}$/', $parentResultSha)) throw new RuntimeException('parent_result_sha_required');

$root = (string)realpath(getcwd());
if ($root === '' || basename($root) !== 'anytoour.ru') throw new RuntimeException('root_guard');
$operationsRoot = rtrim((string)getenv('HOME'), '/') . '/.anytoour-match/operations';
$parentDir = $operationsRoot . '/' . $parentOperation;
$parentResultPath = $parentDir . '/result.json';
$parentReceiptPath = $parentDir . '/receipt.json';
if (!is_file($parentResultPath) || !is_file($parentReceiptPath)) throw new RuntimeException('parent_missing');
$parentRaw = (string)file_get_contents($parentResultPath);
$parent = json_decode($parentRaw, true, 512, JSON_THROW_ON_ERROR);
$parentReceipt = json_decode((string)file_get_contents($parentReceiptPath), true, 512, JSON_THROW_ON_ERROR);
if (($parent['source_sha'] ?? '') !== $parentSourceSha) throw new RuntimeException('parent_source_mismatch');
if (hash('sha256', $parentRaw) !== $parentResultSha) throw new RuntimeException('parent_hash_mismatch');
if (($parentReceipt['result_sha256'] ?? '') !== $parentResultSha || ($parentReceipt['readback_verified'] ?? false) !== true) throw new RuntimeException('parent_receipt_mismatch');
if (($parent['status'] ?? '') !== 'completed' || ($parent['mapping_writes'] ?? -1) !== 0 || ($parent['tourvisor_search_calls'] ?? -1) !== 0) throw new RuntimeException('parent_state_mismatch');

$evidence = [];
foreach (($parent['rows'] ?? []) as $row) {
    if (($row['tier'] ?? '') !== 'EVIDENCE') continue;
    $ids = $row['observed_anex_ids'] ?? [];
    if (!is_array($ids) || count($ids) !== 1) continue;
    $evidence[] = $row;
}
if (count($evidence) !== 9) throw new RuntimeException('parent_evidence_count');

$operationDir = $operationsRoot . '/' . HM_OP;
if (file_exists($operationDir) || !mkdir($operationDir, 0700)) throw new RuntimeException('operation_exists');
hm_write($operationDir . '/reservation.json', [
    'operation_id' => HM_OP,
    'source_sha' => $sourceSha,
    'state' => 'reserved_before_db_access',
    'parent_operation_id' => $parentOperation,
    'parent_result_sha256' => $parentResultSha,
    'evidence_rows' => count($evidence),
    'supplier_calls' => 0,
    'tourvisor_calls' => 0,
    'database_writes' => 0,
    'mapping_writes' => 0,
    'no_replay' => true,
]);

$dbBootstrap = is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php';
require_once $dbBootstrap;
$db = v2_data_db();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
$rows = [];
try {
    foreach ($evidence as $input) {
        $anexId = (int)$input['observed_anex_ids'][0];
        $localId = (int)$input['tourvisor_hotel_id'];
        $stage = hm_one($db, 'SELECT * FROM anex_hotels WHERE anex_hotel_id=? LIMIT 1', [$anexId]) ?? [];
        $observations = hm_many($db, 'SELECT * FROM anex_search_hotel_observations WHERE anex_hotel_id=? ORDER BY search_count DESC,last_seen_utc DESC LIMIT 20', [$anexId]);
        $mappings = hm_many($db, 'SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id=?', [$anexId]);
        $manual = hm_one($db, 'SELECT * FROM anex_hotel_decisions WHERE anex_hotel_id=? LIMIT 1', [$anexId]);
        $exclusion = hm_one($db, 'SELECT * FROM anex_review_pair_exclusions WHERE anex_hotel_id=? AND catalog_hotel_id=? LIMIT 1', [$anexId, $localId]);
        $catalog = hm_one($db, 'SELECT * FROM catalog_hotels WHERE id=? LIMIT 1', [$localId]);

        $sourceName = hm_first($stage, ['name','hotel_name','name_ru','title','hotelName']);
        if ($sourceName === '') {
            foreach ($observations as $observation) {
                $sourceName = hm_first($observation, ['hotel_name','name','source_name','title','hotelName']);
                if ($sourceName !== '') break;
            }
        }
        $sourceCountry = null;
        foreach (array_merge([$stage], $observations) as $sourceRow) {
            foreach (['country_id','countryId'] as $key) {
                if (isset($sourceRow[$key]) && (int)$sourceRow[$key] > 0) {
                    $sourceCountry = (int)$sourceRow[$key];
                    break 2;
                }
            }
        }
        [$sourceLat, $sourceLon] = hm_coords($stage);
        if ($sourceLat === null) {
            foreach ($observations as $observation) {
                [$sourceLat, $sourceLon] = hm_coords($observation);
                if ($sourceLat !== null) break;
            }
        }
        [$targetLat, $targetLon] = hm_coords($catalog ?? []);
        $sameTarget = false;
        $otherTargets = [];
        foreach ($mappings as $mapping) {
            $target = (int)($mapping['catalog_hotel_id'] ?? $mapping['local_hotel_id'] ?? 0);
            if ($target === $localId) $sameTarget = true;
            elseif ($target > 0) $otherTargets[] = $target;
        }
        $rows[] = [
            'anex_hotel_id' => $anexId,
            'tourvisor_local_id' => $localId,
            'tourvisor_name' => (string)($input['tourvisor_hotel_name'] ?? ''),
            'credential_source' => (string)($input['credential_source'] ?? ''),
            'source_name' => $sourceName,
            'source_country_id' => $sourceCountry,
            'target_name' => (string)($catalog['name'] ?? ''),
            'target_country_id' => isset($catalog['country_id']) ? (int)$catalog['country_id'] : null,
            'source_star' => hm_first($stage, ['star','stars','star_name','category']),
            'target_star' => hm_first($catalog ?? [], ['category','star','stars']),
            'coordinate_distance_km' => hm_haversine($sourceLat, $sourceLon, $targetLat, $targetLon),
            'live_search_count' => array_sum(array_map(static fn(array $r): int => (int)($r['search_count'] ?? 0), $observations)),
            'existing_same_target' => $sameTarget,
            'existing_other_targets' => array_values(array_unique($otherTargets)),
            'manual_protected' => $manual !== null,
            'pair_excluded' => $exclusion !== null,
            'stage_present' => $stage !== [],
            'observation_count' => count($observations),
        ];
    }
    $db->exec('ROLLBACK');
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->exec('ROLLBACK');
    throw $error;
}

$result = [
    'operation_id' => HM_OP,
    'source_sha' => $sourceSha,
    'status' => 'completed_read_only',
    'parent_operation_id' => $parentOperation,
    'parent_result_sha256' => $parentResultSha,
    'examined' => count($rows),
    'rows' => $rows,
    'supplier_calls' => 0,
    'tourvisor_calls' => 0,
    'database_writes' => 0,
    'mapping_writes' => 0,
    'no_replay' => true,
];
$resultHash = hm_write($operationDir . '/result.json', $result);
hm_write($operationDir . '/receipt.json', [
    'operation_id' => HM_OP,
    'source_sha' => $sourceSha,
    'state' => 'completed_read_only',
    'result_sha256' => $resultHash,
    'readback_verified' => hash('sha256', (string)file_get_contents($operationDir . '/result.json')) === $resultHash,
    'database_writes' => 0,
    'mapping_writes' => 0,
    'no_replay' => true,
]);
echo hm_json(['examined' => count($rows)]) . "\n";
