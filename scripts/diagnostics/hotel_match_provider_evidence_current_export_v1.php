<?php
declare(strict_types=1);

/**
 * MATCH provider evidence CURRENT exporter v1.
 *
 * READ ONLY by contract. It normalizes evidence already stored in the AnyTour DB
 * into the JSONL contract consumed by hotel_match_provider_evidence_graph_v1.py.
 * It never accepts mappings, never calls suppliers, and never infers identity from
 * equal numeric IDs across namespaces.
 */

const MATCH_PROVIDER_EVIDENCE_EXPORT_SCHEMA = 'provider-evidence-current-export-v1';
const MATCH_PROVIDER_GRAPH_SCHEMA = 'provider-evidence-graph-v1';
const MATCH_TV_NATIVE_NAMESPACES = [
    // Explicitly established in retained MATCH evidence; do not extend by guess.
    18 => 'operator_115', // Biblio-Globus
    25 => 'operator_315', // FUN&SUN
    43 => 'operator_342', // Intourist
];

function me_require(bool $ok, string $code): void {
    if (!$ok) {
        throw new RuntimeException($code);
    }
}

function me_atom($value, string $field): string {
    $text = trim((string)$value);
    me_require($text !== '' && strlen($text) <= 240 && strpos($text, "\0") === false && strpos($text, ':') === false, 'invalid_' . $field);
    return $text;
}

function me_clean_text($value, int $max = 1000): ?string {
    if ($value === null) {
        return null;
    }
    $text = trim((string)$value);
    if ($text === '') {
        return null;
    }
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    if ($text === '') {
        return null;
    }
    return mb_substr($text, 0, $max, 'UTF-8');
}

function me_bool($value): bool {
    if (is_bool($value)) {
        return $value;
    }
    if ($value === null || $value === '') {
        return false;
    }
    if (is_numeric($value)) {
        return (float)$value !== 0.0;
    }
    return in_array(strtolower(trim((string)$value)), ['true', 'yes', 'on'], true);
}

function me_json_decode_object($value): array {
    if ($value === null || trim((string)$value) === '') {
        return [];
    }
    $decoded = json_decode((string)$value, true, 64, JSON_THROW_ON_ERROR);
    me_require(is_array($decoded), 'evidence_json_shape');
    return $decoded;
}

function me_edge(
    string $sourceNamespace,
    string $sourceKind,
    $sourceId,
    string $targetNamespace,
    string $targetKind,
    $targetId,
    string $evidenceType,
    string $authority,
    string $polarity = 'support',
    array $provenance = [],
    array $attributes = []
): array {
    $allowedAuthority = ['observation', 'corroboration', 'direct', 'accepted', 'protected'];
    $allowedPolarity = ['support', 'conflict', 'protect'];
    me_require(in_array($authority, $allowedAuthority, true), 'authority');
    me_require(in_array($polarity, $allowedPolarity, true), 'polarity');
    me_require(!($authority === 'protected' && $polarity === 'support'), 'protected_support');
    return [
        'source' => [
            'namespace' => me_atom($sourceNamespace, 'source_namespace'),
            'kind' => me_atom($sourceKind, 'source_kind'),
            'id' => me_atom($sourceId, 'source_id'),
        ],
        'target' => [
            'namespace' => me_atom($targetNamespace, 'target_namespace'),
            'kind' => me_atom($targetKind, 'target_kind'),
            'id' => me_atom($targetId, 'target_id'),
        ],
        'evidence_type' => me_atom($evidenceType, 'evidence_type'),
        'authority' => $authority,
        'polarity' => $polarity,
        'provenance' => $provenance,
        'attributes' => $attributes,
    ];
}

function me_tv_edges(array $rows): array {
    $edges = [];
    foreach ($rows as $row) {
        $hotelId = (string)($row['hotel_id'] ?? '');
        $operatorId = (int)($row['operator_id'] ?? 0);
        me_require(ctype_digit($hotelId) && (int)$hotelId > 0 && $operatorId > 0, 'tourvisor_identity_shape');
        $provenance = [
            'table' => 'tour_operator_identity_observations',
            'fingerprint' => me_clean_text($row['fingerprint'] ?? null, 128),
            'first_seen_at' => me_clean_text($row['first_seen_at'] ?? null, 64),
            'last_seen_at' => me_clean_text($row['last_seen_at'] ?? null, 64),
        ];
        $attributes = [
            'operator_id' => $operatorId,
            'operator_name' => me_clean_text($row['operator_name'] ?? null, 180),
            'country_id' => isset($row['country_id']) ? (int)$row['country_id'] : null,
            'observation_count' => max(0, (int)($row['observation_count'] ?? 0)),
            'source' => me_clean_text($row['source'] ?? null, 64),
        ];
        $edges[] = me_edge(
            'tourvisor_operator_' . $operatorId,
            'presence',
            'hotel_' . $hotelId,
            'tourvisor',
            'hotel',
            $hotelId,
            'passive_tourvisor_operator_presence',
            'observation',
            'support',
            $provenance,
            $attributes
        );

        $nativeValue = trim((string)($row['native_id_value'] ?? ''));
        $nativeType = trim((string)($row['native_id_type'] ?? ''));
        $nativeConflict = me_bool($row['native_id_conflict'] ?? false);
        $supplierNamespace = MATCH_TV_NATIVE_NAMESPACES[$operatorId] ?? null;
        if ($nativeValue !== '' && $nativeType !== '' && !$nativeConflict && $supplierNamespace !== null) {
            $edges[] = me_edge(
                $supplierNamespace,
                'hotel',
                $nativeValue,
                'tourvisor',
                'hotel',
                $hotelId,
                'tourvisor_explicit_native_query_id',
                'direct',
                'support',
                $provenance + ['native_id_type' => me_clean_text($nativeType, 120)],
                $attributes + ['native_id_type' => me_clean_text($nativeType, 120)]
            );
        }
    }
    return $edges;
}

function me_protected_json(array $value, string $key = '', int $depth = 0): bool {
    if ($depth > 20) {
        return true;
    }
    foreach ($value as $k => $item) {
        $name = (string)$k;
        if (is_array($item)) {
            if (me_protected_json($item, $name, $depth + 1)) {
                return true;
            }
            continue;
        }
        if (!preg_match('/manual|exclude|exclusion|conflict|reject|review/i', $name)) {
            continue;
        }
        if (is_bool($item) && $item) {
            return true;
        }
        if (is_numeric($item) && (float)$item !== 0.0) {
            return true;
        }
        if (is_string($item) && !in_array(strtolower(trim($item)), ['', 'false', 'none', 'no', 'null'], true)) {
            return true;
        }
    }
    return false;
}

function me_andromeda_edges(array $rows): array {
    $edges = [];
    foreach ($rows as $row) {
        $namespace = me_atom($row['supplier_namespace'] ?? '', 'supplier_namespace');
        $externalId = me_atom($row['external_hotel_id'] ?? '', 'external_hotel_id');
        $status = strtolower(trim((string)($row['decision_status'] ?? '')));
        $localId = trim((string)($row['local_hotel_id'] ?? ''));
        $evidenceSha = me_clean_text($row['evidence_sha256'] ?? null, 128);
        $evidence = me_json_decode_object($row['evidence_json'] ?? null);
        $baseProvenance = [
            'table' => 'andromeda_hotel_identities',
            'evidence_sha256' => $evidenceSha,
            'decision_status' => $status,
        ];

        if ($status === 'accepted' && $localId !== '') {
            me_require(ctype_digit($localId) && (int)$localId > 0, 'accepted_local_id');
            $edges[] = me_edge(
                $namespace,
                'hotel',
                $externalId,
                'tourvisor',
                'hotel',
                $localId,
                'accepted_provider_to_tourvisor_mapping',
                'accepted',
                'support',
                $baseProvenance,
                []
            );
            continue;
        }

        $bridge = $evidence['provider_bridges'][0] ?? null;
        if (is_array($bridge) && $namespace !== 'andromeda_catalog') {
            $andromedaId = trim((string)($bridge['andromeda_hotel_id'] ?? ''));
            if ($andromedaId !== '' && ctype_digit($andromedaId)) {
                // Provider bridge is deliberately corroboration in v1. It becomes
                // direct only when a source-specific contract proves native identity.
                $edges[] = me_edge(
                    $namespace,
                    'hotel',
                    $externalId,
                    'andromeda_catalog',
                    'hotel',
                    $andromedaId,
                    'andromeda_provider_bridge',
                    'corroboration',
                    'support',
                    $baseProvenance,
                    [
                        'country_id' => isset($bridge['country_id']) ? (int)$bridge['country_id'] : null,
                        'hotel_name' => me_clean_text($bridge['hotel_name'] ?? null, 255),
                    ]
                );
            }
        }

        if ($localId !== '' && ctype_digit($localId) && (int)$localId > 0) {
            if ($status === 'conflict') {
                $edges[] = me_edge(
                    $namespace,
                    'hotel',
                    $externalId,
                    'tourvisor',
                    'hotel',
                    $localId,
                    'andromeda_identity_conflict',
                    'corroboration',
                    'conflict',
                    $baseProvenance,
                    []
                );
            }
            if (me_protected_json($evidence)) {
                $edges[] = me_edge(
                    $namespace,
                    'hotel',
                    $externalId,
                    'tourvisor',
                    'hotel',
                    $localId,
                    'protected_andromeda_identity_state',
                    'protected',
                    'protect',
                    $baseProvenance,
                    []
                );
            }
        }
    }
    return $edges;
}

function me_anex_mapping_edges(array $rows): array {
    $edges = [];
    foreach ($rows as $row) {
        if (!me_bool($row['enabled'] ?? false)) {
            continue;
        }
        $anexId = trim((string)($row['anex_hotel_id'] ?? ''));
        $localId = trim((string)($row['catalog_hotel_id'] ?? ''));
        me_require(ctype_digit($anexId) && (int)$anexId > 0 && ctype_digit($localId) && (int)$localId > 0, 'anex_mapping_shape');
        $edges[] = me_edge(
            'operator_5',
            'hotel',
            $anexId,
            'tourvisor',
            'hotel',
            $localId,
            'accepted_anex_search_mapping',
            'accepted',
            'support',
            [
                'table' => 'anex_hotel_search_mappings',
                'match_class' => me_clean_text($row['match_class'] ?? null, 120),
                'scope' => me_clean_text($row['scope'] ?? null, 120),
                'approval_policy' => me_clean_text($row['approval_policy'] ?? null, 255),
                'source_row_digest' => me_clean_text($row['source_row_digest'] ?? null, 128),
                'mapping_digest' => me_clean_text($row['mapping_digest'] ?? null, 128),
            ],
            []
        );
    }
    return $edges;
}

function me_anex_exclusion_edges(array $rows, string $tableName, string $evidenceType): array {
    $edges = [];
    foreach ($rows as $row) {
        $anexId = trim((string)($row['anex_hotel_id'] ?? ''));
        $localId = trim((string)($row['catalog_hotel_id'] ?? ''));
        if ($anexId === '' || $localId === '' || !ctype_digit($anexId) || !ctype_digit($localId) || (int)$anexId <= 0 || (int)$localId <= 0) {
            continue;
        }
        $edges[] = me_edge(
            'operator_5',
            'hotel',
            $anexId,
            'tourvisor',
            'hotel',
            $localId,
            $evidenceType,
            'protected',
            'protect',
            ['table' => $tableName],
            []
        );
    }
    return $edges;
}

function me_edge_signature(array $edge): string {
    $stable = [
        $edge['source']['namespace'], $edge['source']['kind'], $edge['source']['id'],
        $edge['target']['namespace'], $edge['target']['kind'], $edge['target']['id'],
        $edge['evidence_type'], $edge['authority'], $edge['polarity'],
        $edge['provenance'], $edge['attributes'],
    ];
    return hash('sha256', json_encode($stable, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function me_sort_dedupe_edges(array $edges): array {
    $unique = [];
    foreach ($edges as $edge) {
        $sig = me_edge_signature($edge);
        if (!isset($unique[$sig])) {
            $unique[$sig] = $edge;
        }
    }
    ksort($unique, SORT_STRING);
    return array_values($unique);
}

function me_census(array $edges): array {
    $authority = [];
    $types = [];
    $sources = [];
    foreach ($edges as $edge) {
        $authority[$edge['authority']] = ($authority[$edge['authority']] ?? 0) + 1;
        $types[$edge['evidence_type']] = ($types[$edge['evidence_type']] ?? 0) + 1;
        $sources[$edge['source']['namespace']] = ($sources[$edge['source']['namespace']] ?? 0) + 1;
    }
    ksort($authority);
    ksort($types);
    ksort($sources);
    return [
        'schema' => MATCH_PROVIDER_EVIDENCE_EXPORT_SCHEMA,
        'edge_count' => count($edges),
        'by_authority' => $authority,
        'by_evidence_type' => $types,
        'by_source_namespace' => $sources,
    ];
}

function me_query(PDO $db, string $sql, array $params = [], int $limit = 60000): array {
    $stmt = $db->prepare($sql);
    $stmt->execute(array_values($params));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    me_require(count($rows) <= $limit, 'row_budget');
    return $rows;
}

function me_table_exists(PDO $db, string $table): bool {
    $rows = me_query($db, 'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table], 2);
    return (int)($rows[0]['c'] ?? 0) === 1;
}

function me_columns(PDO $db, string $table): array {
    $rows = me_query($db, 'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION', [$table], 500);
    $out = [];
    foreach ($rows as $row) {
        $out[(string)$row['COLUMN_NAME']] = true;
    }
    return $out;
}

function me_require_columns(PDO $db, string $table, array $required): array {
    me_require(me_table_exists($db, $table), 'missing_table_' . $table);
    $cols = me_columns($db, $table);
    foreach ($required as $column) {
        me_require(isset($cols[$column]), 'missing_column_' . $table . '_' . $column);
    }
    return $cols;
}

function me_write_exclusive(string $path, string $bytes): string {
    $fh = @fopen($path, 'x+b');
    me_require(is_resource($fh), 'exclusive_output');
    me_require(fwrite($fh, $bytes) === strlen($bytes), 'output_write');
    me_require(fflush($fh), 'output_flush');
    if (function_exists('fsync')) {
        me_require(fsync($fh), 'output_fsync');
    }
    rewind($fh);
    me_require(stream_get_contents($fh) === $bytes, 'output_readback');
    fclose($fh);
    return hash('sha256', $bytes);
}

function me_json_bytes(array $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
}

function me_jsonl_bytes(array $edges): string {
    $lines = [];
    foreach ($edges as $edge) {
        $lines[] = json_encode($edge, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
    return implode("\n", $lines) . (count($lines) ? "\n" : '');
}

function me_export_from_db(PDO $db): array {
    me_require_columns($db, 'tour_operator_identity_observations', [
        'fingerprint', 'first_seen_at', 'last_seen_at', 'observation_count', 'source',
        'country_id', 'hotel_id', 'operator_id', 'operator_name',
        'native_id_type', 'native_id_value', 'native_id_conflict',
    ]);
    me_require_columns($db, 'andromeda_hotel_identities', [
        'supplier_namespace', 'external_hotel_id', 'local_hotel_id', 'decision_status', 'evidence_sha256', 'evidence_json',
    ]);
    me_require_columns($db, 'anex_hotel_search_mappings', [
        'anex_hotel_id', 'catalog_hotel_id', 'match_class', 'scope', 'approval_policy', 'source_row_digest', 'mapping_digest', 'enabled',
    ]);

    $tvRows = me_query($db, "SELECT fingerprint,first_seen_at,last_seen_at,observation_count,source,country_id,hotel_id,operator_id,operator_name,native_id_type,native_id_value,native_id_conflict FROM tour_operator_identity_observations ORDER BY hotel_id,operator_id");
    $andromedaRows = me_query($db, "SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id");
    $anexRows = me_query($db, "SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE enabled=1 ORDER BY anex_hotel_id,catalog_hotel_id");

    $exclusionRows = [];
    if (me_table_exists($db, 'anex_review_pair_exclusions')) {
        $cols = me_columns($db, 'anex_review_pair_exclusions');
        if (isset($cols['anex_hotel_id'], $cols['catalog_hotel_id'])) {
            $exclusionRows = me_query($db, "SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id");
        }
    }

    $decisionRows = [];
    if (me_table_exists($db, 'anex_hotel_decisions')) {
        $cols = me_columns($db, 'anex_hotel_decisions');
        if (isset($cols['anex_hotel_id'], $cols['catalog_hotel_id'])) {
            $decisionRows = me_query($db, "SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions ORDER BY anex_hotel_id,catalog_hotel_id");
        }
    }

    $edges = array_merge(
        me_tv_edges($tvRows),
        me_andromeda_edges($andromedaRows),
        me_anex_mapping_edges($anexRows),
        me_anex_exclusion_edges($exclusionRows, 'anex_review_pair_exclusions', 'anex_pair_exclusion'),
        me_anex_exclusion_edges($decisionRows, 'anex_hotel_decisions', 'anex_manual_decision')
    );
    $edges = me_sort_dedupe_edges($edges);
    return [
        'edges' => $edges,
        'input_counts' => [
            'tourvisor_identity_rows' => count($tvRows),
            'andromeda_identity_rows' => count($andromedaRows),
            'anex_enabled_mapping_rows' => count($anexRows),
            'anex_pair_exclusion_rows' => count($exclusionRows),
            'anex_paired_decision_rows' => count($decisionRows),
        ],
        'census' => me_census($edges),
    ];
}

function me_main(): int {
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
        'schema' => MATCH_PROVIDER_EVIDENCE_EXPORT_SCHEMA,
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
            'graph_schema' => MATCH_PROVIDER_GRAPH_SCHEMA,
            'input_counts' => $export['input_counts'],
            'read_at_utc' => gmdate('c'),
            'transaction' => 'REPEATABLE READ / READ ONLY',
        ];
        $edgeSha = me_write_exclusive($dir . '/provider-evidence.jsonl', $edgeBytes);
        $censusBytes = me_json_bytes($census);
        $censusSha = me_write_exclusive($dir . '/census.json', $censusBytes);
        $result += [
            'state' => 'completed_read_only',
            'edge_count' => count($export['edges']),
            'input_counts' => $export['input_counts'],
            'provider_evidence_sha256' => $edgeSha,
            'census_sha256' => $censusSha,
            'census' => $export['census'],
        ];
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

if (!defined('MATCH_PROVIDER_EVIDENCE_EXPORT_LIBRARY_ONLY')) {
    if (in_array('--self-test', $argv ?? [], true)) {
        $fixture = me_tv_edges([[
            'fingerprint' => str_repeat('a', 64),
            'first_seen_at' => '2026-09-17 00:00:00',
            'last_seen_at' => '2026-09-17 01:00:00',
            'observation_count' => 2,
            'source' => 'user_search',
            'country_id' => 4,
            'hotel_id' => 1221,
            'operator_id' => 25,
            'operator_name' => 'FUN&SUN',
            'native_id_type' => 'hotels',
            'native_id_value' => '30752',
            'native_id_conflict' => 0,
        ]]);
        me_require(count($fixture) === 2, 'self_test_edge_count');
        me_require($fixture[0]['authority'] === 'observation', 'self_test_observation');
        me_require($fixture[1]['authority'] === 'direct' && $fixture[1]['source']['namespace'] === 'operator_315', 'self_test_direct');
        echo "provider evidence current export self-test PASS\n";
        exit(0);
    }
    exit(me_main());
}
