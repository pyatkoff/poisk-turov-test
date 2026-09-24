<?php
declare(strict_types=1);

// Owner-directed CURRENT acceptance of the exact saved-evidence BG delta.
// Raw F4 stays in its own bgoperator namespace; it is never converted to SAMO.
const OP = 'hotel-match-bg-saved-current-accept106-1971-20260922-v4';
const MIN_WRITE = 100;
const NS = 'bgoperator';
const INPUT_HASHES = [
    'alias-output.json' => 'a959d4078639169aaf7bd857140e74e6fe62ccc4dcf8ae8610d480f3e26ccf00',
    'recovery-output.json' => '64b2b77e2e57daf142e266836dbbe94fb93149fa6e473baa29cc6dae762b722d',
    'corroboration-output.json' => 'dc8ade39d090cf2ee836afe6a08c348c751c0307966e776dfacd11e8a49005db',
    'accept297-result.json' => 'b1eb4c206d5643f2efac8f86361e04488848e5bcae8dc10ec3592901d8dbd1c1',
    'accept297-receipt.json' => '7f2d38393dc22b21c34d992dc1125fdb4a3e680cc0877e68b98b73d8adf7e0d3',
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
    $alias = $d['alias-output.json']; $recovery = $d['recovery-output.json']; $corroboration = $d['corroboration-output.json'];
    $prior = $d['accept297-result.json']; $priorReceipt = $d['accept297-receipt.json'];
    need(($alias['schema'] ?? null) === 'match-bg-saved-alias-evidence/1'
        && ($alias['input_zip_sha256'] ?? null) === 'a52b4dbdf4a9eafdd49746a5a1aae461374f8d558e088096165ff82964e2b515'
        && ($alias['input_result_sha256'] ?? null) === '3917d36c7b665d1b44b84e7c009ced24321982367aa155e35554c5dd2f27515a'
        && ($alias['counts']['hotels_examined'] ?? null) === 816
        && ($alias['counts']['alias_evidence_supported'] ?? null) === 369, 'alias_input');
    need(($recovery['schema'] ?? null) === 'match-bg-saved-catalog-recovery/2'
        && ($recovery['counts'] ?? null) == ['input_residual'=>114,'recovery_supported'=>18,'remaining_residual'=>96,'evidence_supported_after'=>387,'candidate_union_after'=>404], 'recovery_input');
    need(($corroboration['schema'] ?? null) === 'match-bg-saved-common4-corroboration/1'
        && ($corroboration['counts'] ?? null) == ['input_unresolved_after_recovery'=>96,'corroboration_supported'=>16,'remaining_unresolved'=>80,'evidence_supported_after'=>403,'candidate_union_after'=>414,'unique_uncommitted_after_accept297'=>106], 'corroboration_input');
    need(($prior['operation'] ?? null) === 'hotel-match-bg-native-current-accept297-1971-20260922-v4'
        && ($prior['state'] ?? null) === 'committed_verified' && ($prior['mapping_writes'] ?? null) === 297
        && ($prior['post_commit_verified'] ?? null) === 297 && ($prior['resolver_verified'] ?? null) === 297
        && ($prior['written_unique_hotels'] ?? null) === 297 && ($prior['holds'] ?? null) === [], 'prior_terminal_result');
    need(($priorReceipt['operation'] ?? null) === $prior['operation'] && ($priorReceipt['state'] ?? null) === 'committed_verified'
        && ($priorReceipt['result_sha256'] ?? null) === INPUT_HASHES['accept297-result.json']
        && ($priorReceipt['mapping_writes'] ?? null) === 297 && ($priorReceipt['database_writes'] ?? null) === 297
        && ($priorReceipt['readback_verified'] ?? false) === true && ($priorReceipt['no_replay'] ?? false) === true, 'prior_terminal_receipt');
    $committed = [];
    foreach ($prior['written_rows'] as $r) {
        need(($r['supplier_namespace'] ?? null) === NS && ($r['operator_115_created'] ?? true) === false, 'prior_namespace');
        $k = keyOf($r); need(!isset($committed[$k]), 'prior_collision'); $committed[$k] = true;
    }
    need(count($committed) === 297, 'prior_membership');
    $byTv = []; foreach ($alias['rows'] as $r) { $tv=(int)($r['tv_hotel_id']??0); need($tv>0&&!isset($byTv[$tv]),'alias_tv_collision'); $byTv[$tv]=$r; }
    $build = function(array $r, string $kind, array $proof) use (&$committed): array {
        $in = $r['input_row'] ?? null; need(is_array($in), 'input_row');
        need(($r['safe_to_write_now'] ?? true) === false && ($in['safe_to_write_now'] ?? true) === false, 'source_not_write_authority');
        need(($in['manual'] ?? null) === [] && count($in['f4_candidates'] ?? []) === 1
            && count($in['accepted_catalog_ids'] ?? []) === 1 && count($in['official_evidence'] ?? []) === 1, 'saved_identity_guards');
        $f4=(string)$in['f4_candidates'][0]; $selectedId=(string)$in['accepted_catalog_ids'][0]; $official=$in['official_evidence'][0];
        $anchors=array_values(array_filter($in['canonical_evidence'] ?? [], fn($x)=>is_array($x)
            && ($x['supplier_namespace']??null)==='andromeda_catalog' && (string)($x['external_hotel_id']??'')===$selectedId));
        need(count($anchors)===1,'selected_canonical_evidence_not_unique'); $anchor=$anchors[0];
        need(preg_match('/^[1-9][0-9]{5,19}$/D',$f4)===1 && (string)($official['native_id']??'')===$f4
            && ($official['native_namespace']??null)===NS, 'native_identity');
        need(($anchor['decision_status']??null)==='accepted' && ($anchor['recorded_evidence_hash_matches']??false)===true
            && ($anchor['evidence_sha256']??null)===($anchor['evidence_json_sha256']??null), 'canonical_anchor');
        need(preg_match('/^[0-9a-f]{64}$/D',(string)$anchor['catalog_sha256'])===1
            && preg_match('/^[0-9a-f]{64}$/D',(string)$anchor['evidence_sha256'])===1, 'canonical_hash');
        $link=(string)($in['operator_link']??''); need($link!=='' && hash('sha256',$link)===($in['operator_link_sha256']??null),'raw_link_hash');
        parse_str((string)(parse_url($link,PHP_URL_QUERY)??''),$query); need((string)($query['F4']??'')===$f4,'operator_link_f4');
        $tv=(int)$r['tv_hotel_id']; need($tv>0 && (int)($in['catalog_hotel']['id']??0)===$tv
            && (int)($anchor['local_hotel_id']??0)===$tv, 'target_identity');
        $seed=['supplier_namespace'=>NS,'native_hotel_id'=>$f4,'tv_hotel_id'=>$tv,'expected_target'=>$in['catalog_hotel'],'expected_anchor'=>$anchor,
            'source'=>['evidence_kind'=>$kind,'operator_link'=>$link,'operator_link_sha256'=>$in['operator_link_sha256'],
                'official_evidence'=>$in['official_evidence'],'retained_geography'=>$in['retained_geography'],'proof'=>$proof]];
        need(!isset($committed[keyOf($seed)]),'prior_committed_overlap'); return $seed;
    };
    $out=[];$keys=[];$pairs=[];$counts=['alias_delta'=>0,'source_catalog'=>0,'independent_common4'=>0];
    $add=function(array $seed,string $kind) use (&$out,&$keys,&$pairs,&$counts): void {
        $key=keyOf($seed);$pair=pairOf($seed);need(!isset($keys[$key])&&!isset($pairs[$pair]),'input_collision');
        $keys[$key]=true;$pairs[$pair]=true;$out[]=$seed;$counts[$kind]++;
    };
    foreach ($alias['rows'] as $r) {
        if (($r['alias_evidence_supported']??false)!==true) continue;
        $f4=(string)($r['input_row']['f4_candidates'][0]??''); if (isset($committed[NS.'|'.$f4])) continue;
        $add($build($r,'alias_delta',['proofs'=>$r['proofs'],'geography_proofs'=>$r['geography_proofs']]),'alias_delta');
    }
    foreach ($recovery['rows'] as $proof) if (($proof['recovery_supported']??false)===true) {
        $tv=(int)$proof['tv_hotel_id']; need(isset($byTv[$tv]),'recovery_target_missing');
        $add($build($byTv[$tv],'source_catalog',$proof),'source_catalog');
    }
    foreach ($corroboration['rows'] as $proof) if (($proof['corroboration_supported']??false)===true) {
        $tv=(int)$proof['tv_hotel_id']; need(isset($byTv[$tv]),'corroboration_target_missing');
        $add($build($byTv[$tv],'independent_common4',$proof),'independent_common4');
    }
    need($counts===['alias_delta'=>72,'source_catalog'=>18,'independent_common4'=>16],'cohort_split');
    need(count($out)===106&&count($keys)===106&&count($pairs)===106,'cohort');
    usort($out,fn($a,$b)=>strcmp(keyOf($a),keyOf($b)));return $out;
}
function query(PDO $db, string $sql, array $params = []): array { $st = $db->prepare($sql); $st->execute(array_values($params)); return $st->fetchAll(PDO::FETCH_ASSOC) ?: []; }
function factsEqual(array $a, array $b): bool {
    foreach (['id','name','country_id','country_name','region_name','subregion_name','category','is_active'] as $f) if ((string)($a[$f] ?? '') !== (string)($b[$f] ?? '')) return false;
    return true;
}
function indexed(array $rows): array {
    $keys = []; $accepted = []; $occupied = [];
    foreach ($rows as $r) { $k = keyOf($r); need(!isset($keys[$k]), 'duplicate_identity'); $keys[$k] = $r;
        if ($r['local_hotel_id'] !== null) { $occupied[pairOf($r)][]=$r; if ($r['decision_status'] === 'accepted') $accepted[pairOf($r)][] = $r; }
    }
    return [$keys, $accepted, $occupied];
}
function reason(array $s, array $keys, array $targets, array $occupied, array $hotels, array $manual): ?string {
    $tv = $s['tv_hotel_id']; $t = $hotels[$tv] ?? null; $aa = $targets['andromeda_catalog|' . $tv] ?? [];
    if (isset($keys[keyOf($s)])) return 'provider_source_present';
    if (!$t || (int)$t['is_active'] !== 1) return 'target_missing_or_inactive';
    if (preg_match('/^(россия|абхазия|russia|russian federation|abkhazia)$/iu', trim((string)$t['country_name']))) return 'excluded_country';
    if (!factsEqual($t, $s['expected_target'])) return 'target_facts_changed';
    if (isset($manual[$tv])) return 'manual_target_protected';
    if (isset($occupied[$s['supplier_namespace'] . '|' . $tv])) return 'provider_target_occupied';
    if (count($aa) !== 1) return 'canonical_anchor_not_unique';
    $a = $aa[0]; $expected = $s['expected_anchor'];
    foreach (['external_hotel_id','catalog_sha256','evidence_sha256'] as $f) if ((string)$a[$f] !== (string)$expected[$f]) return 'canonical_anchor_changed';
    if (!preg_match('/^[0-9a-f]{64}$/D', $a['catalog_sha256']) || hash('sha256', $a['evidence_json']) !== $a['evidence_sha256']) return 'anchor_hash_invalid';
    return null;
}
function writeMappings(PDO $db, array $seeds, string $dir, string $sha): array {
    $base = ['operation'=>OP,'source_sha'=>$sha,'source_evidence_sha256'=>INPUT_HASHES['alias-output.json'],'input_candidates'=>count($seeds),'provider_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'andromeda_calls'=>0];
    $commitAttempt = false; $committed = false; $inserted = [];
    $columns = 'supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json,created_at';
    $identitySql = 'SELECT ' . $columns . ' FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id';
    try {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $db->exec('SET SESSION innodb_lock_wait_timeout=15'); $db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE'); $db->beginTransaction();
        $tables = array_map('current', query($db, 'SHOW TABLES'));
        $guards = array_values(array_filter($tables, fn($t) => preg_match('/(?:andromeda|hotel).*(?:decision|review|exclusion|hold)|(?:decision|review|exclusion|hold).*(?:andromeda|hotel)/i', $t))); sort($guards);
        need($guards === ['anex_hotel_decisions'], 'guard_table_drift');
        $all = query($db, $identitySql . ' FOR UPDATE'); need(count($all) <= 50000, 'identity_cap'); [$keys, $targets, $occupied] = indexed($all);
        $ids = array_values(array_unique(array_column($seeds, 'tv_hotel_id'))); sort($ids, SORT_NUMERIC); $ph = implode(',', array_fill(0, count($ids), '?'));
        $hotelSql = "SELECT id,name,country_id,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id";
        $hotels = []; foreach (query($db, $hotelSql . ' FOR UPDATE', $ids) as $r) $hotels[(int)$r['id']] = $r;
        $decisions = query($db, 'SELECT anex_hotel_id,decision_status,catalog_hotel_id,decided_by,decision_note,decided_at,updated_at FROM anex_hotel_decisions ORDER BY anex_hotel_id FOR UPDATE');
        $manual = []; foreach ($decisions as $r) if ($r['catalog_hotel_id'] !== null) $manual[(int)$r['catalog_hotel_id']] = true;
        $safe = []; $holds = []; $counts = []; $expected = []; $before = [];
        foreach ($keys as $k => $r) $before[$k] = rh($r);
        foreach ($seeds as $s) {
            $why = reason($s, $keys, $targets, $occupied, $hotels, $manual);
            if ($why !== null) { $holds[] = ['key'=>keyOf($s),'tv_hotel_id'=>$s['tv_hotel_id'],'reason'=>$why]; $counts[$why] = ($counts[$why] ?? 0) + 1; continue; }
            $safe[] = $s; $expected[keyOf($s)] = ['anchor'=>$targets['andromeda_catalog|' . $s['tv_hotel_id']][0],'target'=>$hotels[$s['tv_hotel_id']]];
        }
        ksort($counts);
        savej($dir . '/capture.json', $base + ['state'=>'current_locked_capture','at_utc'=>gmdate('c'),'safe_count'=>count($safe),'holds'=>$holds,'anchor_target_rows'=>$expected,'identity_rows_before'=>count($all),'identity_rows_hash'=>rh($before),'manual_rows_hash'=>rh($decisions),'guard_tables'=>$guards]);
        savej($dir . '/plan.json', $base + ['state'=>count($safe)>=MIN_WRITE?'ready_to_insert':'below_threshold','minimum'=>MIN_WRITE,'safe_keys'=>array_map('keyOf',$safe),'safe_count'=>count($safe),'hold_counts'=>$counts]);
        if (count($safe) < MIN_WRITE) { $db->rollBack(); return $base + ['state'=>'completed_no_write_below_threshold','mapping_writes'=>0,'database_writes'=>0,'current_safe'=>count($safe),'holds'=>$holds,'hold_counts'=>$counts,'readback_verified'=>false]; }
        $st = $db->prepare("INSERT INTO andromeda_hotel_identities (supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES (?,?,?,'accepted',?,?,?)");
        foreach ($safe as $s) {
            $k = keyOf($s); $a = $expected[$k]['anchor'];
            $evidence = ['operation_id'=>OP,'rule'=>'official_bg_operator_link_plus_saved_name_category_country_geography_and_current_canonical_anchor','source_sha'=>$sha,'input_hashes'=>INPUT_HASHES,'tv_hotel_id'=>$s['tv_hotel_id'],'supplier_namespace'=>NS,'external_hotel_id'=>$s['native_hotel_id'],'source'=>$s['source'],'canonical_andromeda_catalog_anchor'=>$a,'provider_bridges'=>[['andromeda_catalog_id'=>(string)$a['external_hotel_id'],'local_hotel_id'=>$s['tv_hotel_id'],'evidence_sha256'=>$a['evidence_sha256']]],'target'=>$expected[$k]['target'],'identity_separation'=>'bgoperator_f4_is_raw_and_is_not_operator_115_or_samo_id','prefix_transform_applied'=>false];
            $ej = canon($evidence); $eh = hash('sha256', $ej);
            $st->execute([$s['supplier_namespace'],$s['native_hotel_id'],$s['tv_hotel_id'],$a['catalog_sha256'],$eh,$ej]); need($st->rowCount() === 1, 'insert_count');
            $inserted[$k] = ['supplier_namespace'=>$s['supplier_namespace'],'external_hotel_id'=>$s['native_hotel_id'],'local_hotel_id'=>$s['tv_hotel_id'],'decision_status'=>'accepted','catalog_sha256'=>$a['catalog_sha256'],'evidence_sha256'=>$eh,'evidence_json'=>$ej];
        }
        [$after] = indexed(query($db, $identitySql)); need(count($after) === count($before) + count($safe), 'delta_count');
        foreach ($before as $k => $h) need(isset($after[$k]) && rh($after[$k]) === $h, 'existing_identity_changed');
        foreach ($inserted as $k => $x) foreach ($x as $field => $value) need((string)$after[$k][$field] === (string)$value, 'insert_readback');
        savej($dir . '/pre-commit.json', $base + ['state'=>'verified_before_commit','planned_writes'=>count($safe),'old_rows_preserved'=>count($before),'inserted_keys'=>array_keys($inserted),'hold_counts'=>$counts]);
        savej($dir . '/commit-attempt.json', $base + ['state'=>'commit_attempt_no_replay','planned_writes'=>count($safe)]);
        $commitAttempt = true; need($db->commit(), 'commit_false'); $committed = true;
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->exec('START TRANSACTION READ ONLY');
        [$post, $postTargets] = indexed(query($db, $identitySql)); $postHotels = [];
        foreach ($before as $k => $h) need(isset($post[$k]) && rh($post[$k]) === $h, 'post_old_identity_changed');
        foreach (query($db, $hotelSql, $ids) as $r) $postHotels[(int)$r['id']] = $r;
        $resolverRows = []; $written = [];
        foreach ($inserted as $k => $x) {
            need(isset($post[$k]), 'post_missing'); foreach ($x as $field => $value) need((string)$post[$k][$field] === (string)$value, 'post_row');
            $aa = $postTargets['andromeda_catalog|' . $x['local_hotel_id']] ?? []; need(count($aa) === 1 && rh($aa[0]) === rh($expected[$k]['anchor']), 'post_anchor');
            need(isset($postHotels[$x['local_hotel_id']]) && factsEqual($postHotels[$x['local_hotel_id']], $expected[$k]['target']), 'post_target');
            $resolverRows[] = ['supplier_namespace'=>$x['supplier_namespace'],'external_hotel_id'=>$x['external_hotel_id'],'decision_status'=>'accepted','catalog_hotel_id'=>$x['local_hotel_id'],'existing_catalog_hotel_id'=>$postHotels[$x['local_hotel_id']]['id']];
            $written[] = ['supplier_namespace'=>$x['supplier_namespace'],'external_hotel_id'=>$x['external_hotel_id'],'local_hotel_id'=>$x['local_hotel_id'],'canonical_andromeda_catalog_id'=>(string)$aa[0]['external_hotel_id'],'evidence_sha256'=>$x['evidence_sha256'],'operator_115_created'=>false];
        }
        $resolver = AnyTourAndromedaHotelResolver::fromRows($resolverRows, rh($resolverRows)); $offers = [];
        foreach ($written as $r) $offers[] = ['provider'=>'andromeda','selection_enabled'=>false,'local_hotel_id'=>null,'supplier_namespace'=>$r['supplier_namespace'],'external_hotel_id'=>$r['external_hotel_id']];
        $page = $resolver->apply(['provider'=>'andromeda','selection_enabled'=>false,'offers'=>$offers]);
        foreach ($page['offers'] as $i => $offer) need($offer['local_hotel_id'] === $written[$i]['local_hotel_id'], 'resolver_projection');
        $db->rollBack();
        return $base + ['state'=>'committed_verified','current_safe'=>count($safe),'mapping_writes'=>count($safe),'database_writes'=>count($safe),'post_commit_verified'=>count($written),'resolver_verified'=>count($written),'readback_verified'=>true,'old_rows_preserved'=>count($before),'hold_counts'=>$counts,'holds'=>$holds,'written_rows'=>$written,'written_unique_hotels'=>count(array_unique(array_column($written,'local_hotel_id')))];
    } catch (Throwable $e) {
        $rolledBack = false; try { if ($db->inTransaction()) $rolledBack = $db->rollBack(); } catch (Throwable) {}
        $state = $committed ? 'post_commit_verification_failed_no_replay' : ($commitAttempt ? 'commit_unknown_no_replay' : 'rolled_back_no_write');
        savej($dir . '/failure.json', $base + ['state'=>$state,'commit_attempted'=>$commitAttempt,'committed'=>$committed,'rollback_returned'=>$rolledBack,'attempted_inserts'=>count($inserted),'mapping_writes'=>$commitAttempt?null:0,'database_writes'=>$commitAttempt?null:0,'error_class'=>get_class($e)]); throw $e;
    }
}
if (defined('MATCH_UNIT_TEST')) return;
need(PHP_SAPI === 'cli', 'cli_only'); $mode = $argv[1] ?? '';
need(in_array($mode, ['--plan','--accept-current'], true), 'disabled');
$input = realpath((string)getenv('MATCH_INPUT_DIR')); need(is_string($input), 'input_directory'); $seeds = seeds($input);
if ($mode === '--plan') { echo json_encode(['operation'=>OP,'candidates'=>count($seeds),'unique_hotels'=>count(array_unique(array_column($seeds,'tv_hotel_id'))),'keys'=>array_map('keyOf',$seeds),'supplier_calls'=>0,'database_writes'=>0], JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n"; exit; }
$root = realpath((string)getenv('ANYTOUR_ROOT')); $dir = realpath((string)getenv('MATCH_OPERATION_DIR')); $sha = (string)getenv('MATCH_SOURCE_SHA');
need(is_string($root) && basename($root) === 'anytoour.ru' && is_string($dir) && basename($dir) === OP && preg_match('/^[a-f0-9]{40}$/D',$sha) === 1, 'scope');
$res = loadj($input.'/reservation.json'); need($res['operation']===OP && $res['source_sha']===$sha && $res['script_sha256']===hash_file('sha256',__FILE__) && $res['state']==='reserved_before_db' && $res['candidate_keys']===array_map('keyOf',$seeds), 'reservation');
savej($dir.'/started.json',['operation'=>OP,'state'=>'started_no_replay','source_sha'=>$sha]);
require_once __DIR__.'/andromeda-hotel-resolver.php';
require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
try {
    $result=writeMappings(v2_data_db(),$seeds,$dir,$sha); $h=savej($dir.'/result.json',$result);
    savej($dir.'/receipt.json',['operation'=>OP,'source_sha'=>$sha,'state'=>$result['state'],'result_sha256'=>$h,'mapping_writes'=>$result['mapping_writes'],'database_writes'=>$result['database_writes'],'readback_verified'=>$result['readback_verified'],'provider_calls'=>0,'no_replay'=>true]);
    echo json_encode(['state'=>$result['state'],'mapping_writes'=>$result['mapping_writes'],'hold_counts'=>$result['hold_counts'],'result_sha256'=>$h], JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
} catch (Throwable) { fwrite(STDERR,"MATCH_CURRENT_ACCEPTANCE_FAILED_NO_REPLAY\n"); exit(1); }
