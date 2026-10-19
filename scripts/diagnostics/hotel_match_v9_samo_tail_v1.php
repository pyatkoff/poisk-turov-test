<?php
declare(strict_types=1);

// Three bounded, disjoint successors of the immutable saved-provider-bridge receiver.
// No supplier access and no identity writes. A missing bridge is not a SAMO miss.
const BUNDLE = 'hotel-match-v9-samo-tail-1971-20260921-v1';
const INPUTS = [
    'v9-reconciliation.json' => ['0995ccbd0c14639a335a644748d6dc5af6b359345328698ea3c0fc1300d88814', 'hotel-match-v9-reconcile-1971-20260921-v1', 'completed_read_only_reconciliation'],
    'chunk1-result.json' => ['488b3b9c28cb6f6fd429a6a1925e86217b90022d9372b142ba6e3981c77bc351', 'hotel-match-v9-single-native-samo-chunk1-1971-20260921-v1', 'completed_read_only'],
    'chunk2-result.json' => ['3e5893d0e8580f3060cd1e8d947b1fa10e99d1e1f6a2398a6dc8617121ac1ba2', 'hotel-match-v9-single-native-samo-chunk2-1971-20260921-v1', 'completed_read_only'],
    'chunk3-result.json' => ['26c42c8910a396deb1359cd29998a827d165fb7c16329e862edbfcd6bad8cf44', 'hotel-match-v9-single-native-samo-chunk3-1971-20260921-v2', 'completed_read_only'],
    'chunk4-result.json' => ['6ddedb9545a2715f34f7994f201e35336665cf0ee3c3a0500bd77949c1de2d70', 'hotel-match-v9-single-native-samo-chunk4-1971-20260921-v5', 'completed_read_only'],
];
const NS = [25 => 'operator_315', 43 => 'operator_342'];
function need(bool $ok, string $code): void { if (!$ok) throw new RuntimeException($code); }
function decode(string $s): array { $v = json_decode($s, true, 512, JSON_THROW_ON_ERROR); need(is_array($v), 'json_object'); return $v; }
function num(mixed $v): ?string { if (!is_scalar($v)) return null; $s = trim((string)$v); return preg_match('/^[1-9][0-9]{0,18}$/D', $s) ? $s : null; }
function bridges(mixed $v): array {
    try { $e = is_array($v) ? $v : (is_string($v) && trim($v) !== '' ? decode($v) : []); } catch (Throwable) { return []; }
    $out = [];
    foreach ((array)($e['provider_bridges'] ?? []) as $b) {
        if (!is_array($b)) continue;
        $id = num($b['andromeda_hotel_id'] ?? null);
        if ($id !== null) $out[$id] = true;
    }
    return array_map('strval', array_keys($out));
}
function providerClass(array $rows, int $tv): string {
    if (!$rows) return 'provider_missing';
    if (count($rows) !== 1) return 'provider_multiple';
    $r = $rows[0]; $s = (string)$r['decision_status']; $local = $r['local_hotel_id'] === null ? null : (int)$r['local_hotel_id'];
    if ($s === 'accepted') return $local === $tv ? 'provider_accepted_same_target' : 'provider_accepted_other_target';
    if ($s === 'pending') return $local === null || $local === $tv ? 'provider_pending_same_or_unassigned' : 'provider_pending_other_target';
    return 'provider_' . $s;
}
function bridgeClass(array $ids, array $rows, int $tv): string {
    if (!$ids) return 'no_saved_provider_bridge';
    if (count($ids) !== 1) return 'multiple_saved_provider_bridges';
    if (!$rows) return 'bridge_unanchored';
    if (count($rows) !== 1) return 'bridge_multiple_current_rows';
    $r = $rows[0];
    if ($r['decision_status'] === 'accepted') return (int)$r['local_hotel_id'] === $tv ? 'bridge_accepted_same_target' : 'bridge_accepted_other_target';
    return 'bridge_' . $r['decision_status'];
}
function plan(string $input): array {
    $docs = []; $skip = [];
    foreach (INPUTS as $name => [$sha, $operation, $state]) {
        $path = $input . '/' . $name;
        need(is_file($path) && !is_link($path) && hash_file('sha256', $path) === $sha, 'input_hash');
        $d = decode((string)file_get_contents($path));
        need(($d['operation'] ?? '') === $operation && ($d['state'] ?? '') === $state, 'input_operation_state');
        $docs[$name] = $d;
        if ($name === 'v9-reconciliation.json') continue;
        $ids = $d['selected_hotel_ids'] ?? [];
        need(count($ids) === ($d['selected_unique_hotels'] ?? -1), 'prior_count');
        foreach ($ids as $id) { need(num($id) !== null && !isset($skip[(int)$id]), 'prior_overlap'); $skip[(int)$id] = true; }
    }
    need(count($skip) === 305, 'prior305');
    $v = $docs['v9-reconciliation.json']; $country = [];
    foreach ($v['attempted_batches'] as $b) foreach ($b['hotel_ids'] as $id) {
        need(!isset($country[(int)$id]) || $country[(int)$id] === (int)$b['country_id'], 'country_conflict');
        $country[(int)$id] = (int)$b['country_id'];
    }
    $all = []; $hotels = [];
    foreach ($v['source_result']['edges'] as $e) {
        $op = (int)($e['operator_id'] ?? 0);
        if (($e['link_state'] ?? '') !== 'captured_single_native' || !isset(NS[$op])) continue;
        $tv = (int)$e['tv_hotel_id']; $candidates = $e['positive_native_candidates'] ?? [];
        need(count($candidates) === 1 && num($candidates[0]) !== null && isset($country[$tv]), 'edge');
        $all[] = ['tv_hotel_id' => $tv, 'country_id' => $country[$tv], 'operator_id' => $op, 'supplier_namespace' => NS[$op], 'native_hotel_id' => num($candidates[0]), 'batch' => (int)$e['batch'], 'tour_id' => (string)$e['tour_id'], 'operator_link_sha256' => (string)($e['operator_link_sha256'] ?? '')];
        $hotels[$tv] = true;
    }
    need(count($all) === 532 && count($hotels) === 432 && !array_diff_key($skip, $hotels), 'frontier');
    $remaining = array_values(array_filter($all, fn($e) => !isset($skip[$e['tv_hotel_id']])));
    $groups = [];
    foreach ($remaining as $e) $groups[$e['country_id']][$e['tv_hotel_id']] = true;
    ksort($groups, SORT_NUMERIC);
    need(array_keys($groups) === [9, 16], 'remaining_countries');
    foreach ($groups as &$g) { $g = array_keys($g); sort($g, SORT_NUMERIC); } unset($g);
    need(count($groups[9]) === 23 && count($groups[16]) === 104, 'remaining_counts');
    $slices = [5 => [9, $groups[9]], 6 => [16, array_slice($groups[16], 0, 90)], 7 => [16, array_slice($groups[16], 90)]];
    $chunks = []; $seen = $skip;
    foreach ($slices as $n => [$c, $ids]) {
        need(count($ids) > 0 && count($ids) <= 90, 'bounded_chunk');
        foreach ($ids as $id) { need(!isset($seen[$id]), 'chunk_overlap'); $seen[$id] = true; }
        $set = array_fill_keys($ids, true);
        $chunks[$n] = ['operation' => 'hotel-match-v9-single-native-samo-chunk' . $n . '-1971-20260921-v1', 'selected_country_id' => $c, 'selected_unique_hotels' => count($ids), 'selected_hotel_ids' => $ids, 'edges' => array_values(array_filter($remaining, fn($e) => isset($set[$e['tv_hotel_id']])))];
    }
    need(count($seen) === 432, 'complete_membership');
    return ['bundle' => BUNDLE, 'prior_selected_unique_hotels' => 305, 'remaining_unique_hotels' => 127, 'source_single_native_edges' => 532, 'chunks' => $chunks];
}
function wr(string $path, array $value): string {
    $body = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $f = fopen($path, 'xb'); need(is_resource($f), 'exclusive_output');
    need(fwrite($f, $body) === strlen($body) && fflush($f), 'output_write');
    if (function_exists('fsync')) need(fsync($f), 'output_sync');
    fclose($f); return hash('sha256', $body);
}
function q(PDO $db, string $sql, array $params): array { $s = $db->prepare($sql); $s->execute(array_values($params)); return $s->fetchAll(PDO::FETCH_ASSOC) ?: []; }
function selfTest(): void {
    need(num('7') === '7' && num('0') === null && num([]) === null, 'test_num');
    need(bridges(['provider_bridges' => [['andromeda_hotel_id' => '7'], ['andromeda_hotel_id' => 7]]]) === ['7'], 'test_bridge_dedupe');
    need(bridges('broken') === [] && bridges(['provider_bridges' => [['andromeda_hotel_id' => '0']]]) === [], 'test_invalid_bridge');
    need(providerClass([], 9) === 'provider_missing', 'test_missing');
    need(providerClass([['decision_status' => 'pending', 'local_hotel_id' => null]], 9) === 'provider_pending_same_or_unassigned', 'test_pending');
    need(providerClass([['decision_status' => 'accepted', 'local_hotel_id' => 10]], 9) === 'provider_accepted_other_target', 'test_provider_conflict');
    need(bridgeClass(['7'], [['decision_status' => 'accepted', 'local_hotel_id' => 9]], 9) === 'bridge_accepted_same_target', 'test_same');
    need(bridgeClass(['7'], [['decision_status' => 'accepted', 'local_hotel_id' => 10]], 9) === 'bridge_accepted_other_target', 'test_conflict');
    need(bridgeClass(['7', '8'], [], 9) === 'multiple_saved_provider_bridges' && bridgeClass([], [], 9) === 'no_saved_provider_bridge', 'test_ambiguous');
    echo "MATCH_SAMO_TAIL_SELFTEST_OK\n";
}
need(PHP_SAPI === 'cli', 'cli_only');
$mode = $argv[1] ?? '';
if ($mode === '--self-test') { selfTest(); exit; }
need(in_array($mode, ['--plan', '--execute'], true), 'disabled');
$input = realpath((string)getenv('MATCH_INPUT_DIR')); need(is_string($input), 'input_dir');
$p = plan($input);
if ($mode === '--plan') { echo json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"; exit; }
need(isset($argv[2]) && in_array($argv[2], ['5', '6', '7'], true), 'chunk_selector');
$chunk = $p['chunks'][(int)$argv[2]]; $op = $chunk['operation'];
$dir = realpath((string)getenv('MATCH_OPERATION_DIR')); $root = realpath((string)getenv('ANYTOUR_ROOT'));
need(is_string($dir) && basename($dir) === $op && is_string($root) && basename($root) === 'anytoour.ru', 'scope');
$reservation = decode((string)file_get_contents($input . '/reservation.json'));
need(($reservation['operation'] ?? '') === $op && ($reservation['state'] ?? '') === 'reserved_before_db' && ($reservation['script_sha256'] ?? '') === hash_file('sha256', __FILE__), 'reservation');
wr($dir . '/started.json', ['operation' => $op, 'state' => 'current_read_starting_no_replay', 'at_utc' => gmdate('c')]);
$db = null;
try {
    require_once $root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
    $db = v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $providers = []; $anchors = []; $bridgeIds = [];
    foreach ($chunk['edges'] as $e) {
        $key = $e['supplier_namespace'] . '|' . $e['native_hotel_id'];
        if (isset($providers[$key])) continue;
        $providers[$key] = q($db, 'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_json,evidence_sha256,catalog_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace=? AND external_hotel_id=?', [$e['supplier_namespace'], $e['native_hotel_id']]);
        foreach ($providers[$key] as $r) foreach (bridges($r['evidence_json']) as $id) $bridgeIds[$id] = true;
    }
    foreach (array_keys($bridgeIds) as $id) $anchors[$id] = q($db, "SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256,catalog_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?", [$id]);
    $ids = $chunk['selected_hotel_ids']; $ph = implode(',', array_fill(0, count($ids), '?')); $targets = []; $occupants = [];
    foreach (q($db, "SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph)", $ids) as $r) $targets[(int)$r['id']] = $r;
    foreach (q($db, "SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IN ($ph) ORDER BY local_hotel_id,supplier_namespace,external_hotel_id", $ids) as $r) $occupants[(int)$r['local_hotel_id']][] = $r;
    $db->rollBack();
    $rows = []; $pc = []; $bc = [];
    foreach ($chunk['edges'] as $e) {
        $prs = $providers[$e['supplier_namespace'] . '|' . $e['native_hotel_id']]; $bset = [];
        foreach ($prs as $r) foreach (bridges($r['evidence_json']) as $id) $bset[$id] = true;
        $bids = array_map('strval', array_keys($bset)); $ars = [];
        foreach ($bids as $id) foreach ($anchors[$id] ?? [] as $a) $ars[] = $a;
        $provider = providerClass($prs, $e['tv_hotel_id']); $bridge = bridgeClass($bids, $ars, $e['tv_hotel_id']);
        $pc[$provider] = ($pc[$provider] ?? 0) + 1; $bc[$bridge] = ($bc[$bridge] ?? 0) + 1;
        $rows[] = $e + ['target' => $targets[$e['tv_hotel_id']] ?? null, 'target_occupants' => $occupants[$e['tv_hotel_id']] ?? [], 'provider_classification' => $provider, 'saved_bridge_ids' => $bids, 'bridge_classification' => $bridge, 'safe_to_write_now' => false];
    }
    ksort($pc); ksort($bc); unset($chunk['edges']);
    $result = $chunk + ['bundle' => BUNDLE, 'state' => 'completed_read_only', 'selected_edges' => count($rows), 'prior_selected_unique_hotels' => 305, 'provider_classification_counts' => $pc, 'bridge_classification_counts' => $bc, 'rows' => $rows, 'provider_calls' => 0, 'tourvisor_calls' => 0, 'samo_calls' => 0, 'andromeda_calls' => 0, 'database_writes' => 0, 'mapping_writes' => 0, 'safe_to_write_now' => false];
    $sha = wr($dir . '/result.json', $result);
    wr($dir . '/receipt.json', ['operation' => $op, 'state' => 'completed_read_only', 'result_sha256' => $sha, 'selected_unique_hotels' => count($ids), 'selected_edges' => count($rows), 'provider_calls' => 0, 'database_writes' => 0, 'mapping_writes' => 0, 'no_replay' => true]);
    echo json_encode(['operation' => $op, 'hotels' => count($ids), 'edges' => count($rows), 'provider' => $pc, 'bridge' => $bc, 'result_sha256' => $sha], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    if (!is_file($dir . '/receipt.json')) wr($dir . '/failure.json', ['operation' => $op, 'state' => 'failed_read_only_no_replay', 'error_class' => get_class($e), 'provider_calls' => 0, 'database_writes' => 0, 'mapping_writes' => 0]);
    fwrite(STDERR, "MATCH_CURRENT_READ_FAILED_NO_REPLAY\n"); exit(1);
}
