<?php
declare(strict_types=1);

/**
 * MATCH provider evidence CURRENT exporter v2.
 *
 * Immutable successor to v1 after operation ...20260917-v1 proved a terminal
 * state-assignment bug. Normalization remains delegated to the merged v1 helpers;
 * v2 owns corrected success-state orchestration only.
 */

if (!defined('MATCH_PROVIDER_EVIDENCE_EXPORT_LIBRARY_ONLY')) {
    define('MATCH_PROVIDER_EVIDENCE_EXPORT_LIBRARY_ONLY', true);
}
require_once __DIR__ . '/hotel_match_provider_evidence_current_export_v1.php';

const MATCH_PROVIDER_EVIDENCE_EXPORT_V2_SCHEMA = 'provider-evidence-current-export-v2';

function me_v2_complete_result(array $base, array $success): array {
    $base['state'] = 'completed_read_only';
    return array_merge($base, $success);
}

function me_v2_main(): int {
    me_require(PHP_SAPI === 'cli', 'cli_only');
    $operationId = trim((string)getenv('MATCH_OPERATION_ID'));
    $sourceSha = trim((string)getenv('MATCH_SOURCE_SHA'));
    me_require((bool)preg_match('/^hotel-match-provider-evidence-current-export-1971-[0-9]{8}-v[0-9]+$/D', $operationId), 'operation_id');
    me_require((bool)preg_match('/^[a-f0-9]{40}$/D', $sourceSha), 'source_sha');

    $root = realpath(getcwd());
    me_require(is_string($root) && basename($root) === 'anytoour.ru', 'repo_root');
    $dir = rtrim((string)getenv('HOME'), '/') . '/.anytoour-match/operations/' . $operationId;
    me_require(is_dir($dir), 'operation_dir_missing');
    $reservationPath = $dir . '/reservation.json';
    me_require(is_file($reservationPath), 'reservation_missing');
    $reservation = json_decode((string)file_get_contents($reservationPath), true, 32, JSON_THROW_ON_ERROR);
    me_require(($reservation['operation_id'] ?? '') === $operationId, 'reservation_operation');
    me_require(($reservation['source_sha'] ?? '') === $sourceSha, 'reservation_source');
    me_require(in_array(($reservation['state'] ?? ''), ['reserved_before_db_access', 'reserved_before_db'], true), 'reservation_state');

    $result = [
        'schema' => MATCH_PROVIDER_EVIDENCE_EXPORT_V2_SCHEMA,
        'graph_schema' => MATCH_PROVIDER_GRAPH_SCHEMA,
        'operation_id' => $operationId,
        'source_sha' => $sourceSha,
        'state' => 'failed_no_replay',
        'no_replay' => true,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'supplier_calls' => 0,
        'tourvisor_calls' => 0,
        'samo_andromeda_calls' => 0,
    ];
    $db = null;
    try {
        $dbFile = is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php';
        require_once $dbFile;
        $db = v2_data_db();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        $export = me_export_from_db($db);
        $db->exec('ROLLBACK');

        $edgeBytes = me_jsonl_bytes($export['edges']);
        $census = $export['census'] + [
            'export_schema' => MATCH_PROVIDER_EVIDENCE_EXPORT_V2_SCHEMA,
            'graph_schema' => MATCH_PROVIDER_GRAPH_SCHEMA,
            'input_counts' => $export['input_counts'],
            'read_at_utc' => gmdate('c'),
            'transaction' => 'REPEATABLE READ / READ ONLY',
        ];
        $edgeSha = me_write_exclusive($dir . '/provider-evidence.jsonl', $edgeBytes);
        $censusBytes = me_json_bytes($census);
        $censusSha = me_write_exclusive($dir . '/census.json', $censusBytes);
        $result = me_v2_complete_result($result, [
            'edge_count' => count($export['edges']),
            'input_counts' => $export['input_counts'],
            'provider_evidence_sha256' => $edgeSha,
            'census_sha256' => $censusSha,
            'census' => $export['census'],
        ]);
    } catch (Throwable $e) {
        if ($db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }
        $message = $e->getMessage();
        $result['error_code'] = preg_match('/^[a-zA-Z0-9_\-]{2,120}$/D', $message) ? $message : 'sanitized_failure';
    }

    $resultBytes = me_json_bytes($result);
    $resultSha = me_write_exclusive($dir . '/result.json', $resultBytes);
    $receipt = [
        'operation_id' => $operationId,
        'source_sha' => $sourceSha,
        'state' => $result['state'],
        'result_sha256' => $resultSha,
        'readback_verified' => hash('sha256', (string)file_get_contents($dir . '/result.json')) === $resultSha,
        'no_replay' => true,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'supplier_calls' => 0,
        'tourvisor_calls' => 0,
        'samo_andromeda_calls' => 0,
    ];
    me_write_exclusive($dir . '/receipt.json', me_json_bytes($receipt));
    echo me_json_bytes([
        'state' => $result['state'],
        'edge_count' => $result['edge_count'] ?? null,
        'input_counts' => $result['input_counts'] ?? null,
        'census' => $result['census'] ?? null,
        'result_sha256' => $resultSha,
    ]);
    return $result['state'] === 'completed_read_only' ? 0 : 2;
}

if (!defined('MATCH_PROVIDER_EVIDENCE_EXPORT_V2_LIBRARY_ONLY')) {
    if (in_array('--self-test', $argv ?? [], true)) {
        $base = ['state' => 'failed_no_replay', 'database_writes' => 0];
        $done = me_v2_complete_result($base, ['edge_count' => 123]);
        me_require($done['state'] === 'completed_read_only', 'v2_success_state');
        me_require($done['edge_count'] === 123 && $done['database_writes'] === 0, 'v2_success_payload');
        echo "provider evidence current export v2 self-test PASS\n";
        exit(0);
    }
    exit(me_v2_main());
}
