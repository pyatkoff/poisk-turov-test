<?php
declare(strict_types=1);

if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_strong_fuzzy_current_review.php';
require_once __DIR__ . '/anex_strong_bridge_current_review.php';

const HSEA_OPERATION = 'hotel-match-strong-evidence-accept-1971-20260911-v1';

function hsea_require_transactional(PDO $db): void {
    $tables = [
        'catalog_hotels','catalog_hotel_details','hotel_aliases',
        'anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions',
        'anex_hotels','anex_search_hotel_observations',
        'andromeda_hotel_identities','andromeda_search_hotel_observations',
    ];
    $q=$db->prepare("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    foreach($tables as $table){
        $q->execute([$table]);$engine=$q->fetchColumn();
        if($engine===false)throw new RuntimeException('required_table_missing:'.$table);
        if(strcasecmp((string)$engine,'InnoDB')!==0)throw new RuntimeException('required_table_not_innodb:'.$table);
    }
}

function hsea_identity_counts(PDO $db): array {
    $out=['accepted'=>0,'pending'=>0,'conflict'=>0];
    foreach($db->query("SELECT decision_status,COUNT(*) n FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' GROUP BY decision_status")->fetchAll(PDO::FETCH_ASSOC) as $row){
        $s=(string)$row['decision_status'];if(array_key_exists($s,$out))$out[$s]=(int)$row['n'];
    }
    return $out;
}

function hsea_andromeda_safe(array $row): bool {
    if(($row['provider']??'')!=='andromeda')return false;
    if(!in_array((string)($row['rule']??''),['strong_fuzzy_direct_geo_anex_bridge','strong_fuzzy_direct_geo_tourvisor'],true))return false;
    if((int)($row['target_local_hotel_id']??0)<=0||!isset(MBR_CORE8[(int)($row['country_id']??0)]))return false;
    $pair=$row['pair']??null;if(!is_array($pair)||!($pair['safe_strong_fuzzy']??false))return false;
    if(!($pair['critical_ok']??false)||!($pair['anchor_ok']??false)||(int)($pair['token_diff']??99)>2)return false;
    $bridge=(bool)($row['existing_anex_link']??false);$min=min(count($pair['source_tokens']??[]),count($pair['target_tokens']??[]));$shared=(int)($pair['shared_unique']??0);$score=(float)($pair['score']??0);
    if($bridge){if($min<2||$shared<2||$score<0.78)return false;}else{if($min<3||$shared<3||$score<0.84)return false;}
    if((float)($row['score_margin']??0)<0.12)return false;
    if(!fc_place(array_map('strval',$row['source_places']??[]),[(string)($row['target_region']??''),(string)($row['target_subregion']??'')]))return false;
    return true;
}

function hsea_anex_safe(array $row): bool {
    if(($row['provider']??'')!=='anex')return false;
    if(!in_array((string)($row['rule']??''),['strong_fuzzy_direct_geo_andromeda_bridge','strong_fuzzy_direct_geo_tourvisor'],true))return false;
    if((int)($row['target_local_hotel_id']??0)<=0||!isset(MBR_CORE8[(int)($row['country_id']??0)]))return false;
    $pair=$row['pair']??null;if(!is_array($pair)||!($pair['safe_strong_bridge']??false))return false;
    if(!($pair['critical_ok']??false)||!($pair['anchor_ok']??false)||(int)($pair['token_diff']??99)>2)return false;
    $bridge=(bool)($row['existing_andromeda_link']??false);$min=min(count($pair['source_tokens']??[]),count($pair['target_tokens']??[]));$shared=(int)($pair['shared_unique']??0);$score=(float)($pair['score']??0);
    if($bridge){if($min<2||$shared<2||$score<0.78)return false;}else{if($min<3||$shared<3||$score<0.84)return false;}
    if((float)($row['score_margin']??0)<0.12)return false;
    $distance=$row['distance_m']??null;if($distance!==null&&(float)$distance>5000.0)return false;
    if(!fc_place(array_map('strval',$row['source_places']??[]),[(string)($row['target_region']??''),(string)($row['target_subregion']??'')]))return false;
    return true;
}

function hsea_andromeda_evidence(string $operation,array $row,array $prior): array {
    return ['prior_evidence'=>$prior,'promotion'=>[
        'operation_id'=>$operation,'lane'=>'MATCH','provider'=>'andromeda','rule'=>$row['rule']??null,
        'country_id'=>(int)($row['country_id']??0),'target'=>(int)($row['target_local_hotel_id']??0),
        'source_names'=>$row['source_names']??[],'source_places'=>$row['source_places']??[],
        'source_category'=>$row['source_category']??null,'target_category'=>$row['target_category']??null,
        'category_mismatch'=>(bool)($row['category_mismatch']??false),'existing_anex_link'=>(bool)($row['existing_anex_link']??false),
        'pair'=>$row['pair']??null,'score_margin'=>$row['score_margin']??null,
        'live_observed'=>(bool)($row['live_observed']??false),'observation_count'=>(int)($row['observation_count']??0),
        'last_seen_utc'=>$row['last_seen_utc']??null,'server_current'=>true,
    ]];
}

function hsea_anex_evidence(string $operation,array $row): array {
    return [
        'operation_id'=>$operation,'lane'=>'MATCH','provider'=>'anex','rule'=>$row['rule']??null,
        'anex_hotel_id'=>(int)($row['external_id']??0),'country_id'=>(int)($row['country_id']??0),
        'source_names'=>$row['source_names']??[],'source_places'=>$row['source_places']??[],
        'search_count'=>(int)($row['search_count']??0),'last_seen_utc'=>$row['last_seen_utc']??null,
        'target'=>(int)($row['target_local_hotel_id']??0),'target_name'=>$row['target_name']??null,
        'target_region'=>$row['target_region']??null,'target_subregion'=>$row['target_subregion']??null,
        'pair'=>$row['pair']??null,'score_margin'=>$row['score_margin']??null,'distance_m'=>$row['distance_m']??null,
        'existing_andromeda_link'=>(bool)($row['existing_andromeda_link']??false),'server_current'=>true,
    ];
}

function hsea_accept(PDO $db,string $operation=HSEA_OPERATION,int $maxWrites=100): array {
    if($operation!==HSEA_OPERATION)throw new RuntimeException('operation_scope');
    if($maxWrites<1||$maxWrites>100)throw new RuntimeException('write_scope_limit_config');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);hsea_require_transactional($db);
    $db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $beforeCoverage=fc_coverage($db);$beforeIdentity=hsea_identity_counts($db);
    $andRows=[];$anRows=[];$writes=0;$planned=['andromeda'=>0,'anex'=>0];$safe=['andromeda'=>0,'anex'=>0];
    $skipped=['andromeda_planner_guard'=>0,'andromeda_stale_or_protected'=>0,'anex_planner_guard'=>0,'anex_protected'=>0,'anex_pair_excluded'=>0];$committed=false;
    try{
        $db->beginTransaction();
        $db->query("SELECT external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN)),true);
        $existing=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN)),true);
        $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC) as $x)$excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']]=true;

        $andReview=hfsr_review($db,HFSR_OPERATION);$anReview=asbr_review($db,ASBR_OPERATION);
        $andPrepared=is_array($andReview['safe_prepared']??null)?$andReview['safe_prepared']:[];$anPrepared=is_array($anReview['safe_prepared']??null)?$anReview['safe_prepared']:[];
        $planned=['andromeda'=>count($andPrepared),'anex'=>count($anPrepared)];
        if(array_sum($planned)>$maxWrites)throw new RuntimeException('write_scope_limit');

        $selectAnd=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? FOR UPDATE");
        $updateAnd=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256 <=> ?");
        foreach($andPrepared as $row){
            if(!is_array($row)||!hsea_andromeda_safe($row)){$skipped['andromeda_planner_guard']++;continue;}$safe['andromeda']++;
            $external=(string)$row['external_id'];$target=(int)$row['target_local_hotel_id'];$selectAnd->execute([$external]);$cur=$selectAnd->fetch(PDO::FETCH_ASSOC);
            if(!$cur||$cur['decision_status']!=='pending'||$cur['local_hotel_id']!==null){$skipped['andromeda_stale_or_protected']++;continue;}
            $prior=fc_evidence($cur['evidence_json']??'');$evidence=hsea_andromeda_evidence($operation,$row,$prior);$json=fc_json($evidence);$sha=hash('sha256',$json);$old=$cur['evidence_sha256'];
            $updateAnd->execute([$target,$sha,$json,$external,$old]);if($updateAnd->rowCount()!==1)throw new RuntimeException('andromeda_concurrency_guard_failed:'.$external);
            $andRows[$external]=['target'=>$target,'evidence_sha256'=>$sha,'rule'=>(string)$row['rule'],'live'=>(bool)($row['live_observed']??false),'bridge'=>(bool)($row['existing_anex_link']??false)];$writes++;
        }

        $insertAn=$db->prepare("INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,'strong_candidate','preview',?,?,?,1)");
        $mappingDigest=fc_hash([$operation,'strong_evidence_accept_v1']);
        foreach($anPrepared as $row){
            if(!is_array($row)||!hsea_anex_safe($row)){$skipped['anex_planner_guard']++;continue;}$safe['anex']++;
            $external=(int)$row['external_id'];$target=(int)$row['target_local_hotel_id'];
            if(isset($manual[$external])||isset($existing[$external])){$skipped['anex_protected']++;continue;}
            if(isset($excluded[$external][$target])){$skipped['anex_pair_excluded']++;continue;}
            $evidence=hsea_anex_evidence($operation,$row);$digest=fc_hash($evidence);$insertAn->execute([$external,$target,MBR_POLICY,$digest,$mappingDigest]);if($insertAn->rowCount()!==1)throw new RuntimeException('anex_insert_not_one:'.$external);
            $anRows[$external]=['target'=>$target,'source_row_digest'=>$digest,'rule'=>(string)$row['rule'],'live'=>(bool)($row['observed']??false),'bridge'=>(bool)($row['existing_andromeda_link']??false)];$existing[$external]=true;$writes++;
        }
        if($writes>$maxWrites)throw new RuntimeException('write_scope_limit');
        $db->commit();$committed=true;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    if(!$committed)throw new RuntimeException('not_committed');

    $readAnd=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");
    foreach($andRows as $external=>$expected){$readAnd->execute([$external]);$actual=$readAnd->fetch(PDO::FETCH_ASSOC);if(!$actual||(int)$actual['local_hotel_id']!==$expected['target']||$actual['decision_status']!=='accepted'||!hash_equals($expected['evidence_sha256'],(string)$actual['evidence_sha256']))throw new RuntimeException('andromeda_post_commit_readback_failed:'.$external);$andRows[$external]['readback']='verified';}
    $readAn=$db->prepare('SELECT catalog_hotel_id,source_row_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=?');
    foreach($anRows as $external=>$expected){$readAn->execute([(int)$external]);$actual=$readAn->fetch(PDO::FETCH_ASSOC);if(!$actual||(int)$actual['catalog_hotel_id']!==$expected['target']||(int)$actual['enabled']!==1||!hash_equals($expected['source_row_digest'],(string)$actual['source_row_digest']))throw new RuntimeException('anex_post_commit_readback_failed:'.$external);$anRows[$external]['readback']='verified';}

    $afterCoverage=fc_coverage($db);$afterIdentity=hsea_identity_counts($db);$liveWrites=0;$bridgeWrites=0;foreach(array_merge(array_values($andRows),array_values($anRows)) as $r){if($r['live'])$liveWrites++;if($r['bridge'])$bridgeWrites++;}
    return ['status'=>'committed_readback_verified','operation_id'=>$operation,'database_writes'=>$writes,'supplier_calls'=>0,'historical_operations_replayed'=>false,'planner_operations'=>[HFSR_OPERATION,ASBR_OPERATION],'planned'=>$planned,'safe'=>$safe,'skipped'=>$skipped,'live_writes'=>$liveWrites,'bridge_writes'=>$bridgeWrites,'andromeda_rows'=>$andRows,'anex_rows'=>$anRows,'before'=>['coverage'=>$beforeCoverage,'identity'=>$beforeIdentity],'after'=>['coverage'=>$afterCoverage,'identity'=>$afterIdentity]];
}
