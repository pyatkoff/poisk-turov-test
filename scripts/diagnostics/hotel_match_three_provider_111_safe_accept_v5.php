<?php
declare(strict_types=1);

const M111A_OPERATION = 'hotel-match-three-provider-111-safe-accept-1971-20260911-v5';
const M111A_EXTERNAL = '2000063066';
const M111A_TARGET = 73475;
const M111A_ANEX = 29695;
const M111A_POLICY = 'owner_exact_and_strong_20260908';

function m111a_out(array $v): void {
    echo 'MATCH111A_JSON:' . json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . PHP_EOL;
}
function m111a_key(string $v): string {
    $v = str_replace(['Ё','ё'], ['Е','е'], $v);
    $v = function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
    $v = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $v) ?? $v;
    $generic = array_fill_keys(['hotel','hotels','otel','отель','отели','resort','resorts','spa','спа'], true);
    $out = [];
    foreach (preg_split('/\s+/u', trim($v)) ?: [] as $x) if ($x !== '' && !isset($generic[$x])) $out[] = $x;
    sort($out, SORT_STRING);
    return implode(' ', array_values(array_unique($out)));
}
function m111a_evidence(string $raw): array {
    if (trim($raw) === '') return [];
    try { $v = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); return is_array($v) ? $v : ['legacy_scalar' => $v]; }
    catch (Throwable $e) { return ['legacy_raw_evidence_json' => $raw]; }
}
function m111a_has_name(array $v, string $wanted): bool {
    foreach ($v as $k => $x) {
        if (is_array($x) && m111a_has_name($x, $wanted)) return true;
        if (!is_scalar($x)) continue;
        if (in_array((string)$k, ['name','lName','hotel_name','hotelName','title'], true) && m111a_key((string)$x) === $wanted) return true;
    }
    return false;
}
function m111a_num($v): ?float {
    if ($v === null || $v === '') return null;
    $x = (float)$v;
    return is_finite($x) && $x != 0.0 ? $x : null;
}
function m111a_coord(array $v): array {
    foreach ([['latitude','longitude'],['lat','lng'],['lat','lon'],['hotelLatitude','hotelLongitude']] as $k) {
        if (array_key_exists($k[0], $v) && array_key_exists($k[1], $v)) {
            $a = m111a_num($v[$k[0]]); $b = m111a_num($v[$k[1]]);
            if ($a !== null && $b !== null && abs($a) <= 90 && abs($b) <= 180) return [$a,$b];
        }
    }
    foreach ($v as $x) if (is_array($x)) { [$a,$b] = m111a_coord($x); if ($a !== null) return [$a,$b]; }
    return [null,null];
}
function m111a_distance($a,$b,$c,$d): ?float {
    $p = [m111a_num($a),m111a_num($b),m111a_num($c),m111a_num($d)];
    if (in_array(null, $p, true)) return null;
    $p = array_map('deg2rad', $p); [$a,$b,$c,$d] = $p;
    $x = sin(($c-$a)/2)**2 + cos($a)*cos($c)*sin(($d-$b)/2)**2;
    return 6371000 * 2 * asin(min(1, sqrt($x)));
}
function m111a_bridge(PDO $db, bool $lock): array {
    $suffix = $lock ? ' FOR UPDATE' : '';
    $q = $db->prepare('SELECT decision_status,catalog_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id=?' . $suffix);
    $q->execute([M111A_ANEX]); $d = $q->fetchAll(PDO::FETCH_ASSOC);
    if ($d) {
        if (count($d) !== 1 || (string)$d[0]['decision_status'] !== 'accepted' || (int)$d[0]['catalog_hotel_id'] !== M111A_TARGET) throw new RuntimeException('anex_manual_protected_other');
        return ['kind'=>'manual_accepted_exact','target'=>M111A_TARGET];
    }
    $q = $db->prepare('SELECT catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id=?' . $suffix);
    $q->execute([M111A_ANEX]); foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $x) if ((int)$x === M111A_TARGET) throw new RuntimeException('anex_pair_exclusion_protected');
    $q = $db->prepare('SELECT catalog_hotel_id,enabled,scope,approval_policy FROM anex_hotel_search_mappings WHERE anex_hotel_id=? ORDER BY catalog_hotel_id' . $suffix);
    $q->execute([M111A_ANEX]); $exact = 0; $other = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $active = (int)$r['enabled'] === 1 && (string)$r['scope'] === 'preview' && (string)$r['approval_policy'] === M111A_POLICY;
        if (!$active) continue;
        $local = (int)$r['catalog_hotel_id']; if ($local === M111A_TARGET) $exact++; else $other[] = $local;
    }
    if ($exact !== 1 || $other) throw new RuntimeException('anex_current_exact_bridge_changed');
    return ['kind'=>'policy_mapping_exact','target'=>M111A_TARGET,'active_exact_rows'=>1];
}
function m111a_counts(PDO $db): array {
    $accepted = (int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted'")->fetchColumn();
    $pending = (int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending'")->fetchColumn();
    $triple = (int)$db->query("SELECT COUNT(DISTINCT i.local_hotel_id) FROM andromeda_hotel_identities i WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL AND (EXISTS(SELECT 1 FROM anex_hotel_search_mappings m LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.catalog_hotel_id=i.local_hotel_id AND m.enabled=1 AND m.scope='preview' AND m.approval_policy='owner_exact_and_strong_20260908' AND d.anex_hotel_id IS NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)) OR EXISTS(SELECT 1 FROM anex_hotel_decisions d WHERE d.catalog_hotel_id=i.local_hotel_id AND d.decision_status='accepted' AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)))")->fetchColumn();
    return ['andromeda_accepted'=>$accepted,'andromeda_pending'=>$pending,'all_three'=>$triple];
}

try {
    ini_set('display_errors','0'); ini_set('log_errors','0');
    $root = realpath(getcwd()); if (!$root || basename($root) !== 'anytoour.ru') throw new RuntimeException('server_root_invalid');
    require_once $root . '/config.php';
    $helper = is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php'; require_once $helper;
    $db = v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $engine = $db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='andromeda_hotel_identities'")->fetchColumn();
    if ($engine === false || strcasecmp((string)$engine, 'InnoDB') !== 0) throw new RuntimeException('identity_table_not_transactional');
    $before = m111a_counts($db); $newSha = null; $distance = null;
    $db->exec('SET SESSION innodb_lock_wait_timeout=20'); $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->beginTransaction();
    try {
        $q = $db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? FOR UPDATE");
        $q->execute([M111A_EXTERNAL]); $id = $q->fetch(PDO::FETCH_ASSOC); if (!$id || $q->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('andromeda_identity_not_unique');
        if ((string)$id['decision_status'] !== 'pending' || $id['local_hotel_id'] !== null) throw new RuntimeException('andromeda_state_changed');
        $q = $db->prepare("SELECT external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id=? AND external_hotel_id<>? FOR UPDATE");
        $q->execute([M111A_TARGET,M111A_EXTERNAL]); if ($q->fetchColumn() !== false) throw new RuntimeException('same_provider_target_occupied');
        $q = $db->prepare('SELECT name,country_id,latitude,longitude FROM catalog_hotels WHERE id=? FOR UPDATE'); $q->execute([M111A_TARGET]); $target = $q->fetch(PDO::FETCH_ASSOC);
        if (!$target || (int)$target['country_id'] !== 4 || m111a_key((string)$target['name']) !== 'megaron') throw new RuntimeException('catalog_target_changed');
        $bridge = m111a_bridge($db, true);
        $prior = m111a_evidence((string)($id['evidence_json'] ?? '')); if (!m111a_has_name($prior, 'megaron')) throw new RuntimeException('current_andromeda_name_changed');
        [$slat,$slon] = m111a_coord($prior); $distance = m111a_distance($slat,$slon,$target['latitude'] ?? null,$target['longitude'] ?? null);
        if ($distance !== null && $distance > 5000) throw new RuntimeException('coordinate_conflict_gt_5km');
        $evidence = ['prior_evidence'=>$prior,'promotion'=>['operation_id'=>M111A_OPERATION,'lane'=>'MATCH','rule'=>'synchronized_three_provider_exact_plus_current_anex_bridge','server_current'=>true,'source_evidence_run_id'=>34623973480,'source_evidence_artifact_id'=>10274360025,'reconcile_run_id'=>34624801097,'andromeda_external_id'=>M111A_EXTERNAL,'target_local_hotel_id'=>M111A_TARGET,'anex_hotel_id'=>M111A_ANEX,'synchronized_search'=>['country'=>'Turkey','departure'=>'Moscow','date'=>'2026-10-23','nights'=>7,'adults'=>2],'names'=>['andromeda'=>'Megaron Hotel','anex'=>'Megaron Hotel','tourvisor'=>'MEGARON'],'identity_key'=>'megaron','current_anex_bridge'=>$bridge,'distance_m'=>$distance===null?null:(int)round($distance),'coordinate_guard_m'=>5000]];
        $json = json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR); $newSha = hash('sha256',$json);
        $u = $db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256 <=> ?");
        $u->execute([M111A_TARGET,$newSha,$json,M111A_EXTERNAL,$id['evidence_sha256']]); if ($u->rowCount() !== 1) throw new RuntimeException('concurrency_guard_failed');
        $db->commit();
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    $q = $db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");
    $q->execute([M111A_EXTERNAL]); $read = $q->fetch(PDO::FETCH_ASSOC);
    if (!$read || (int)$read['local_hotel_id'] !== M111A_TARGET || (string)$read['decision_status'] !== 'accepted' || !hash_equals((string)$newSha,(string)$read['evidence_sha256'])) throw new RuntimeException('post_commit_identity_readback_failed');
    $bridgeRead = m111a_bridge($db, false); $after = m111a_counts($db);
    m111a_out(['status'=>'committed_readback_verified','operation_id'=>M111A_OPERATION,'database_writes'=>1,'mapping_writes'=>1,'supplier_calls'=>0,'booking_calls'=>0,'continue_calls'=>0,'lead_calls'=>0,'row'=>['andromeda_external_id'=>M111A_EXTERNAL,'target_local_hotel_id'=>M111A_TARGET,'anex_hotel_id'=>M111A_ANEX,'decision_status'=>'accepted','readback'=>'verified','evidence_sha256'=>$newSha,'anex_bridge_readback'=>$bridgeRead,'distance_m'=>$distance===null?null:(int)round($distance)],'before'=>$before,'after'=>$after,'historical_operations_replayed'=>false,'no_replay'=>true]);
} catch (Throwable $e) {
    m111a_out(['status'=>'rolled_back_or_blocked','operation_id'=>M111A_OPERATION,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'safe_message'=>substr($e->getMessage(),0,180),'historical_operations_replayed'=>false,'no_replay'=>true]); exit(2);
}
