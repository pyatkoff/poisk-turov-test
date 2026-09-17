<?php
declare(strict_types=1);

/**
 * MATCH read-only adapter for the LOCAL-owned canonical hotel bridge.
 *
 * Source contract (owned by LOCAL, read here only):
 *   anytour_hotel_sources.namespace = legacy_catalog
 *   external_key                    = historical Tourvisor hotel id
 *   anytour_hotel_id                = independent AnyTour canonical hotel id
 *
 * The adapter never infers identity from equal numeric IDs. It emits an accepted
 * Tourvisor -> AnyTour graph edge only for an explicit, hash-valid saved_catalog
 * source row whose canonical profile is present and active.
 */

const MATCH_ANYTOUR_CANONICAL_BRIDGE_SCHEMA = 'anytour-canonical-bridge-graph-v1';

function macb_json(mixed $value, int $flags = 0): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | $flags);
}

function macb_positive_id(mixed $value, string $field): int
{
    if (is_int($value) && $value > 0) return $value;
    if (is_string($value) && preg_match('/\A[1-9][0-9]*\z/D', $value)
        && filter_var($value, FILTER_VALIDATE_INT) !== false) return (int)$value;
    throw new InvalidArgumentException('invalid_' . $field);
}

function macb_sha(mixed $value, string $field): string
{
    if (!is_string($value) || !preg_match('/\A[a-f0-9]{64}\z/D', $value)) {
        throw new InvalidArgumentException('invalid_' . $field);
    }
    return $value;
}

function macb_timestamp(mixed $value, string $field): string
{
    if (!is_string($value) || $value === '' || strlen($value) > 40
        || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        throw new InvalidArgumentException('invalid_' . $field);
    }
    return $value;
}

/** @return array{status:string,record:?array<string,mixed>} */
function macb_row_to_record(array $row): array
{
    $legacy = macb_positive_id($row['external_key'] ?? null, 'legacy_hotel_id');
    $own = macb_positive_id($row['anytour_hotel_id'] ?? null, 'anytour_hotel_id');
    if (($row['namespace'] ?? null) !== 'legacy_catalog') {
        throw new DomainException('unexpected_namespace');
    }
    if (($row['acquired_via'] ?? null) !== 'saved_catalog') {
        throw new DomainException('unexpected_acquired_via');
    }

    $sourceJson = $row['source_json'] ?? null;
    $profileJson = $row['profile_json'] ?? null;
    if (!is_string($sourceJson) || !is_string($profileJson)) {
        throw new DomainException('missing_integrity_payload');
    }
    $sourceSha = macb_sha($row['source_sha256'] ?? null, 'source_sha256');
    $profileSha = macb_sha($row['profile_sha256'] ?? null, 'profile_sha256');
    if (!hash_equals($sourceSha, hash('sha256', $sourceJson))) {
        throw new DomainException('source_hash_mismatch');
    }
    if (!hash_equals($profileSha, hash('sha256', $profileJson))) {
        throw new DomainException('profile_hash_mismatch');
    }

    $source = json_decode($sourceJson, true, 512, JSON_THROW_ON_ERROR);
    $profile = json_decode($profileJson, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($source) || !is_array($profile)) throw new DomainException('payload_not_object');
    if (macb_positive_id($source['id'] ?? null, 'source_id') !== $legacy) {
        throw new DomainException('source_identity_mismatch');
    }

    $revision = macb_positive_id($row['revision'] ?? null, 'revision');
    $firstSeen = macb_timestamp($row['first_seen_at'] ?? null, 'first_seen_at');
    $lastSeen = macb_timestamp($row['last_seen_at'] ?? null, 'last_seen_at');
    $active = (int)($row['is_active'] ?? -1);
    if (!in_array($active, [0, 1], true)) throw new DomainException('invalid_active_state');
    if ($active !== 1) return ['status' => 'hold_inactive_canonical', 'record' => null];

    return [
        'status' => 'accepted',
        'record' => [
            'source' => ['namespace'=>'tourvisor','kind'=>'hotel','id'=>(string)$legacy],
            'target' => ['namespace'=>'anytour','kind'=>'hotel','id'=>(string)$own],
            'evidence_type' => 'legacy_catalog_canonical_bridge',
            'authority' => 'accepted',
            'polarity' => 'support',
            'provenance' => [
                'source_table' => 'anytour_hotel_sources',
                'namespace' => 'legacy_catalog',
                'acquired_via' => 'saved_catalog',
                'source_sha256' => $sourceSha,
                'profile_sha256' => $profileSha,
                'revision' => $revision,
                'first_seen_at' => $firstSeen,
                'last_seen_at' => $lastSeen,
            ],
            'attributes' => [
                'canonical_active' => true,
                'bridge_schema' => MATCH_ANYTOUR_CANONICAL_BRIDGE_SCHEMA,
            ],
        ],
    ];
}

/** @return array{records:list<array<string,mixed>>,census:array<string,mixed>} */
function macb_export_rows(array $rows): array
{
    if (!array_is_list($rows) || count($rows) > 20000) {
        throw new InvalidArgumentException('row_budget');
    }
    $records = [];
    $seenLegacy = [];
    $inactive = 0;
    foreach ($rows as $row) {
        if (!is_array($row)) throw new InvalidArgumentException('row_shape');
        $legacy = macb_positive_id($row['external_key'] ?? null, 'legacy_hotel_id');
        if (isset($seenLegacy[$legacy])) throw new DomainException('duplicate_legacy_target');
        $seenLegacy[$legacy] = true;
        $converted = macb_row_to_record($row);
        if ($converted['status'] === 'accepted') $records[] = $converted['record'];
        elseif ($converted['status'] === 'hold_inactive_canonical') $inactive++;
        else throw new DomainException('unexpected_row_status');
    }
    usort($records, static function(array $a, array $b): int {
        return [(int)$a['source']['id'], (int)$a['target']['id']]
            <=> [(int)$b['source']['id'], (int)$b['target']['id']];
    });
    return [
        'records' => $records,
        'census' => [
            'source_rows' => count($rows),
            'accepted_edges' => count($records),
            'inactive_held' => $inactive,
            'distinct_legacy_ids' => count($seenLegacy),
        ],
    ];
}

function macb_write_new(string $path, string $bytes): string
{
    $handle = @fopen($path, 'x+b');
    if (!is_resource($handle)) throw new RuntimeException('exclusive_output');
    if (fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle)) {
        fclose($handle); throw new RuntimeException('output_write');
    }
    if (function_exists('fsync') && !fsync($handle)) {
        fclose($handle); throw new RuntimeException('output_sync');
    }
    rewind($handle);
    if (stream_get_contents($handle) !== $bytes) {
        fclose($handle); throw new RuntimeException('output_readback');
    }
    fclose($handle);
    return hash('sha256', $bytes);
}

function macb_jsonl(array $records): string
{
    $out = '';
    foreach ($records as $record) $out .= macb_json($record) . "\n";
    return $out;
}

function macb_query(PDO $db): array
{
    $engines = $db->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('anytour_hotel_sources','anytour_hotels')")
        ->fetchAll(PDO::FETCH_KEY_PAIR);
    if (($engines['anytour_hotel_sources'] ?? null) !== 'InnoDB'
        || ($engines['anytour_hotels'] ?? null) !== 'InnoDB') {
        throw new RuntimeException('canonical_table_contract');
    }
    $sql = "SELECT s.namespace,CAST(s.external_key AS CHAR) AS external_key,s.anytour_hotel_id,
        s.acquired_via,s.source_json,s.source_sha256,s.first_seen_at,s.last_seen_at,
        h.profile_json,h.profile_sha256,h.revision,h.is_active
        FROM anytour_hotel_sources s
        LEFT JOIN anytour_hotels h ON h.id=s.anytour_hotel_id
        WHERE s.namespace='legacy_catalog'
        ORDER BY CAST(s.external_key AS UNSIGNED),s.external_key,s.anytour_hotel_id";
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) > 20000) throw new RuntimeException('row_budget');
    return $rows;
}

function macb_self_test(): void
{
    $source = ['id'=>102,'name'=>'Example Hotel'];
    $profile = ['name'=>'Example Hotel','traits'=>[]];
    $row = [
        'namespace'=>'legacy_catalog','external_key'=>'102','anytour_hotel_id'=>'1',
        'acquired_via'=>'saved_catalog','source_json'=>macb_json($source),
        'source_sha256'=>hash('sha256',macb_json($source)),'first_seen_at'=>'2026-09-17 12:00:00',
        'last_seen_at'=>'2026-09-17 13:00:00','profile_json'=>macb_json($profile),
        'profile_sha256'=>hash('sha256',macb_json($profile)),'revision'=>'1','is_active'=>'1',
    ];
    $out = macb_export_rows([$row]);
    if (($out['census']['accepted_edges'] ?? 0) !== 1) throw new RuntimeException('self_edge_count');
    $edge = $out['records'][0] ?? [];
    if (($edge['source']['id'] ?? null) !== '102' || ($edge['target']['id'] ?? null) !== '1'
        || ($edge['source']['namespace'] ?? null) !== 'tourvisor'
        || ($edge['target']['namespace'] ?? null) !== 'anytour'
        || ($edge['authority'] ?? null) !== 'accepted') throw new RuntimeException('self_edge_contract');
    $inactive = $row; $inactive['external_key']='103'; $inactive['source_json']=macb_json(['id'=>103,'name'=>'Inactive']);
    $inactive['source_sha256']=hash('sha256',$inactive['source_json']); $inactive['is_active']='0';
    $held = macb_export_rows([$inactive]);
    if (($held['census']['inactive_held'] ?? 0) !== 1 || $held['records'] !== []) throw new RuntimeException('self_inactive');
    $bad = $row; $bad['source_sha256']=str_repeat('0',64);
    try { macb_export_rows([$bad]); throw new RuntimeException('self_hash_not_rejected'); }
    catch (DomainException $e) { if ($e->getMessage() !== 'source_hash_mismatch') throw $e; }
    try { macb_export_rows([$row,$row]); throw new RuntimeException('self_duplicate_not_rejected'); }
    catch (DomainException $e) { if ($e->getMessage() !== 'duplicate_legacy_target') throw $e; }
}

if (in_array('--self-test', $argv ?? [], true)) {
    macb_self_test(); echo "anytour canonical bridge export v1 self-test PASS\n"; exit(0);
}
if (defined('MACB_LIBRARY_ONLY') && MACB_LIBRARY_ONLY === true) return;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$op = trim((string)getenv('MATCH_OPERATION_ID'));
$sourceSha = trim((string)getenv('MATCH_SOURCE_SHA'));
if (!preg_match('/\Ahotel-match-anytour-canonical-bridge-[a-z0-9-]{8,120}\z/D', $op)
    || !preg_match('/\A[a-f0-9]{40}\z/D', $sourceSha)) {
    throw new RuntimeException('operation_guard');
}
$home = rtrim((string)getenv('HOME'), '/');
$dir = $home . '/.anytoour-match/operations/' . $op;
$reservationPath = $dir . '/reservation.json';
if (!is_file($reservationPath)) throw new RuntimeException('reservation_missing');
$reservation = json_decode((string)file_get_contents($reservationPath), true, 32, JSON_THROW_ON_ERROR);
if (($reservation['operation_id'] ?? null) !== $op || ($reservation['source_sha'] ?? null) !== $sourceSha
    || ($reservation['state'] ?? null) !== 'reserved_before_db_access') {
    throw new RuntimeException('reservation_contract');
}

$result = [
    'operation_id'=>$op,'source_sha'=>$sourceSha,'state'=>'failed_no_replay','no_replay'=>true,
    'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0,
];
$db = null;
try {
    $root = realpath(getcwd());
    if (!is_string($root) || basename($root) !== 'anytoour.ru') throw new RuntimeException('runtime_root');
    $dbFile = is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php';
    require_once $dbFile;
    $db = v2_data_db();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $rows = macb_query($db);
    $export = macb_export_rows($rows);
    $db->exec('ROLLBACK');

    $jsonl = macb_jsonl($export['records']);
    $censusBytes = macb_json($export['census'], JSON_PRETTY_PRINT) . "\n";
    $edgeSha = macb_write_new($dir . '/canonical-bridge.jsonl', $jsonl);
    $censusSha = macb_write_new($dir . '/census.json', $censusBytes);
    $result['state'] = 'completed_read_only';
    $result['census'] = $export['census'];
    $result['edge_sha256'] = $edgeSha;
    $result['census_sha256'] = $censusSha;
    $result['read_at_utc'] = gmdate('c');
    $result['transaction'] = 'REPEATABLE READ / READ ONLY';
} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    $code = preg_match('/\A[a-z0-9_]{2,100}\z/D', $e->getMessage()) ? $e->getMessage() : 'sanitized_failure';
    $result['error_code'] = $code;
}
$resultBytes = macb_json($result, JSON_PRETTY_PRINT) . "\n";
$resultSha = macb_write_new($dir . '/result.json', $resultBytes);
$receipt = [
    'operation_id'=>$op,'source_sha'=>$sourceSha,'state'=>$result['state'],'result_sha256'=>$resultSha,
    'readback_verified'=>hash('sha256',(string)file_get_contents($dir . '/result.json'))===$resultSha,
    'no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0,
];
macb_write_new($dir . '/receipt.json', macb_json($receipt, JSON_PRETTY_PRINT) . "\n");
echo macb_json(['state'=>$result['state'],'census'=>$result['census'] ?? null,'edge_sha256'=>$result['edge_sha256'] ?? null,'result_sha256'=>$resultSha]) . "\n";
exit($result['state'] === 'completed_read_only' ? 0 : 2);
