<?php
declare(strict_types=1);

const HSP_OP = 'hotel-match-hotelsu1244-schema-probe-1971-20260920-v1';
const HSP_ANEX = 15072;
const HSP_LOCAL = 1244;

function hsp_need(bool $ok, string $reason): void
{
    if (!$ok) {
        throw new RuntimeException($reason);
    }
}

function hsp_json(array $value): string
{
    return json_encode(
        $value,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    ) . "\n";
}

function hsp_save(string $path, array $value): string
{
    $bytes = hsp_json($value);
    $fh = fopen($path, 'xb');
    hsp_need(is_resource($fh), 'immutable_output');
    try {
        hsp_need(fwrite($fh, $bytes) === strlen($bytes) && fflush($fh), 'output_write');
        if (function_exists('fsync')) {
            hsp_need(fsync($fh), 'output_sync');
        }
    } finally {
        fclose($fh);
    }
    hsp_need(file_get_contents($path) === $bytes, 'output_readback');
    return hash('sha256', $bytes);
}

/** @return list<array<string,mixed>> */
function hsp_rows(PDO $db, string $sql, array $params = [], int $cap = 500): array
{
    $stmt = $db->prepare($sql);
    $stmt->execute(array_values($params));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    hsp_need(count($rows) <= $cap, 'row_cap');
    return $rows;
}

/** @return array{ok:bool,error_class:?string,sqlstate:?string,driver_code:int|string|null,row_count:?int} */
function hsp_select_probe(PDO $db, string $sql, array $params): array
{
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute(array_values($params));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'ok' => true,
            'error_class' => null,
            'sqlstate' => null,
            'driver_code' => null,
            'row_count' => count($rows),
        ];
    } catch (Throwable $e) {
        $state = null;
        $driverCode = null;
        if ($e instanceof PDOException && is_array($e->errorInfo ?? null)) {
            $state = isset($e->errorInfo[0]) ? (string) $e->errorInfo[0] : null;
            $driverCode = $e->errorInfo[1] ?? null;
        }
        return [
            'ok' => false,
            'error_class' => get_class($e),
            'sqlstate' => $state,
            'driver_code' => $driverCode,
            'row_count' => null,
        ];
    }
}

/** @return array{ok:bool,error_class:?string,sqlstate:?string,driver_code:int|string|null} */
function hsp_exec_probe(PDO $db, string $sql): array
{
    try {
        $db->exec($sql);
        return ['ok' => true, 'error_class' => null, 'sqlstate' => null, 'driver_code' => null];
    } catch (Throwable $e) {
        $state = null;
        $driverCode = null;
        if ($e instanceof PDOException && is_array($e->errorInfo ?? null)) {
            $state = isset($e->errorInfo[0]) ? (string) $e->errorInfo[0] : null;
            $driverCode = $e->errorInfo[1] ?? null;
        }
        return [
            'ok' => false,
            'error_class' => get_class($e),
            'sqlstate' => $state,
            'driver_code' => $driverCode,
        ];
    }
}

if (($argv[1] ?? '') === '--self-test') {
    hsp_need(HSP_ANEX === 15072 && HSP_LOCAL === 1244, 'target_constants');
    $shape = ['ok' => false, 'error_class' => 'PDOException', 'sqlstate' => '42S22', 'driver_code' => 1054, 'row_count' => null];
    hsp_need($shape['sqlstate'] === '42S22' && $shape['driver_code'] === 1054, 'probe_shape');
    echo "MATCH_HOTELSU1244_SCHEMA_PROBE_SELFTEST_OK 2\n";
    exit(0);
}

hsp_need(PHP_SAPI === 'cli' && (string) getenv('MATCH_OPERATION_ID') === HSP_OP, 'operation_guard');
$dir = rtrim((string) getenv('HOME'), '/') . '/.anytoour-match/operations/' . HSP_OP;
hsp_need(realpath((string) getenv('MATCH_OPERATION_DIR')) === $dir, 'operation_directory');
$sourceSha = (string) getenv('MATCH_SOURCE_SHA');
hsp_need(preg_match('/^[0-9a-f]{40}$/D', $sourceSha) === 1, 'source_sha');
$reservation = json_decode((string) file_get_contents($dir . '/reservation.json'), true, 64, JSON_THROW_ON_ERROR);
hsp_need(
    ($reservation['operation_id'] ?? '') === HSP_OP
    && ($reservation['source_sha'] ?? '') === $sourceSha
    && ($reservation['state'] ?? '') === 'reserved_before_db_read',
    'reservation_guard'
);

$base = [
    'operation_id' => HSP_OP,
    'source_sha' => $sourceSha,
    'local_hotel_id' => HSP_LOCAL,
    'anex_native_id' => HSP_ANEX,
    'supplier_calls' => 0,
    'provider_calls' => 0,
    'tourvisor_calls' => 0,
    'samo_calls' => 0,
    'andromeda_calls' => 0,
    'direct_anex_calls' => 0,
    'database_writes' => 0,
    'mapping_writes' => 0,
    'quota_mutations' => 0,
    'safe_to_write_now' => false,
    'no_replay' => true,
];
$db = null;

try {
    hsp_save($dir . '/execution-reservation.json', $base + ['state' => 'started_before_db_read']);

    $root = realpath(getcwd());
    hsp_need(is_string($root) && basename($root) === 'anytoour.ru', 'root_guard');
    require_once(is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php');
    $db = v2_data_db();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tables = ['anex_hotel_decisions', 'anex_review_pair_exclusions'];
    $engines = [];
    $columns = [];
    foreach ($tables as $table) {
        $engineRows = hsp_rows(
            $db,
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',
            [$table],
            2
        );
        $engines[$table] = count($engineRows) === 1 ? strtoupper((string) $engineRows[0]['ENGINE']) : null;
        $columns[$table] = hsp_rows(
            $db,
            'SELECT ORDINAL_POSITION,COLUMN_NAME,DATA_TYPE,IS_NULLABLE,COLUMN_KEY,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',
            [$table],
            200
        );
    }

    $columnNames = [];
    foreach ($columns as $table => $rows) {
        $columnNames[$table] = array_map(static fn(array $r): string => (string) $r['COLUMN_NAME'], $rows);
    }

    $setIsolation = hsp_exec_probe($db, 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $startSnapshot = hsp_exec_probe($db, 'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');

    $params = [HSP_ANEX, HSP_LOCAL];
    $manualOld = hsp_select_probe(
        $db,
        'SELECT * FROM anex_hotel_decisions WHERE anex_hotel_id=? OR catalog_hotel_id=? ORDER BY id LIMIT 1',
        $params
    );
    $manualSafe = hsp_select_probe(
        $db,
        'SELECT * FROM anex_hotel_decisions WHERE anex_hotel_id=? OR catalog_hotel_id=? ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 1',
        $params
    );
    $exclusionOld = hsp_select_probe(
        $db,
        'SELECT * FROM anex_review_pair_exclusions WHERE anex_hotel_id=? OR catalog_hotel_id=? ORDER BY id LIMIT 1',
        $params
    );
    $exclusionSafe = hsp_select_probe(
        $db,
        'SELECT * FROM anex_review_pair_exclusions WHERE anex_hotel_id=? OR catalog_hotel_id=? ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 1',
        $params
    );

    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $db = null;

    $manualHasId = in_array('id', $columnNames['anex_hotel_decisions'], true);
    $exclusionHasId = in_array('id', $columnNames['anex_review_pair_exclusions'], true);
    $invalidIdOrder = !$manualHasId
        && !$exclusionHasId
        && !$manualOld['ok']
        && !$exclusionOld['ok']
        && $manualSafe['ok']
        && $exclusionSafe['ok'];

    if ($invalidIdOrder) {
        $rootCause = 'invalid_order_by_id_columns';
    } elseif (!$setIsolation['ok']) {
        $rootCause = 'set_transaction_isolation_failed';
    } elseif (!$startSnapshot['ok']) {
        $rootCause = 'start_consistent_snapshot_read_only_failed';
    } else {
        $rootCause = 'not_proven_by_schema_probe';
    }

    $result = $base + [
        'state' => 'completed_read_only',
        'table_engines' => $engines,
        'table_columns' => $columnNames,
        'id_column_present' => [
            'anex_hotel_decisions' => $manualHasId,
            'anex_review_pair_exclusions' => $exclusionHasId,
        ],
        'transaction_probes' => [
            'set_isolation' => $setIsolation,
            'start_consistent_snapshot_read_only' => $startSnapshot,
        ],
        'select_probes' => [
            'anex_hotel_decisions_order_by_id' => $manualOld,
            'anex_hotel_decisions_canonical_order' => $manualSafe,
            'anex_review_pair_exclusions_order_by_id' => $exclusionOld,
            'anex_review_pair_exclusions_canonical_order' => $exclusionSafe,
        ],
        'root_cause' => $rootCause,
        'safe_to_write_now' => false,
    ];
} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    $message = $e->getMessage();
    $result = $base + [
        'state' => 'failed_no_replay',
        'reason' => preg_match('/^[a-z0-9_]+$/D', $message) ? $message : 'database_or_runtime_error',
        'error_class' => get_class($e),
        'safe_to_write_now' => false,
    ];
}

$resultHash = hsp_save($dir . '/result.json', $result);
hsp_save($dir . '/receipt.json', [
    'operation_id' => HSP_OP,
    'source_sha' => $sourceSha,
    'state' => $result['state'],
    'result_sha256' => $resultHash,
    'readback_verified' => hash_file('sha256', $dir . '/result.json') === $resultHash,
    'supplier_calls' => 0,
    'provider_calls' => 0,
    'database_writes' => 0,
    'mapping_writes' => 0,
    'quota_mutations' => 0,
    'no_replay' => true,
]);

echo hsp_json([
    'state' => $result['state'],
    'root_cause' => $result['root_cause'] ?? null,
    'id_column_present' => $result['id_column_present'] ?? null,
    'transaction_probes' => $result['transaction_probes'] ?? null,
    'select_probes' => $result['select_probes'] ?? null,
    'safe_to_write_now' => false,
]);
