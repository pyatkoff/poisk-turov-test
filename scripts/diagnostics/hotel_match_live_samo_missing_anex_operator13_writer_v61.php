<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';
require_once dirname(__DIR__,2).'/app/integrations/anex-search-mapping-registry.php';

const V61_OP='hotel-match-live-samo-missing-anex-operator13-writer-1971-20260926-v61';
const V61_AUDIT_OP='hotel-match-live-samo-missing-anex-operator13-current-1971-20260926-v60';
const V61_EXPECTED=2;
const V61_POLICY='owner_exact_operator_key_20260912_v2';
const V61_CLASS='exact_operator_key';
const V61_READY_DIGEST='5d01d83631fe9d10b518ac0be1955fd665426fc0d1858674b9d0720c1f71a7ca';

function v61_need(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function v61_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function v61_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);v61_need(is_array($v),'json_shape');return $v;}
function v61_save(string $p,array $v):string{$raw=v61_json($v)."\n";$f=@fopen($p,'x+b');v61_need($f!==false,'exclusive_create');try{v61_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'write');if(function_exists('fsync'))v61_need(fsync($f),'sync');}finally{fclose($f);}return hash('sha256',$raw);}
function v61_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function v61_sha(mixed $v):bool{return is_string($v)&&preg_match('/^[0-9a-f]{64}$/D',$v)===1;}
function v61_native(mixed $v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,7}$/D',$s)===1?$s:null;}
function v61_excluded(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}
function v61_target(array $h):array{$o=[];foreach(['id','name','country_id','country_name','region_id','region_name','subregion_id','subregion_name','category','is_active'] as $k)$o[$k]=$h[$k]??null;return$o;}
function v61_anchor_projection(array $a):array{$o=[];foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256'] as$k)$o[$k]=$a[$k]??null;return$o;}

function v61_manifest(array $audit):array{
    v61_need(($audit['operation']??'')===V61_AUDIT_OP&&($audit['state']??'')==='completed_read_only_v59_current_audit','audit_state');
    v61_need((int)($audit['input_count']??0)===V61_EXPECTED&&(int)($audit['writer_ready_count']??0)===V61_EXPECTED,'audit_counts');
    v61_need(($audit['writer_ready_target_digest']??'')===V61_READY_DIGEST,'audit_digest');
    foreach(['provider_http_calls','tourvisor_calls','samo_calls','anex_calls','andromeda_calls','database_writes','mapping_writes'] as$k)v61_need((int)($audit[$k]??-1)===0,'audit_zero_'.$k);
    v61_need(($audit['safe_to_write_now']??null)===false,'audit_safe_flag');
    $out=[];$sources=[];$targets=[];
    foreach($audit['rows']??[] as$r){
        if(!is_array($r)||($r['writer_ready']??false)!==true)continue;
        v61_need(($r['status']??'')==='current_missing_exact_key'&&($r['anchor_state']??'')==='canonical_anchor_ok','row_status');
        $id=v61_native($r['anex_hotel_id']??null);$tv=(int)($r['tv_hotel_id']??0);
        v61_need($id!==null&&$tv>0&&(int)($r['operator_id']??0)===13&&($r['namespace']??'')==='anex','row_identity');
        foreach(['source_result_sha256','search_id_sha256','tour_id_sha256','operator_link_sha256'] as$f)v61_need(v61_sha($r[$f]??null),'row_hash_'.$f);
        $hotel=$r['catalog_hotel']??null;$anchors=$r['anchors']??null;$cat=(string)($r['unanimous_catalog_sha256']??'');
        v61_need(is_array($hotel)&&(int)($hotel['id']??0)===$tv&&is_array($anchors)&&count($anchors)>=1&&v61_sha($cat),'row_target_anchor');
        v61_need(!isset($sources[$id])&&!isset($targets[$tv]),'manifest_collision');$sources[$id]=true;$targets[$tv]=true;
        $out[]=[
            'anex_hotel_id'=>$id,'catalog_hotel_id'=>$tv,'target'=>$hotel,
            'unanimous_catalog_sha256'=>$cat,'anchors'=>array_map('v61_anchor_projection',$anchors),
            'source_operation'=>(string)($r['source_operation']??''),'source_result_sha256'=>(string)$r['source_result_sha256'],
            'batch'=>(int)($r['batch']??0),'search_id_sha256'=>(string)$r['search_id_sha256'],
            'tour_id_sha256'=>(string)$r['tour_id_sha256'],'operator_link_sha256'=>(string)$r['operator_link_sha256'],
            'audit_source_sha'=>(string)($audit['source_sha']??''),'audit_result_source_sha'=>(string)($audit['source_result_sha256']??''),
        ];
    }
    v61_need(count($out)===V61_EXPECTED,'manifest_count');
    usort($out,fn($a,$b)=>strcmp($a['anex_hotel_id'],$b['anex_hotel_id']));return$out;
}
function v61_anchor_state(array $aa,int $tv):array{
    v61_need(count($aa)>=1,'anchor_missing');$cats=[];$out=[];
    foreach($aa as$a){
        v61_need(($a['supplier_namespace']??'')==='andromeda_catalog'&&($a['decision_status']??'')==='accepted'&&(int)($a['local_hotel_id']??0)===$tv,'anchor_invalid');
        $raw=(string)($a['evidence_json']??'');$eh=(string)($a['evidence_sha256']??'');$cat=(string)($a['catalog_sha256']??'');
        v61_need(v61_sha($eh)&&v61_sha($cat)&&hash('sha256',$raw)===$eh,'anchor_evidence_hash_drift');
        $cats[$cat]=true;$out[]=v61_anchor_projection($a);
    }
    usort($out,fn($a,$b)=>strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));
    v61_need(count($cats)===1,'anchor_catalog_conflict');
    return['catalog_sha256'=>array_key_first($cats),'anchors'=>$out];
}
function v61_evidence(array $m,array $anchors,string $sourceSha,string $auditSha):array{
    return[
        'operation_id'=>V61_OP,'rule'=>'tourvisor_anex_exact_native_plus_current_canonical_anchor',
        'source_sha'=>$sourceSha,'audit_result_sha256'=>$auditSha,
        'anex_hotel_id'=>$m['anex_hotel_id'],'catalog_hotel_id'=>$m['catalog_hotel_id'],
        'source_operation'=>$m['source_operation'],'source_result_sha256'=>$m['source_result_sha256'],
        'batch'=>$m['batch'],'search_id_sha256'=>$m['search_id_sha256'],'tour_id_sha256'=>$m['tour_id_sha256'],
        'operator_link_sha256'=>$m['operator_link_sha256'],'target'=>v61_target($m['target']),
        'canonical_anchors'=>$anchors,'raw_operator_url_exported'=>false,'provider_http_calls'=>0
    ];
}
function v61_write(PDO $db,array $manifest,string $sourceSha,string $auditSha,string $opDir):array{
    v61_need(count($manifest)===V61_EXPECTED,'write_manifest_count');
    $ids=array_map(fn($m)=>(string)$m['anex_hotel_id'],$manifest);$targets=array_map(fn($m)=>(int)$m['catalog_hotel_id'],$manifest);
    $ph=implode(',',array_fill(0,count($ids),'?'));$tph=implode(',',array_fill(0,count($targets),'?'));
    $commitAttempted=false;$committed=false;
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    try{
        $db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();
        $catalog=[];foreach(v61_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($tph) ORDER BY id FOR UPDATE",$targets) as$r)$catalog[(int)$r['id']]=$r;
        $dec=[];foreach(v61_query($db,"SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id FOR UPDATE",$ids) as$r)$dec[(string)$r['anex_hotel_id']]=$r;
        $maps=[];foreach(v61_query($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id FOR UPDATE",$ids) as$r)$maps[(string)$r['anex_hotel_id']]=$r;
        $ex=[];try{foreach(v61_query($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE",$ids) as$r)$ex[(string)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;}catch(Throwable){}
        $anchors=[];foreach(v61_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IN ($tph) ORDER BY local_hotel_id,external_hotel_id FOR UPDATE",$targets) as$r)$anchors[(int)$r['local_hotel_id']][]=$r;

        $coverage=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$planned=[];
        foreach($manifest as$m){
            $id=(string)$m['anex_hotel_id'];$tv=(int)$m['catalog_hotel_id'];
            v61_need(!isset($dec[$id]),'manual_source_protected_'.$id);
            v61_need(!isset($maps[$id]),'source_mapping_present_'.$id);
            v61_need(!isset($ex[$id][$tv]),'pair_excluded_'.$id);
            $h=$catalog[$tv]??null;v61_need(is_array($h)&&(int)($h['is_active']??0)===1&&!v61_excluded((string)($h['country_name']??'')),'target_guard_'.$tv);
            v61_need(v61_target($h)===v61_target($m['target']),'target_facts_drift_'.$tv);
            $a=v61_anchor_state($anchors[$tv]??[],$tv);
            v61_need($a['catalog_sha256']===$m['unanimous_catalog_sha256']&&$a['anchors']===$m['anchors'],'anchor_projection_drift_'.$tv);
            v61_need(($coverage['by_native'][(int)$id]??null)===null,'source_effective_now_'.$id);
            $targetNatives=array_keys($coverage['by_local'][$tv]??[]);foreach($targetNatives as$n)v61_need((string)$n===$id,'target_effective_other_'.$tv);
            $ev=v61_evidence($m,$a['anchors'],$sourceSha,$auditSha);$sd=hash('sha256',v61_json($ev));
            $planned[]=$m+['source_row_digest'=>$sd,'evidence'=>$ev];
        }
        $mappingDigest=hash('sha256',v61_json(array_map(fn($p)=>['anex_hotel_id'=>$p['anex_hotel_id'],'catalog_hotel_id'=>$p['catalog_hotel_id'],'source_row_digest'=>$p['source_row_digest']],$planned)));
        v61_save($opDir.'/pre-commit.json',['operation'=>V61_OP,'state'=>'verified_before_commit','planned_writes'=>V61_EXPECTED,'mapping_digest'=>$mappingDigest,'audit_result_sha256'=>$auditSha]);
        $st=$db->prepare("INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES (?,?,'".V61_CLASS."','preview','".V61_POLICY."',?,?,1)");
        foreach($planned as$p){$st->execute([$p['anex_hotel_id'],$p['catalog_hotel_id'],$p['source_row_digest'],$mappingDigest]);v61_need($st->rowCount()===1,'insert_count');}
        $staged=AnyTourAnexSearchMappingRegistry::fromPdo($db);foreach($planned as$p)v61_need($staged->resolve('anex_online',$p['anex_hotel_id'],'preview')===(int)$p['catalog_hotel_id'],'staged_registry_readback');
        v61_save($opDir.'/commit-attempt.json',['operation'=>V61_OP,'state'=>'commit_attempt_no_replay','planned_writes'=>V61_EXPECTED,'mapping_digest'=>$mappingDigest]);
        $commitAttempted=true;v61_need($db->commit(),'commit_false');$committed=true;

        $post=v61_query($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id",$ids);
        v61_need(count($post)===V61_EXPECTED,'post_count');$by=[];foreach($post as$r)$by[(string)$r['anex_hotel_id']]=$r;
        $postReg=AnyTourAnexSearchMappingRegistry::fromPdo($db);
        foreach($planned as$p){$r=$by[$p['anex_hotel_id']]??null;v61_need(is_array($r)&&(int)$r['catalog_hotel_id']===(int)$p['catalog_hotel_id']&&$r['match_class']===V61_CLASS&&$r['approval_policy']===V61_POLICY&&$r['scope']==='preview'&&(int)$r['enabled']===1&&$r['source_row_digest']===$p['source_row_digest']&&$r['mapping_digest']===$mappingDigest,'post_row_mismatch');v61_need($postReg->resolve('anex_online',$p['anex_hotel_id'],'preview')===(int)$p['catalog_hotel_id'],'post_registry_mismatch');}
        return['state'=>'committed_verified','inserted'=>V61_EXPECTED,'database_writes'=>V61_EXPECTED,'mapping_writes'=>V61_EXPECTED,'mapping_digest'=>$mappingDigest,'readback_verified'=>true,'registry_readback_verified'=>true,'provider_http_calls'=>0,'rows'=>array_map(fn($p)=>['anex_hotel_id'=>$p['anex_hotel_id'],'catalog_hotel_id'=>$p['catalog_hotel_id'],'source_row_digest'=>$p['source_row_digest']],$planned)];
    }catch(Throwable $e){
        try{if($db->inTransaction())$db->rollBack();}catch(Throwable){}
        $state=$committed?'post_commit_verification_failed_no_replay':($commitAttempted?'commit_unknown_no_replay':'rolled_back_no_write');
        throw new RuntimeException($state.':'.preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,120,'UTF-8')));
    }
}
function v61_self_test():void{
    $a=['operation'=>V61_AUDIT_OP,'state'=>'completed_read_only_v59_current_audit','input_count'=>2,'writer_ready_count'=>2,'writer_ready_target_digest'=>V61_READY_DIGEST,'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,'rows'=>[]];
    for($i=0;$i<2;$i++){$tv=100+$i;$id=(string)(5000+$i);$anchor=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)(900+$i),'local_hotel_id'=>$tv,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>str_repeat('b',64)];$a['rows'][]=['writer_ready'=>true,'status'=>'current_missing_exact_key','anchor_state'=>'canonical_anchor_ok','anex_hotel_id'=>$id,'tv_hotel_id'=>$tv,'operator_id'=>13,'namespace'=>'anex','source_result_sha256'=>str_repeat('c',64),'search_id_sha256'=>str_repeat('d',64),'tour_id_sha256'=>str_repeat('e',64),'operator_link_sha256'=>str_repeat('f',64),'catalog_hotel'=>['id'=>$tv,'name'=>'H','country_id'=>4,'country_name'=>'Turkey','region_id'=>1,'region_name'=>'R','subregion_id'=>null,'subregion_name'=>'','category'=>'5','is_active'=>1],'anchors'=>[$anchor],'unanimous_catalog_sha256'=>str_repeat('a',64)];}
    v61_need(count(v61_manifest($a))===2,'manifest');
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){v61_self_test();echo"MATCH_V61_SELFTEST_OK\n";exit;}
    v61_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$audit=(string)getenv('MATCH_AUDIT_RESULT');$auditSha=(string)getenv('MATCH_AUDIT_SHA');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
    v61_need(is_dir($root)&&is_dir($dir)&&basename($dir)===V61_OP&&is_file($audit)&&v61_sha($auditSha)&&hash_file('sha256',$audit)===$auditSha&&preg_match('/^[0-9a-f]{40}$/D',$sourceSha)===1,'runtime_scope');
    $res=v61_load($dir.'/reservation.json');v61_need(($res['operation']??'')===V61_OP&&($res['state']??'')==='reserved_before_write','reservation');
    try{$manifest=v61_manifest(v61_load($audit));require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$r=v61_write(v2_data_db(),$manifest,$sourceSha,$auditSha,$dir);$h=v61_save($dir.'/result.json',['operation'=>V61_OP]+$r);v61_save($dir.'/receipt.json',['operation'=>V61_OP,'state'=>$r['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>$r['database_writes'],'mapping_writes'=>$r['mapping_writes'],'no_replay'=>true]);echo v61_json(['state'=>$r['state'],'inserted'=>$r['inserted'],'mapping_digest'=>$r['mapping_digest'],'registry_readback_verified'=>$r['registry_readback_verified']])."\n";}
    catch(Throwable$e){$msg=$e->getMessage();$state=str_starts_with($msg,'commit_unknown_no_replay:')?'commit_unknown_no_replay':(str_starts_with($msg,'post_commit_verification_failed_no_replay:')?'post_commit_verification_failed_no_replay':'rolled_back_no_write');$writes=$state==='rolled_back_no_write'?0:null;$f=['operation'=>V61_OP,'state'=>$state,'reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($msg,0,160,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>$writes,'mapping_writes'=>$writes,'no_replay'=>$state!=='rolled_back_no_write'];$h=v61_save($dir.'/result.json',$f);v61_save($dir.'/receipt.json',['operation'=>V61_OP,'state'=>$state,'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>$writes,'mapping_writes'=>$writes,'no_replay'=>$f['no_replay']]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
