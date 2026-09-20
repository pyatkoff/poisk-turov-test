<?php
declare(strict_types=1);

const OP = 'hotel-match-detail251-current-bridge-audit-1971-20260921-v1';
const PROVIDERS = [25 => 'operator_315', 43 => 'operator_342'];

function need(bool $ok, string $reason): void { if (!$ok) throw new RuntimeException($reason); }
function rows(PDO $db, string $sql, array $params = []): array {
    $s = $db->prepare($sql); $s->execute(array_values($params));
    return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function write_json(string $path, array $value): string {
    $bytes = json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n";
    $f = fopen($path, 'xb'); need(is_resource($f), 'open_result');
    need(fwrite($f, $bytes) === strlen($bytes), 'short_write'); fflush($f); if (function_exists('fsync')) fsync($f); fclose($f);
    return hash('sha256', $bytes);
}
function pos_int(mixed $v): ?string {
    $s = (string)$v; return preg_match('/^[1-9][0-9]{0,15}$/D', $s) ? $s : null;
}

if (($argv[1] ?? '') === '--self-test') {
    need(pos_int('123') === '123' && pos_int('0') === null && PROVIDERS[25] === 'operator_315', 'selftest');
    echo "DETAIL251_CURRENT_AUDIT_SELFTEST_OK\n"; exit(0);
}
need(PHP_SAPI === 'cli' && ($argv[1] ?? '') === '--execute', 'disabled');
$root = realpath((string)getenv('ANYTOUR_ROOT'));
$detailPath = realpath((string)getenv('MATCH_DETAIL_PATH'));
$opdir = (string)getenv('MATCH_OPERATION_DIR');
need(is_string($root) && basename($root) === 'anytoour.ru' && is_string($detailPath) && is_dir($opdir) && basename($opdir) === OP, 'runtime');
need(hash_file('sha256', $detailPath) === '2534eebc2a8b0ba79dbf32dedda165c7e87da609284b25c5209c04747624e564', 'detail_hash');
$detail = json_decode((string)file_get_contents($detailPath), true, 256, JSON_THROW_ON_ERROR);
need(($detail['operation'] ?? '') === 'hotel-match-residual2041-common4-nonanex-detail-1971-20260921-v4', 'detail_operation');

$input = [];
foreach (($detail['edges'] ?? []) as $e) {
    if (!is_array($e) || ($e['link_state'] ?? '') !== 'captured_single_native') continue;
    $op = (int)($e['operator_id'] ?? 0); if (!isset(PROVIDERS[$op])) continue;
    $tokens = array_values(array_unique(array_filter(array_map('pos_int', (array)($e['positive_native_candidates'] ?? [])))));
    need(count($tokens) === 1, 'single_native_shape');
    $tv = (int)($e['tv_hotel_id'] ?? 0); need($tv > 0, 'tv_id');
    $k = $op . '|' . $tokens[0];
    need(!isset($input[$k]), 'duplicate_provider_native');
    $input[$k] = [
        'tv_hotel_id' => $tv,
        'operator_id' => $op,
        'supplier_namespace' => PROVIDERS[$op],
        'native_hotel_id' => $tokens[0],
        'hotel_name' => (string)($e['hotel_name'] ?? ''),
        'operator_link_sha256' => $e['operator_link_sha256'] ?? null,
        'tour_id' => (string)($e['tour_id'] ?? ''),
        'search_id' => (string)($e['search_id'] ?? ''),
    ];
}
need(count($input) === 251, 'input_251');

require_once $root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
$db = v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
try {
    $providerRows = [];
    foreach (PROVIDERS as $op => $ns) {
        $ids = [];
        foreach ($input as $x) if ($x['operator_id'] === $op) $ids[] = $x['native_hotel_id'];
        foreach (array_chunk($ids, 400) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            $params = array_merge([$ns], $chunk);
            foreach (rows($db, "SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace=? AND external_hotel_id IN ($ph)", $params) as $r) {
                $providerRows[$ns . '|' . (string)$r['external_hotel_id']][] = $r;
            }
        }
    }
    $tvIds = array_values(array_unique(array_map(fn($x) => $x['tv_hotel_id'], $input)));
    $occupants = [];
    $hotels = [];
    foreach (array_chunk($tvIds, 400) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        foreach (rows($db, "SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IN ($ph)", $chunk) as $r) {
            $occupants[(int)$r['local_hotel_id']][] = $r;
        }
        foreach (rows($db, "SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph)", $chunk) as $r) {
            $hotels[(int)$r['id']] = $r;
        }
    }
    $db->rollBack();

    $outRows = []; $counts = [];
    foreach ($input as $key => $x) {
        $pk = $x['supplier_namespace'] . '|' . $x['native_hotel_id'];
        $matches = $providerRows[$pk] ?? [];
        $state = 'no_current_provider_namespace_evidence';
        $reasons = [];
        if (count($matches) === 1) {
            $m = $matches[0];
            $local = isset($m['local_hotel_id']) ? (int)$m['local_hotel_id'] : 0;
            $decision = (string)($m['decision_status'] ?? '');
            if ($decision === 'accepted' && $local === $x['tv_hotel_id']) $state = 'already_accepted_same_target';
            elseif ($decision === 'accepted' && $local > 0) { $state = 'accepted_other_target_conflict'; $reasons[] = 'provider_native_accepted_other_target'; }
            elseif ($decision === 'pending' && ($local === 0 || $local === $x['tv_hotel_id'])) $state = 'current_pending_same_or_unassigned';
            else { $state = 'current_provider_row_protected'; $reasons[] = 'provider_row_nonacceptance_state'; }
        } elseif (count($matches) > 1) {
            $state = 'multiple_current_provider_rows'; $reasons[] = 'provider_native_not_unique';
        }
        $target = $hotels[$x['tv_hotel_id']] ?? null;
        if (!$target || (int)($target['is_active'] ?? 0) !== 1) $reasons[] = 'target_inactive_or_missing';
        $counts[$state] = ($counts[$state] ?? 0) + 1;
        $outRows[] = $x + [
            'classification' => $state,
            'current_provider_rows' => $matches,
            'current_target' => $target,
            'current_target_occupants' => $occupants[$x['tv_hotel_id']] ?? [],
            'reasons' => array_values(array_unique($reasons)),
            'safe_to_write_now' => false,
        ];
    }
    ksort($counts);
    $result = [
        'operation' => OP,
        'state' => 'completed_read_only',
        'input_edges' => 251,
        'unique_tv_hotels' => count(array_unique(array_column($outRows, 'tv_hotel_id'))),
        'classification_counts' => $counts,
        'rows' => $outRows,
        'provider_calls' => 0,
        'tourvisor_calls' => 0,
        'samo_calls' => 0,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'safe_to_write_now' => false,
    ];
    $rh = write_json($opdir . '/result.json', $result);
    write_json($opdir . '/receipt.json', ['operation'=>OP,'state'=>'completed_read_only','result_sha256'=>$rh,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
    echo json_encode(['classification_counts'=>$counts,'result_sha256'=>$rh], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack(); throw $e;
}
