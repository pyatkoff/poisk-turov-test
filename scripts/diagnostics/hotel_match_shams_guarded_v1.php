<?php
declare(strict_types=1);
/** Exact one-row SHAMS intake. It consumes neither the native110 review nor its writer marker. */
require_once __DIR__.'/hotel_match_native110_guarded_v1.php';

const SHG_OP='int-andromeda-match-shams-current-write-20261001-v1';
const SHG_BATCH='shams9501-geo-20261001';
const SHG_GEO_OP='int-andromeda-match-shams-geo-readback-20261001-v1';
const SHG_GEO_EVIDENCE_OP='int-andromeda-match-shams-geo-evidence-20261001-v1';
const SHG_GEO_SOURCE='12dc06dbdfd047c05caa346092cb9bd1c1dd0323';

function shg_prepare(string $root): array {
    $entries=ng110_prepare($root);
    w76_need(isset($entries[420])&&count($entries)===4,'shams_base_input');
    $receipt=pm1_read($root,SHG_GEO_OP.'/result.json',null,1048576);
    w76_need(($receipt['state']??null)==='completed_saved_geography_readback'
        &&($receipt['operation']??null)===SHG_GEO_OP&&($receipt['source_sha']??null)===SHG_GEO_SOURCE
        &&($receipt['batch']??null)===NC110_BATCH&&($receipt['input_sha256']??null)===NG110_INPUT_SHA
        &&($receipt['evidence_operation']??null)===SHG_GEO_EVIDENCE_OP
        &&($receipt['evidence_source_sha']??null)===SHG_GEO_SOURCE
        &&($receipt['provider_http_calls']??null)===0&&($receipt['database_reads']??null)===0
        &&($receipt['database_writes']??null)===0&&($receipt['mapping_writes']??null)===0
        &&($receipt['safe_to_write_now']??null)===false&&($receipt['no_replay']??null)===true,'shams_geo_receipt');
    $evidence=$receipt['evidence']??null;
    w76_need(is_array($evidence)&&($evidence['schema']??null)==='match-shams-saved-geography/1'
        &&($evidence['state']??null)==='completed_saved_geography_evidence'
        &&($evidence['operation']??null)===SHG_GEO_EVIDENCE_OP&&($evidence['source_sha']??null)===SHG_GEO_SOURCE
        &&($evidence['catalog_id']??null)==='9501'&&($evidence['tv_hotel_id']??null)===420
        &&($evidence['references_examined']??null)===3&&($evidence['raw_files_read']??null)===3
        &&($evidence['source_history_geography_exported']??null)===false,'shams_geo_evidence');
    $towns=[];$seen=[];
    foreach($evidence['references']??[] as $ref){
        w76_need(is_array($ref)&&($ref['raw_verified']??null)===true&&($ref['failures']??null)===[],'shams_geo_raw');
        $key=($ref['namespace']??'').':'.($ref['native_id']??'');$seen[$key]=($seen[$key]??0)+1;
        if($key==='operator_342:24402')foreach($ref['location_fields']??[] as $field)
            if(($field['source_field']??null)==='row.town')$towns[]=mb_strtolower(w76_text($field['value']??''));
    }
    w76_need(($seen['operator_342:24402']??0)===1&&($seen['operator_5:835']??0)===2
        &&array_values(array_unique($towns))===['марса алам'],'shams_geo_native');
    $target=[];foreach($evidence['saved_target_geography']??[] as $field)$target[$field['source_field']??'']=$field['value']??null;
    w76_need(mb_strtolower(w76_text($target['saved_target.country_name']??''))==='египет'
        &&mb_strtolower(w76_text($target['saved_target.region_name']??''))==='марса алам','shams_geo_target');
    $entry=$entries[420];
    $entry['geo_source']=['town'=>'Марса Алам','townKey'=>'5821','namespace'=>'operator_342','native_id'=>'24402'];
    $entry['geo_receipt_sha256']=w76_hash($receipt);
    $entry['geo_evidence']=$evidence;
    return [420=>$entry];
}

function shg_classify(array $entry,array $context): array {
    $decision=ng110_classify($entry,$context);
    if($decision['status']!=='hold'||!in_array('geography_requires_review',$decision['reasons'],true))return $decision;
    $hotel=$context['hotels'][$entry['id']]??null;
    if(!is_array($hotel)||pm1_geography($entry['geo_source'],$hotel)!==[])return $decision;
    $reasons=array_values(array_filter($decision['reasons'],fn($r)=>$r!=='geography_requires_review'));
    return ['status'=>$reasons?'hold':'ready','reasons'=>$reasons];
}

function shg_write(PDO $db,array $entries,string $head,string $dir): array {
    w76_need(array_keys($entries)===[420]&&$entries[420]['catalog_id']==='9501'&&!$db->inTransaction(),'shams_write_scope');
    $attempt=false;$committed=false;$sql=false;$rollback=false;$planned=[];$held=[];$beforeCoverage=null;
    try{
        foreach(['andromeda_hotel_identities','catalog_hotels','tour_operator_identity_observations','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions'] as $t){
            $engine=w76_q($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);
            w76_need(count($engine)===1&&strtoupper($engine[0]['ENGINE'])==='INNODB','nontransactional_table');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=20');
        $db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');w76_need($db->beginTransaction(),'begin');
        $all=w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001 FOR UPDATE');
        $before=w76_index($all);$context=pm1_context($all);
        foreach(w76_q($db,'SELECT id,name,country_id,country_name,region_name,subregion_name,category,is_active,latitude,longitude FROM catalog_hotels WHERE id=420 FOR UPDATE') as $h)$context['hotels'][420]=$h;
        $manual=w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001 FOR UPDATE');
        $excluded=w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001 FOR UPDATE');
        foreach($manual as $r)if($r['catalog_hotel_id']!==null)$context['manual'][(int)$r['catalog_hotel_id']]=true;
        foreach($excluded as $r)if($r['catalog_hotel_id']!==null)$context['exclusions'][(int)$r['catalog_hotel_id']]=true;
        $anexBefore=w76_q($db,'SELECT * FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001 FOR UPDATE');
        $beforeCoverage=w76_census($db);$entry=$entries[420];$decision=shg_classify($entry,$context);
        if($decision['status']!=='ready')$held[]=['catalog_id'=>'9501','local_hotel_id'=>420]+$decision;
        else{
            $old=$context['sources']['9501'][0];$history=json_decode($old['evidence_json'],true);
            $evidence=['operation_id'=>SHG_OP,'batch'=>SHG_BATCH,'source_sha'=>$head,
                'rule'=>'proven_same_operator_native_plus_independent_concrete_geography_current_pending_null',
                'review_operation'=>NC110_OP,'review_sha256'=>NG110_INPUT_SHA,'geography_operation'=>SHG_GEO_OP,
                'geography_receipt_sha256'=>$entry['geo_receipt_sha256'],'inputs'=>[PM1_NATIVE_OP=>PM1_NATIVE_SHA],
                'source'=>$history['source'],'target'=>$context['hotels'][420],'concrete_geography'=>$entry['geo_source'],
                'proofs'=>$entry['proofs'],'prior_evidence_json'=>$old['evidence_json'],'prior_evidence_sha256'=>$old['evidence_sha256'],
                'catalog_sha256_preserved'=>$old['catalog_sha256'],'provider_http_calls'=>0];
            $raw=w76_json($evidence);$planned['andromeda_catalog|9501']=['id'=>420,'cat'=>'9501','name'=>$context['hotels'][420]['name'],
                'old'=>$old,'new_json'=>$raw,'new_sha'=>hash('sha256',$raw),'proof_count'=>count($entry['proofs'])];
        }
        w76_need(count($planned)<=1,'write_cap');
        w76_save($dir.'/write-plan.json',['operation'=>SHG_OP,'source_sha'=>$head,'review_sha256'=>NG110_INPUT_SHA,
            'geography_receipt_sha256'=>$entry['geo_receipt_sha256'],'planned'=>$planned,'held'=>$held,'before_sha256'=>w76_hash($before)]);
        if(!$planned){w76_need($db->rollBack(),'rollback_failed');return ['state'=>'completed_no_new_writes','rows'=>[],'held'=>$held,
            'current_candidates_evaluated'=>1,'mapping_writes'=>0,'database_writes'=>0,'readback_verified'=>true,
            'coverage_before'=>$beforeCoverage,'coverage_after'=>$beforeCoverage];}
        $update=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND catalog_sha256=? AND evidence_sha256=?");
        $p=$planned['andromeda_catalog|9501'];$sql=true;
        w76_need($update->execute([$p['id'],$p['new_sha'],$p['new_json'],$p['cat'],$p['old']['catalog_sha256'],$p['old']['evidence_sha256']])&&$update->rowCount()===1,'conditional_update');
        $verify=static function() use($db,$before,$planned,$manual,$excluded,$anexBefore): array {
            $rows=w76_verify($before,$planned,w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001'));
            w76_need($manual===w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001'),'manual_changed');
            w76_need($excluded===w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001'),'exclusions_changed');
            w76_need($anexBefore===w76_q($db,'SELECT * FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001'),'anex_mappings_changed');
            pm1_resolver_readback($db,$planned);return $rows;
        };
        $verify();w76_save($dir.'/pre-commit.json',['operation'=>SHG_OP,'planned_count'=>1,'plan_sha256'=>w76_hash($planned)]);
        w76_save($dir.'/commit-attempt.json',['operation'=>SHG_OP,'state'=>'commit_attempt_no_replay']);
        $attempt=true;w76_need($db->commit(),'commit');$committed=true;
        $db->exec('START TRANSACTION READ ONLY');$read=$verify();$after=w76_census($db);w76_need($db->rollBack(),'readback_end');
        return ['state'=>'committed_readback_verified','current_candidates_evaluated'=>1,'commit_attempted'=>true,'commit_completed'=>true,
            'rows'=>$read,'held'=>[],'database_writes'=>1,'mapping_writes'=>1,'readback_verified'=>true,
            'effective_resolver_verified'=>true,'prior_evidence_preserved'=>true,'unrelated_identities_unchanged'=>true,
            'coverage_before'=>$beforeCoverage,'coverage_after'=>$after,'new_full_triples'=>$after['full_triple']-$beforeCoverage['full_triple']];
    }catch(Throwable $e){
        if($db->inTransaction())try{$rollback=$db->rollBack();}catch(Throwable $ignored){}
        $count=$committed?count($planned):(($attempt||($sql&&!$rollback))?null:0);
        return ['state'=>$committed?'committed_readback_unconfirmed':($attempt?'commit_outcome_unknown_no_replay':($count===0?'rolled_back_no_writes':'write_outcome_unknown_no_replay')),
            'reason'=>preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'shams_guarded_writer_failed',
            'commit_attempted'=>$attempt,'commit_completed'=>$committed,'current_candidates_evaluated'=>1,'rows'=>[],'held'=>$held,
            'database_writes'=>$count,'mapping_writes'=>$count,'readback_verified'=>false];
    }
}

function shg_main(array $args): int {
    w76_need(count($args)===2&&$args[1]==='--execute','shams_guarded_disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
    w76_need(realpath($root)===$root&&basename($root)==='anytoour.ru'&&realpath($dir)===$dir&&basename($dir)===SHG_OP
        &&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'shams_guarded_scope');
    $reservation=pm1_read(dirname($dir),SHG_OP.'/reservation.json',null,1048576);
    w76_need(($reservation['operation']??null)===SHG_OP&&($reservation['source_sha']??null)===$head
        &&($reservation['batch']??null)===SHG_BATCH&&($reservation['input_sha256']??null)===NG110_INPUT_SHA
        &&($reservation['geography_operation']??null)===SHG_GEO_OP&&($reservation['maximum_writes']??null)===1
        &&($reservation['provider_http_calls']??null)===0,'shams_guarded_reservation');
    foreach(['execution-started.json','write-plan.json','pre-commit.json','commit-attempt.json','result.json','receipt.json'] as $f)w76_need(!file_exists($dir.'/'.$f),'shams_guarded_no_replay');
    w76_save($dir.'/execution-started.json',['operation'=>SHG_OP,'source_sha'=>$head,'input_sha256'=>NG110_INPUT_SHA,'geography_operation'=>SHG_GEO_OP]);
    try{$entries=shg_prepare(dirname($dir));require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$out=shg_write(v2_data_db(),$entries,$head,$dir);}
    catch(Throwable $e){$out=['state'=>'failed_before_writer','reason'=>preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'shams_guarded_prepare_failed','rows'=>[],'held'=>[],'current_candidates_evaluated'=>0,'database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>false];}
    $out+=['operation'=>SHG_OP,'source_sha'=>$head,'batch'=>SHG_BATCH,'input_sha256'=>NG110_INPUT_SHA,'geography_operation'=>SHG_GEO_OP,'provider_http_calls'=>0,'no_replay'=>true];
    $hash=w76_save($dir.'/result.json',$out);w76_save($dir.'/receipt.json',['operation'=>SHG_OP,'source_sha'=>$head,'batch'=>SHG_BATCH,'input_sha256'=>NG110_INPUT_SHA,
        'geography_operation'=>SHG_GEO_OP,'state'=>$out['state'],'result_sha256'=>$hash,'database_writes'=>$out['database_writes'],'mapping_writes'=>$out['mapping_writes'],
        'readback_verified'=>$out['readback_verified'],'provider_http_calls'=>0,'no_replay'=>true]);
    echo w76_json($out)."\n";return in_array($out['state'],['committed_readback_verified','completed_no_new_writes'],true)?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(shg_main($argv));
