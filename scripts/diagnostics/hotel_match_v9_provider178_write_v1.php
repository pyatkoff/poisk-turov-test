<?php
declare(strict_types=1);
// Complete the original, never-executed provider178 operation; append-only, no HTTP.
const OP = 'hotel-match-v9-provider178-write-1971-20260921-v1';
const MIN_WRITE = 100;
const NS = [25 => 'operator_315', 43 => 'operator_342'];
const INPUT_HASHES = [
    'v9.json' => '0995ccbd0c14639a335a644748d6dc5af6b359345328698ea3c0fc1300d88814',
    'precommit178.json' => '8cb977214acf20bd93226103d752119d0615bb49692aa95191ede30bec4a0d1d',
    'committed328.json' => 'e7e8b2aa9210514629d2849307ee436608b283b22700dabb12020cea8fafe9f7',
];
function need(bool $ok, string $code): void { if (!$ok) throw new RuntimeException($code); }
function ordered(mixed $v): mixed {
    if (is_array($v)) { if (!array_is_list($v)) ksort($v, SORT_STRING); foreach ($v as &$x) $x = ordered($x); unset($x); }
    return $v;
}
function canon(mixed $v): string { return json_encode(ordered($v), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
function rh(array $v): string { return hash('sha256', canon($v) . "\n"); }
function loadj(string $path): array { $v = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); need(is_array($v), 'json'); return $v; }
function savej(string $path, array $v): string {
    $b = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $f = fopen($path, 'xb'); need(is_resource($f), 'exclusive_output');
    need(fwrite($f, $b) === strlen($b) && fflush($f), 'record_write');
    if (function_exists('fsync')) need(fsync($f), 'record_sync');
    fclose($f); $h = hash('sha256', $b); need(hash_file('sha256', $path) === $h, 'disk_readback'); return $h;
}
function keyOf(array $r): string { return $r['supplier_namespace'] . '|' . ($r['native_hotel_id'] ?? $r['external_hotel_id']); }
function pairOf(array $r): string { return $r['supplier_namespace'] . '|' . ($r['tv_hotel_id'] ?? $r['local_hotel_id']); }
function seeds(string $dir): array {
    $d = []; foreach (INPUT_HASHES as $name => $hash) {
        $path = $dir . '/' . $name; need(is_file($path) && !is_link($path) && hash_file('sha256', $path) === $hash, 'input_hash'); $d[$name] = loadj($path);
    }
    $v = $d['v9.json']; $pre = $d['precommit178.json']; $done = $d['committed328.json'];
    need($v['operation'] === 'hotel-match-v9-reconcile-1971-20260921-v1' && $v['state'] === 'completed_read_only_reconciliation' && count($v['source_result']['edges']) === 1456, 'v9');
    need($pre['operation'] === 'hotel-match-v9-provider178-precommit-audit-1971-20260921-v1' && $pre['state'] === 'completed_read_only' && $pre['current_core_safe'] === 178 && count($pre['rows']) === 178, 'precommit');
    need($done['operation'] === 'hotel-match-v9-provider328-write-1971-20260921-v1' && $done['state'] === 'committed_verified' && $done['mapping_writes'] === 328 && $done['readback_verified'] === true && count($done['written_rows']) === 328, 'committed_input');
    $dk = []; $dp = []; foreach ($done['written_rows'] as $r) { $dk[keyOf($r)] = true; $dp[pairOf($r)] = true; } need(count($dk) === 328, 'committed_keys');
    $native = []; $source = [];
    foreach ($v['source_result']['edges'] as $e) {
        if (!isset(NS[(int)$e['operator_id']])) continue;
        $ns = NS[(int)$e['operator_id']]; need(hash('sha256', $e['operator_link']) === $e['operator_link_sha256'], 'raw_link_hash');
        foreach ($e['positive_native_candidates'] as $id) $native[$ns . '|' . $id][(int)$e['tv_hotel_id']] = true;
        if ($e['link_state'] === 'captured_single_native') {
            need(count($e['positive_native_candidates']) === 1, 'single'); $k = $ns . '|' . $e['positive_native_candidates'][0] . '|' . $e['tv_hotel_id'];
            need(!isset($source[$k]), 'duplicate_source_edge'); $source[$k] = $e;
        }
    }
    $out = []; $keys = []; $pairs = [];
    foreach ($pre['rows'] as $r) {
        $key = keyOf($r); $pair = pairOf($r); $tv = (int)$r['tv_hotel_id']; $sk = $key . '|' . $tv;
        need($r['current_state'] === 'core_safe' && (NS[(int)$r['operator_id']] ?? null) === $r['supplier_namespace'], 'scope');
        need(!isset($dk[$key]) && !isset($dp[$pair]) && !isset($keys[$key]) && !isset($pairs[$pair]), 'overlap');
        need(isset($source[$sk]) && array_keys($native[$key]) === [$tv], 'global_native_conflict');
        $e = $source[$sk]; foreach (['operator_id', 'tour_id', 'batch', 'operator_link_sha256'] as $f) need((string)$r[$f] === (string)$e[$f], 'provenance');
        need((int)$r['current_target']['id'] === $tv && (string)$r['input_canonical_anchor']['external_hotel_id'] === (string)$r['canonical_anchor']['external_hotel_id'], 'target_anchor');
        $keys[$key] = true; $pairs[$pair] = true;
        $out[] = ['supplier_namespace' => $r['supplier_namespace'], 'native_hotel_id' => (string)$r['native_hotel_id'], 'tv_hotel_id' => $tv, 'expected_target' => $r['current_target'], 'expected_anchor' => $r['canonical_anchor'], 'source' => $e];
    }
    need(count($out) === 178 && count(array_unique(array_column($out, 'tv_hotel_id'))) === 150, 'cohort');
    usort($out, fn($a, $b) => strcmp(keyOf($a), keyOf($b))); return $out;
}
function query(PDO $db, string $sql, array $params = []): array { $st = $db->prepare($sql); $st->execute(array_values($params)); return $st->fetchAll(PDO::FETCH_ASSOC) ?: []; }
function factsEqual(array $a, array $b): bool {
    foreach (['id', 'name', 'country_id', 'country_name', 'region_name', 'subregion_name', 'category', 'is_active'] as $f) if ((string)($a[$f] ?? '') !== (string)($b[$f] ?? '')) return false;
    return true;
}
function indexed(array $rows): array {
    $keys = []; $targets = [];
    foreach ($rows as $r) { $k = keyOf($r); need(!isset($keys[$k]), 'duplicate_identity'); $keys[$k] = $r; if ($r['decision_status'] === 'accepted' && $r['local_hotel_id'] !== null) $targets[pairOf($r)][] = $r; }
    return [$keys, $targets];
}
function reason(array $s, array $keys, array $targets, array $hotels, array $manual): ?string {
    $tv = $s['tv_hotel_id']; $t = $hotels[$tv] ?? null; $aa = $targets['andromeda_catalog|' . $tv] ?? [];
    if (isset($keys[keyOf($s)])) return 'provider_source_present';
    if (!$t || (int)$t['is_active'] !== 1) return 'target_missing_or_inactive';
    if (preg_match('/^(россия|абхазия|russia|russian federation|abkhazia)$/iu', trim((string)$t['country_name']))) return 'excluded_country';
    if (!factsEqual($t, $s['expected_target'])) return 'target_facts_changed';
    if (isset($manual[$tv])) return 'manual_target_protected';
    if (isset($targets[$s['supplier_namespace'] . '|' . $tv])) return 'provider_target_occupied';
    if (count($aa) !== 1) return 'canonical_anchor_not_unique';
    $a = $aa[0]; $expected = $s['expected_anchor'];
    foreach (['external_hotel_id', 'catalog_sha256', 'evidence_sha256'] as $f) if ((string)$a[$f] !== (string)$expected[$f]) return 'canonical_anchor_changed';
    if (rh($a) !== $expected['row_sha256']) return 'canonical_anchor_row_changed';
    if (!preg_match('/^[0-9a-f]{64}$/D', $a['catalog_sha256']) || hash('sha256', $a['evidence_json']) !== $a['evidence_sha256']) return 'anchor_hash_invalid';
    return null;
}
function writeMappings(PDO $db, array $seeds, string $dir, string $sha): array {
    $base = ['operation' => OP, 'source_sha' => $sha, 'input_candidates' => count($seeds), 'provider_calls' => 0, 'tourvisor_calls' => 0, 'samo_calls' => 0, 'andromeda_calls' => 0];
    $commitAttempt = false; $committed = false; $inserted = [];
    $columns = 'supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json,created_at';
    $identitySql = 'SELECT ' . $columns . ' FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id';
    try {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $db->exec('SET SESSION innodb_lock_wait_timeout=15'); $db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE'); $db->beginTransaction();
        $tables = array_map('current', query($db, 'SHOW TABLES'));
        $guards = array_values(array_filter($tables, fn($t) => preg_match('/(?:andromeda|hotel).*(?:decision|review|exclusion|hold)|(?:decision|review|exclusion|hold).*(?:andromeda|hotel)/i', $t))); sort($guards);
        need($guards === ['anex_hotel_decisions'], 'guard_table_drift');
        $all = query($db, $identitySql . ' FOR UPDATE'); [$keys, $targets] = indexed($all);
        $ids = array_values(array_unique(array_column($seeds, 'tv_hotel_id'))); sort($ids, SORT_NUMERIC); $ph = implode(',', array_fill(0, count($ids), '?'));
        $hotelSql = "SELECT id,name,country_id,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id";
        $hotels = []; foreach (query($db, $hotelSql . ' FOR UPDATE', $ids) as $r) $hotels[(int)$r['id']] = $r;
        $decisions = query($db, 'SELECT anex_hotel_id,decision_status,catalog_hotel_id,decided_by,decision_note,decided_at,updated_at FROM anex_hotel_decisions ORDER BY anex_hotel_id FOR UPDATE');
        $manual = []; foreach ($decisions as $r) if ($r['catalog_hotel_id'] !== null) $manual[(int)$r['catalog_hotel_id']] = true;
        $safe = []; $holds = []; $counts = []; $expected = []; $before = [];
        foreach ($keys as $k => $r) $before[$k] = rh($r);
        foreach ($seeds as $s) {
            $why = reason($s, $keys, $targets, $hotels, $manual);
            if ($why !== null) { $holds[] = ['key' => keyOf($s), 'tv_hotel_id' => $s['tv_hotel_id'], 'reason' => $why]; $counts[$why] = ($counts[$why] ?? 0) + 1; continue; }
            $safe[] = $s; $expected[keyOf($s)] = ['anchor' => $targets['andromeda_catalog|' . $s['tv_hotel_id']][0], 'target' => $hotels[$s['tv_hotel_id']]];
        }
        ksort($counts);
        savej($dir . '/capture.json', $base + ['state' => 'current_locked_capture', 'safe_count' => count($safe), 'holds' => $holds, 'anchor_target_rows' => $expected, 'identity_rows_before' => count($all), 'identity_rows_hash' => rh($before), 'manual_rows_hash' => rh($decisions), 'guard_tables' => $guards]);
        savej($dir . '/plan.json', $base + ['state' => count($safe) >= MIN_WRITE ? 'ready_to_insert' : 'below_threshold', 'minimum' => MIN_WRITE, 'safe_keys' => array_map('keyOf', $safe), 'safe_count' => count($safe), 'hold_counts' => $counts]);
        if (count($safe) < MIN_WRITE) { $db->rollBack(); return $base + ['state' => 'completed_no_write_below_threshold', 'mapping_writes' => 0, 'database_writes' => 0, 'current_safe' => count($safe), 'holds' => $holds, 'hold_counts' => $counts, 'readback_verified' => false]; }
        $st = $db->prepare("INSERT INTO andromeda_hotel_identities (supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES (?,?,?,'accepted',?,?,?)");
        foreach ($safe as $s) {
            $k = keyOf($s); $a = $expected[$k]['anchor'];
            $evidence = ['operation_id' => OP, 'rule' => 'direct_saved_tourvisor_operator_link_and_independent_current_samo_target', 'source_sha' => $sha, 'input_hashes' => INPUT_HASHES, 'tv_hotel_id' => $s['tv_hotel_id'], 'supplier_namespace' => $s['supplier_namespace'], 'external_hotel_id' => $s['native_hotel_id'], 'source' => $s['source'], 'canonical_samo_anchor' => $a, 'provider_bridges' => [['andromeda_hotel_id' => (string)$a['external_hotel_id'], 'local_hotel_id' => $s['tv_hotel_id'], 'evidence_sha256' => $a['evidence_sha256']]], 'target' => $expected[$k]['target'], 'identity_separation' => 'native_operator_id_is_not_samo_id'];
            $ej = canon($evidence); $eh = hash('sha256', $ej);
            $st->execute([$s['supplier_namespace'], $s['native_hotel_id'], $s['tv_hotel_id'], $a['catalog_sha256'], $eh, $ej]); need($st->rowCount() === 1, 'insert_count');
            $inserted[$k] = ['supplier_namespace' => $s['supplier_namespace'], 'external_hotel_id' => $s['native_hotel_id'], 'local_hotel_id' => $s['tv_hotel_id'], 'decision_status' => 'accepted', 'catalog_sha256' => $a['catalog_sha256'], 'evidence_sha256' => $eh, 'evidence_json' => $ej];
        }
        [$after] = indexed(query($db, $identitySql)); need(count($after) === count($before) + count($safe), 'delta_count');
        foreach ($before as $k => $h) need(isset($after[$k]) && rh($after[$k]) === $h, 'existing_identity_changed');
        foreach ($inserted as $k => $x) foreach ($x as $field => $value) need((string)$after[$k][$field] === (string)$value, 'insert_readback');
        savej($dir . '/pre-commit.json', $base + ['state' => 'verified_before_commit', 'planned_writes' => count($safe), 'old_rows_preserved' => count($before), 'inserted_keys' => array_keys($inserted), 'hold_counts' => $counts]);
        savej($dir . '/commit-attempt.json', $base + ['state' => 'commit_attempt_no_replay', 'planned_writes' => count($safe)]);
        $commitAttempt = true; need($db->commit(), 'commit_false'); $committed = true;
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->exec('START TRANSACTION READ ONLY');
        [$post, $postTargets] = indexed(query($db, $identitySql)); $postHotels = [];
        foreach (query($db, $hotelSql, $ids) as $r) $postHotels[(int)$r['id']] = $r;
        $resolverRows = []; $written = [];
        foreach ($inserted as $k => $x) {
            need(isset($post[$k]), 'post_missing'); foreach ($x as $field => $value) need((string)$post[$k][$field] === (string)$value, 'post_row');
            $aa = $postTargets['andromeda_catalog|' . $x['local_hotel_id']] ?? []; need(count($aa) === 1 && rh($aa[0]) === rh($expected[$k]['anchor']), 'post_anchor');
            need(isset($postHotels[$x['local_hotel_id']]) && factsEqual($postHotels[$x['local_hotel_id']], $expected[$k]['target']), 'post_target');
            $resolverRows[] = ['supplier_namespace' => $x['supplier_namespace'], 'external_hotel_id' => $x['external_hotel_id'], 'decision_status' => 'accepted', 'catalog_hotel_id' => $x['local_hotel_id'], 'existing_catalog_hotel_id' => $postHotels[$x['local_hotel_id']]['id']];
            $written[] = ['supplier_namespace' => $x['supplier_namespace'], 'external_hotel_id' => $x['external_hotel_id'], 'local_hotel_id' => $x['local_hotel_id'], 'canonical_samo_id' => (string)$aa[0]['external_hotel_id'], 'evidence_sha256' => $x['evidence_sha256']];
        }
        $resolver = AnyTourAndromedaHotelResolver::fromRows($resolverRows, rh($resolverRows)); $offers = [];
        foreach ($written as $r) $offers[] = ['provider' => 'andromeda', 'selection_enabled' => false, 'local_hotel_id' => null, 'supplier_namespace' => $r['supplier_namespace'], 'external_hotel_id' => $r['external_hotel_id']];
        $page = $resolver->apply(['provider' => 'andromeda', 'selection_enabled' => false, 'offers' => $offers]);
        foreach ($page['offers'] as $i => $offer) need($offer['local_hotel_id'] === $written[$i]['local_hotel_id'], 'resolver_projection');
        $db->rollBack();
        return $base + ['state' => 'committed_verified', 'current_safe' => count($safe), 'mapping_writes' => count($safe), 'database_writes' => count($safe), 'post_commit_verified' => count($written), 'resolver_verified' => count($written), 'readback_verified' => true, 'old_rows_preserved' => count($before), 'hold_counts' => $counts, 'holds' => $holds, 'written_rows' => $written, 'written_unique_hotels' => count(array_unique(array_column($written, 'local_hotel_id')))];
    } catch (Throwable $e) {
        $rolledBack = false; try { if ($db->inTransaction()) $rolledBack = $db->rollBack(); } catch (Throwable) {}
        $state = $committed ? 'post_commit_verification_failed_no_replay' : ($commitAttempt ? 'commit_unknown_no_replay' : 'rolled_back_no_write');
        savej($dir . '/failure.json', $base + ['state' => $state, 'commit_attempted' => $commitAttempt, 'committed' => $committed, 'rollback_returned' => $rolledBack, 'attempted_inserts' => count($inserted), 'mapping_writes' => $commitAttempt ? null : 0, 'database_writes' => $commitAttempt ? null : 0, 'error_class' => get_class($e)]); throw $e;
    }
}
function selfTest(): void {
    need(canon(['b' => 1, 'a' => 2]) === '{"a":2,"b":1}', 'canonical_json');
    $a = ['external_hotel_id' => '7', 'catalog_sha256' => str_repeat('a', 64), 'evidence_sha256' => hash('sha256', '{}'), 'evidence_json' => '{}'];
    $s = ['supplier_namespace' => 'operator_315', 'native_hotel_id' => '1', 'tv_hotel_id' => 9, 'expected_target' => ['id' => 9, 'name' => 'A', 'is_active' => 1, 'country_name' => 'Турция'], 'expected_anchor' => $a + ['row_sha256' => rh($a)]];
    $h = [9 => $s['expected_target']]; $t = ['andromeda_catalog|9' => [$a]];
    need(reason($s, [], $t, $h, []) === null, 'safe');
    need(reason($s, ['operator_315|1' => []], $t, $h, []) === 'provider_source_present', 'occupied');
    need(reason($s, [], $t, $h, [9 => true]) === 'manual_target_protected', 'manual');
    need(reason($s, [], ['andromeda_catalog|9' => [$a, $a]], $h, []) === 'canonical_anchor_not_unique', 'ambiguous');
    $a['created_at'] = 'changed'; need(reason($s, [], ['andromeda_catalog|9' => [$a]], $h, []) === 'canonical_anchor_row_changed', 'row_drift');
    echo "PROVIDER178_SELFTEST_OK\n";
}
if (defined('MATCH_UNIT_TEST')) return;
need(PHP_SAPI === 'cli', 'cli_only'); $mode = $argv[1] ?? '';
if ($mode === '--self-test') { selfTest(); exit; }
need(in_array($mode, ['--plan', '--execute'], true), 'disabled');
$input = realpath((string)getenv('MATCH_INPUT_DIR')); need(is_string($input), 'input_directory'); $seeds = seeds($input);
if ($mode === '--plan') { echo json_encode(['operation' => OP, 'candidates' => count($seeds), 'unique_hotels' => count(array_unique(array_column($seeds, 'tv_hotel_id'))), 'keys' => array_map('keyOf', $seeds), 'supplier_calls' => 0, 'database_writes' => 0], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"; exit; }
$root = realpath((string)getenv('ANYTOUR_ROOT')); $dir = realpath((string)getenv('MATCH_OPERATION_DIR')); $sha = (string)getenv('MATCH_SOURCE_SHA');
need(is_string($root) && basename($root) === 'anytoour.ru' && is_string($dir) && basename($dir) === OP && preg_match('/^[a-f0-9]{40}$/D', $sha) === 1, 'scope');
$res = loadj($input . '/reservation.json'); need($res['operation'] === OP && $res['source_sha'] === $sha && $res['script_sha256'] === hash_file('sha256', __FILE__) && $res['state'] === 'reserved_before_db' && $res['candidate_keys'] === array_map('keyOf', $seeds), 'reservation');
savej($dir . '/started.json', ['operation' => OP, 'state' => 'started_no_replay', 'source_sha' => $sha]);
require_once __DIR__ . '/andromeda-hotel-resolver.php';
require_once $root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
try {
    $result = writeMappings(v2_data_db(), $seeds, $dir, $sha); $h = savej($dir . '/result.json', $result);
    savej($dir . '/receipt.json', ['operation' => OP, 'source_sha' => $sha, 'state' => $result['state'], 'result_sha256' => $h, 'mapping_writes' => $result['mapping_writes'], 'database_writes' => $result['database_writes'], 'readback_verified' => $result['readback_verified'], 'provider_calls' => 0, 'no_replay' => true]);
    echo json_encode(['state' => $result['state'], 'mapping_writes' => $result['mapping_writes'], 'hold_counts' => $result['hold_counts'], 'result_sha256' => $h], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable) { fwrite(STDERR, "MATCH_OPERATION_FAILED_NO_REPLAY\n"); exit(1); }
