<?php
declare(strict_types=1);

/** Exact v65 successor: read-only readiness, never an identity writer. */
const V66_OP = 'hotel-match-live234-v65-current-audit-1971-20260926-v66';
const V66_SOURCE_OP = 'hotel-match-live234-andromeda-context-acquire-1971-20260926-v65';
const V66_SOURCE_SHA = '42829f8f7a7988f3f033bfd8e377758b537ccb95c3ace9a09d953191bfe17810';
const V66_RECEIPT_SHA = '46792a26c166c597602814afc217e08e07709d2ed3aa6941ab291a418e28c626';
const V66_NS = ['operator_5', 'operator_115', 'operator_315', 'operator_342'];
const V66_MAX_ROWS = 100000;

function v66_need(bool $ok, string $code): void {
    if (!$ok) throw new RuntimeException($code);
}
function v66_json(mixed $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
function v66_id(mixed $value): string {
    v66_need(is_int($value) || is_string($value), 'invalid_id_type');
    $id = (string)$value;
    v66_need(preg_match('/^[1-9][0-9]{0,21}$/D', $id) === 1, 'invalid_id');
    return $id;
}
function v66_ids(array $values): array {
    $ids = array_values(array_unique(array_map('v66_id', $values)));
    sort($ids, SORT_NATURAL);
    return $ids;
}
function v66_hash(string $value): bool {
    return preg_match('/^[0-9a-f]{64}$/D', $value) === 1;
}
function v66_load(string $path): array {
    v66_need(is_file($path) && !is_link($path) && filesize($path) <= 8388608, 'input_file');
    $value = json_decode((string)file_get_contents($path), true, 128, JSON_THROW_ON_ERROR);
    v66_need(is_array($value), 'input_shape');
    return $value;
}
function v66_save(string $path, array $value): string {
    $raw = v66_json($value)."\n";
    $file = @fopen($path, 'x+b');
    v66_need($file !== false, 'exclusive_create');
    try {
        v66_need(fwrite($file, $raw) === strlen($raw) && fflush($file), 'file_write');
        if (function_exists('fsync')) v66_need(fsync($file),'file_sync');
    } finally { fclose($file); }
    return hash('sha256', $raw);
}
function v66_excluded(string $country): bool {
    return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu', trim($country)) === 1;
}

/** Normalize types WITHIN each namespace; never bridge direct ANEX numerically. */
function v66_support(array $direct, array $operator): string {
    $a = v66_ids($direct); $b = v66_ids($operator);
    if (count($a) > 1 || count($b) > 1) return 'support_collision';
    if ($a === [] || $b === []) return 'none';
    return $a[0] === $b[0] ? 'support_equal' : 'support_different';
}
function v66_source(array $source): array {
    v66_need(($source['operation'] ?? '') === V66_SOURCE_OP && ($source['state'] ?? '') === 'completed_read_only', 'source_state');
    foreach (['input_future_count'=>176, 'current_active_count'=>175, 'strict_multi_lane_candidate_count'=>49,
              'review_count'=>59, 'hold_count'=>67, 'provider_http_calls'=>295, 'andromeda_calls'=>295,
              'price_calls'=>250, 'task_count'=>249, 'completed_task_count'=>249, 'task_error_count'=>1,
              'tourvisor_calls'=>0, 'direct_anex_calls'=>0, 'database_writes'=>0, 'mapping_writes'=>0] as $key=>$value) {
        v66_need(($source[$key] ?? null) === $value, 'source_count_'.$key);
    }
    v66_need(($source['safe_to_write_now'] ?? null) === false && count($source['dossiers'] ?? []) === 175, 'source_dossiers');
    $selected = []; $targets = []; $catalogTargets = []; $nativeCatalog = []; $catalogNatives = [];
    foreach ($source['dossiers'] as $dossier) {
        $local = v66_id($dossier['local_hotel_id'] ?? null);
        v66_need(!isset($targets[$local]), 'duplicate_target'); $targets[$local] = true;
        v66_ids($dossier['direct_anex_ids']);
        $candidates = [];
        foreach ($dossier['candidates'] as $candidate) {
            $catalog = v66_id($candidate['catalog_id'] ?? null);
            v66_need(!isset($candidates[$catalog]), 'duplicate_candidate');
            v66_need(array_keys($candidate['lanes'] ?? []) === V66_NS, 'namespace_set');
            $observed = 0;
            foreach (V66_NS as $ns) {
                $lane = $candidate['lanes'][$ns]; $ids = v66_ids($lane['native_ids'] ?? []);
                $expected = count($ids) === 1 ? 'single_native' : (count($ids) > 1 ? 'ambiguous_native' : 'not_observed');
                v66_need(($lane['state'] ?? '') === $expected, 'lane_shape');
                if ($expected === 'single_native') $observed++;
                foreach ($ids as $id) {
                    $nativeCatalog[$ns.'|'.$id][$catalog] = true;
                    $catalogNatives[$catalog][$ns][$id] = true;
                }
            }
            v66_need(($candidate['lane_count'] ?? null) === $observed, 'lane_count');
            $candidates[$catalog] = $candidate;
        }
        if (($dossier['status'] ?? '') !== 'strict_multi_lane_candidate') continue;
        $catalog = v66_id($dossier['strict_catalog_id'] ?? null);
        v66_need(isset($candidates[$catalog]), 'selected_candidate_missing');
        $candidate = $candidates[$catalog];
        v66_need(($candidate['strict_multi_lane'] ?? null) === true && $candidate['lane_count'] >= 2 && ($candidate['source_collision'] ?? null) === false, 'strict_shape');
        v66_need(($candidate['retrieval'] ?? '') === 'exact_variant' || ($candidate['geo_relation'] ?? '') === 'direct_label_match', 'strict_retrieval');
        v66_need(count(array_filter($candidates, fn($c) => ($c['strict_multi_lane'] ?? false) === true)) === 1, 'multiple_strict');
        $selected[$local] = ['dossier'=>$dossier, 'candidate'=>$candidate,
            'dossier_sha256'=>hash('sha256', v66_json($dossier)), 'candidate_sha256'=>hash('sha256', v66_json($candidate))];
        $catalogTargets[$catalog][$local] = true;
    }
    v66_need(count($selected) === 49 && count($catalogTargets) === 48, 'exact_strict_scope');
    return compact('selected', 'catalogTargets', 'nativeCatalog', 'catalogNatives');
}
/**
 * Pure bulk preparation from already sealed local evidence; no execution route.
 * The existing CLI still consumes its historical exact49 contract unchanged.
 * This pin identifies an evidence file, NOT a server or write authorization.
 */
const V66_BULK_PLAN_SHA = '048523ef5a1d8440e39c6341afb531db6346b8786a63312c6328d70f2c1f70b8';
function v66_prepare_bulk(string $sourceRaw, string $planRaw): array {
    v66_need(strlen($sourceRaw) <= 8388608 && strlen($planRaw) <= 8388608, 'bulk_input_cap');
    v66_need(hash_equals(V66_SOURCE_SHA, hash('sha256', $sourceRaw)), 'bulk_source_hash');
    v66_need(hash_equals(V66_BULK_PLAN_SHA, hash('sha256', $planRaw)), 'bulk_plan_hash');
    $source=json_decode($sourceRaw, true, 128, JSON_THROW_ON_ERROR);
    $plan=json_decode($planRaw, true, 128, JSON_THROW_ON_ERROR);
    $legacy=v66_source($source); // Validate immutable acquisition, not select bulk work.
    v66_need(($plan['schema'] ?? '')==='match_retained175_tv_segment_union_v2', 'bulk_schema');
    v66_need(($plan['required_exact_operator_lanes'] ?? null)===1 && ($plan['second_operator_required'] ?? null)===false, 'bulk_one_operator');
    v66_need(($plan['safe_to_write_now'] ?? null)===false && ($plan['current_validation_performed'] ?? null)===false, 'bulk_not_acceptance');
    $byPair=[];
    foreach ($source['dossiers'] as $dossier) foreach ($dossier['candidates'] as $candidate) {
        $key=v66_id($dossier['local_hotel_id']).'|'.v66_id($candidate['catalog_id']);
        v66_need(!isset($byPair[$key]), 'bulk_duplicate_source_pair');
        $byPair[$key]=['dossier'=>$dossier,'candidate'=>$candidate,
            'dossier_sha256'=>hash('sha256',v66_json($dossier)),
            'candidate_sha256'=>hash('sha256',v66_json($candidate))];
    }
    v66_need(count($byPair)===($plan['candidate_pairs_reviewed'] ?? null), 'bulk_pair_count');
    $selected=[]; $catalogTargets=[]; $ordinary=[]; $historical=[]; $omitted=[];
    $inputResults=[];
    foreach ($plan['input_hashes'] as $pin) $inputResults[$pin['result_sha256'] ?? $pin[1]]=true;
    foreach ($plan['candidates'] as $row) {
        $local=(int)v66_id($row['local_hotel_id']); $catalog=v66_id($row['andromeda_catalog_id']);
        $entry=$byPair[$local.'|'.$catalog] ?? null;
        v66_need($entry!==null && !isset($selected[$local]), 'bulk_pair_membership');
        v66_need($row['hotel_name']===$entry['dossier']['hotel_name'] && $row['candidate_names']===$entry['candidate']['names'], 'bulk_name_binding');
        v66_need($row['safe_to_write_now']===false && is_array($row['historical_review_reasons']), 'bulk_row_state');
        $proofs=[];
        foreach ($row['proven_lanes'] as $proof) {
            $ns=$proof['namespace']; $native=v66_id($proof['native_id']);
            // These two exact cross-provider namespace contracts are already proven.
            v66_need(in_array($ns,['operator_315','operator_342'],true) && !isset($proofs[$ns]), 'bulk_proof_namespace');
            v66_need(v66_ids($entry['candidate']['lanes'][$ns]['native_ids'])===[$native], 'bulk_native_binding');
            v66_need(is_array($proof['evidence']) && $proof['evidence']!==[], 'bulk_proof_missing');
            foreach ($proof['evidence'] as $evidence) {
                v66_need(isset($inputResults[$evidence['archive_result_sha256'] ?? '']), 'bulk_proof_input');
                v66_need(preg_match('~^/(rows|single_native_edges)/(0|[1-9][0-9]*)$~D', $evidence['row_path'] ?? '')===1, 'bulk_proof_path');
                foreach (['row_canonical_sha256','tv_child_result_sha256','tv_link_sha256','tv_tour_sha256'] as $field)
                    v66_need(v66_hash($evidence[$field] ?? ''), 'bulk_proof_hash');
            }
            $proofs[$ns]=$proof;
        }
        v66_need(count($proofs)>=1 && count($proofs)===$row['independent_tv_lane_count'], 'bulk_proof_count');
        $entry['retained_plan_sha256']=V66_BULK_PLAN_SHA;
        $entry['retained_proofs']=$proofs;
        $entry['historical_review_reasons']=$row['historical_review_reasons'];
        $selected[$local]=$entry; $catalogTargets[$catalog][$local]=true;
        if ($row['historical_review_reasons']!==[]) $historical[]=$local;
        else {
            $ordinary[]=$local;
            if (!isset($legacy['selected'][$local])) $omitted[]=$local;
        }
    }
    v66_need(count($selected)===$plan['proven_candidate_hotels'], 'bulk_selected_count');
    foreach ([$ordinary,$historical,$omitted] as $list) v66_need(count($list)===count(array_unique($list)), 'bulk_duplicate_group');
    sort($ordinary,SORT_NUMERIC); sort($historical,SORT_NUMERIC); sort($omitted,SORT_NUMERIC);
    return ['selected'=>$selected,'catalogTargets'=>$catalogTargets,
        'nativeCatalog'=>$legacy['nativeCatalog'],'catalogNatives'=>$legacy['catalogNatives'],
        'input_dossiers'=>count($source['dossiers']),'candidate_pairs_examined'=>count($byPair),
        'ordinary_review_ids'=>$ordinary,'historical_review_ids'=>$historical,
        'ordinary_ids_outside_legacy49'=>$omitted,'required_exact_operator_lanes'=>1,
        'source_result_sha256'=>V66_SOURCE_SHA,'retained_plan_sha256'=>V66_BULK_PLAN_SHA,
        'execution_authorized'=>false,'safe_to_write_now'=>false];
}

/** Pure contract evaluation against a supplied snapshot; never opens a DB. */
function v66_assess_bulk(string $sourceRaw, string $planRaw, array $current): array {
    $input=v66_prepare_bulk($sourceRaw,$planRaw); $rows=[]; $counts=[];
    foreach ($input['selected'] as $local=>$entry) {
        $row=v66_classify((int)$local,$entry,$input,$current,true); $rows[]=$row;
        $counts[$row['status']]=($counts[$row['status']] ?? 0)+1;
    }
    ksort($counts);
    return ['mode'=>'pure_supplied_snapshot_evaluation_not_execution','rows'=>$rows,'status_counts'=>$counts,
        'input_dossiers'=>$input['input_dossiers'],'candidate_pairs_examined'=>$input['candidate_pairs_examined'],
        'ordinary_ids_outside_legacy49'=>$input['ordinary_ids_outside_legacy49'],
        'retained_plan_sha256'=>V66_BULK_PLAN_SHA,'database_reads'=>0,'database_writes'=>0,
        'provider_http_calls'=>0,'mapping_writes'=>0,'current_validation_performed'=>false,
        'execution_authorized'=>false,'safe_to_write_now'=>false];
}

function v66_query(PDO $db, string $sql, array $args = []): array {
    $statement = $db->prepare($sql); v66_need($statement !== false, 'query_prepare');
    v66_need($statement->execute(array_values($args)), 'query_execute');
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    v66_need(count($rows) <= V66_MAX_ROWS, 'query_row_cap');
    return $rows;
}
function v66_indexes(array $rows): array {
    $registry=[]; $source=[]; $target=[];
    foreach ($rows as $row) {
        $ns=(string)$row['supplier_namespace']; $external=v66_id($row['external_hotel_id']);
        $local=$row['local_hotel_id'] === null ? null : (int)v66_id($row['local_hotel_id']);
        $raw=(string)($row['evidence_json'] ?? ''); $sha=(string)($row['evidence_sha256'] ?? '');
        $valid=v66_hash($sha) && hash_equals($sha, hash('sha256', $raw));
        if ($valid) {
            try { $e=json_decode($raw, true, 128, JSON_THROW_ON_ERROR); $valid=is_array($e) && $e !== []; }
            catch (Throwable) { $valid=false; }
        }
        $safe=['external_hotel_id'=>$external, 'local_hotel_id'=>$local,
               'decision_status'=>(string)$row['decision_status'], 'evidence_valid'=>$valid,
               'catalog_sha256'=>(string)($row['catalog_sha256'] ?? ''), 'evidence_sha256'=>$sha];
        if ($ns === 'andromeda_catalog') {
            $source[$external][]=$safe;
            if ($local !== null) $target[$local][]=$safe;
        } else {
            v66_need(in_array($ns, V66_NS, true), 'registry_namespace');
            $registry[$ns.'|'.$external][]=$safe;
        }
    }
    return compact('registry','source','target');
}
function v66_current(PDO $db, array $selected): array {
    $ids=array_map('intval', array_keys($selected)); $ph=implode(',', array_fill(0,count($ids),'?'));
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try {
        $hotels=[];
        foreach (v66_query($db,"SELECT id,name,country_name,is_active FROM catalog_hotels WHERE id IN ($ph)",$ids) as $h) $hotels[(int)$h['id']]=$h;
        $live=[];
        foreach (v66_query($db,"SELECT hotel_id,MAX(last_seen_at) AS seen FROM tour_operator_identity_observations WHERE hotel_id IN ($ph) GROUP BY hotel_id HAVING seen>=?",[...$ids,gmdate('Y-m-d H:i:s',time()-30*86400)]) as $row) $live[(int)$row['hotel_id']]=true;
        $manual=[];
        foreach (v66_query($db,"SELECT DISTINCT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph)",$ids) as $row) $manual[(int)$row['catalog_hotel_id']]=true;
        $rows=v66_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace IN ('andromeda_catalog','operator_5','operator_115','operator_315','operator_342') ORDER BY supplier_namespace,external_hotel_id,local_hotel_id LIMIT 100001");
        $indexes=v66_indexes($rows);
        $coverage=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);
        $anex=[];
        foreach ($ids as $id) $anex[$id]=v66_ids(array_keys($coverage['by_local'][$id] ?? []));
        $db->rollBack();
        return $indexes+compact('hotels','live','manual','anex')+['registry_rows_read'=>count($rows)];
    } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
}
function v66_classify(int $local, array $entry, array $input, array $current, bool $useRetained = false): array {
    $d=$entry['dossier']; $c=$entry['candidate']; $catalog=v66_id($c['catalog_id']); $reasons=[]; $lanes=[]; $matched=0; $retainedMatched=0;
    $hasRetained=$useRetained && ($entry['retained_plan_sha256'] ?? '')===V66_BULK_PLAN_SHA;
    if ($hasRetained) foreach ($entry['historical_review_reasons'] as $reason) $reasons[]='retained_history:'.$reason;
    $hotel=$current['hotels'][$local] ?? null;
    if (!$hotel || (int)$hotel['is_active'] !== 1 || v66_excluded((string)$hotel['country_name'])) $reasons[]='target_inactive_or_excluded';
    if (!isset($current['live'][$local])) $reasons[]='target_not_tv_live30';
    if (isset($current['manual'][$local])) $reasons[]='manual_target';
    $direct=$current['anex'][$local] ?? [];
    if ($direct === []) $reasons[]='effective_anex_missing';
    elseif (v66_ids($direct) !== v66_ids($d['direct_anex_ids'])) $reasons[]='effective_anex_drift';
    if (count($input['catalogTargets'][$catalog] ?? []) !== 1) $reasons[]='candidate_catalog_multiple_targets';
    $same=false;
    foreach ($current['source'][$catalog] ?? [] as $row) {
        if ($row['decision_status'] === 'accepted' && $row['local_hotel_id'] === $local && $row['evidence_valid']) $same=true;
        else $reasons[]='source_catalog_occupied_or_protected';
    }
    foreach ($current['target'][$local] ?? [] as $row) {
        if ($row['external_hotel_id'] !== $catalog || $row['decision_status'] !== 'accepted' || !$row['evidence_valid']) $reasons[]='target_catalog_occupied_or_protected';
    }
    foreach (V66_NS as $ns) {
        $ids=v66_ids($c['lanes'][$ns]['native_ids']); $state='not_observed'; $evidence=[]; $targets=[];
        if (count($ids)>1 || count($input['catalogNatives'][$catalog][$ns] ?? [])>1) {
            $state='source_namespace_ambiguous'; $reasons[]=$ns.':'.$state;
        } elseif (count($ids) === 1) {
            $native=$ids[0];
            if (count($input['nativeCatalog'][$ns.'|'.$native] ?? []) !== 1) {
                $state='native_catalog_collision'; $reasons[]=$ns.':'.$state;
            } else {
                $state='registry_missing'; $invalid=false; $protected=false;
                foreach ($current['registry'][$ns.'|'.$native] ?? [] as $row) {
                    if ($row['decision_status'] !== 'accepted') { $protected=true; continue; }
                    if (!$row['evidence_valid'] || $row['local_hotel_id'] === null) { $invalid=true; continue; }
                    $targets[$row['local_hotel_id']]=true;
                    $evidence[]=['local_hotel_id'=>$row['local_hotel_id'],'evidence_sha256'=>$row['evidence_sha256'],'catalog_sha256'=>$row['catalog_sha256']];
                }
                if ($protected) $state='registry_protected_decision';
                elseif ($invalid) $state='registry_invalid_evidence';
                elseif (count($targets)>1) $state='registry_target_collision';
                elseif (count($targets)===1) $state=(int)array_key_first($targets)===$local?'registry_matches_target':'registry_other_target';
                if ($state==='registry_matches_target') $matched++;
                elseif ($state==='registry_missing' && $hasRetained && isset($entry['retained_proofs'][$ns])) {
                    v66_need(v66_id($entry['retained_proofs'][$ns]['native_id'])===$native, 'retained_native_drift');
                    $state='retained_exact_proof_matches_target'; $retainedMatched++;
                }
                elseif ($state!=='registry_missing') $reasons[]=$ns.':'.$state;
            }
        }
        $lanes[$ns]=['state'=>$state,'native_ids'=>$ids,'registry_target_ids'=>array_map('intval',array_keys($targets)),'registry_evidence'=>$evidence];
        if ($hasRetained && isset($entry['retained_proofs'][$ns])) $lanes[$ns]['retained_evidence']=$entry['retained_proofs'][$ns]['evidence'];
    }
    // One proven same-operator identity is sufficient; contradictory lanes still veto.
    if ($matched + $retainedMatched < 1) $reasons[]='no_proven_cross_source_lane';
    $reasons=array_values(array_unique($reasons)); sort($reasons, SORT_STRING);
    $status=$reasons!==[]?'hold':($same?'already_resolved_same':'ready_for_guarded_writer');
    return ['local_hotel_id'=>$local,'andromeda_catalog_id'=>$catalog,'hotel_name'=>$d['hotel_name'],
            'candidate_names'=>$c['names'],'retrieval'=>$c['retrieval'],'geo_relation'=>$c['geo_relation'],
            'observed_operator_lanes'=>$c['lane_count'],'current_cross_source_lanes'=>$matched,
            'retained_cross_source_lanes'=>$retainedMatched,'proven_cross_source_lanes'=>$matched+$retainedMatched,
            'direct_anex_support'=>v66_support($direct,$c['lanes']['operator_5']['native_ids']),
            'status'=>$status,'reasons'=>$reasons,'lanes'=>$lanes,
            'v65_dossier_sha256'=>$entry['dossier_sha256'],'v65_candidate_sha256'=>$entry['candidate_sha256'],
            'source_result_sha256'=>V66_SOURCE_SHA,'safe_to_write_now'=>false];
}
function v66_run(PDO $db, array $source, string $head): array {
    $input=v66_source($source); $current=v66_current($db,$input['selected']); $rows=[]; $counts=[]; $reasons=[]; $hist=[];
    foreach ($input['selected'] as $id=>$entry) {
        $r=v66_classify((int)$id,$entry,$input,$current); $rows[]=$r;
        $counts[$r['status']]=($counts[$r['status']] ?? 0)+1;
        $n=(string)$r['current_cross_source_lanes']; $hist[$n]=($hist[$n] ?? 0)+1;
        foreach ($r['reasons'] as $reason) $reasons[$reason]=($reasons[$reason] ?? 0)+1;
    }
    ksort($counts); ksort($reasons); ksort($hist,SORT_NUMERIC);
    return ['operation'=>V66_OP,'state'=>'completed_read_only_v65_current_audit','source_sha'=>$head,
            'source_result_sha256'=>V66_SOURCE_SHA,'generated_at_utc'=>gmdate('c'),
            'input_strict_count'=>49,'input_catalog_count'=>48,'registry_rows_read'=>$current['registry_rows_read'],
            'status_counts'=>$counts,'reason_counts'=>$reasons,'current_lane_histogram'=>$hist,'rows'=>$rows,
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,
            'database_reads'=>1,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
}
if (PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__) {
    v66_need(($argv[1] ?? '')==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT'); $dir=(string)getenv('MATCH_OPERATION_DIR');
    $source=(string)getenv('MATCH_SOURCE_RESULT'); $receipt=(string)getenv('MATCH_SOURCE_RECEIPT'); $head=(string)getenv('MATCH_SOURCE_SHA');
    v66_need(is_dir($root) && !is_link($root) && is_dir($dir) && !is_link($dir) && basename($dir)===V66_OP && preg_match('/^[0-9a-f]{40}$/D',$head)===1,'runtime_scope');
    v66_need(hash_file('sha256',$source)===V66_SOURCE_SHA && hash_file('sha256',$receipt)===V66_RECEIPT_SHA,'source_hash');
    $q=v66_load($receipt); v66_need(($q['operation'] ?? '')===V66_SOURCE_OP && ($q['result_sha256'] ?? '')===V66_SOURCE_SHA && ($q['readback_verified'] ?? false)===true && ($q['no_replay'] ?? false)===true,'source_receipt');
    $reservation=v66_load($dir.'/reservation.json');
    v66_need(($reservation['operation'] ?? '')===V66_OP && ($reservation['state'] ?? '')==='reserved_before_db_read','reservation');
    v66_need(!file_exists($dir.'/result.json') && !file_exists($dir.'/receipt.json'),'terminal_no_replay');
    v66_save($dir.'/execution-started.json',['operation'=>V66_OP,'source_sha'=>$head,'state'=>'db_read_reserved']);
    $readStarted=false;
    try {
        $input=v66_load($source); v66_source($input);
        require_once __DIR__.'/hotel_match_anex_effective_coverage.php';
        require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        $readStarted=true; $result=v66_run(v2_data_db(),$input,$head);
    } catch (Throwable $error) {
        // Database exception messages can contain private connection information.
        $code=$error->getMessage();
        if (preg_match('/^[a-z][a-z0-9_]{0,99}$/D',$code)!==1) $code=$error instanceof PDOException?'database_read_failed':'audit_failed';
        $result=['operation'=>V66_OP,'state'=>'failed_read_only_v65_current_audit','reason'=>$code,'source_sha'=>$head,
                 'provider_http_calls'=>0,'database_reads'=>$readStarted?1:0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }
    $hash=v66_save($dir.'/result.json',$result);
    v66_save($dir.'/receipt.json',['operation'=>V66_OP,'state'=>$result['state'],'result_sha256'=>$hash,
        'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'no_replay'=>true,
        'provider_http_calls'=>0,'database_reads'=>$readStarted?1:0,'database_writes'=>0,'mapping_writes'=>0]);
    echo v66_json(array_diff_key($result,['rows'=>true]))."\n";
    exit($result['state']==='completed_read_only_v65_current_audit'?0:2);
}
