<?php
declare(strict_types=1);

const HMSOW_OP='hotel-match-samo-live30-original-hotelkey-writer-1971-20260924-v1';
const HMSOW_AUDIT_OP='hotel-match-samo-live30-original-hotelkey-current-1971-20260924-v3';
const HMSOW_AUDIT_RESULT_SHA='2846e8a1cebbafce27a800aefb48e9378beae118ee3aefe4cbe6b19f47b932c5';
const HMSOW_READY_SHA='f4e91e9d44067ce652d825d89859edcc4c4ec59b140ad5835d53d0c0a09ab7b4';
const HMSOW_SOURCE_OP='hotel-match-samo-live30-original-hotelkey-calibration-1971-20260924-v1';
const HMSOW_SOURCE_RESULT_SHA='0d5d7e830a3eb94442365c1bd8ece3df14eaf66ecb6b2ed4aa8290b1f596c704';
const HMSOW_SOURCE_CANDIDATE_SHA='b5cb3444c8aa63729863b768b03ea40f302c6901ed1c8d2778d0ae31e30cd6e9';
const HMSOW_EXPECTED=39;
const HMSOW_NAMESPACE='operator_115';

function hmsow_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function hmsow_sort(mixed $v):mixed{if(!is_array($v))return $v;if(array_is_list($v))return array_map('hmsow_sort',$v);ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=hmsow_sort($x);return $v;}
function hmsow_json(mixed $v):string{return json_encode(hmsow_sort($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmsow_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);hmsow_need(is_array($v),'json_shape');return $v;}
function hmsow_save(string $p,array $v):string{$raw=hmsow_json($v)."\n";$f=@fopen($p,'x+b');hmsow_need($f!==false,'exclusive_create');try{hmsow_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmsow_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function hmsow_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hmsow_id(mixed $v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,21}$/D',$s)===1?$s:null;}
function hmsow_sha(mixed $v):bool{return is_string($v)&&preg_match('/^[0-9a-f]{64}$/D',$v)===1;}
function hmsow_excluded(string $country):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($country))===1;}
function hmsow_key(array $r):string{return (string)$r['supplier_namespace'].'|'.(string)$r['external_hotel_id'];}
function hmsow_row_hash(array $r):string{return hash('sha256',hmsow_json($r));}
function hmsow_anchor_projection(array $a):array{
    $p=[];foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256'] as $k)$p[$k]=$a[$k]??null;
    if($p['local_hotel_id']!==null)$p['local_hotel_id']=(int)$p['local_hotel_id'];return $p;
}
function hmsow_anchor_set(array $rows):array{
    $out=[];foreach($rows as $a){hmsow_need(is_array($a),'anchor_shape');$p=hmsow_anchor_projection($a);hmsow_need($p['supplier_namespace']==='andromeda_catalog'&&$p['decision_status']==='accepted'&&hmsow_id($p['external_hotel_id'])!==null&&(int)$p['local_hotel_id']>0&&hmsow_sha($p['catalog_sha256'])&&hmsow_sha($p['evidence_sha256']),'anchor_fields');$k=(string)$p['external_hotel_id'].'|'.$p['catalog_sha256'].'|'.$p['evidence_sha256'];hmsow_need(!isset($out[$k]),'anchor_duplicate');$out[$k]=$p;}ksort($out,SORT_STRING);return $out;
}
function hmsow_manifest(array $audit,array $private):array{
    hmsow_need(($audit['operation']??'')===HMSOW_AUDIT_OP&&($audit['state']??'')==='completed_read_only_original_hotelkey_current','audit_state');
    hmsow_need(($audit['source_operation']??'')===HMSOW_SOURCE_OP&&($audit['source_result_sha256']??'')===HMSOW_SOURCE_RESULT_SHA&&($audit['source_candidate_manifest_sha256']??'')===HMSOW_SOURCE_CANDIDATE_SHA,'audit_source');
    hmsow_need((int)($audit['writer_ready_count']??-1)===HMSOW_EXPECTED&&($audit['writer_ready_manifest_sha256']??'')===HMSOW_READY_SHA,'audit_ready');
    foreach(['provider_http_calls','andromeda_calls','tourvisor_calls','direct_anex_calls','database_writes','mapping_writes'] as $k)hmsow_need((int)($audit[$k]??-1)===0,'audit_zero_'.$k);
    hmsow_need(($audit['safe_to_write_now']??null)===false,'audit_safe_flag');
    hmsow_need(($private['operation']??'')===HMSOW_AUDIT_OP&&($private['state']??'')==='private_writer_ready_not_committed'&&($private['safe_to_write_now']??null)===false,'private_state');
    hmsow_need(($private['source_operation']??'')===HMSOW_SOURCE_OP&&($private['source_result_sha256']??'')===HMSOW_SOURCE_RESULT_SHA&&($private['source_candidate_manifest_sha256']??'')===HMSOW_SOURCE_CANDIDATE_SHA,'private_source');
    $rows=$private['rows']??null;hmsow_need(is_array($rows)&&count($rows)===HMSOW_EXPECTED,'private_count');
    $out=[];$sources=[];$targets=[];
    foreach($rows as $r){
        hmsow_need(is_array($r)&&($r['supplier_namespace']??'')===HMSOW_NAMESPACE&&($r['safe_to_write_now']??null)===false,'row_scope');
        $ext=hmsow_id($r['external_hotel_id']??null);$local=(int)($r['local_hotel_id']??0);$catalog=hmsow_id($r['catalog_external_id']??null);$catSha=(string)($r['unanimous_catalog_sha256']??'');
        hmsow_need($ext!==null&&$local>0&&$catalog!==null&&hmsow_sha($catSha),'row_ids');
        hmsow_need(($r['source_operation']??'')===HMSOW_SOURCE_OP&&($r['source_result_sha256']??'')===HMSOW_SOURCE_RESULT_SHA&&($r['source_candidate_manifest_sha256']??'')===HMSOW_SOURCE_CANDIDATE_SHA,'row_source');
        $anchors=hmsow_anchor_set($r['anchors']??[]);hmsow_need(count($anchors)>=1,'row_anchor_count');
        foreach($anchors as $a)hmsow_need((int)$a['local_hotel_id']===$local&&$a['catalog_sha256']===$catSha,'row_anchor_binding');
        $sk=HMSOW_NAMESPACE.'|'.$ext;$tk=HMSOW_NAMESPACE.'|'.$local;hmsow_need(!isset($sources[$sk])&&!isset($targets[$tk]),'manifest_collision');$sources[$sk]=true;$targets[$tk]=true;
        $out[]=['supplier_namespace'=>HMSOW_NAMESPACE,'external_hotel_id'=>$ext,'local_hotel_id'=>$local,'catalog_external_id'=>$catalog,'unanimous_catalog_sha256'=>$catSha,'anchors'=>$anchors];
    }
    usort($out,fn($a,$b)=>strcmp($a['external_hotel_id'],$b['external_hotel_id']));return $out;
}
function hmsow_current_anchors(array $m,array $rows):array{
    $valid=[];foreach($rows as $a){if(($a['supplier_namespace']??'')!=='andromeda_catalog'||($a['decision_status']??'')!=='accepted'||(int)($a['local_hotel_id']??0)!==(int)$m['local_hotel_id'])continue;$raw=(string)($a['evidence_json']??'');$eh=(string)($a['evidence_sha256']??'');$cat=(string)($a['catalog_sha256']??'');hmsow_need(hmsow_sha($eh)&&hash('sha256',$raw)===$eh,'anchor_evidence_hash_drift');hmsow_need(hmsow_sha($cat)&&$cat===$m['unanimous_catalog_sha256'],'anchor_catalog_drift');$valid[]=$a;}
    hmsow_need(hmsow_anchor_set($valid)===$m['anchors'],'anchor_set_drift');return $valid;
}
function hmsow_target_projection(array $h):array{
    $out=[];foreach(['id','name','country_id','country_name','region_id','region_name','subregion_id','subregion_name','category','is_active'] as $k)$out[$k]=$h[$k]??null;return $out;
}
function hmsow_evidence(array $m,array $target,array $anchors,string $sourceSha):array{
    return ['operation_id'=>HMSOW_OP,'rule'=>'andromeda_original_hotelkey_calibrated_operator_identity_v1','source_sha'=>$sourceSha,
        'audit'=>['operation'=>HMSOW_AUDIT_OP,'result_sha256'=>HMSOW_AUDIT_RESULT_SHA,'writer_ready_manifest_sha256'=>HMSOW_READY_SHA],
        'calibration'=>['operation'=>HMSOW_SOURCE_OP,'result_sha256'=>HMSOW_SOURCE_RESULT_SHA,'candidate_manifest_sha256'=>HMSOW_SOURCE_CANDIDATE_SHA],
        'supplier_namespace'=>HMSOW_NAMESPACE,'external_hotel_id'=>$m['external_hotel_id'],'local_hotel_id'=>$m['local_hotel_id'],'catalog_external_id'=>$m['catalog_external_id'],
        'identity_semantics'=>'top_level_operatorKey_115_plus_original.hotelKey_empirically_calibrated_against_existing_operator_115','target'=>hmsow_target_projection($target),'canonical_anchors'=>array_values(hmsow_anchor_set($anchors)),
        'provider_calls'=>0,'raw_supplier_payload_exported'=>false];
}
function hmsow_write(PDO $db,array $manifest,string $sourceSha,?string $opDir=null):array{
    hmsow_need(count($manifest)===HMSOW_EXPECTED,'writer_count');$ids=array_values(array_unique(array_map(fn($r)=>(int)$r['local_hotel_id'],$manifest)));sort($ids,SORT_NUMERIC);$ph=implode(',',array_fill(0,count($ids),'?'));
    $commitAttempted=false;$committed=false;$planned=[];$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    try{
        $db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();
        $before=hmsow_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id FOR UPDATE');$beforeHashes=[];$byKey=[];$byTarget=[];$anchors=[];
        foreach($before as $r){$k=hmsow_key($r);$beforeHashes[$k]=hmsow_row_hash($r);$byKey[$k][]=$r;if($r['local_hotel_id']!==null){$local=(int)$r['local_hotel_id'];$byTarget[(string)$r['supplier_namespace'].'|'.$local][]=$r;if(($r['supplier_namespace']??'')==='andromeda_catalog'&&($r['decision_status']??'')==='accepted')$anchors[$local][]=$r;}}
        $catalog=[];foreach(hmsow_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id FOR UPDATE",$ids) as $r)$catalog[(int)$r['id']]=$r;
        $manual=[];foreach(hmsow_query($db,"SELECT * FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph) ORDER BY catalog_hotel_id FOR UPDATE",$ids) as $r)$manual[(int)$r['catalog_hotel_id']][]=$r;
        foreach($manifest as $m){
            $ext=$m['external_hotel_id'];$local=(int)$m['local_hotel_id'];$key=HMSOW_NAMESPACE.'|'.$ext;hmsow_need(empty($byKey[$key]??[]),'source_key_now_present');
            $h=$catalog[$local]??null;hmsow_need(is_array($h)&&(int)($h['is_active']??0)===1&&!hmsow_excluded((string)($h['country_name']??'')),'target_guard');
            hmsow_need(!isset($manual[$local]),'manual_target_protected');hmsow_need(empty($byTarget[HMSOW_NAMESPACE.'|'.$local]??[]),'target_namespace_occupied');
            $aa=hmsow_current_anchors($m,$anchors[$local]??[]);$ev=hmsow_evidence($m,$h,$aa,$sourceSha);$ej=hmsow_json($ev);$eh=hash('sha256',$ej);$anchorHashes=[];foreach($aa as $a)$anchorHashes[hmsow_key($a)]=hmsow_row_hash($a);
            $planned[]=['supplier_namespace'=>HMSOW_NAMESPACE,'external_hotel_id'=>$ext,'local_hotel_id'=>$local,'decision_status'=>'accepted','catalog_sha256'=>$m['unanimous_catalog_sha256'],'evidence_sha256'=>$eh,'evidence_json'=>$ej,'anchor_hashes'=>$anchorHashes];
        }
        $st=$db->prepare("INSERT INTO andromeda_hotel_identities (supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES (?,?,?,'accepted',?,?,?)");
        foreach($planned as $p){$st->execute([$p['supplier_namespace'],$p['external_hotel_id'],$p['local_hotel_id'],$p['catalog_sha256'],$p['evidence_sha256'],$p['evidence_json']]);hmsow_need($st->rowCount()===1,'insert_count');}
        $after=hmsow_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');hmsow_need(count($after)===count($before)+HMSOW_EXPECTED,'identity_count_delta');$afterBy=[];foreach($after as $r)$afterBy[hmsow_key($r)]=$r;
        foreach($beforeHashes as $k=>$hash)hmsow_need(isset($afterBy[$k])&&hmsow_row_hash($afterBy[$k])===$hash,'preexisting_identity_changed');
        foreach($planned as $p){$k=$p['supplier_namespace'].'|'.$p['external_hotel_id'];$r=$afterBy[$k]??null;hmsow_need(is_array($r),'staged_missing');foreach(['local_hotel_id','decision_status','catalog_sha256','evidence_sha256','evidence_json'] as $f)hmsow_need((string)$r[$f]===(string)$p[$f],'staged_mismatch');foreach($p['anchor_hashes'] as $ak=>$ah)hmsow_need(isset($afterBy[$ak])&&hmsow_row_hash($afterBy[$ak])===$ah,'anchor_changed');}
        if($opDir!==null){hmsow_save($opDir.'/pre-commit.json',['operation'=>HMSOW_OP,'state'=>'verified_before_commit','planned_writes'=>HMSOW_EXPECTED,'audit_result_sha256'=>HMSOW_AUDIT_RESULT_SHA,'writer_ready_manifest_sha256'=>HMSOW_READY_SHA]);hmsow_save($opDir.'/commit-attempt.json',['operation'=>HMSOW_OP,'state'=>'commit_attempt_no_replay','planned_writes'=>HMSOW_EXPECTED]);}
        $commitAttempted=true;hmsow_need($db->commit(),'commit_false');$committed=true;
        $post=hmsow_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');$postBy=[];foreach($post as $r)$postBy[hmsow_key($r)]=$r;foreach($planned as $p){$k=$p['supplier_namespace'].'|'.$p['external_hotel_id'];$r=$postBy[$k]??null;hmsow_need(is_array($r),'post_missing');foreach(['local_hotel_id','decision_status','catalog_sha256','evidence_sha256','evidence_json'] as $f)hmsow_need((string)$r[$f]===(string)$p[$f],'post_mismatch');}
        return ['operation'=>HMSOW_OP,'state'=>'committed_verified','inserted'=>HMSOW_EXPECTED,'inserted_by_namespace'=>[HMSOW_NAMESPACE=>HMSOW_EXPECTED],'unique_targets'=>count($ids),'database_writes'=>HMSOW_EXPECTED,'mapping_writes'=>HMSOW_EXPECTED,'readback_verified'=>true,'preexisting_rows_preserved'=>count($before),'provider_http_calls'=>0,'andromeda_calls'=>0,'tourvisor_calls'=>0,'direct_anex_calls'=>0];
    }catch(Throwable $e){try{if($db->inTransaction())$db->rollBack();}catch(Throwable){}$state=$committed?'post_commit_verification_failed_no_replay':($commitAttempted?'commit_unknown_no_replay':'rolled_back_no_write');throw new RuntimeException($state.':'.preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,140,'UTF-8')));}
}
function hmsow_self_test():void{
    $rows=[];for($i=1;$i<=HMSOW_EXPECTED;$i++)$rows[]=['supplier_namespace'=>HMSOW_NAMESPACE,'external_hotel_id'=>(string)(1000+$i),'local_hotel_id'=>100+$i,'catalog_external_id'=>(string)(9000+$i),'source_operation'=>HMSOW_SOURCE_OP,'source_result_sha256'=>HMSOW_SOURCE_RESULT_SHA,'source_candidate_manifest_sha256'=>HMSOW_SOURCE_CANDIDATE_SHA,'unanimous_catalog_sha256'=>str_repeat('a',64),'anchors'=>[['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)(200000+$i),'local_hotel_id'=>100+$i,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>str_repeat('b',64)]],'safe_to_write_now'=>false];
    $audit=['operation'=>HMSOW_AUDIT_OP,'state'=>'completed_read_only_original_hotelkey_current','source_operation'=>HMSOW_SOURCE_OP,'source_result_sha256'=>HMSOW_SOURCE_RESULT_SHA,'source_candidate_manifest_sha256'=>HMSOW_SOURCE_CANDIDATE_SHA,'writer_ready_count'=>HMSOW_EXPECTED,'writer_ready_manifest_sha256'=>HMSOW_READY_SHA,'provider_http_calls'=>0,'andromeda_calls'=>0,'tourvisor_calls'=>0,'direct_anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    $private=['operation'=>HMSOW_AUDIT_OP,'state'=>'private_writer_ready_not_committed','source_operation'=>HMSOW_SOURCE_OP,'source_result_sha256'=>HMSOW_SOURCE_RESULT_SHA,'source_candidate_manifest_sha256'=>HMSOW_SOURCE_CANDIDATE_SHA,'rows'=>$rows,'safe_to_write_now'=>false];
    $m=hmsow_manifest($audit,$private);hmsow_need(count($m)===HMSOW_EXPECTED&&$m[0]['supplier_namespace']===HMSOW_NAMESPACE,'self_manifest');
    $bad=$private;$bad['rows'][1]['external_hotel_id']=$bad['rows'][0]['external_hotel_id'];$thrown=false;try{hmsow_manifest($audit,$bad);}catch(Throwable){$thrown=true;}hmsow_need($thrown,'self_collision');
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';if($mode==='--self-test'){hmsow_self_test();echo "MATCH_SAMO_ORIGINAL_HOTELKEY_WRITER_V1_SELFTEST_OK\n";exit;}
    hmsow_need($mode==='--execute','disabled');$dir=(string)getenv('MATCH_OPERATION_DIR');$auditDir=(string)getenv('MATCH_AUDIT_DIR');$root=(string)getenv('ANYTOUR_ROOT');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hmsow_need(is_dir($dir)&&basename($dir)===HMSOW_OP&&is_dir($auditDir)&&basename($auditDir)===HMSOW_AUDIT_OP&&is_dir($root)&&basename($root)==='anytoour.ru'&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    foreach(['result.json','receipt.json','writer-ready-private.json'] as $f)hmsow_need(is_file($auditDir.'/'.$f)&&!is_link($auditDir.'/'.$f),'audit_'.$f);
    $raw=(string)file_get_contents($auditDir.'/result.json');hmsow_need(hash('sha256',$raw)===HMSOW_AUDIT_RESULT_SHA,'audit_result_hash');$audit=json_decode($raw,true,256,JSON_THROW_ON_ERROR);$receipt=hmsow_load($auditDir.'/receipt.json');hmsow_need(($receipt['result_sha256']??'')===HMSOW_AUDIT_RESULT_SHA&&($receipt['readback_verified']??false)===true&&($receipt['no_replay']??false)===true,'audit_receipt');
    hmsow_need(hash_file('sha256',$auditDir.'/writer-ready-private.json')===HMSOW_READY_SHA,'audit_private_hash');$private=hmsow_load($auditDir.'/writer-ready-private.json');$manifest=hmsow_manifest($audit,$private);
    $reservation=hmsow_load($dir.'/reservation.json');hmsow_need(($reservation['operation']??'')===HMSOW_OP&&($reservation['state']??'')==='reserved_before_write'&&(int)($reservation['expected_writes']??0)===HMSOW_EXPECTED,'reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{$result=hmsow_write(v2_data_db(),$manifest,$sha,$dir);$result['source_sha']=$sha;$result['audit_result_sha256']=HMSOW_AUDIT_RESULT_SHA;$result['writer_ready_manifest_sha256']=HMSOW_READY_SHA;$h=hmsow_save($dir.'/result.json',$result);hmsow_save($dir.'/receipt.json',['operation'=>HMSOW_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>HMSOW_EXPECTED,'mapping_writes'=>HMSOW_EXPECTED,'no_replay'=>true]);echo hmsow_json($result)."\n";}
    catch(Throwable $e){$msg=$e->getMessage();$state=str_starts_with($msg,'commit_unknown_no_replay:')?'commit_unknown_no_replay':(str_starts_with($msg,'post_commit_verification_failed_no_replay:')?'post_commit_verification_failed_no_replay':'rolled_back_no_write');if(!is_file($dir.'/result.json')){$fail=['operation'=>HMSOW_OP,'state'=>$state,'reason'=>$msg,'provider_http_calls'=>0];$h=hmsow_save($dir.'/result.json',$fail);hmsow_save($dir.'/receipt.json',['operation'=>HMSOW_OP,'state'=>$state,'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'no_replay'=>$state!=='rolled_back_no_write']);}fwrite(STDERR,$msg."\n");exit(2);}
}
