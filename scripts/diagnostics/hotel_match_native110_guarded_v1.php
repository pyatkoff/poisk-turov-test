<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_native110_current_v1.php';

const NG110_OP='int-andromeda-match-native110-write-20261001-v1';
const NG110_INPUT_SHA='59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e';
const NG110_INPUT_SOURCE='9c82d143ccd6173ade0d6b1d52c6e3a41657d460';
const NG110_PAIRS=[42903=>'3126',420=>'9501',28529=>'475947',16944=>'2000034238'];

/** The new raw/CURRENT intake is immutable. The old PM1 writer is never invoked. */
function ng110_prepare(string $root): array {
    $input=pm1_read($root,NC110_OP.'/native110-current-manifest.json',NG110_INPUT_SHA);
    $terminal=pm1_read($root,NC110_OP.'/native110-current-summary.json',null,262144);
    w76_need(($terminal['state']??null)==='completed_native110_current_review'
        &&($terminal['operation']??null)===NC110_OP&&($terminal['source_sha']??null)===NG110_INPUT_SOURCE
        &&($terminal['manifest_sha256']??null)===NG110_INPUT_SHA&&($terminal['sources_examined']??null)===109
        &&($terminal['protected_skipped']??null)===1&&($terminal['raw_verified_facts']??null)===107
        &&($terminal['provider_http_calls']??null)===0&&($terminal['database_writes']??null)===0
        &&($terminal['mapping_writes']??null)===0&&($terminal['no_replay']??null)===true,'guarded_input_terminal');
    w76_need(($input['schema']??null)==='native110-current-review/1'&&($input['operation']??null)===NC110_OP
        &&($input['source_sha']??null)===NG110_INPUT_SOURCE&&($input['batch']??null)===NC110_BATCH
        &&($input['provider_http_calls']??null)===0&&($input['database_writes']??null)===0
        &&($input['mapping_writes']??null)===0&&($input['no_replay']??null)===true
        &&($input['safe_to_write_now']??null)===false&&count($input['rows']??[])===110,'guarded_input_binding');
    $native=pm1_terminal($root,PM1_NATIVE_OP,PM1_NATIVE_SHA,['completed_retained_native_scan']);
    $global=[];foreach($native['native_facts'] as $f)$global[$f['supplier_namespace']][(string)$f['native_id']][(string)$f['catalog_id']]=true;
    $manifest=json_decode(file_get_contents(__DIR__.'/fixtures/hotel_match_native110_current_v1.json'),true,64,JSON_THROW_ON_ERROR);
    w76_need(hash_file('sha256',__DIR__.'/fixtures/hotel_match_native110_current_v1.json')===NC110_MANIFEST_SHA,'guarded_fixture');
    nc110_scope($manifest);$requests=[];foreach($manifest['rows'] as $r)$requests[$r['catalog_id']]=$r;
    $current=[];foreach($input['rows'] as $r)$current[$r['catalog_id']]=$r;
    $entries=[];
    foreach(NG110_PAIRS as $id=>$cat){
        $row=$current[$cat]??null;$request=$requests[$cat]??null;
        w76_need(is_array($row)&&is_array($request)&&$row['state']==='current_review_observed'
            &&$row['holds']===[]&&$row['source_catalog_digest_matches_saved']===true
            &&$row['source_evidence_digest_matches_saved']===true&&$row['source_history_catalog_id_matches']===true,'guarded_source_input');
        $targets=array_values(array_filter($row['targets'],fn($t)=>$t['kind']==='tv_candidate'&&$t['id']===$id));
        w76_need(count($targets)===1&&$targets[0]['holds']===[]&&$targets[0]['tv_live30_observed']===true,'guarded_target_input');
        $proofs=[];
        foreach($request['tv_candidates'] as $candidate){
            if($candidate['tv_hotel_id']!==$id)continue;
            $lane=$candidate['operator'];$ns=NC110_NS[$lane];$n=$candidate['native_id'];
            w76_need(in_array($lane,['funsun','intourist'],true)&&$candidate['tv_native_id']===$n,'guarded_namespace');
            w76_need(array_map('strval',array_keys($global[$ns][$n]??[]))===[$cat],'guarded_global_unique');
            $facts=array_values(array_filter($input['saved_evidence']['source_facts'][$cat]??[],fn($f)=>$f['namespace']===$ns&&$f['native_id']===$n));
            w76_need(count($facts)===1&&$facts[0]['unique_catalog_in_saved_union']===true&&$facts[0]['raw']['raw_verified']===true,'guarded_raw_proof');
            $p=$input['saved_evidence']['tv_proofs'][$cat.'|'.$lane.'|'.$id]??[];$verified=[];
            foreach($p['producers']??[] as $producer){
                $audit=$producer['audit'];
                foreach($audit['source_targets']??[] as $target)w76_need($target===$id,'guarded_global_tv_conflict');
                foreach($audit['target_natives']??[] as $value)w76_need($value===$n,'guarded_global_tv_conflict');
                if(($audit['state']??null)==='saved_tv_proof_verified'&&($audit['failures']??null)===[])$verified[]=$producer;
            }
            w76_need(count($verified)>0,'guarded_independent_tv_proof');
            $proofs[]=['namespace'=>$ns,'native_id'=>$n,'samo'=>$facts[0]['raw'],'tv'=>$verified];
        }
        w76_need($proofs!==[],'guarded_no_proof');
        $entries[$id]=['id'=>$id,'catalog_id'=>$cat,'prior'=>$row['source_revision'],
            'history_sha256'=>$row['source_history_sha256'],'target'=>$targets[0]['catalog_record'],
            'operator_facts'=>$input['saved_evidence']['source_facts'][$cat],'proofs'=>$proofs];
    }
    return $entries;
}

/** Owner common4 identity policy: proven native identity, CURRENT protections and geography. */
function ng110_classify(array $entry,array $context): array {
    $cat=$entry['catalog_id'];$id=$entry['id'];$why=[];
    if($cat===NC110_PROTECTED)return ['status'=>'hold','reasons'=>['protected_source']];
    $rows=$context['sources'][$cat]??[];$row=count($rows)===1?$rows[0]:null;
    if(!$row||!w76_evidence_valid($row))return ['status'=>'hold','reasons'=>['current_source_missing_or_invalid']];
    if($row['decision_status']!=='pending'||$row['local_hotel_id']!==null)$why[]='source_not_pending_null';
    foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256'] as $key)
        if(($row[$key]??null)!==($entry['prior'][$key]??null))$why[]='source_drift';
    $history=json_decode($row['evidence_json'],true);$source=$history['source']??null;
    if(!is_array($source)||(string)($source['id']??'')!==$cat||w76_hash($source)!==$entry['history_sha256'])$why[]='source_history_drift';
    if(($history['manual']??false)===true||($history['decision']['manual']??false)===true)$why[]='protected_source';
    $hotel=$context['hotels'][$id]??null;
    if(!$hotel||(int)$hotel['is_active']!==1)$why[]='target_missing_or_inactive';
    elseif(w76_target($hotel)!==w76_target($entry['target']))$why[]='target_drift';
    if(isset($context['manual'][$id])||isset($context['exclusions'][$id]))$why[]='protected_target';
    foreach($context['targets'][$id]??[] as $occupant)if((string)$occupant['external_hotel_id']!==$cat)$why[]='target_catalog_occupied';
    if(is_array($source)&&$hotel){
        if(w76_text($source['state']??'')===''||w76_text($source['state']??'')!==w76_text($hotel['country_name']??'')
            ||preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',(string)$hotel['country_name']))$why[]='country_conflict';
        $why=array_merge($why,pm1_geography($source,$hotel));
    }
    foreach($entry['operator_facts'] as $fact){
        $operator=$context['operators'][$fact['namespace']][$fact['native_id']]??[];
        if(count($operator)>1)$why[]='operator_not_unique';
        foreach($operator as $r){
            if(!w76_evidence_valid($r))$why[]='operator_evidence_invalid';
            if(!in_array($r['decision_status'],['pending','accepted'],true))$why[]='protected_operator';
            if($r['local_hotel_id']!==null&&(int)$r['local_hotel_id']!==$id)$why[]='operator_other_target';
            if($r['decision_status']==='accepted'&&$r['local_hotel_id']===null)$why[]='operator_missing_accepted_target';
            $e=json_decode($r['evidence_json'],true);
            if(($e['manual']??false)===true||($e['decision']['manual']??false)===true)$why[]='protected_operator';
            if(isset($e['source']['id'])&&(string)$e['source']['id']!==$fact['native_id'])$why[]='operator_source_id_conflict';
            if(isset($e['source']['operator_key'])&&'operator_'.(string)$e['source']['operator_key']!==$fact['namespace'])$why[]='operator_namespace_conflict';
        }
    }
    if(!$entry['proofs'])$why[]='independent_proof_missing';
    $why=array_values(array_unique($why));return ['status'=>$why?'hold':'ready','reasons'=>$why];
}

function ng110_write(PDO $db,array $entries,string $head,string $dir): array {
    w76_need(array_map(fn($e)=>$e['catalog_id'],$entries)===NG110_PAIRS&&!$db->inTransaction(),'guarded_write_scope');
    $attempt=false;$committed=false;$sql=false;$rollback=false;$planned=[];$held=[];$beforeCoverage=null;
    try{
        foreach(['andromeda_hotel_identities','catalog_hotels','tour_operator_identity_observations','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions'] as $t){
            $engine=w76_q($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);
            w76_need(count($engine)===1&&strtoupper($engine[0]['ENGINE'])==='INNODB','nontransactional_table');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=20');
        $db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');w76_need($db->beginTransaction(),'begin');
        $all=w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001 FOR UPDATE');
        $before=w76_index($all);$context=pm1_context($all);$ids=array_keys($entries);$ph=implode(',',array_fill(0,count($ids),'?'));
        foreach(w76_q($db,"SELECT id,name,country_id,country_name,region_name,subregion_name,category,is_active,latitude,longitude FROM catalog_hotels WHERE id IN ($ph) ORDER BY id FOR UPDATE",$ids) as $h)$context['hotels'][(int)$h['id']]=$h;
        $manual=w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001 FOR UPDATE');
        $excluded=w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001 FOR UPDATE');
        foreach($manual as $r)if($r['catalog_hotel_id']!==null)$context['manual'][(int)$r['catalog_hotel_id']]=true;
        foreach($excluded as $r)if($r['catalog_hotel_id']!==null)$context['exclusions'][(int)$r['catalog_hotel_id']]=true;
        $anexBefore=w76_q($db,'SELECT * FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001 FOR UPDATE');
        $beforeCoverage=w76_census($db);
        foreach($entries as $id=>$entry){
            $decision=ng110_classify($entry,$context);
            if($decision['status']!=='ready'){$held[]=['catalog_id'=>$entry['catalog_id'],'local_hotel_id'=>$id]+$decision;continue;}
            $old=$context['sources'][$entry['catalog_id']][0];$history=json_decode($old['evidence_json'],true);
            $evidence=['operation_id'=>NG110_OP,'batch'=>NC110_BATCH,'source_sha'=>$head,
                'rule'=>'proven_same_operator_native_current_pending_null','review_operation'=>NC110_OP,'review_sha256'=>NG110_INPUT_SHA,
                'inputs'=>[PM1_NATIVE_OP=>PM1_NATIVE_SHA],'source'=>$history['source'],'target'=>$context['hotels'][$id],
                'proofs'=>$entry['proofs'],'prior_evidence_json'=>$old['evidence_json'],'prior_evidence_sha256'=>$old['evidence_sha256'],
                'catalog_sha256_preserved'=>$old['catalog_sha256'],'provider_http_calls'=>0];
            $raw=w76_json($evidence);$planned['andromeda_catalog|'.$entry['catalog_id']]=['id'=>$id,'cat'=>$entry['catalog_id'],
                'name'=>$context['hotels'][$id]['name'],'old'=>$old,'new_json'=>$raw,'new_sha'=>hash('sha256',$raw),'proof_count'=>count($entry['proofs'])];
        }
        w76_need(count($planned)<=4,'write_cap');
        w76_save($dir.'/write-plan.json',['operation'=>NG110_OP,'source_sha'=>$head,'review_sha256'=>NG110_INPUT_SHA,
            'planned'=>$planned,'held'=>$held,'before_sha256'=>w76_hash($before)]);
        if(!$planned){w76_need($db->rollBack(),'rollback_failed');return ['state'=>'completed_no_new_writes','rows'=>[],'held'=>$held,
            'current_candidates_evaluated'=>4,'mapping_writes'=>0,'database_writes'=>0,'readback_verified'=>true,
            'coverage_before'=>$beforeCoverage,'coverage_after'=>$beforeCoverage];}
        $update=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND catalog_sha256=? AND evidence_sha256=?");
        foreach($planned as $p){$sql=true;w76_need($update->execute([$p['id'],$p['new_sha'],$p['new_json'],$p['cat'],$p['old']['catalog_sha256'],$p['old']['evidence_sha256']])&&$update->rowCount()===1,'conditional_update');}
        $verify=static function() use($db,$before,$planned,$manual,$excluded,$anexBefore): array {
            $rows=w76_verify($before,$planned,w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001'));
            w76_need($manual===w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001'),'manual_changed');
            w76_need($excluded===w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001'),'exclusions_changed');
            w76_need($anexBefore===w76_q($db,'SELECT * FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001'),'anex_mappings_changed');
            pm1_resolver_readback($db,$planned);return $rows;
        };
        $verify();w76_save($dir.'/pre-commit.json',['operation'=>NG110_OP,'planned_count'=>count($planned),'plan_sha256'=>w76_hash($planned)]);
        w76_save($dir.'/commit-attempt.json',['operation'=>NG110_OP,'state'=>'commit_attempt_no_replay']);
        $attempt=true;w76_need($db->commit(),'commit');$committed=true;
        $db->exec('START TRANSACTION READ ONLY');$read=$verify();$after=w76_census($db);w76_need($db->rollBack(),'readback_end');
        return ['state'=>'committed_readback_verified','current_candidates_evaluated'=>4,'commit_attempted'=>true,'commit_completed'=>true,
            'rows'=>$read,'held'=>$held,'database_writes'=>count($read),'mapping_writes'=>count($read),'readback_verified'=>true,
            'effective_resolver_verified'=>true,'prior_evidence_preserved'=>true,'unrelated_identities_unchanged'=>true,
            'coverage_before'=>$beforeCoverage,'coverage_after'=>$after,'new_full_triples'=>$after['full_triple']-$beforeCoverage['full_triple']];
    }catch(Throwable $e){
        if($db->inTransaction())try{$rollback=$db->rollBack();}catch(Throwable $ignored){}
        $count=$committed?count($planned):(($attempt||($sql&&!$rollback))?null:0);
        return ['state'=>$committed?'committed_readback_unconfirmed':($attempt?'commit_outcome_unknown_no_replay':($count===0?'rolled_back_no_writes':'write_outcome_unknown_no_replay')),
            'reason'=>preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'guarded_writer_failed',
            'commit_attempted'=>$attempt,'commit_completed'=>$committed,'current_candidates_evaluated'=>4,'rows'=>[],'held'=>$held,
            'database_writes'=>$count,'mapping_writes'=>$count,'readback_verified'=>false];
    }
}

function ng110_main(array $args): int {
    w76_need(count($args)===2&&$args[1]==='--execute','guarded_disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
    w76_need(realpath($root)===$root&&basename($root)==='anytoour.ru'&&realpath($dir)===$dir&&basename($dir)===NG110_OP
        &&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'guarded_scope');
    $reservation=pm1_read(dirname($dir),NG110_OP.'/reservation.json',null,1048576);
    w76_need(($reservation['operation']??null)===NG110_OP&&($reservation['source_sha']??null)===$head
        &&($reservation['batch']??null)===NC110_BATCH&&($reservation['input_sha256']??null)===NG110_INPUT_SHA
        &&($reservation['maximum_writes']??null)===4&&($reservation['provider_http_calls']??null)===0,'guarded_reservation');
    foreach(['execution-started.json','write-plan.json','pre-commit.json','commit-attempt.json','result.json','receipt.json'] as $f)w76_need(!file_exists($dir.'/'.$f),'guarded_no_replay');
    w76_save($dir.'/execution-started.json',['operation'=>NG110_OP,'source_sha'=>$head,'input_sha256'=>NG110_INPUT_SHA]);
    try{
        $entries=ng110_prepare(dirname($dir));
        require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        $out=ng110_write(v2_data_db(),$entries,$head,$dir);
    }catch(Throwable $e){$out=['state'=>'failed_before_writer','reason'=>preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'guarded_prepare_failed',
        'rows'=>[],'held'=>[],'current_candidates_evaluated'=>0,'database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>false];}
    $out+=['operation'=>NG110_OP,'source_sha'=>$head,'batch'=>NC110_BATCH,'input_sha256'=>NG110_INPUT_SHA,'provider_http_calls'=>0,'no_replay'=>true];
    $hash=w76_save($dir.'/result.json',$out);w76_save($dir.'/receipt.json',['operation'=>NG110_OP,'source_sha'=>$head,'batch'=>NC110_BATCH,
        'input_sha256'=>NG110_INPUT_SHA,'state'=>$out['state'],'result_sha256'=>$hash,'database_writes'=>$out['database_writes'],
        'mapping_writes'=>$out['mapping_writes'],'readback_verified'=>$out['readback_verified'],'provider_http_calls'=>0,'no_replay'=>true]);
    echo w76_json($out)."\n";return in_array($out['state'],['committed_readback_verified','completed_no_new_writes'],true)?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(ng110_main($argv));
