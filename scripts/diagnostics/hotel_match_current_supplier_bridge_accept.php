<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_current_supplier_bridge.php';

const HMSBA_OPERATION='hotel-match-current-supplier-bridge-accept-1971-20260913-v1';
const HMSBA_REVIEW_OPERATION='hotel-match-current-supplier-bridge-review-1971-20260913-v2';

function hmsba_manifest(): array {
    return [
      ['provider'=>'andromeda','external_id'=>'13770','country_id'=>4,'target'=>17493],
      ['provider'=>'andromeda','external_id'=>'2000030681','country_id'=>4,'target'=>17527],
      ['provider'=>'andromeda','external_id'=>'2000034107','country_id'=>4,'target'=>1112],
      ['provider'=>'andromeda','external_id'=>'2000038497','country_id'=>4,'target'=>17689],
      ['provider'=>'andromeda','external_id'=>'2000108615','country_id'=>1,'target'=>9373],
      ['provider'=>'andromeda','external_id'=>'337925','country_id'=>16,'target'=>22784],
      ['provider'=>'andromeda','external_id'=>'67774','country_id'=>4,'target'=>17703],
      ['provider'=>'andromeda','external_id'=>'84955','country_id'=>4,'target'=>17317],
      ['provider'=>'anex','external_id'=>'16691','country_id'=>4,'target'=>1330],
    ];
}
function hmsba_identity_counts(PDO $db): array {
    $o=['accepted'=>0,'pending'=>0,'conflict'=>0];
    foreach($db->query("SELECT decision_status,COUNT(*) n FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' GROUP BY decision_status")->fetchAll(PDO::FETCH_ASSOC) as $r){
        $s=(string)$r['decision_status']; if(array_key_exists($s,$o))$o[$s]=(int)$r['n'];
    }
    return $o;
}
function hmsba_accept(PDO $db): array {
    $review=hmsb_review($db,HMSBA_REVIEW_OPERATION);
    $strict=[]; foreach($review['rows'] as $r) if(($r['status']??'')==='strict_supplier_bridge') $strict[$r['provider'].'|'.$r['external_id'].'|'.$r['target_local_id']]=$r;
    $manifest=hmsba_manifest();
    if(count($strict)!==count($manifest)) throw new RuntimeException('strict_set_size_changed');
    foreach($manifest as $m) if(!isset($strict[$m['provider'].'|'.$m['external_id'].'|'.$m['target']])) throw new RuntimeException('strict_set_changed');

    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET SESSION innodb_lock_wait_timeout=20');
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $writes=0; $committed=false; $written=['anex'=>[],'andromeda'=>[]];
    try {
        $db->beginTransaction();
        $db->query('SELECT anex_hotel_id FROM anex_hotel_decisions ORDER BY anex_hotel_id FOR UPDATE')->fetchAll();
        $db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings ORDER BY anex_hotel_id FOR UPDATE')->fetchAll();
        $db->query("SELECT external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id FOR UPDATE")->fetchAll();
        $before=fc_coverage($db); $ib=hmsba_identity_counts($db);

        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
        $existing=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)),true);
        $excluded=[]; foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $anLocal=[]; foreach($db->query("SELECT m.anex_hotel_id,m.catalog_hotel_id FROM anex_hotel_search_mappings m LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy='".FC_POLICY."' AND d.anex_hotel_id IS NULL")->fetchAll(PDO::FETCH_ASSOC) as $r)$anLocal[(int)$r['catalog_hotel_id']][(int)$r['anex_hotel_id']]=true;
        foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions WHERE decision_status='accepted' AND catalog_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r)$anLocal[(int)$r['catalog_hotel_id']][(int)$r['anex_hotel_id']]=true;
        $adLocal=[]; foreach($db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r)$adLocal[(int)$r['local_hotel_id']][(string)$r['external_hotel_id']]=true;

        $qHotel=$db->prepare('SELECT id,country_id FROM catalog_hotels WHERE id=? AND is_active=1');
        $qAnd=$db->prepare("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");
        $ins=$db->prepare("INSERT INTO anex_hotel_search_mappings(anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,'strong_candidate','preview',?,?,?,1)");
        $upd=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256 <=> ?");

        foreach($manifest as $m){
            $p=$m['provider']; $e=(string)$m['external_id']; $target=(int)$m['target']; $country=(int)$m['country_id'];
            $qHotel->execute([$target]); $h=$qHotel->fetch(PDO::FETCH_ASSOC);
            if(!$h||(int)$h['country_id']!==$country) throw new RuntimeException('target_country_changed');
            $rr=$strict[$p.'|'.$e.'|'.$target];
            if($p==='andromeda'){
                $qAnd->execute([$e]); $r=$qAnd->fetch(PDO::FETCH_ASSOC);
                if(!$r||(string)$r['decision_status']!=='pending'||$r['local_hotel_id']!==null) throw new RuntimeException('andromeda_state_changed');
                if(isset($adLocal[$target])) throw new RuntimeException('andromeda_target_occupied');
                if(!isset($anLocal[$target])) throw new RuntimeException('opposite_bridge_missing');
                $prior=fc_evidence($r['evidence_json']??'');
                $ev=['prior_evidence'=>$prior,'promotion'=>['operation_id'=>HMSBA_OPERATION,'review_operation'=>HMSBA_REVIEW_OPERATION,'lane'=>'MATCH','rule'=>'strict_supplier_bridge','provider'=>'andromeda','country_id'=>$country,'target'=>$target,'source_names'=>$rr['source_names'],'source_places'=>$rr['source_places'],'significant_tokens'=>$rr['significant_tokens'],'geo'=>$rr['geo'],'bridge_provider'=>'anex','server_current'=>true]];
                $json=fc_json($ev); $hash=hash('sha256',$json);
                $upd->execute([$target,$hash,$json,$e,$r['evidence_sha256']]);
                if($upd->rowCount()!==1) throw new RuntimeException('andromeda_concurrent_change');
                $written['andromeda'][$e]=['target'=>$target,'evidence_sha256'=>$hash]; $writes++;
            } else {
                $id=(int)$e;
                if(isset($manual[$id])||isset($existing[$id])||isset($excluded[$id][$target])) throw new RuntimeException('anex_protected_changed');
                if(isset($anLocal[$target])) throw new RuntimeException('anex_target_occupied');
                if(!isset($adLocal[$target])) throw new RuntimeException('opposite_bridge_missing');
                $ev=['operation_id'=>HMSBA_OPERATION,'review_operation'=>HMSBA_REVIEW_OPERATION,'lane'=>'MATCH','provider'=>'anex','rule'=>'strict_supplier_bridge','anex_hotel_id'=>$id,'country_id'=>$country,'target'=>$target,'source_names'=>$rr['source_names'],'source_places'=>$rr['source_places'],'significant_tokens'=>$rr['significant_tokens'],'geo'=>$rr['geo'],'bridge_provider'=>'andromeda','server_current'=>true];
                $digest=fc_hash($ev); $mappingDigest=fc_hash([HMSBA_OPERATION,'strict_supplier_bridge']);
                $ins->execute([$id,$target,FC_POLICY,$digest,$mappingDigest]);
                if($ins->rowCount()!==1) throw new RuntimeException('anex_insert_not_one');
                $written['anex'][$id]=['target'=>$target,'source_row_digest'=>$digest,'mapping_digest'=>$mappingDigest]; $writes++;
            }
        }
        if($writes!==9) throw new RuntimeException('write_count_changed');
        $db->commit(); $committed=true;

        $qa=$db->prepare("SELECT catalog_hotel_id,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=? AND scope='preview' AND approval_policy=?");
        foreach($written['anex'] as $id=>$x){$qa->execute([$id,FC_POLICY]);$r=$qa->fetch(PDO::FETCH_ASSOC);if(!$r||(int)$r['catalog_hotel_id']!==$x['target']||(int)$r['enabled']!==1||!hash_equals($x['source_row_digest'],(string)$r['source_row_digest'])||!hash_equals($x['mapping_digest'],(string)$r['mapping_digest']))throw new RuntimeException('anex_post_commit_readback_failed');}
        $qd=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");
        foreach($written['andromeda'] as $id=>$x){$qd->execute([$id]);$r=$qd->fetch(PDO::FETCH_ASSOC);if(!$r||(int)$r['local_hotel_id']!==$x['target']||(string)$r['decision_status']!=='accepted'||!hash_equals($x['evidence_sha256'],(string)$r['evidence_sha256']))throw new RuntimeException('andromeda_post_commit_readback_failed');}
        $after=fc_coverage($db); $ia=hmsba_identity_counts($db);
        return ['status'=>'completed','operation_id'=>HMSBA_OPERATION,'review_operation'=>HMSBA_REVIEW_OPERATION,'planned'=>['anex'=>1,'andromeda'=>8],'database_writes'=>$writes,'mapping_writes'=>$writes,'supplier_calls'=>0,'tourvisor_calls'=>0,'committed'=>true,'readback_verified'=>true,'coverage_before'=>$before,'coverage_after'=>$after,'identity_before'=>$ib,'identity_after'=>$ia,'written'=>$written,'no_replay'=>true];
    } catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        return ['status'=>'failed','operation_id'=>HMSBA_OPERATION,'reason'=>$e->getMessage(),'database_writes'=>$committed?'unknown':0,'mapping_writes'=>$committed?'unknown':0,'supplier_calls'=>0,'tourvisor_calls'=>0,'committed'=>$committed,'readback_verified'=>false,'no_replay'=>true];
    }
}
