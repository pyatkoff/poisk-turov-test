<?php
declare(strict_types=1);

/** Supplier-free CURRENT review. This file has no mapping-write action. */
const FW_OP = 'hotel-match-fruit-white-materialization-review-1971-20260920-v1';
const FW_INPUT = '4e844ac83dfe93c0a5c6e4a5795f28f659d55feb09716f23f0e7a3629a0d6eba';
const FW_REVIEW = '8e8c90a545a045d1272b1c41f80bdf078a9e63a3d507cbb43d7d93173be03b2b';
const FW_SUPPORT = '3144fbbfe9a886bb94954d8a12147119172f570285ae7622edf2ffb8ed16657a';
const FW_REGISTRY_BLOB = 'cc135a95d2a6e0f73ce50be2141c9b8a26fddc58';

function fw_need(bool $ok, string $why): void {
    if (!$ok) throw new RuntimeException($why);
}
function fw_json(array $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
}
function fw_put(string $path, array $value): string {
    $bytes = fw_json($value);
    $handle = @fopen($path, 'xb');
    fw_need(is_resource($handle), 'output_already_exists');
    try {
        fw_need(fwrite($handle, $bytes) === strlen($bytes) && fflush($handle), 'output_write');
        if (function_exists('fsync')) fw_need(fsync($handle), 'output_sync');
    } finally { fclose($handle); }
    fw_need(file_get_contents($path) === $bytes, 'output_readback');
    return hash('sha256', $bytes);
}
function fw_rows(PDO $db, string $query, array $params = [], int $cap = 500): array {
    $stmt = $db->prepare($query);
    $stmt->execute(array_values($params));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    fw_need(count($rows) <= $cap, 'snapshot_row_cap');
    return $rows;
}
function fw_input(array $input): array {
    fw_need(($input['review_result_sha256'] ?? '') === FW_REVIEW
        && ($input['support_result_sha256'] ?? '') === FW_SUPPORT, 'input_provenance');
    $pairs = $input['pairs'] ?? null;
    fw_need(is_array($pairs) && count($pairs) === 2 && array_is_list($pairs), 'input_pair_count');
    $expected = [
        [49104, '2000034436', '41074', '11077', 28626, 'Fruit & Spice Wellness Resort Zanzibar', 'FRUIT & SPICE WELLNESS RESORT', 5],
        [69340, '2000063032', '41080', '46562', 29125, 'White Paradise Zanzibar', 'WHITE PARADISE ZANZIBAR', 4],
    ];
    foreach ($expected as $index => [$tv, $samo, $anex, $intourist, $older, $sourceName, $targetName, $stars]) {
        $row = $pairs[$index];
        $r = $row['review'] ?? [];
        $s = $row['support'] ?? [];
        fw_need(($r['tv_hotel_id'] ?? null) === $tv && ($s['tv_hotel_id'] ?? null) === $tv
            && ($r['samo_hotel_id'] ?? null) === $samo && ($s['samo_hotel_id'] ?? null) === $samo, 'input_pair_binding');
        fw_need(($r['classification'] ?? '') === 'safe_pair_needs_anex_materialization'
            && ($r['anex_mapping_state'] ?? '') === 'missing_materialization'
            && ($r['safe_to_write_now'] ?? null) === false
            && ($r['hold_reasons'] ?? null) === [] && ($r['current_source_rows'] ?? null) === []
            && ($r['current_target_occupants'] ?? null) === [], 'review_state');
        fw_need(($r['proof_native_ids'] ?? []) === [13 => $anex, 43 => $intourist]
            && ($r['anex_native_id'] ?? '') === $anex, 'review_native_binding');
        // Full saved names are retained. No global token deletion or fuzzy acceptance.
        fw_need(($r['source_name'] ?? '') === $sourceName && ($r['target']['name'] ?? '') === $targetName
            && ($r['source_country'] ?? '') === 'Танзания' && ($r['target']['country_name'] ?? '') === 'Танзания'
            && ($r['target']['region_name'] ?? '') === 'Занзибар'
            && ($r['target']['category'] ?? null) === $stars, 'review_name_country');
        $old = $r['anex_mapping_rows'] ?? [];
        fw_need(count($old) === 1 && ($old[0]['anex_hotel_id'] ?? null) === $older
            && ($old[0]['catalog_hotel_id'] ?? null) === $tv && ($old[0]['enabled'] ?? null) === 1, 'review_older_anchor');
        $proofs = $s['operator_proofs'] ?? [];
        fw_need(is_array($proofs) && count($proofs) === 2, 'operator_proof_count');
        $seen = [];
        foreach ($proofs as $p) {
            $op = $p['operator_id'] ?? null;
            fw_need(is_int($op) && in_array($op, [13, 43], true) && !isset($seen[$op]), 'operator_proof_binding');
            $seen[$op] = true;
            $native = $op === 13 ? $anex : $intourist;
            $providerOp = $op === 13 ? '5' : '342';
            $np = $p['native_proofs'] ?? [];
            fw_need(($p['tv_hotel_id'] ?? null) === $tv && ($p['samo_hotel_id'] ?? '') === $samo
                && count($np) === 1 && ($np[0]['native_token'] ?? '') === $native, 'operator_hotel_binding');
            $fact = $np[0]['fact'] ?? [];
            fw_need(($fact['samo_hotel_id'] ?? '') === $samo
                && ($fact['native_operator_hotel_id'] ?? '') === $native
                && ($fact['operator_id'] ?? '') === $providerOp, 'independent_samo_binding');
            foreach ([$p['operator_link_sha256'] ?? '', $fact['raw_sha256'] ?? ''] as $sha)
                fw_need(is_string($sha) && preg_match('/^[a-f0-9]{64}$/D', $sha) === 1, 'raw_evidence_pin');
        }
        fw_need(($s['source_catalog']['hotel']['name'] ?? '') === $sourceName
            && ($s['source_catalog']['hotel']['state'] ?? '') === 'Танзания', 'catalog_binding');
    }
    return $pairs;
}
function fw_select(array $rows, string $column, array $ids): array {
    $wanted = array_fill_keys(array_map('strval', $ids), true);
    return array_values(array_filter($rows, static fn(array $r): bool => isset($wanted[(string)($r[$column] ?? '')])));
}

if (($argv[1] ?? '') === '--self-test') {
    fw_need(isset($argv[2]), 'selftest_input_required');
    $input = json_decode((string)file_get_contents($argv[2]), true, 128, JSON_THROW_ON_ERROR);
    fw_need(hash_file('sha256', $argv[2]) === FW_INPUT, 'selftest_input_digest');
    fw_need(count(fw_input($input)) === 2, 'positive_input');
    $mutations = [
        static function (array &$a): void { $a['review_result_sha256'] = str_repeat('0', 64); },
        static function (array &$a): void { $a['pairs'] = array_reverse($a['pairs']); },
        static function (array &$a): void { array_pop($a['pairs']); },
        static function (array &$a): void { $a['pairs'][0]['review']['safe_to_write_now'] = true; },
        static function (array &$a): void { $a['pairs'][0]['review']['proof_native_ids'][13] = '28626'; },
        static function (array &$a): void { $a['pairs'][0]['support']['operator_proofs'][1]['operator_id'] = 13; },
        static function (array &$a): void { $a['pairs'][0]['support']['operator_proofs'][0]['native_proofs'][0]['fact']['operator_id'] = '13'; },
        static function (array &$a): void { $a['pairs'][0]['support']['operator_proofs'][0]['native_proofs'][0]['fact']['samo_hotel_id'] = '41074'; },
        static function (array &$a): void { $a['pairs'][0]['review']['source_name'] = 'Fruit & Spice Wellness Resort'; },
        static function (array &$a): void { $a['pairs'][1]['review']['target']['name'] = 'WHITE PARADISE'; },
        static function (array &$a): void { $a['pairs'][1]['review']['target']['country_name'] = 'Турция'; },
        static function (array &$a): void { $a['pairs'][1]['review']['anex_mapping_rows'][0]['enabled'] = 0; },
        static function (array &$a): void { $a['pairs'][0]['support']['operator_proofs'][0]['operator_link_sha256'] = 'unknown'; },
    ];
    foreach ($mutations as $mutate) {
        $bad = $input; $mutate($bad); $failed = false;
        try { fw_input($bad); } catch (RuntimeException $e) { $failed = true; }
        fw_need($failed, 'negative_input_admitted');
    }
    $tmp = sys_get_temp_dir() . '/fw-review-' . bin2hex(random_bytes(12)) . '.json';
    try {
        fw_need(fw_put($tmp, ['test' => true]) === hash_file('sha256', $tmp), 'durable_output');
        $failed = false;
        try { fw_put($tmp, ['test' => false]); } catch (RuntimeException $e) { $failed = true; }
        fw_need($failed && json_decode((string)file_get_contents($tmp), true)['test'] === true, 'no_replay_output');
    } finally { @unlink($tmp); }
    echo 'FRUIT_WHITE_MATERIALIZATION_REVIEW_SELFTEST_OK 16' . "\n";
    exit(0);
}
fw_need(PHP_SAPI === 'cli' && ($argv[1] ?? '') === '--execute', 'execute_disabled');
$dir = realpath((string)getenv('MATCH_OPERATION_DIR'));
$root = realpath((string)getenv('ANYTOUR_ROOT'));
fw_need(is_string($dir) && basename($dir) === FW_OP && is_string($root) && basename($root) === 'anytoour.ru', 'runtime_paths');
$res = json_decode((string)file_get_contents($dir . '/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
fw_need(($res['operation'] ?? '') === FW_OP && ($res['state'] ?? '') === 'reserved_before_db'
    && ($res['input_sha256'] ?? '') === FW_INPUT && ($res['provider_calls'] ?? null) === 0
    && ($res['max_mapping_writes'] ?? null) === 0
    && preg_match('/^[a-f0-9]{40}$/D', $res['source_sha'] ?? '') === 1, 'reservation_binding');
$base = ['operation' => FW_OP, 'source_sha' => $res['source_sha'], 'input_sha256' => FW_INPUT,
    'review_result_sha256' => FW_REVIEW, 'support_result_sha256' => FW_SUPPORT,
    'provider_calls' => 0, 'database_writes' => 0, 'mapping_writes' => 0, 'no_replay' => true];
$db = null;
$dbStarted = false;
try {
    fw_put($dir . '/execution-reservation.json', $res);
    $ip = $dir . '/payload/input.json';
    fw_need(hash_file('sha256', $ip) === FW_INPUT, 'input_digest');
    $pairs = fw_input(json_decode((string)file_get_contents($ip), true, 128, JSON_THROW_ON_ERROR));
    $rf = $dir . '/payload/anex-search-mapping-registry.php';
    $rb = (string)file_get_contents($rf);
    fw_need(sha1('blob ' . strlen($rb) . "\0" . $rb) === FW_REGISTRY_BLOB, 'registry_digest');
    require_once $rf;
    require_once (is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php');
    $dbStarted = true;
    $db = v2_data_db();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('SET SESSION innodb_lock_wait_timeout=10');
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $clock = fw_rows($db, 'SELECT UTC_TIMESTAMP AS db_utc_timestamp', [], 1)[0]['db_utc_timestamp'];
    $seedIds = [41074, 41080, 28626, 29125];
    $targets = [49104, 69340];
    $auto = fw_rows($db, 'SELECT * FROM anex_hotel_auto_matches WHERE anex_hotel_id IN (?,?,?,?) OR suggested_catalog_hotel_id IN (?,?) ORDER BY anex_hotel_id LIMIT 501', array_merge($seedIds, $targets));
    $nativeIds = $seedIds;
    foreach ($auto as $a) $nativeIds[] = (int)$a['anex_hotel_id'];
    $nativeIds = array_values(array_unique($nativeIds)); sort($nativeIds, SORT_NUMERIC);
    fw_need(count($nativeIds) <= 100 && min($nativeIds) > 0, 'related_native_cap');
    $ph = implode(',', array_fill(0, count($nativeIds), '?'));
    $args = array_merge($nativeIds, $targets);
    $maps = fw_rows($db, "SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN (?,?) ORDER BY anex_hotel_id LIMIT 501", $args);
    $manual = fw_rows($db, "SELECT * FROM anex_hotel_decisions WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN (?,?) ORDER BY anex_hotel_id LIMIT 501", $args);
    $exclusions = fw_rows($db, "SELECT * FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN (?,?) ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 501", $args);
    $native = fw_rows($db, "SELECT * FROM anex_hotels WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id LIMIT 501", $nativeIds);
    $observations = fw_rows($db, "SELECT * FROM anex_search_hotel_observations WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id LIMIT 501", $nativeIds);
    $identities = fw_rows($db, "SELECT * FROM andromeda_hotel_identities WHERE local_hotel_id IN (?,?) OR (supplier_namespace='andromeda_catalog' AND external_hotel_id IN (?,?)) OR (supplier_namespace='operator_5' AND external_hotel_id IN (?,?,?,?)) OR (supplier_namespace='operator_342' AND external_hotel_id IN (?,?)) ORDER BY supplier_namespace,external_hotel_id LIMIT 501", [49104, 69340, '2000034436', '2000063032', '41074', '41080', '28626', '29125', '11077', '46562']);
    $localIds = $targets;
    foreach (array_merge($maps, $manual) as $row) if ((int)($row['catalog_hotel_id'] ?? 0) > 0) $localIds[] = (int)$row['catalog_hotel_id'];
    foreach ($auto as $row) if ((int)($row['suggested_catalog_hotel_id'] ?? 0) > 0) $localIds[] = (int)$row['suggested_catalog_hotel_id'];
    $localIds = array_values(array_unique($localIds)); sort($localIds, SORT_NUMERIC);
    fw_need(count($localIds) <= 200, 'related_local_cap');
    $lh = implode(',', array_fill(0, count($localIds), '?'));
    $local = fw_rows($db, "SELECT id,name,country_id,country_name,region_name,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE id IN ($lh) ORDER BY id LIMIT 501", $localIds);
    $peers = fw_rows($db, 'SELECT id,name,country_id,country_name,region_name,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE country_name=? AND is_active=1 ORDER BY id LIMIT 2001', ['Танзания'], 2000);
    $schema = fw_rows($db, "SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('anex_hotels','anex_hotel_auto_matches','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_hotel_identities') ORDER BY TABLE_NAME,ORDINAL_POSITION LIMIT 501");
    $registry = AnyTourAnexSearchMappingRegistry::fromPdo($db);
    $resolved = [];
    foreach ($nativeIds as $id) $resolved[(string)$id] = $registry->resolve('anex_online', (string)$id, 'preview');
    $dossiers = [];
    foreach ($pairs as $pair) {
        $r = $pair['review']; $tv = $r['tv_hotel_id']; $aid = (int)$r['anex_native_id'];
        $dossiers[] = ['tv_hotel_id' => $tv, 'samo_hotel_id' => $r['samo_hotel_id'],
            'native_anex_id' => (string)$aid, 'saved_review' => $r, 'saved_support' => $pair['support'],
            'current_local' => fw_select($local, 'id', [$tv]),
            'current_native_catalog' => fw_select($native, 'anex_hotel_id', [$aid]),
            'current_native_auto_review' => fw_select($auto, 'anex_hotel_id', [$aid]),
            'current_native_observations' => fw_select($observations, 'anex_hotel_id', [$aid]),
            'effective_current_anex_target' => $resolved[(string)$aid],
            'state' => 'current_evidence_needs_independent_review', 'safe_to_write_now' => false];
    }
    $db->exec('ROLLBACK');
    $out = $base + ['state' => 'completed_read_only', 'captured_at_utc' => $clock, 'pair_count' => 2,
        'related_native_ids' => $nativeIds, 'effective_anex_targets' => $resolved, 'dossiers' => $dossiers,
        'current_mapping_rows' => $maps, 'current_manual_rows' => $manual, 'current_exclusion_rows' => $exclusions,
        'current_native_catalog_rows' => $native, 'current_auto_review_rows' => $auto,
        'current_observation_rows' => $observations, 'current_identity_rows' => $identities,
        'related_local_rows' => $local, 'active_country_peers' => $peers, 'schema_columns' => $schema,
        'acceptance_authority' => false];
} catch (Throwable $e) {
    try { if ($db instanceof PDO && $db->inTransaction()) $db->rollBack(); } catch (Throwable $ignored) {}
    $out = $base + ['state' => 'blocked_read_only', 'db_access_started' => $dbStarted,
        'reason' => preg_match('/^[a-z0-9_]+$/D', $e->getMessage()) ? $e->getMessage() : 'database_or_runtime_error',
        'sqlstate' => $e instanceof PDOException ? (string)$e->getCode() : null,
        'driver_code' => $e instanceof PDOException ? ($e->errorInfo[1] ?? null) : null,
        'acceptance_authority' => false];
}
$sha = fw_put($dir . '/result.json', $out);
fw_put($dir . '/receipt.json', $base + ['state' => $out['state'], 'result_sha256' => $sha,
    'readback_verified' => hash_file('sha256', $dir . '/result.json') === $sha]);
echo fw_json(['state' => $out['state'], 'pair_count' => $out['pair_count'] ?? null,
    'result_sha256' => $sha, 'provider_calls' => 0, 'mapping_writes' => 0]);
exit($out['state'] === 'completed_read_only' ? 0 : 2);
