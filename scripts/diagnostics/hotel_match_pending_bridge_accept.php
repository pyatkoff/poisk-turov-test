<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_pending_candidate_bridge_review.php';

const PBA_OPERATION = 'hotel-match-pending-bridge-accept-1971-20260911-v1';

function pba_identity_counts(PDO $db): array {
    $out=['accepted'=>0,'pending'=>0,'conflict'=>0];
    foreach($db->query("SELECT decision_status,COUNT(*) n FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' GROUP BY decision_status")->fetchAll(PDO::FETCH_ASSOC) as $r){if(isset($out[$r['decision_status']]))$out[$r['decision_status']]=(int)$r['n'];}
    return $out;
}

function pba_evidence(string $operation,array $row,array $prior): array {
    return ['prior_evidence'=>$prior,'promotion'=>[
        'operation_id'=>$operation,'lane'=>'MATCH','rule'=>'strict_ordered_identity_plus_direct_geo_plus_existing_anex_tourvisor',
        'country_id'=>(int)$row['country_id'],'target'=>(int)$row['target_local_hotel_id'],
        'source_names'=>$row['source_names']??[],'source_places'=>$row['source_places']??[],
        'strict_identity'=>$row['strict_identity']??null,'source_category'=>$row['source_category']??null,'target_category'=>$row['target_category']??null,
        'category_mismatch'=>(bool)($row['category_mismatch']??false),'server_current'=>true,
    ]];
}

function pba_accept(PDO $db,string $operation=PBA_OPERATION): array {
    if($operation!==PBA_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $engine=$db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='andromeda_hotel_identities'")->fetchColumn();
    if($engine===false||strcasecmp((string)$engine,'InnoDB')!==0)throw new RuntimeException('identity_table_not_transactional');
    $beforeCoverage=fc_coverage($db);$beforeIdentity=pba_identity_counts($db);$writes=0;$planned=0;$rows=[];$committed=false;
    try{
        $db->exec('SET SESSION innodb_lock_wait_timeout=20');
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->beginTransaction();
        $db->query("SELECT external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
        $db->query("SELECT anex_hotel_id FROM anex_hotel_search_mappings ORDER BY anex_hotel_id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
        $review=pcbr_review($db,PCBR_OPERATION);
        $strict=$review['strict_prepared']??[];$planned=count($strict);
        $select=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? FOR UPDATE");
        $update=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256 <=> ?");
        foreach($strict as $row){
            $external=(string)$row['external_id'];$target=(int)$row['target_local_hotel_id'];
            $select->execute([$external]);$current=$select->fetch(PDO::FETCH_ASSOC);if(!$current)throw new RuntimeException('identity_missing:'.$external);
            if($current['decision_status']!=='pending'||$current['local_hotel_id']!==null)continue;
            $prior=fc_evidence($current['evidence_json']??'');$evidence=pba_evidence($operation,$row,$prior);$json=fc_json($evidence);$sha=hash('sha256',$json);$oldSha=$current['evidence_sha256'];
            $update->execute([$target,$sha,$json,$external,$oldSha]);if($update->rowCount()!==1)throw new RuntimeException('concurrency_guard_failed:'.$external);
            $rows[$external]=['target'=>$target,'evidence_sha256'=>$sha,'category_mismatch'=>(bool)($row['category_mismatch']??false),'strict_identity'=>$row['strict_identity']??null];$writes++;
        }
        $db->commit();$committed=true;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    if(!$committed)throw new RuntimeException('not_committed');
    $read=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");
    foreach($rows as $external=>$expected){$read->execute([$external]);$actual=$read->fetch(PDO::FETCH_ASSOC);if(!$actual||(int)$actual['local_hotel_id']!==$expected['target']||$actual['decision_status']!=='accepted'||!hash_equals($expected['evidence_sha256'],(string)$actual['evidence_sha256']))throw new RuntimeException('post_commit_readback_failed:'.$external);$rows[$external]['readback']='verified';}
    $afterCoverage=fc_coverage($db);$afterIdentity=pba_identity_counts($db);
    return ['status'=>'committed_readback_verified','operation_id'=>$operation,'database_writes'=>$writes,'supplier_calls'=>0,'historical_operations_replayed'=>false,'planned_strict'=>$planned,'rows'=>$rows,'before'=>['coverage'=>$beforeCoverage,'identity'=>$beforeIdentity],'after'=>['coverage'=>$afterCoverage,'identity'=>$afterIdentity]];
}
