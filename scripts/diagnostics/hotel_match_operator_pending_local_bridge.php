<?php
declare(strict_types=1);
/* MATCH: consume saved native identity evidence; never fetch supplier data. */
define('OPB_LIBRARY_ONLY', true);
require_once __DIR__.'/hotel_match_operator_pending_anex_bridge.php';
const OPL_OP = 'hotel-match-operator-pending-local-bridge-1971-20260915-v1';
const OPL_INPUT_SHA = '34338419421f373d194e0d863828d0bc8a1e61bd11fd34d4d0ded11dcd17ffec';
const OPL_CAPTURE_SHA = 'f379062abd43c36e6feb792f8e0313466ddd579a1959cfe814e90b863a43968e';

function opl_evidence(array $row): array {
    $raw=$row['evidence_json']??null;
    if(!is_string($raw)||!hash_equals((string)($row['evidence_sha256']??''),hash('sha256',$raw))) throw new RuntimeException('evidence_hash');
    $value=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($value)) throw new RuntimeException('evidence_shape');
    return $value;
}
function opl_binding(array $row,array $seed,array $anchors): array {
    $fail=static fn(string $reason):array=>['ok'=>false,'reason'=>$reason];
    $ns=(string)($row['supplier_namespace']??'');$id=(string)($row['external_hotel_id']??'');
    if(!in_array($ns,['operator_315','operator_342'],true)||$ns!==($seed['supplier_namespace']??'')||$id!==(string)($seed['external_hotel_id']??'')) return $fail('namespace_or_id');
    if(($row['decision_status']??'')!=='pending'||($row['local_hotel_id']??null)!==null) return $fail('current_decision_protected');
    if(($row['evidence_sha256']??'')!==($seed['evidence_sha256']??null)||($row['catalog_sha256']??'')!==($seed['catalog_sha256']??null)||$row['catalog_sha256']!==OPL_CAPTURE_SHA) return $fail('changed_saved_evidence');
    $e=opl_evidence($row);$op=substr($ns,9);
    if(($e['schema']??'')!=='operator-original-price-bridge/1'||($e['source_result_sha256']??'')!==OPL_CAPTURE_SHA||(string)($e['source']['operator_key']??'')!==$op||(string)($e['source']['id']??'')!==$id||($e['decision']['state']??'')!=='pending'||($e['decision']['local_hotel_id']??null)!==null) return $fail('typed_contract');
    $facts=$e['provider_bridges']??[];$ands=[];$names=[];$country=(int)($e['country_id']??0);
    if(!is_array($facts)||!$facts||!in_array($country,[1,4],true))return $fail('empty_or_country');
    foreach($facts as $f){
        if(!is_array($f)||($f['action']??'')!=='price'||($f['is_operator_hotel_key']??null)!==false||(string)($f['operator_key']??'')!==$op||(string)($f['native_hotel_id']??'')!==$id||(int)($f['country_id']??0)!==$country) return $fail('raw_bridge_contract');
        foreach(['request_sha256','response_sha256'] as $k)if(!preg_match('/^[0-9a-f]{64}$/D',(string)($f[$k]??'')))return $fail('source_digest');
        $and=(string)($f['andromeda_hotel_id']??'');if(!preg_match('/^[1-9][0-9]{0,19}$/D',$and))return $fail('andromeda_key');$ands[$and]=true;
        foreach(['hotel_name','original_name'] as $k){if(!is_string($f[$k]??null)||trim($f[$k])==='')return $fail('missing_identity_name');$names[]=$f[$k];}
    }
    if(count($ands)!==1)return $fail('multiple_andromeda_keys');
    $and=(string)array_key_first($ands);if(!isset($anchors[$and]))return $fail('no_new_accepted_anchor');
    return ['ok'=>true,'andromeda_id'=>$and,'proof'=>$anchors[$and],'country_id'=>$country,'names'=>$names,'prior'=>$e];
}
function opl_compatible(array $names,string $local): bool {
    if(!opb_name_guard($names,$local))return false;
    $qual=['annex','beach','garden','gardens','north','south','pool','hill','hills','aquamarine'];
    $target=opb_tokens($local);$tq=array_values(array_intersect($target,$qual));sort($tq);
    foreach($names as $name){$tokens=opb_tokens($name);$q=array_values(array_intersect($tokens,$qual));sort($q);if($q!==$tq)return false;
        // Direct key evidence is primary; reject unrelated names sharing one generic brand word.
        $shared=count(array_intersect($tokens,$target));$min=min(count($tokens),count($target));
        if($min<1||($shared!==$min&&($shared<2||$shared/$min<0.75)))return false;
    }
    return true;
}
function opl_coordinates(array $e,array $hotel,int $depth=0): bool {
    if($depth>8)throw new RuntimeException('provenance_depth');
    foreach(['source','geography'] as $key)if(is_array($e[$key]??null)){$d=opb_distance($e[$key],$hotel);if($d!==null&&$d>5)return false;}
    return !is_array($e['prior_evidence']??null)||opl_coordinates($e['prior_evidence'],$hotel,$depth+1);
}
function opl_anchor(array $row,array $proof,array $hotel,array $binding): array {
    $fail=static fn(string $reason):array=>['ok'=>false,'reason'=>$reason];
    if(($row['supplier_namespace']??'')!=='andromeda_catalog'||(string)($row['external_hotel_id']??'')!==$binding['andromeda_id']||($row['decision_status']??'')!=='accepted'||(int)($row['local_hotel_id']??0)!==(int)$proof['local_hotel_id']||($row['evidence_sha256']??'')!==$proof['evidence_sha256'])return $fail('anchor_current_state_changed');
    $e=opl_evidence($row);$p=$e['promotion']??[];
    if(($p['operation_id']??'')!==OPB_OP||($p['rule']??'')!=='price_original_exact_anex_native_current_authority'||(string)($p['native_anex_hotel_id']??'')!==(string)$proof['native_anex_hotel_id']||(int)($p['target_local_hotel_id']??0)!==(int)$proof['local_hotel_id'])return $fail('anchor_provenance');
    if((int)($hotel['id']??0)!==(int)$proof['local_hotel_id']||(int)($hotel['is_active']??0)!==1||(int)($hotel['country_id']??0)!==$binding['country_id'])return $fail('local_country_or_activity');
    if(!opl_compatible($binding['names'],(string)($hotel['name']??'')))return $fail('name_or_qualifier_conflict');
    if(!opl_coordinates($e,$hotel)||!opl_coordinates($binding['prior'],$hotel))return $fail('coordinate_conflict_gt5km');
    return ['ok'=>true,'target'=>(int)$proof['local_hotel_id'],'anchor_sha256'=>$row['evidence_sha256']];
}
function opl_counts(PDO $db): array {
    return opb_query($db,"SELECT supplier_namespace,decision_status,COUNT(*) n FROM andromeda_hotel_identities WHERE supplier_namespace IN ('operator_315','operator_342') GROUP BY supplier_namespace,decision_status ORDER BY supplier_namespace,decision_status");
}
function opl_apply(string $dir,string $sourceSha): void {
    $res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    if(($res['operation_id']??'')!==OPL_OP||($res['source_sha']??'')!==$sourceSha||($res['state']??'')!=='reserved_before_db_access'||($res['input_sha256']??'')!==OPL_INPUT_SHA)throw new RuntimeException('reservation_contract');
    $raw=(string)file_get_contents($dir.'/input.json');if(!hash_equals(OPL_INPUT_SHA,hash('sha256',$raw)))throw new RuntimeException('input_hash');
    $input=json_decode($raw,true,64,JSON_THROW_ON_ERROR);if(($input['schema']??'')!=='operator-pending-local-input/1'||count($input['seeds']??[])!==145||count($input['anchors']??[])!==14)throw new RuntimeException('input_scope');
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root_guard');
    $anchors=[];foreach($input['anchors'] as $a){$id=(string)$a['andromeda_hotel_id'];if(isset($anchors[$id]))throw new RuntimeException('duplicate_anchor');$anchors[$id]=$a;}
    $db=null;$committed=false;$phase='bootstrap';
    try {
        require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->exec('SET SESSION innodb_lock_wait_timeout=30');$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->beginTransaction();$phase='current_guards';
        $tables=['andromeda_hotel_identities','catalog_hotels','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions'];$eng=array_column(opb_query($db,'SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,count($tables),'?')).')',$tables),'ENGINE','TABLE_NAME');foreach($tables as $t)if(strtoupper($eng[$t]??'')!=='INNODB')throw new RuntimeException('transactional_engine');
        $review=opb_query($db,"SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anex_review_state'");$hasReview=false;if($review){if(strtoupper($review[0]['ENGINE'])!=='INNODB')throw new RuntimeException('review_engine');$hasReview=(bool)opb_query($db,"SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anex_review_state' AND COLUMN_NAME='anex_hotel_id'");}
        $before=opl_counts($db);$plan=[];$holds=[];$seen=[];
        foreach($input['seeds'] as $seed){
            $ns=$seed['supplier_namespace'];$id=(string)$seed['external_hotel_id'];$key=$ns.'|'.$id;if(isset($seen[$key]))throw new RuntimeException('duplicate_seed');$seen[$key]=true;
            $hold=static fn(string $r):array=>['supplier_namespace'=>$ns,'external_hotel_id'=>$id,'reason'=>$r];
            $rows=opb_query($db,'SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace=? AND external_hotel_id=? FOR UPDATE',[$ns,$id]);
            if(count($rows)!==1){$holds[]=$hold('current_identity_missing');continue;}$row=$rows[0];$b=opl_binding($row,$seed,$anchors);if(!$b['ok']){$holds[]=$hold($b['reason']);continue;}
            $ar=opb_query($db,"SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? FOR UPDATE",[$b['andromeda_id']]);
            $hr=opb_query($db,'SELECT id,country_id,name,latitude,longitude,is_active FROM catalog_hotels WHERE id=? FOR UPDATE',[$b['proof']['local_hotel_id']]);
            if(count($ar)!==1||count($hr)!==1){$holds[]=$hold('current_anchor_missing');continue;}$a=opl_anchor($ar[0],$b['proof'],$hr[0],$b);if(!$a['ok']){$holds[]=$hold($a['reason']);continue;}
            $auth=opb_authority($db,(int)$b['proof']['native_anex_hotel_id'],$hasReview);if(!$auth['ok']||(int)$auth['target']!==$a['target']){$holds[]=$hold('underlying_anex_authority_changed_or_protected');continue;}
            $e=$b['prior'];$e['prior_evidence']=$b['prior'];$e['operation_id']=OPL_OP;$e['decision']=['state'=>'accepted','local_hotel_id'=>$a['target'],'country_id'=>$b['country_id'],'reason'=>'saved_original_to_newly_accepted_current_anchor'];
            $e['promotion']=['operation_id'=>OPL_OP,'source_sha'=>$sourceSha,'rule'=>'saved_original_to_newly_accepted_current_anchor','andromeda_hotel_id'=>$b['andromeda_id'],'anchor_evidence_sha256'=>$a['anchor_sha256'],'underlying_anex_hotel_id'=>$b['proof']['native_anex_hotel_id'],'prior_evidence_sha256'=>$row['evidence_sha256'],'server_current'=>true,'coordinate_conflict_gt5km_blocked'=>true];
            $json=opb_json($e);$plan[]=['supplier_namespace'=>$ns,'external_hotel_id'=>$id,'local_hotel_id'=>$a['target'],'local_name'=>$hr[0]['name'],'andromeda_hotel_id'=>$b['andromeda_id'],'old_sha256'=>$row['evidence_sha256'],'catalog_sha256'=>$row['catalog_sha256'],'evidence_json'=>$json,'evidence_sha256'=>hash('sha256',$json)];
        }
        if(count($plan)>26)throw new RuntimeException('unexpected_promotion_scope');
        opb_write($dir.'/prepared-current-plan.json',['operation_id'=>OPL_OP,'source_sha'=>$sourceSha,'rows'=>$plan,'holds'=>$holds,'counts_before'=>$before]);
        $phase='apply';$update=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace=? AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND catalog_sha256=? AND evidence_sha256=?");
        foreach($plan as $p){$update->execute([$p['local_hotel_id'],$p['evidence_sha256'],$p['evidence_json'],$p['supplier_namespace'],$p['external_hotel_id'],$p['catalog_sha256'],$p['old_sha256']]);if($update->rowCount()!==1)throw new RuntimeException('current_row_changed');}
        $phase='commit';$committed=true;$db->commit();opb_write($dir.'/committed.json',['operation_id'=>OPL_OP,'source_sha'=>$sourceSha,'rows'=>count($plan),'state'=>'committed_before_readback']);
        $phase='post_commit_readback';$readback=[];$split=[];
        foreach($plan as $p){$rows=opb_query($db,'SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace=? AND external_hotel_id=?',[$p['supplier_namespace'],$p['external_hotel_id']]);if(count($rows)!==1)throw new RuntimeException('readback_missing');$r=$rows[0];opl_evidence($r);
            if($r['decision_status']!=='accepted'||(int)$r['local_hotel_id']!==$p['local_hotel_id']||$r['evidence_sha256']!==$p['evidence_sha256']||$r['evidence_json']!==$p['evidence_json']||$r['catalog_sha256']!==$p['catalog_sha256'])throw new RuntimeException('readback_mismatch');
            $readback[]=array_diff_key($p,['evidence_json'=>true]);$split[$p['supplier_namespace']]=($split[$p['supplier_namespace']]??0)+1;
        }
        $result=['operation_id'=>OPL_OP,'source_sha'=>$sourceSha,'state'=>'completed_committed','input_sha256'=>OPL_INPUT_SHA,'examined'=>count($input['seeds']),'native_promotions'=>count($readback),'by_operator'=>$split,'holds'=>$holds,'post_commit_readback'=>$readback,'counts_before'=>$before,'counts_after'=>opl_counts($db),'andromeda_writes'=>0,'anex_mapping_writes'=>0,'supplier_calls'=>0,'booking_calls'=>0,'no_replay'=>true];
        $hash=opb_write($dir.'/result.json',$result);opb_write($dir.'/receipt.json',['operation_id'=>OPL_OP,'source_sha'=>$sourceSha,'state'=>'completed_committed','result_sha256'=>$hash,'post_commit_rows'=>count($readback),'readback_verified'=>true,'supplier_calls'=>0,'no_replay'=>true]);echo json_encode(['state'=>$result['state'],'written'=>count($readback),'by_operator'=>$split,'holds'=>count($holds)])."\n";
    }catch(Throwable $e){if(!$committed&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();$state=$committed?'unknown_after_commit':'failed_before_commit';$failure=['operation_id'=>OPL_OP,'source_sha'=>$sourceSha,'state'=>$state,'phase'=>$phase,'error_class'=>get_class($e),'reason'=>preg_match('/^[A-Za-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'sanitized_error','no_replay'=>true];
        if($committed)opb_write($dir.'/unknown.json',$failure);else{$hash=opb_write($dir.'/result.json',$failure);opb_write($dir.'/receipt.json',['operation_id'=>OPL_OP,'source_sha'=>$sourceSha,'state'=>$state,'result_sha256'=>$hash,'database_writes'=>0,'no_replay'=>true]);}fwrite(STDERR,$state.':'.$phase."\n");exit(2);
    }
}
if(!defined('OPL_LIBRARY_ONLY')){$sha=getenv('MATCH_SOURCE_SHA')?:'';if((getenv('MATCH_OPERATION_ID')?:'')!==OPL_OP||!preg_match('/^[0-9a-f]{40}$/D',$sha))throw new RuntimeException('operation_environment');opl_apply(getenv('HOME').'/.anytoour-match/operations/'.OPL_OP,$sha);}
