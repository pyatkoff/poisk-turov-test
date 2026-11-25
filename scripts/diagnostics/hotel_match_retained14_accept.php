<?php
declare(strict_types=1);

/**
 * Distinct owner-authorized acceptance, not a replay or mutation of the v68 audit.
 * Storage contract: andromeda_country_expansion.php / common4 writers, consumed by
 * v2/api-andromeda-search3-preview.php. Only new andromeda_catalog identities are
 * inserted; no upsert, UPDATE, DELETE, DDL, provider client, or public route.
 */
require_once __DIR__.'/hotel_match_live234_v65_current_audit_v66.php';
require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const MRA_OP = 'hotel-match-retained14-accept-1971-20260927-v1';
const MRA_AUDIT_OP = 'hotel-match-retained29-current-1971-20260927-v68';
const MRA_AUDIT_SHA = '0b592a30a5c301d9631433042c5239baf9cc7f7f427cac5895c9de9300ed4eb9';
const MRA_RECEIPT_SHA = '636f24b549f4e7aa96c5d3d0e151fe167e37d793f01cf4afbe4b12fb03be3ad3';
const MRA_PAIRS = [11742=>'183505',11748=>'191231',11771=>'364948',11773=>'364934',
    27691=>'246797',45455=>'2000034442',60000=>'2000055823',64351=>'2000042345',
    64355=>'2000056913',64722=>'2000055559',71376=>'2000061777',113617=>'2000090568',
    117800=>'2000090560',119844=>'2000106853'];

function mra_inputs(string $source, string $plan, string $audit, string $receipt): array {
    v66_need(hash('sha256',$audit)===MRA_AUDIT_SHA && hash('sha256',$receipt)===MRA_RECEIPT_SHA,'audit_bytes_changed');
    $a=json_decode($audit,true,128,JSON_THROW_ON_ERROR); $q=json_decode($receipt,true,128,JSON_THROW_ON_ERROR);
    v66_need($a['operation']===MRA_AUDIT_OP && $a['state']==='completed_read_only_retained29_current','audit_state');
    v66_need($q['operation']===MRA_AUDIT_OP && $q['result_sha256']===MRA_AUDIT_SHA && $q['readback_verified']===true && $q['no_replay']===true,'audit_receipt');
    v66_need($a['source_sha']==='cd9b5503678cc3c426f491181f0cc5c0cf745ef7' && $a['source_result_sha256']===V66_SOURCE_SHA && $a['retained_plan_sha256']===V66_BULK_PLAN_SHA,'audit_provenance');
    foreach ([$a,$q] as $old) foreach (['database_writes','mapping_writes','provider_http_calls'] as $key) v66_need($old[$key]===0,'audit_not_read_only');
    $input=v66_prepare_bulk($source,$plan); $selected=[]; $held=[]; $seen=[];
    foreach ($a['rows'] as $r) {
        $id=(int)v66_id($r['local_hotel_id']); $catalog=v66_id($r['andromeda_catalog_id']);
        v66_need(!isset($seen[$id]),'audit_duplicate'); $seen[$id]=true;
        $e=$input['selected'][$id]??null;
        v66_need(is_array($e) && v66_id($e['candidate']['catalog_id'])===$catalog,'audit_pair_binding');
        v66_need($e['dossier_sha256']===$r['v65_dossier_sha256'] && $e['candidate_sha256']===$r['v65_candidate_sha256'],'audit_object_binding');
        if ($r['status']==='ready_for_guarded_writer') {
            v66_need((MRA_PAIRS[$id]??null)===$catalog && $r['reasons']===[] && $r['proven_cross_source_lanes']>=1,'ready_scope');
            v66_need($e['historical_review_reasons']===[] && count($e['retained_proofs'])>=1,'historical_or_proof_guard');
            $selected[$id]=$e;
        } else {
            v66_need($r['status']==='hold' && !isset(MRA_PAIRS[$id]),'hold_scope'); $held[$id]=$catalog;
        }
    }
    ksort($selected,SORT_NUMERIC);
    v66_need(count($seen)===29 && count($held)===15 && array_keys($selected)===array_keys(MRA_PAIRS),'exact_14_15_scope');
    return ['input'=>$input,'selected'=>$selected,'excluded'=>$held];
}

function mra_schema(PDO $db): void {
    $required=['andromeda_hotel_identities','catalog_hotels','tour_operator_identity_observations','anex_hotel_decisions','anex_hotel_search_mappings'];
    $wanted=[...$required,'anex_review_pair_exclusions']; $ph=implode(',',array_fill(0,count($wanted),'?'));
    $tables=v66_query($db,"SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($ph)",$wanted);
    $seen=[]; foreach ($tables as $t) { v66_need(strtoupper((string)$t['ENGINE'])==='INNODB','nontransactional_table'); $seen[$t['TABLE_NAME']]=true; }
    foreach ($required as $name) v66_need(isset($seen[$name]),'required_table_missing');
    $index=v66_query($db,"SHOW INDEX FROM andromeda_hotel_identities WHERE Key_name='PRIMARY'");
    usort($index,fn($a,$b)=>(int)$a['Seq_in_index']<=>(int)$b['Seq_in_index']);
    v66_need(array_column($index,'Column_name')===['supplier_namespace','external_hotel_id'],'identity_primary_key');
}
function mra_key(array $r): string { return (string)$r['supplier_namespace'].'|'.(string)$r['external_hotel_id']; }
function mra_index(array $rows): array {
    $out=[]; foreach ($rows as $r) { $k=mra_key($r); v66_need(!isset($out[$k]),'identity_duplicate'); $out[$k]=$r; } return $out;
}
function mra_hashes(array $rows): array {
    $out=[];foreach(mra_index($rows) as $k=>$r)$out[$k]=hash('sha256',v66_json($r));ksort($out,SORT_STRING);return $out;
}

/** Uses caller's SERIALIZABLE read/write transaction, never v66_current's RO transaction. */
function mra_locked_current(PDO $db,array $scope,array $identities): array {
    v66_need($db->inTransaction(),'transaction_required');
    $ids=array_keys($scope['selected']);$ph=implode(',',array_fill(0,count($ids),'?'));
    $hotels=[];foreach(v66_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id FOR UPDATE",$ids) as $r)$hotels[(int)$r['id']]=$r;
    $manual=[];foreach(v66_query($db,"SELECT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph) ORDER BY catalog_hotel_id FOR UPDATE",$ids) as $r)$manual[(int)$r['catalog_hotel_id']]=true;
    // SERIALIZABLE shared reads protect the live30 and effective-ANEX projections.
    $live=[];foreach(v66_query($db,"SELECT hotel_id,MAX(last_seen_at) AS seen FROM tour_operator_identity_observations WHERE hotel_id IN ($ph) GROUP BY hotel_id HAVING seen>=?",[...$ids,gmdate('Y-m-d H:i:s',time()-30*86400)]) as $r)$live[(int)$r['hotel_id']]=true;
    $coverage=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anex=[];
    foreach($ids as $id)$anex[$id]=v66_ids(array_keys($coverage['by_local'][$id]??[]));
    $relevant=array_values(array_filter($identities,fn($r)=>in_array($r['supplier_namespace'],['andromeda_catalog',...V66_NS],true)));
    return v66_indexes($relevant)+compact('hotels','manual','live','anex');
}
function mra_evidence(int $id,array $entry,array $assessment,array $target,string $head): array {
    return ['operation_id'=>MRA_OP,'rule'=>'retained_exact_operator_cross_source_identity_v1','source_sha'=>$head,
        'supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>MRA_PAIRS[$id],'local_hotel_id'=>$id,
        'source_result_sha256'=>V66_SOURCE_SHA,'source_operation'=>V66_SOURCE_OP,
        'catalog_digest_semantics'=>'sealed_andromeda_context_acquisition_result_not_full_country_catalogue',
        'source_candidate'=>$entry['candidate'],'source_candidate_sha256'=>$entry['candidate_sha256'],
        'source_dossier_sha256'=>$entry['dossier_sha256'],'source_binding'=>$entry['dossier']['binding'],
        'retained_plan_sha256'=>V66_BULK_PLAN_SHA,'retained_proofs'=>$entry['retained_proofs'],
        'prior_current_audit'=>['operation'=>MRA_AUDIT_OP,'result_sha256'=>MRA_AUDIT_SHA],
        'target'=>$target,'transaction_time_assessment'=>$assessment,'required_exact_operator_lanes'=>1,
        'second_operator_required'=>false,'provider_http_calls'=>0];
}

/** Return terminal accounting even if COMMIT succeeds but readback fails. No automatic retries. */
function mra_write(PDO $db,array $scope,string $head,string $dir): array {
    v66_need(array_keys($scope['selected'])===array_keys(MRA_PAIRS),'writer_scope');
    v66_need(preg_match('/^[0-9a-f]{40}$/D',$head)===1 && is_dir($dir),'writer_environment');
    $committed=false;$attempted=false;$planned=[];$held=[];$already=[];$phase='schema';
    $base=['operation'=>MRA_OP,'source_sha'=>$head,'prior_audit_result_sha256'=>MRA_AUDIT_SHA,
        'source_result_sha256'=>V66_SOURCE_SHA,'retained_plan_sha256'=>V66_BULK_PLAN_SHA,
        'input_count'=>14,'excluded_hold_count'=>15,'provider_http_calls'=>0,'no_replay'=>true];
    try {
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);mra_schema($db);
        $db->exec('SET SESSION innodb_lock_wait_timeout=20');
        $db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();$phase='locked_current';
        // Existing MATCH lock order: identity rows, local catalogue, manual/ANEX guards.
        $before=v66_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 100001 FOR UPDATE');
        $beforeHashes=mra_hashes($before);$byKey=mra_index($before);
        $current=mra_locked_current($db,$scope,$before);
        foreach($scope['selected'] as $id=>$entry) {
            $row=v66_classify((int)$id,$entry,$scope['input'],$current,true);
            $hotel=$current['hotels'][$id]??null;
            if($hotel && (string)$hotel['name']!==$entry['dossier']['hotel_name']) { $row['status']='hold';$row['reasons'][]='target_name_changed'; }
            if($row['status']==='hold') { $held[]=$row;continue; }
            if($row['status']==='already_resolved_same') { $already[]=$row;continue; }
            v66_need($row['status']==='ready_for_guarded_writer' && $row['reasons']===[] && $hotel!==null,'current_not_ready');
            $key='andromeda_catalog|'.MRA_PAIRS[$id];v66_need(!isset($byKey[$key]),'existing_source_never_overwrite');
            $ev=mra_evidence((int)$id,$entry,$row,$hotel,$head);$raw=v66_json($ev);
            $planned[]=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>MRA_PAIRS[$id],
                'local_hotel_id'=>(int)$id,'decision_status'=>'accepted','catalog_sha256'=>V66_SOURCE_SHA,
                'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];
        }
        v66_need(count($planned)+count($held)+count($already)===14,'current_accounting');
        v66_save($dir.'/write-plan.json',$base+['state'=>'locked_and_validated_before_insert','planned_pairs'=>array_map(fn($r)=>[$r['local_hotel_id'],$r['external_hotel_id'],$r['evidence_sha256']],$planned),'current_hold'=>$held,'already_resolved'=>$already,'preexisting_count'=>count($before),'preexisting_sha256'=>hash('sha256',v66_json($beforeHashes))]);
        $phase='insert';
        $st=$db->prepare("INSERT INTO andromeda_hotel_identities(supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES(?,?,?,'accepted',?,?,?)");
        foreach($planned as $p) { $st->execute([$p['supplier_namespace'],$p['external_hotel_id'],$p['local_hotel_id'],$p['catalog_sha256'],$p['evidence_sha256'],$p['evidence_json']]);v66_need($st->rowCount()===1,'insert_count'); }
        $staged=v66_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 100001');$after=mra_index($staged);
        v66_need(count($staged)===count($before)+count($planned),'staged_count');
        foreach($beforeHashes as $k=>$h)v66_need(isset($after[$k]) && hash('sha256',v66_json($after[$k]))===$h,'preexisting_identity_changed');
        foreach($planned as $p)foreach($p as $f=>$v)v66_need((string)($after[mra_key($p)][$f]??'')===(string)$v,'staged_identity_mismatch');
        v66_save($dir.'/pre-commit.json',$base+['state'=>'verified_before_commit','planned_writes'=>count($planned),'preexisting_preserved'=>count($before)]);
        $phase='commit';v66_save($dir.'/commit-attempt.json',$base+['state'=>'commit_attempt_no_replay','planned_writes'=>count($planned)]);
        $attempted=true;v66_need($db->commit(),'commit_false');$committed=true;$phase='post_commit_readback';
        $read=[];$q=$db->prepare("SELECT i.supplier_namespace,i.external_hotel_id,i.local_hotel_id,i.decision_status,i.catalog_sha256,i.evidence_sha256,i.evidence_json,h.id AS existing_catalog_hotel_id,h.is_active FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.supplier_namespace='andromeda_catalog' AND i.external_hotel_id=?");
        foreach($planned as $p) {
            $q->execute([$p['external_hotel_id']]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);v66_need(count($rows)===1,'post_commit_row_count');$r=$rows[0];
            foreach($p as $f=>$v)v66_need((string)($r[$f]??'')===(string)$v,'post_commit_identity_mismatch');
            v66_need((int)$r['existing_catalog_hotel_id']===$p['local_hotel_id'] && (int)$r['is_active']===1,'runtime_resolution_failed');
            v66_need(hash('sha256',$r['evidence_json'])===$r['evidence_sha256'],'post_commit_evidence_hash');
            $read[]=['local_hotel_id'=>$p['local_hotel_id'],'andromeda_catalog_id'=>$p['external_hotel_id'],
                'decision_status'=>'accepted','catalog_sha256'=>$p['catalog_sha256'],'evidence_sha256'=>$p['evidence_sha256'],'runtime_resolves'=>true];
        }
        return $base+['state'=>'committed_verified','committed'=>true,'commit_attempted'=>true,'inserted'=>count($planned),
            'database_writes'=>count($planned),'mapping_writes'=>count($planned),'readback_verified'=>true,
            'preexisting_rows_preserved'=>count($before),'current_hold_count'=>count($held),'already_resolved_count'=>count($already),
            'rows'=>$read,'current_hold'=>$held,'already_resolved'=>$already];
    } catch(Throwable $error) {
        try {if($db->inTransaction())$db->rollBack();}catch(Throwable){}
        $state=$committed?'committed_readback_failed_no_replay':($attempted?'commit_unknown_no_replay':'rolled_back_no_write');
        $writes=$committed?count($planned):($attempted?null:0);
        $reason=$error->getMessage();if(preg_match('/^[a-z][a-z0-9_]{0,99}$/D',$reason)!==1)$reason=$error instanceof PDOException?'database_operation_failed':'guard_failed';
        return $base+['state'=>$state,'failure_stage'=>$phase,'reason'=>$reason,'committed'=>$committed,'commit_attempted'=>$attempted,
            'database_writes'=>$writes,'mapping_writes'=>$writes,'readback_verified'=>false,'planned_count'=>count($planned),'current_hold'=>$held];
    }
}

function mra_main(array $argv): int {
    v66_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
    v66_need(is_dir($root)&&!is_link($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&!is_link($dir)&&basename($dir)===MRA_OP,'runtime_scope');
    $reservation=v66_load($dir.'/reservation.json');
    v66_need(($reservation['operation']??'')===MRA_OP&&($reservation['state']??'')==='reserved_before_write'&&($reservation['source_sha']??'')===$head&&($reservation['expected_max_writes']??0)===14&&($reservation['prior_audit_result_sha256']??'')===MRA_AUDIT_SHA,'reservation');
    foreach(['execution-started.json','result.json','receipt.json','commit-attempt.json'] as $name)v66_need(!file_exists($dir.'/'.$name),'terminal_no_replay');
    $paths=[];foreach(['source','plan','audit','audit_receipt','source_receipt'] as $n){$p=$dir.'/payload/'.$n.'.json';v66_need(is_file($p)&&!is_link($p)&&filesize($p)<=8388608,'input_file');$paths[$n]=(string)file_get_contents($p);}
    v66_need(hash('sha256',$paths['source_receipt'])===V66_RECEIPT_SHA,'source_receipt_hash');
    $scope=mra_inputs($paths['source'],$paths['plan'],$paths['audit'],$paths['audit_receipt']);
    v66_save($dir.'/execution-started.json',['operation'=>MRA_OP,'source_sha'=>$head,'state'=>'reserved_before_db','prior_audit_result_sha256'=>MRA_AUDIT_SHA]);
    try {
        $bootstrap=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        v66_need(is_file($bootstrap)&&!is_link($bootstrap),'db_bootstrap');require_once $bootstrap;
        $result=mra_write(v2_data_db(),$scope,$head,$dir);
    }catch(Throwable){$result=['operation'=>MRA_OP,'source_sha'=>$head,'state'=>'failed_before_write','database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>false,'provider_http_calls'=>0,'no_replay'=>true];}
    $hash=v66_save($dir.'/result.json',$result);
    v66_save($dir.'/receipt.json',['operation'=>MRA_OP,'state'=>$result['state'],'source_sha'=>$head,'result_sha256'=>$hash,
        'artifact_readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'readback_verified'=>$result['readback_verified'],
        'database_writes'=>$result['database_writes'],'mapping_writes'=>$result['mapping_writes'],'provider_http_calls'=>0,'no_replay'=>true]);
    echo v66_json(array_diff_key($result,['rows'=>true,'current_hold'=>true,'already_resolved'=>true]))."\n";
    return $result['state']==='committed_verified'?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(mra_main($argv));
