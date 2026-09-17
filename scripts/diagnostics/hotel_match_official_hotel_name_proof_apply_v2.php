<?php
declare(strict_types=1);

const HMOH_OP = 'hotel-match-official-hotel-name-proof-apply-1971-20260917-v2';

function hmoh_need(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); }
function hmoh_q(PDO $db, string $sql, array $args = []): array {
    $q = $db->prepare($sql); $q->execute(array_values($args));
    $rows = $q->fetchAll(PDO::FETCH_ASSOC); hmoh_need(count($rows) <= 10000, 'query_budget'); return $rows;
}
function hmoh_lower(string $s): string { return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s); }
function hmoh_key(string $s): string {
    $s = str_replace('ё', 'е', hmoh_lower(trim($s)));
    return preg_replace('/[^\p{L}\p{N}]+/u', '', $s) ?? '';
}
function hmoh_protected($v, string $key = '', int $depth = 0): bool {
    if ($depth > 24) return true;
    $hit = preg_match('/manual|exclude|exclusion|conflict|reject|review|protect/i', $key) === 1;
    if (is_array($v)) {
        if ($hit && $v !== []) return true;
        foreach ($v as $k => $x) if (hmoh_protected($x, (string)$k, $depth + 1)) return true;
        return false;
    }
    if (!$hit || $v === null) return false;
    if (is_bool($v)) return $v;
    if (is_numeric($v)) return (float)$v != 0.0;
    if (is_string($v)) return !in_array(strtolower(trim($v)), ['', 'false', 'none', 'no', 'null'], true);
    return true;
}
function hmoh_points($v, array &$out, int $depth = 0): void {
    if ($depth > 24 || !is_array($v)) return;
    $a = $v['latitude'] ?? $v['lat'] ?? null;
    $b = $v['longitude'] ?? $v['lon'] ?? $v['lng'] ?? null;
    if (is_numeric($a) && is_numeric($b)) {
        $a = (float)$a; $b = (float)$b;
        if (is_finite($a) && is_finite($b) && abs($a) <= 90 && abs($b) <= 180 && ($a != 0 || $b != 0)) $out[] = [$a, $b];
    }
    foreach ($v as $x) if (is_array($x)) hmoh_points($x, $out, $depth + 1);
}
function hmoh_km(array $a, array $b): float {
    [$x,$y,$u,$v] = array_map('deg2rad', [$a[0],$a[1],$b[0],$b[1]]);
    $h = sin(($u-$x)/2)**2 + cos($x)*cos($u)*sin(($v-$y)/2)**2;
    return 6371.0088 * 2 * asin(sqrt(min(1.0, max(0.0, $h))));
}
function hmoh_write(string $path, array $value): string {
    $raw = json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR) . "\n";
    $f = fopen($path, 'c+b'); hmoh_need(is_resource($f), 'output_open');
    ftruncate($f, 0); rewind($f); hmoh_need(fwrite($f, $raw) === strlen($raw) && fflush($f), 'output_write');
    if (function_exists('fsync')) hmoh_need(fsync($f), 'output_sync');
    rewind($f); hmoh_need(stream_get_contents($f) === $raw, 'output_readback'); fclose($f);
    return hash('sha256', $raw);
}

function hmoh_main(): void {
    hmoh_need(PHP_SAPI === 'cli' && getenv('MATCH_OPERATION_ID') === HMOH_OP, 'operation_guard');
    $sourceSha = (string)getenv('MATCH_SOURCE_SHA'); hmoh_need(preg_match('/^[a-f0-9]{40}$/D', $sourceSha) === 1, 'source_guard');
    $dir = (string)getenv('HOME') . '/.anytoour-match/operations/' . HMOH_OP;
    $reservation = json_decode((string)file_get_contents($dir . '/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
    $proofRaw = (string)file_get_contents($dir . '/proof.json');
    $proof = json_decode($proofRaw, true, 64, JSON_THROW_ON_ERROR);
    hmoh_need(($reservation['operation_id'] ?? '') === HMOH_OP && ($reservation['source_sha'] ?? '') === $sourceSha && ($reservation['state'] ?? '') === 'reserved_before_db_access', 'reservation_guard');
    hmoh_need(($reservation['proof_sha256'] ?? '') === hash('sha256', $proofRaw), 'proof_digest');
    hmoh_need(($proof['operation_id'] ?? '') === HMOH_OP && is_array($proof['candidates'] ?? null) && count($proof['candidates']) === 2, 'proof_shape');

    $out = ['operation_id'=>HMOH_OP,'source_sha'=>$sourceSha,'state'=>'failed_no_replay','planned_count'=>2,'committed_count'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true,'post_commit_readback_verified'=>false];
    $db = null; $holds = []; $prepared = [];
    ob_start();
    try {
        $root = realpath(getcwd()); hmoh_need(is_string($root) && basename($root) === 'anytoour.ru', 'root_guard');
        $bootstrap = $root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
        hmoh_need(realpath($bootstrap) === $bootstrap && !is_link($bootstrap), 'bootstrap_path'); require_once $bootstrap;
        $db = v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('SET SESSION innodb_lock_wait_timeout=20'); $db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE'); $db->beginTransaction();

        foreach ($proof['candidates'] as $candidate) {
            $ext = (string)$candidate['external_hotel_id']; $local = (int)$candidate['local_hotel_id'];
            $rows = hmoh_q($db, "SELECT local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? FOR UPDATE", [$ext]);
            if (count($rows) !== 1) { $holds[] = "$ext:row_missing_or_duplicate"; continue; }
            $row = $rows[0];
            if ($row['local_hotel_id'] !== null || (string)$row['decision_status'] !== 'pending') $holds[] = "$ext:not_pending_null";
            if (!hash_equals((string)$candidate['source_evidence_sha256'], (string)$row['evidence_sha256'])) $holds[] = "$ext:evidence_changed";
            $evidence = json_decode((string)$row['evidence_json'], true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($evidence) || hmoh_protected($evidence)) $holds[] = "$ext:protected";
            $src = is_array($evidence['source'] ?? null) ? $evidence['source'] : [];
            if (trim((string)($src['name'] ?? '')) !== (string)$candidate['source_name']) $holds[] = "$ext:source_name_changed";
            $stateKey = (string)($src['stateKey'] ?? $src['state_key'] ?? '');
            if ($stateKey !== (string)$candidate['source_state_key']) $holds[] = "$ext:state_key_changed";
            $town = trim((string)($src['town'] ?? ''));
            if ($town !== '' && hmoh_key($town) !== hmoh_key((string)$candidate['source_town'])) $holds[] = "$ext:town_changed";

            $hotelRows = hmoh_q($db, 'SELECT id,country_id,name,region_name,subregion_name,latitude,longitude FROM catalog_hotels WHERE id=? AND is_active=1 FOR UPDATE', [$local]);
            if (count($hotelRows) !== 1) { $holds[] = "$ext:target_missing_or_inactive"; continue; }
            $hotel = $hotelRows[0];
            if ((int)$hotel['country_id'] !== 4 || hmoh_key((string)$hotel['name']) !== hmoh_key((string)$candidate['local_name']) || hmoh_key((string)$hotel['region_name']) !== hmoh_key((string)$candidate['local_region']) || hmoh_key((string)$hotel['subregion_name']) !== hmoh_key((string)$candidate['local_subregion'])) $holds[] = "$ext:target_context_changed";
            if (hmoh_q($db, "SELECT external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id=? AND external_hotel_id<>? FOR UPDATE", [$local,$ext])) $holds[] = "$ext:target_occupied";
            if (!hmoh_q($db, 'SELECT id FROM tour_price_observations WHERE hotel_id=? LIMIT 1', [$local])) $holds[] = "$ext:not_live_observed";
            $points = []; hmoh_points($evidence, $points);
            if ($points) {
                if (!is_numeric($hotel['latitude']) || !is_numeric($hotel['longitude'])) $holds[] = "$ext:target_coordinates_missing";
                else {
                    $target = [(float)$hotel['latitude'], (float)$hotel['longitude']];
                    foreach ($points as $p) if (hmoh_km($p, $target) > 5.0) { $holds[] = "$ext:coordinate_conflict_gt5km"; break; }
                }
            }
            $prepared[] = ['candidate'=>$candidate,'evidence'=>$evidence];
        }

        $holds = array_values(array_unique($holds));
        hmoh_write($dir . '/precommit.json', ['operation_id'=>HMOH_OP,'source_sha'=>$sourceSha,'holds'=>$holds,'prepared_count'=>count($prepared),'proof_sha256'=>hash('sha256',$proofRaw),'checked_at_utc'=>gmdate('c')]);
        if ($holds || count($prepared) !== 2) {
            $db->rollBack(); $out['state'] = 'completed_no_write'; $out['holds'] = $holds;
        } else {
            foreach ($prepared as $item) {
                $c = $item['candidate']; $e = $item['evidence'];
                $e['independent_official_hotel_identity_acceptance'] = ['operation_id'=>HMOH_OP,'source_sha'=>$sourceSha,'prior_evidence_sha256'=>$c['source_evidence_sha256'],'local_hotel_id'=>$c['local_hotel_id'],'live_tourvisor_artifact_id'=>$proof['live_tourvisor_artifact_id'],'official_name'=>$c['official_name'],'official_geo'=>$c['official_geo'],'official_relation'=>$c['official_relation'],'official_urls'=>$c['official_urls'],'canonical_fact_sha256'=>$c['canonical_fact_sha256'],'identity_chain'=>'CURRENT Andromeda source name/state/town + live-observed AnyTour target + independent official hotel-owner name/geography proof','transaction_time_guards_verified'=>true];
                $json = json_encode($e, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); $newSha = hash('sha256', $json);
                $u = $db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256=?");
                $u->execute([(int)$c['local_hotel_id'],$newSha,$json,(string)$c['external_hotel_id'],(string)$c['source_evidence_sha256']]); hmoh_need($u->rowCount() === 1, 'conditional_update');
                $out['new_evidence_sha256'][(string)$c['external_hotel_id']] = $newSha;
            }
            $db->commit(); $out['state'] = 'committed'; $out['committed_count'] = 2; $out['database_writes'] = 2; $out['mapping_writes'] = 2;
            $ok = true; $readback = [];
            foreach ($proof['candidates'] as $c) {
                $rows = hmoh_q($db, "SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?", [(string)$c['external_hotel_id']]);
                $x = $rows[0] ?? []; $valid = count($rows) === 1 && (int)($x['local_hotel_id'] ?? 0) === (int)$c['local_hotel_id'] && ($x['decision_status'] ?? '') === 'accepted' && ($x['evidence_sha256'] ?? '') === ($out['new_evidence_sha256'][(string)$c['external_hotel_id']] ?? '');
                $ok = $ok && $valid; $readback[] = ['external_hotel_id'=>$c['external_hotel_id'],'local_hotel_id'=>$x['local_hotel_id']??null,'decision_status'=>$x['decision_status']??null,'evidence_sha256'=>$x['evidence_sha256']??null,'verified'=>$valid];
            }
            $out['readback'] = $readback; $out['post_commit_readback_verified'] = $ok; hmoh_need($ok, 'post_commit_readback');
        }
    } catch (Throwable $e) {
        if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
        $out['error_code'] = preg_match('/^[a-z_]{2,100}$/D', $e->getMessage()) ? $e->getMessage() : 'sanitized_failure';
    }
    while (ob_get_level()) ob_end_clean();
    $digest = hmoh_write($dir . '/result.json', $out);
    hmoh_write($dir . '/receipt.json', ['operation_id'=>HMOH_OP,'source_sha'=>$sourceSha,'state'=>$out['state'],'result_sha256'=>$digest,'readback_verified'=>$out['post_commit_readback_verified'] || $out['state']==='completed_no_write','committed_count'=>$out['committed_count'],'database_writes'=>$out['database_writes'],'mapping_writes'=>$out['mapping_writes'],'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0,'no_replay'=>true]);
    echo json_encode(['state'=>$out['state'],'committed_count'=>$out['committed_count'],'holds'=>$out['holds']??[],'readback'=>$out['readback']??[],'result_sha256'=>$digest], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n";
    if (!in_array($out['state'], ['committed','completed_no_write'], true)) exit(2);
}

if (in_array('--self-test', $argv ?? [], true)) {
    hmoh_need(hmoh_key('ADELMAR ŞİŞLİ') === hmoh_key('Adelmar Şişli'), 'key');
    hmoh_need(hmoh_protected(['manual'=>false]) === false && hmoh_protected(['review'=>'hold']) === true, 'protect');
    echo "2 official-name writer self-tests PASS\n"; exit;
}
hmoh_main();
