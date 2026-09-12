<?php
declare(strict_types=1);

// Targeted audit only. No registry mutation, supplier access or application deployment.
const AUDIT_OP = 'hotel-match-conflict-readback-1971-20260912-v1';
const PRIOR_OPS = [
    'hotel-match-anexkey-current-accept-1971-20260912-v1',
    'hotel-match-current-cross-provider-exact-mass-1971-20260912-v2',
    'hotel-match-anexkey-current-accept-1971-20260912-v2',
    'hotel-match-coordinate-rescue-current-accept-1971-20260912-v1',
    'hotel-match-multi-evidence-current-accept-1971-20260912-v1',
];
const HOTSPOTS = [
    44411 => ['name' => 'Nova City', 'disputed_local_ids' => [123389, 131384]],
    37719 => ['name' => 'Posh Club', 'disputed_local_ids' => [9373, 132075]],
];
function json_text($value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
function keep(array $row, array $fields): array {
    return array_intersect_key($row, array_fill_keys($fields, true));
}
function save_once(string $path, array $value): void {
    $raw = json_text($value) . "\n";
    $file = fopen($path, 'x');
    if ($file === false) throw new RuntimeException('receipt_exists');
    try {
        if (!chmod($path, 0600) || fwrite($file, $raw) !== strlen($raw) || !fflush($file)) {
            throw new RuntimeException('receipt_write_failed');
        }
        if (function_exists('fsync') && !fsync($file)) throw new RuntimeException('receipt_sync_failed');
    } finally { fclose($file); }
    if (file_get_contents($path) !== $raw) throw new RuntimeException('receipt_readback_failed');
}
function select_rows(PDO $db, string $sql, array $params = []): array {
    if (!preg_match('/^SELECT\s/i', $sql) || str_contains($sql, ';')) {
        throw new RuntimeException('select_only');
    }
    $query = $db->prepare($sql);
    $query->execute($params);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}
function evidence_sources(array $node, string $externalId, int $depth = 0): array {
    if ($depth > 16) return [];
    $result = [];
    if ((string)($node['id'] ?? $node['hotelKey'] ?? '') === $externalId && isset($node['name'])) {
        $result[] = keep($node, ['id', 'hotelKey', 'name', 'lName', 'state', 'town', 'region', 'latitude', 'longitude', 'lat', 'lon']);
    }
    foreach ($node as $key => $value) {
        if (is_array($value) && !in_array((string)$key, ['candidates', 'targets', 'local', 'hotels', 'offers'], true)) {
            $result = array_merge($result, evidence_sources($value, $externalId, $depth + 1));
        }
    }
    $unique = [];
    foreach ($result as $row) $unique[hash('sha256', json_text($row))] = $row;
    return array_values($unique);
}
function conflict_state(array $mappings): string {
    foreach ($mappings as $row) {
        if ((int)$row['enabled'] === 1) return 'conflicting_evidence_enabled_mapping';
    }
    return $mappings ? 'conflicting_evidence_disabled_mapping' : 'conflicting_evidence_unmapped';
}
if (in_array('--self-test', $_SERVER['argv'] ?? [], true)) {
    $tests = [
        conflict_state([]) === 'conflicting_evidence_unmapped',
        conflict_state([['enabled' => 0]]) === 'conflicting_evidence_disabled_mapping',
        conflict_state([['enabled' => 1, 'approval_policy' => 'accepted']]) === 'conflicting_evidence_enabled_mapping',
        conflict_state([['enabled' => 0], ['enabled' => '1']]) === 'conflicting_evidence_enabled_mapping',
        keep(['id' => 1, 'secret' => 'synthetic'], ['id']) === ['id' => 1],
        evidence_sources(['prior_evidence' => ['source' => ['id' => '9', 'name' => 'Hotel', 'token' => 'synthetic']]], '9') === [['id' => '9', 'name' => 'Hotel']],
        evidence_sources(['candidates' => [['id' => '9', 'name' => 'Wrong']]], '9') === [],
        count(PRIOR_OPS) === count(array_unique(PRIOR_OPS)),
    ];
    foreach ($tests as $test) if (!$test) throw new RuntimeException('self_test_failed');
    echo 'MATCH conflict audit self-test PASS cases=' . count($tests) . "\n";
    exit(0);
}

error_reporting(0);
ob_start();
$db = null;
$dir = null;
$readTransaction = false;
try {
    if (PHP_SAPI !== 'cli') throw new RuntimeException('cli_only');
    $sourceSha = (string)getenv('MATCH_SOURCE_SHA');
    if (!preg_match('/^[a-f0-9]{40}$/D', $sourceSha)) throw new RuntimeException('source_sha_required');
    $root = realpath(getcwd());
    $home = realpath((string)getenv('HOME'));
    if (!$root || basename($root) !== 'anytoour.ru' || !$home) throw new RuntimeException('root_guard');
    $base = $home . '/.anytoour-match/operations';
    if (!is_dir($base)) throw new RuntimeException('existing_receipt_root_required');
    $candidateDir = $base . '/' . AUDIT_OP;
    if (file_exists($candidateDir) || !mkdir($candidateDir, 0700)) throw new RuntimeException('prior_operation_no_replay');
    $dir = $candidateDir;
    save_once($dir . '/reservation.json', ['operation_id' => AUDIT_OP, 'source_sha' => $sourceSha, 'state' => 'reserved_before_db_access', 'read_only' => true, 'no_replay' => true]);

    $history = [];
    $priorRows = [];
    foreach (PRIOR_OPS as $op) {
        $documents = [];
        foreach (['result.json', 'precommit-intent.json'] as $file) {
            $path = $base . '/' . $op . '/' . $file;
            if (!is_file($path) || is_link($path) || filesize($path) > 2097152) throw new RuntimeException('prior_receipt_missing_or_oversize');
            $raw = file_get_contents($path);
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (($data['operation_id'] ?? null) !== $op) throw new RuntimeException('prior_receipt_identity');
            $documents[$file] = ['sha256' => hash('sha256', $raw), 'data' => $data];
        }
        $result = $documents['result.json']['data'];
        $rows = $documents['precommit-intent.json']['data']['written'] ?? null;
        if (($result['status'] ?? '') !== 'accepted' || ($result['readback_verified'] ?? false) !== true || !is_array($rows) || count($rows) !== (int)($result['written_count'] ?? -1)) {
            throw new RuntimeException('prior_receipt_unverified');
        }
        $history[$op] = ['result_sha256' => $documents['result.json']['sha256'], 'intent_sha256' => $documents['precommit-intent.json']['sha256'], 'written_count' => count($rows), 'current_verified' => 0, 'current_changed' => 0, 'hotspot_rows' => []];
        foreach ($rows as $row) {
            $provider = $row['provider'] ?? (($row['bucket'] ?? '') === 'safe_missing_andromeda_side' ? 'andromeda' : 'anex');
            $external = $row['external_id'] ?? ($provider === 'anex' ? ($row['anex_id'] ?? $row['anex_hotel_id'] ?? null) : ($row['andromeda_id'] ?? null));
            if (!in_array($provider, ['anex', 'andromeda'], true) || !is_numeric($external) || !isset($row['local_id'])) throw new RuntimeException('prior_row_shape');
            $entry = keep($row, ['local_id', 'mapping_digest', 'source_row_digest', 'evidence_sha256']);
            $entry += ['provider' => $provider, 'external_id' => (string)$external, 'operation_id' => $op];
            $priorRows[] = $entry;
            if ($provider === 'anex' && isset(HOTSPOTS[(int)$external])) $history[$op]['hotspot_rows'][] = $entry;
        }
    }

    require_once $root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
    $db = v2_data_db();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $readTransaction = true;
    $changed = [];
    foreach ($priorRows as $row) {
        if ($row['provider'] === 'anex') {
            $current = select_rows($db, 'SELECT catalog_hotel_id,enabled,mapping_digest,source_row_digest FROM anex_hotel_search_mappings WHERE anex_hotel_id=?', [$row['external_id']]);
            $same = count($current) === 1 && (int)$current[0]['catalog_hotel_id'] === (int)$row['local_id'] && (int)$current[0]['enabled'] === 1 && isset($row['mapping_digest'], $row['source_row_digest']) && hash_equals($row['mapping_digest'], (string)$current[0]['mapping_digest']) && hash_equals($row['source_row_digest'], (string)$current[0]['source_row_digest']);
        } else {
            $current = select_rows($db, "SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?", [$row['external_id']]);
            $same = count($current) === 1 && (int)$current[0]['local_hotel_id'] === (int)$row['local_id'] && $current[0]['decision_status'] === 'accepted' && isset($row['evidence_sha256']) && hash_equals($row['evidence_sha256'], (string)$current[0]['evidence_sha256']);
        }
        ++$history[$row['operation_id']][$same ? 'current_verified' : 'current_changed'];
        if (!$same) $changed[] = $row + ['current' => $current];
    }
    $hotspots = [];
    foreach (HOTSPOTS as $id => $case) {
        $maps = select_rows($db, 'SELECT anex_hotel_id,catalog_hotel_id,enabled,match_class,scope,approval_policy,mapping_digest,source_row_digest FROM anex_hotel_search_mappings WHERE anex_hotel_id=? ORDER BY catalog_hotel_id', [$id]);
        $stage = select_rows($db, 'SELECT * FROM anex_hotels WHERE anex_hotel_id=?', [$id]);
        $hotspots[] = $case + ['anex_hotel_id' => $id, 'status' => conflict_state($maps), 'block_auto_propagation' => true, 'mappings' => $maps, 'manual_present' => (bool)select_rows($db, 'SELECT anex_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id=? LIMIT 1', [$id]), 'exclusion_present' => (bool)select_rows($db, 'SELECT anex_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id=? LIMIT 1', [$id]), 'staging' => array_map(fn($r) => keep($r, ['anex_hotel_id','api_name','xml_name','xml_alternate_name','api_country','api_town','api_region','latitude','longitude']), $stage)];
    }
    $locals = select_rows($db, 'SELECT id,name,country_id,country_name,region_name,subregion_name,latitude,longitude,is_active FROM catalog_hotels WHERE id IN (123389,131384,9373,132075) ORDER BY id');
    $andromeda = select_rows($db, "SELECT external_hotel_id,local_hotel_id,decision_status,evidence_json,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND (external_hotel_id IN ('2000105901','2000091833','2000042757') OR local_hotel_id IN (123389,131384,9373,132075)) ORDER BY external_hotel_id");
    foreach ($andromeda as &$row) {
        $raw = (string)$row['evidence_json'];
        $evidence = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        unset($row['evidence_json']);
        $row['actual_evidence_sha256'] = hash('sha256', $raw);
        $row['source_rows'] = evidence_sources(is_array($evidence) ? $evidence : [], (string)$row['external_hotel_id']);
    }
    unset($row);
    $related = select_rows($db, 'SELECT anex_hotel_id,catalog_hotel_id,enabled,approval_policy,mapping_digest FROM anex_hotel_search_mappings WHERE catalog_hotel_id IN (123389,131384,9373,132075) ORDER BY catalog_hotel_id,anex_hotel_id');
    $db->exec('ROLLBACK');
    $readTransaction = false;
    $out = ['status' => 'read_only_audit_complete', 'operation_id' => AUDIT_OP, 'source_sha' => $sourceSha, 'generated_at_utc' => gmdate('c'), 'historical_row_count' => count($priorRows), 'current_verified' => count($priorRows) - count($changed), 'current_changed' => count($changed), 'history' => $history, 'changed_rows' => $changed, 'hotspots' => $hotspots, 'local_hotels' => $locals, 'andromeda_anchors' => $andromeda, 'related_anex_mappings' => $related, 'identity_correctness_not_proved_by_readback' => true, 'automatic_remediation' => false, 'database_writes' => 0, 'mapping_writes' => 0, 'supplier_calls' => 0, 'tourvisor_calls' => 0, 'no_replay' => true];
    save_once($dir . '/result.json', $out);
} catch (Throwable $error) {
    if ($db instanceof PDO && $readTransaction) { try { $db->exec('ROLLBACK'); } catch (Throwable $ignored) {} }
    $out = ['status' => 'audit_failed_no_writes', 'operation_id' => AUDIT_OP, 'safe_message' => preg_match('/^[a-z_]+$/D', $error->getMessage()) ? $error->getMessage() : 'audit_guard_failure', 'database_writes' => 0, 'mapping_writes' => 0, 'supplier_calls' => 0, 'tourvisor_calls' => 0, 'no_replay' => true];
    if ($dir !== null && !is_file($dir . '/failure.json')) { try { save_once($dir . '/failure.json', $out); } catch (Throwable $ignored) {} }
}
while (ob_get_level()) ob_end_clean();
echo json_text($out) . "\n";
exit(($out['status'] ?? '') === 'read_only_audit_complete' ? 0 : 2);
