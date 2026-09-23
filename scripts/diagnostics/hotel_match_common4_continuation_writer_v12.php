<?php
declare(strict_types=1);

const HMC12_OP='hotel-match-common4-continuation-writer-1971-20260923-v12';
const HMC12_PLAN_SHA='e33b67751882c0ec70311f4d7b46429c99ace9e3d59cbe9760b835fa604fcb1f';
const HMC12_ALLOWED=['bgoperator'=>18,'operator_315'=>25,'operator_342'=>43];

function hmc12_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function hmc12_sort(mixed $v):mixed{if(!is_array($v))return $v;if(array_is_list($v))return array_map('hmc12_sort',$v);ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=hmc12_sort($x);return $v;}
function hmc12_json(mixed $v):string{return json_encode(hmc12_sort($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmc12_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);hmc12_need(is_array($v),'json_shape');return $v;}
function hmc12_save(string $p,array $v):string{$raw=hmc12_json($v)."\n";$f=@fopen($p,'x+b');hmc12_need($f!==false,'exclusive_create');try{hmc12_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmc12_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function hmc12_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hmc12_key(array $r):string{return (string)$r['supplier_namespace'].'|'.(string)$r['external_hotel_id'];}
function hmc12_row_hash(array $r):string{return hash('sha256',hmc12_json($r));}
function hmc12_excluded(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}
function hmc12_target(array $h):array{
    $numeric=['id'=>true,'country_id'=>true,'region_id'=>true,'subregion_id'=>true,'is_active'=>true];
    $out=[];foreach(['id','name','country_id','country_name','region_id','region_name','subregion_id','subregion_name','category','is_active'] as $k){$v=$h[$k]??null;$out[$k]=isset($numeric[$k])&&$v!==null?(string)$v:($v===null?null:(string)$v);}return $out;
}
function hmc12_anchor_projection(array $a):array{
    $out=[];foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256'] as $k)$out[$k]=$a[$k]??null;
    if($out['local_hotel_id']!==null)$out['local_hotel_id']=(string)$out['local_hotel_id'];return $out;
}
function hmc12_anchor_set(array $aa):array{
    $out=[];foreach($aa as $a){$p=hmc12_anchor_projection($a);$k=(string)$p['external_hotel_id'].'|'.(string)$p['catalog_sha256'].'|'.(string)$p['evidence_sha256'];hmc12_need(!isset($out[$k]),'anchor_duplicate');$out[$k]=$p;}ksort($out);return $out;
}
function hmc12_manifest(array $audit,int $expected):array{
    hmc12_need(($audit['state']??'')==='completed_read_only_continuation_current','audit_state');
    hmc12_need(($audit['continuation_plan_sha256']??'')===HMC12_PLAN_SHA,'audit_plan_sha');
    hmc12_need($expected>=1&&$expected<=300,'expected_count');
    $out=[];$sources=[];$nt=[];
    foreach(($audit['rows']??[]) as $r){
        if(!is_array($r)||($r['kind']??'')!=='identity'||($r['writer_ready']??false)!==true)continue;
        hmc12_need(($r['status']??'')==='current_missing_edge'&&($r['anchor_state']??'')==='canonical_anchor_ok'&&($r['safe_to_write_now']??null)===false,'manifest_status');
        $ns=(string)($r['supplier_namespace']??'');$ext=(string)($r['external_hotel_id']??'');$tv=(int)($r['tv_hotel_id']??0);$op=(int)($r['operator_id']??0);
        hmc12_need(isset(HMC12_ALLOWED[$ns])&&HMC12_ALLOWED[$ns]===$op&&$tv>0&&preg_match('/^[1-9][0-9]{0,19}$/D',$ext)===1,'manifest_identity');
        $sk=$ns.'|'.$ext;$tk=$ns.'|'.$tv;hmc12_need(!isset($sources[$sk])&&!isset($nt[$tk]),'manifest_collision');$sources[$sk]=true;$nt[$tk]=true;
        foreach(['source_result_sha256','search_id_sha256','tour_id_sha256','operator_link_sha256'] as $f)hmc12_need(preg_match('/^[0-9a-f]{64}$/D',(string)($r[$f]??''))===1,'manifest_'.$f);
        $target=$r['catalog_hotel']??null;$anchors=$r['anchors']??null;$cat=(string)($r['unanimous_catalog_sha256']??'');
        hmc12_need(is_array($target)&&(int)($target['id']??0)===$tv&&is_array($anchors)&&count($anchors)>=1&&preg_match('/^[0-9a-f]{64}$/D',$cat)===1,'manifest_target');
        foreach($anchors as $a)hmc12_need(is_array($a)&&($a['supplier_namespace']??'')==='andromeda_catalog'&&($a['decision_status']??'')==='accepted'&&(int)($a['local_hotel_id']??0)===$tv&&($a['catalog_sha256']??'')===$cat,'manifest_anchor');
        $out[]=['supplier_namespace'=>$ns,'external_hotel_id'=>$ext,'tv_hotel_id'=>$tv,'operator_id'=>$op,'source_operation'=>(string)$r['source_operation'],
            'source_result_sha256'=>(string)$r['source_result_sha256'],'batch'=>(int)($r['batch']??0),'search_id_sha256'=>(string)$r['search_id_sha256'],
            'tour_id_sha256'=>(string)$r['tour_id_sha256'],'operator_link_sha256'=>(string)$r['operator_link_sha256'],'target'=>$target,
            'unanimous_catalog_sha256'=>$cat,'anchors'=>hmc12_anchor_set($anchors)];
    }
    hmc12_need(count($out)===$expected,'manifest_count');usort($out,fn($a,$b)=>strcmp($a['supplier_namespace'].'|'.$a['external_hotel_id'],$b['supplier_namespace'].'|'.$b['external_hotel_id']));return $out;
}
function hmc12_indexes(array $rows):array{
    $byKey=[];$byTarget=[];$anchors=[];
    foreach($rows as $r){$k=hmc12_key($r);hmc12_need(!isset($byKey[$k]),'duplicate_source_key');$byKey[$k]=$r;if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null){$byTarget[$r['supplier_namespace'].'|'.$r['local_hotel_id']][]=$r;if($r['supplier_namespace']==='andromeda_catalog')$anchors[(int)$r['local_hotel_id']][]=$r;}}
    return [$byKey,$byTarget,$anchors];
}
function hmc12_current_anchors(array $m,array $aa):array{
    hmc12_need(count($aa)===count($m['anchors']),'anchor_count_drift');$cats=[];
    foreach($aa as $a){hmc12_need(($a['supplier_namespace']??'')==='andromeda_catalog'&&($a['decision_status']??'')==='accepted'&&(int)($a['local_hotel_id']??0)===(int)$m['tv_hotel_id'],'anchor_target_drift');$raw=(string)($a['evidence_json']??'');$eh=(string)($a['evidence_sha256']??'');$cat=(string)($a['catalog_sha256']??'');hmc12_need(hash('sha256',$raw)===$eh,'anchor_evidence_hash_drift');hmc12_need($cat===$m['unanimous_catalog_sha256'],'anchor_catalog_drift');$cats[$cat]=true;}
    hmc12_need(count($cats)===1&&hmc12_anchor_set($aa)===$m['anchors'],'anchor_set_drift');return $aa;
}
function hmc12_evidence(array $m,array $anchors,string $sourceSha,string $auditSha):array{
    return ['operation_id'=>HMC12_OP,'rule'=>'continuation_single_native_plus_current_canonical_anchor','source_sha'=>$sourceSha,'audit_result_sha256'=>$auditSha,
        'supplier_namespace'=>$m['supplier_namespace'],'external_hotel_id'=>$m['external_hotel_id'],'tv_hotel_id'=>$m['tv_hotel_id'],
        'saved_operator_edge'=>['operator_id'=>$m['operator_id'],'source_operation'=>$m['source_operation'],'source_result_sha256'=>$m['source_result_sha256'],'batch'=>$m['batch'],
            'search_id_sha256'=>$m['search_id_sha256'],'tour_id_sha256'=>$m['tour_id_sha256'],'operator_link_sha256'=>$m['operator_link_sha256']],
        'target'=>hmc12_target($m['target']),'canonical_anchors'=>array_values(hmc12_anchor_set($anchors)),'raw_operator_url_exported'=>false,'supplier_calls'=>0];
}
function hmc12_write(PDO $db,array $manifest,string $sourceSha,string $auditSha,?string $opDir=null):array{
    $count=count($manifest);hmc12_need($count>=1&&$count<=300,'writer_count');$ids=array_values(array_unique(array_map(fn($r)=>(int)$r['tv_hotel_id'],$manifest)));sort($ids,SORT_NUMERIC);$ph=implode(',',array_fill(0,count($ids),'?'));
    $commitAttempted=false;$committed=false;$planned=[];$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    try{
        $db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();
        $before=hmc12_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id FOR UPDATE');$beforeHashes=[];foreach($before as $r)$beforeHashes[hmc12_key($r)]=hmc12_row_hash($r);[$byKey,$byTarget,$anchors]=hmc12_indexes($before);
        $catalog=[];foreach(hmc12_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id FOR UPDATE",$ids) as $r)$catalog[(int)$r['id']]=$r;
        $manual=[];foreach(hmc12_query($db,"SELECT * FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph) ORDER BY catalog_hotel_id FOR UPDATE",$ids) as $r)$manual[(int)$r['catalog_hotel_id']][]=$r;
        foreach($manifest as $m){$ns=$m['supplier_namespace'];$ext=$m['external_hotel_id'];$tv=(int)$m['tv_hotel_id'];hmc12_need(!isset($byKey[$ns.'|'.$ext]),'source_key_now_present');$h=$catalog[$tv]??null;hmc12_need(is_array($h)&&(int)($h['is_active']??0)===1&&!hmc12_excluded((string)($h['country_name']??'')),'target_guard');hmc12_need(hmc12_target($h)===hmc12_target($m['target']),'target_facts_drift');hmc12_need(!isset($manual[$tv]),'manual_target_protected');hmc12_need(empty($byTarget[$ns.'|'.$tv]??[]),'target_namespace_occupied');$aa=hmc12_current_anchors($m,$anchors[$tv]??[]);$ev=hmc12_evidence($m,$aa,$sourceSha,$auditSha);$ej=hmc12_json($ev);$eh=hash('sha256',$ej);$anchorHashes=[];foreach($aa as $a)$anchorHashes[hmc12_key($a)]=hmc12_row_hash($a);$planned[]=['supplier_namespace'=>$ns,'external_hotel_id'=>$ext,'local_hotel_id'=>$tv,'decision_status'=>'accepted','catalog_sha256'=>$m['unanimous_catalog_sha256'],'evidence_sha256'=>$eh,'evidence_json'=>$ej,'anchor_hashes'=>$anchorHashes];}
        $st=$db->prepare("INSERT INTO andromeda_hotel_identities (supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES (?,?,?,'accepted',?,?,?)");
        foreach($planned as $p){$st->execute([$p['supplier_namespace'],$p['external_hotel_id'],$p['local_hotel_id'],$p['catalog_sha256'],$p['evidence_sha256'],$p['evidence_json']]);hmc12_need($st->rowCount()===1,'insert_count');}
        $after=hmc12_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');hmc12_need(count($after)===count($before)+$count,'identity_count_delta');$afterBy=[];foreach($after as $r)$afterBy[hmc12_key($r)]=$r;foreach($beforeHashes as $k=>$h)hmc12_need(isset($afterBy[$k])&&hmc12_row_hash($afterBy[$k])===$h,'preexisting_identity_changed');
        foreach($planned as $p){$k=$p['supplier_namespace'].'|'.$p['external_hotel_id'];$r=$afterBy[$k]??null;hmc12_need(is_array($r),'staged_missing');foreach(['local_hotel_id','decision_status','catalog_sha256','evidence_sha256','evidence_json'] as $f)hmc12_need((string)$r[$f]===(string)$p[$f],'staged_mismatch');foreach($p['anchor_hashes'] as $ak=>$ah)hmc12_need(isset($afterBy[$ak])&&hmc12_row_hash($afterBy[$ak])===$ah,'anchor_changed');}
        if($opDir!==null){hmc12_save($opDir.'/pre-commit.json',['operation'=>HMC12_OP,'state'=>'verified_before_commit','planned_writes'=>$count,'audit_result_sha256'=>$auditSha]);hmc12_save($opDir.'/commit-attempt.json',['operation'=>HMC12_OP,'state'=>'commit_attempt_no_replay','planned_writes'=>$count]);}
        $commitAttempted=true;hmc12_need($db->commit(),'commit_false');$committed=true;
        $post=hmc12_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');$postBy=[];foreach($post as $r)$postBy[hmc12_key($r)]=$r;$byNs=[];
        foreach($planned as $p){$k=$p['supplier_namespace'].'|'.$p['external_hotel_id'];$r=$postBy[$k]??null;hmc12_need(is_array($r),'post_missing');foreach(['local_hotel_id','decision_status','catalog_sha256','evidence_sha256','evidence_json'] as $f)hmc12_need((string)$r[$f]===(string)$p[$f],'post_mismatch');$byNs[$p['supplier_namespace']]=($byNs[$p['supplier_namespace']]??0)+1;}ksort($byNs);
        return ['state'=>'committed_verified','inserted'=>$count,'inserted_by_namespace'=>$byNs,'unique_targets'=>count($ids),'database_writes'=>$count,'mapping_writes'=>$count,'readback_verified'=>true,'preexisting_rows_preserved'=>count($before),'provider_calls'=>0,'supplier_calls'=>0];
    }catch(Throwable $e){try{if($db->inTransaction())$db->rollBack();}catch(Throwable){}$state=$committed?'post_commit_verification_failed_no_replay':($commitAttempted?'commit_unknown_no_replay':'rolled_back_no_write');throw new RuntimeException($state.':'.preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,100,'UTF-8')));}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){echo "MATCH_COMMON4_CONTINUATION_WRITER_V12_SELFTEST_OK\n";exit;}
    hmc12_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$input=(string)getenv('MATCH_AUDIT_RESULT');$auditSha=(string)getenv('MATCH_AUDIT_SHA');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');$expected=(int)getenv('MATCH_EXPECTED_WRITES');
    hmc12_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&is_file($input)&&preg_match('/^[0-9a-f]{64}$/D',$auditSha)===1&&hash_file('sha256',$input)===$auditSha&&preg_match('/^[0-9a-f]{40}$/D',$sourceSha)===1,'runtime_scope');$res=hmc12_load($dir.'/reservation.json');hmc12_need(($res['state']??'')==='reserved_before_write'&&(int)($res['expected_writes']??0)===$expected,'reservation');
    try{$manifest=hmc12_manifest(hmc12_load($input),$expected);require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$result=hmc12_write(v2_data_db(),$manifest,$sourceSha,$auditSha,$dir);$h=hmc12_save($dir.'/result.json',['operation'=>HMC12_OP]+$result);hmc12_save($dir.'/receipt.json',['operation'=>HMC12_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_calls'=>0,'database_writes'=>$result['database_writes'],'mapping_writes'=>$result['mapping_writes'],'no_replay'=>true]);echo hmc12_json(['state'=>$result['state'],'inserted'=>$result['inserted'],'inserted_by_namespace'=>$result['inserted_by_namespace'],'unique_targets'=>$result['unique_targets']])."\n";}
    catch(Throwable $e){$msg=$e->getMessage();$state=str_starts_with($msg,'commit_unknown_no_replay:')?'commit_unknown_no_replay':(str_starts_with($msg,'post_commit_verification_failed_no_replay:')?'post_commit_verification_failed_no_replay':'rolled_back_no_write');$writes=$state==='rolled_back_no_write'?0:null;$f=['operation'=>HMC12_OP,'state'=>$state,'reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($msg,0,150,'UTF-8')),'provider_calls'=>0,'database_writes'=>$writes,'mapping_writes'=>$writes,'no_replay'=>$state!=='rolled_back_no_write'];$h=hmc12_save($dir.'/result.json',$f);hmc12_save($dir.'/receipt.json',['operation'=>HMC12_OP,'state'=>$state,'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_calls'=>0,'database_writes'=>$writes,'mapping_writes'=>$writes,'no_replay'=>$f['no_replay']]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
