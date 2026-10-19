<?php
declare(strict_types=1);

if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_anex_bridge_fuzzy_review.php';

const HTCA_OPERATION = 'hotel-match-tourvisor-containment-accept-1971-20260911-v1';

function htca_require_transactional(PDO $db): void {
    $tables = [
        'catalog_hotels','catalog_hotel_details','hotel_aliases','anex_hotel_search_mappings',
        'andromeda_hotel_identities','andromeda_search_hotel_observations',
    ];
    $q=$db->prepare("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    foreach($tables as $table){
        $q->execute([$table]); $engine=$q->fetchColumn();
        if($engine===false)throw new RuntimeException('required_table_missing:'.$table);
        if(strcasecmp((string)$engine,'InnoDB')!==0)throw new RuntimeException('required_table_not_innodb:'.$table);
    }
}

function htca_identity_counts(PDO $db): array {
    $out=['accepted'=>0,'pending'=>0,'conflict'=>0];
    foreach($db->query("SELECT decision_status,COUNT(*) n FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' GROUP BY decision_status")->fetchAll(PDO::FETCH_ASSOC) as $row){
        $status=(string)$row['decision_status']; if(array_key_exists($status,$out))$out[$status]=(int)$row['n'];
    }
    return $out;
}

function htca_candidate_safe(array $row): bool {
    if(($row['rule']??'')!=='tourvisor_one_sided_identity_plus_direct_geo')return false;
    if((int)($row['target_local_hotel_id']??0)<=0)return false;
    if(!isset(MBR_CORE8[(int)($row['country_id']??0)]))return false;
    $pair=$row['pair']??null; if(!is_array($pair)||($pair['safe_rank']??0)!==2)return false;
    $policy=$pair['policy']??null; if(!is_array($policy)||!($policy['safe_containment']??false))return false;
    if(!($policy['critical_ok']??false)||!($policy['anchor_ok']??false)||!($policy['one_sided']??false)||!($policy['subsequence']??false))return false;
    if((int)($policy['extra_token_count']??99)>1||(int)($policy['shared_unique']??0)<2||(float)($policy['score']??0)<0.60)return false;
    if((float)($row['score_margin']??0)<0.15)return false;
    $distance=$row['distance_m']??null; if($distance!==null&&(float)$distance>5000.0)return false;
    $places=array_map('strval',$row['source_places']??[]);
    if(!fc_place($places,[(string)($row['target_region']??''),(string)($row['target_subregion']??'')]))return false;
    return true;
}

function htca_evidence(string $operation,array $row,array $prior): array {
    return [
        'prior_evidence'=>$prior,
        'promotion'=>[
            'operation_id'=>$operation,'lane'=>'MATCH','rule'=>$row['rule']??null,
            'country_id'=>(int)($row['country_id']??0),'target'=>(int)($row['target_local_hotel_id']??0),
            'source_names'=>$row['source_names']??[],'source_places'=>$row['source_places']??[],
            'source_category'=>$row['source_category']??null,'target_category'=>$row['target_category']??null,
            'category_mismatch'=>(bool)($row['category_mismatch']??false),'existing_anex_link'=>(bool)($row['existing_anex_link']??false),
            'pair'=>$row['pair']??null,'score_margin'=>$row['score_margin']??null,'distance_m'=>$row['distance_m']??null,
            'live_observed'=>(bool)($row['live_observed']??false),'observation_count'=>(int)($row['observation_count']??0),
            'last_seen_utc'=>$row['last_seen_utc']??null,'server_current'=>true,
        ],
    ];
}

function htca_accept(PDO $db,string $operation=HTCA_OPERATION,int $maxWrites=300): array {
    if($operation!==HTCA_OPERATION)throw new RuntimeException('operation_scope');
    if($maxWrites<1||$maxWrites>300)throw new RuntimeException('write_scope_limit_config');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    htca_require_transactional($db);
    $db->exec('SET SESSION innodb_lock_wait_timeout=20');
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $beforeCoverage=fc_coverage($db); $beforeIdentity=htca_identity_counts($db);
    $rows=[]; $plannedRaw=0; $plannedSafe=0; $writes=0; $skipped=['planner_guard'=>0,'stale_or_protected'=>0]; $committed=false;
    try{
        $db->beginTransaction();
        $db->query("SELECT external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
        $review=habfr_review($db,HABFR_OPERATION);
        $prepared=is_array($review['safe_prepared']??null)?$review['safe_prepared']:[]; $plannedRaw=count($prepared);
        if($plannedRaw>$maxWrites)throw new RuntimeException('write_scope_limit');
        $select=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? FOR UPDATE");
        $update=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256 <=> ?");
        foreach($prepared as $row){
            if(!is_array($row)||!htca_candidate_safe($row)){$skipped['planner_guard']++;continue;}
            $plannedSafe++; $external=(string)$row['external_id']; $target=(int)$row['target_local_hotel_id'];
            $select->execute([$external]); $current=$select->fetch(PDO::FETCH_ASSOC);
            if(!$current||$current['decision_status']!=='pending'||$current['local_hotel_id']!==null){$skipped['stale_or_protected']++;continue;}
            $prior=fc_evidence($current['evidence_json']??''); $evidence=htca_evidence($operation,$row,$prior); $json=fc_json($evidence); $sha=hash('sha256',$json); $oldSha=$current['evidence_sha256'];
            $update->execute([$target,$sha,$json,$external,$oldSha]); if($update->rowCount()!==1)throw new RuntimeException('concurrency_guard_failed:'.$external);
            $rows[$external]=['target'=>$target,'evidence_sha256'=>$sha,'rule'=>(string)$row['rule'],'live_observed'=>(bool)($row['live_observed']??false),'category_mismatch'=>(bool)($row['category_mismatch']??false),'existing_anex_link'=>(bool)($row['existing_anex_link']??false)];
            $writes++; if($writes>$maxWrites)throw new RuntimeException('write_scope_limit');
        }
        $db->commit(); $committed=true;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    if(!$committed)throw new RuntimeException('not_committed');
    $read=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");
    foreach($rows as $external=>$expected){
        $read->execute([$external]); $actual=$read->fetch(PDO::FETCH_ASSOC);
        if(!$actual||(int)$actual['local_hotel_id']!==$expected['target']||$actual['decision_status']!=='accepted'||!hash_equals($expected['evidence_sha256'],(string)$actual['evidence_sha256']))throw new RuntimeException('post_commit_readback_failed:'.$external);
        $rows[$external]['readback']='verified';
    }
    $afterCoverage=fc_coverage($db); $afterIdentity=htca_identity_counts($db); $liveWrites=0;$categoryMismatchWrites=0;$anexBridgeWrites=0;
    foreach($rows as $row){if($row['live_observed'])$liveWrites++;if($row['category_mismatch'])$categoryMismatchWrites++;if($row['existing_anex_link'])$anexBridgeWrites++;}
    return [
        'status'=>'committed_readback_verified','operation_id'=>$operation,'database_writes'=>$writes,'supplier_calls'=>0,'historical_operations_replayed'=>false,
        'planner_operation_used_as_library'=>HABFR_OPERATION,'planned_raw'=>$plannedRaw,'planned_safe'=>$plannedSafe,'skipped'=>$skipped,
        'live_writes'=>$liveWrites,'category_mismatch_writes'=>$categoryMismatchWrites,'existing_anex_bridge_writes'=>$anexBridgeWrites,'rows'=>$rows,
        'before'=>['coverage'=>$beforeCoverage,'identity'=>$beforeIdentity],'after'=>['coverage'=>$afterCoverage,'identity'=>$afterIdentity],
    ];
}
