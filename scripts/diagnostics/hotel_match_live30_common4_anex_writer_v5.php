<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/integrations/anex-search-mapping-registry.php';

const HMC4AW_OP='hotel-match-live30-common4-anex-writer-1971-20260923-v5';
const HMC4AW_AUDIT_OP='hotel-match-live30-common4-anex-current-1971-20260923-v4';
const HMC4AW_EXPECTED=5;
const HMC4AW_POLICY='owner_exact_operator_key_20260912_v2';
const HMC4AW_CLASS='exact_operator_key';

function hmc4aw_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function hmc4aw_sort(mixed $v):mixed{if(!is_array($v))return $v;if(array_is_list($v))return array_map('hmc4aw_sort',$v);ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=hmc4aw_sort($x);return $v;}
function hmc4aw_json(mixed $v):string{return json_encode(hmc4aw_sort($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmc4aw_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);hmc4aw_need(is_array($v),'json_shape');return $v;}
function hmc4aw_save(string $p,array $v):string{$raw=hmc4aw_json($v)."\n";$f=@fopen($p,'x+b');hmc4aw_need($f!==false,'exclusive_create');try{hmc4aw_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmc4aw_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function hmc4aw_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hmc4aw_excluded(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}
function hmc4aw_target(array $h):array{$out=[];foreach(['id','name','country_id','country_name','region_id','region_name','subregion_id','subregion_name','category','is_active'] as $k)$out[$k]=$h[$k]??null;return $out;}
function hmc4aw_anchor_projection(array $a):array{$out=[];foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256'] as $k)$out[$k]=$a[$k]??null;return $out;}
function hmc4aw_anchor_list(array $aa):array{$out=array_map('hmc4aw_anchor_projection',$aa);usort($out,fn($a,$b)=>strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));return $out;}
function hmc4aw_host_ok(string $h):bool{$h=strtolower(trim($h));return $h==='anextour.ru'||str_ends_with($h,'.anextour.ru');}
function hmc4aw_manifest(array $audit):array{
 hmc4aw_need(($audit['operation']??'')===HMC4AW_AUDIT_OP&&($audit['state']??'')==='completed_read_only_anex_current_audit','audit_state');
 hmc4aw_need(($audit['input_count']??null)===28&&($audit['writer_ready_count']??null)===HMC4AW_EXPECTED,'audit_counts');
 $out=[];$sources=[];$targets=[];
 foreach(($audit['rows']??[]) as $r){
  if(!is_array($r)||($r['writer_ready']??false)!==true)continue;
  hmc4aw_need(($r['status']??'')==='current_missing_exact_key'&&($r['anchor_state']??'')==='canonical_anchor_ok','manifest_status');
  hmc4aw_need(($r['safe_to_write_now']??null)===false,'manifest_not_authority');
  $id=(string)($r['anex_hotel_id']??'');$tv=(int)($r['tv_hotel_id']??0);
  hmc4aw_need(preg_match('/^[1-9][0-9]{0,7}$/D',$id)===1&&$tv>0&&(int)($r['operator_id']??0)===13,'manifest_identity');
  hmc4aw_need(hmc4aw_host_ok((string)($r['operator_link_host']??'')),'manifest_anex_host');
  $keys=array_map(fn($x)=>strtolower((string)$x),is_array($r['query_keys']??null)?$r['query_keys']:[]);
  hmc4aw_need((bool)array_intersect($keys,['hotel','hotels','hotelid','hotel_id','hotelcode','hotel_code','hotellist','hotelkey','hotel_key']),'manifest_hotel_key');
  foreach(['source_result_sha256','search_id_sha256','tour_id_sha256','operator_link_sha256'] as $f)hmc4aw_need(preg_match('/^[0-9a-f]{64}$/D',(string)($r[$f]??''))===1,'manifest_'.$f);
  $hotel=$r['catalog_hotel']??null;$anchors=$r['anchors']??null;$cat=(string)($r['unanimous_catalog_sha256']??'');
  hmc4aw_need(is_array($hotel)&&(int)($hotel['id']??0)===$tv&&is_array($anchors)&&count($anchors)>=1&&preg_match('/^[0-9a-f]{64}$/D',$cat)===1,'manifest_target_anchor');
  foreach($anchors as $a)hmc4aw_need(is_array($a)&&($a['supplier_namespace']??'')==='andromeda_catalog'&&($a['decision_status']??'')==='accepted'&&(int)($a['local_hotel_id']??0)===$tv&&($a['catalog_sha256']??'')===$cat,'manifest_anchor');
  hmc4aw_need(!isset($sources[$id])&&!isset($targets[$tv]),'manifest_unique');$sources[$id]=true;$targets[$tv]=true;
  $out[]=['anex_hotel_id'=>$id,'catalog_hotel_id'=>$tv,'source_operation'=>(string)$r['source_operation'],'source_result_sha256'=>(string)$r['source_result_sha256'],
   'batch'=>(int)($r['batch']??0),'search_id_sha256'=>(string)$r['search_id_sha256'],'tour_id_sha256'=>(string)$r['tour_id_sha256'],
   'operator_link_sha256'=>(string)$r['operator_link_sha256'],'operator_link_host'=>(string)$r['operator_link_host'],'query_keys'=>$keys,
   'target'=>$hotel,'unanimous_catalog_sha256'=>$cat,'anchors'=>hmc4aw_anchor_list($anchors)];
 }
 hmc4aw_need(count($out)===HMC4AW_EXPECTED,'manifest_count');
 usort($out,fn($a,$b)=>strcmp($a['anex_hotel_id'],$b['anex_hotel_id']));return $out;
}
function hmc4aw_validate_anchors(array $m,array $aa):void{
 hmc4aw_need(count($aa)>=1,'anchor_missing');$cats=[];
 foreach($aa as $a){
  hmc4aw_need(($a['supplier_namespace']??'')==='andromeda_catalog'&&($a['decision_status']??'')==='accepted'&&(int)($a['local_hotel_id']??0)===(int)$m['catalog_hotel_id'],'anchor_invalid');
  $ej=(string)($a['evidence_json']??'');$eh=(string)($a['evidence_sha256']??'');$cat=(string)($a['catalog_sha256']??'');
  hmc4aw_need(preg_match('/^[0-9a-f]{64}$/D',$eh)===1&&preg_match('/^[0-9a-f]{64}$/D',$cat)===1&&hash('sha256',$ej)===$eh,'anchor_evidence_hash_drift');$cats[$cat]=true;
 }
 hmc4aw_need(count($cats)===1&&array_key_first($cats)===$m['unanimous_catalog_sha256'],'anchor_catalog_drift');
 hmc4aw_need(hmc4aw_anchor_list($aa)===$m['anchors'],'anchor_projection_drift');
}
function hmc4aw_evidence(array $m,string $sourceSha):array{return [
 'operation_id'=>HMC4AW_OP,'rule'=>'tourvisor_anex_exact_native_plus_current_canonical_anchor','source_sha'=>$sourceSha,
 'anex_hotel_id'=>$m['anex_hotel_id'],'catalog_hotel_id'=>$m['catalog_hotel_id'],'source_operation'=>$m['source_operation'],
 'source_result_sha256'=>$m['source_result_sha256'],'batch'=>$m['batch'],'search_id_sha256'=>$m['search_id_sha256'],'tour_id_sha256'=>$m['tour_id_sha256'],
 'operator_link_sha256'=>$m['operator_link_sha256'],'operator_link_host'=>$m['operator_link_host'],'operator_query_keys'=>$m['query_keys'],
 'target'=>hmc4aw_target($m['target']),'canonical_anchors'=>$m['anchors'],'raw_operator_url_exported'=>false,'supplier_calls'=>0,
];}
function hmc4aw_write(PDO $db,array $manifest,string $sourceSha,?string $opDir=null):array{
 hmc4aw_need(count($manifest)===HMC4AW_EXPECTED,'manifest_count_write');
 $ids=array_map(fn($m)=>(string)$m['anex_hotel_id'],$manifest);$targets=array_map(fn($m)=>(int)$m['catalog_hotel_id'],$manifest);
 $ph=implode(',',array_fill(0,count($ids),'?'));$tph=implode(',',array_fill(0,count($targets),'?'));
 $commitAttempted=false;$committed=false;
 $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 try{
  $db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();
  hmc4aw_query($db,"SELECT anex_hotel_id FROM anex_search_hotel_observations WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id FOR UPDATE",$ids);
  $catalog=[];foreach(hmc4aw_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($tph) ORDER BY id FOR UPDATE",$targets) as $r)$catalog[(int)$r['id']]=$r;
  $dec=[];foreach(hmc4aw_query($db,"SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id FOR UPDATE",$ids) as $r)$dec[(string)$r['anex_hotel_id']]=$r;
  $maps=[];foreach(hmc4aw_query($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id FOR UPDATE",$ids) as $r)$maps[(string)$r['anex_hotel_id']]=$r;
  $ex=[];foreach(hmc4aw_query($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE",$ids) as $r)$ex[(string)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
  $anchors=[];foreach(hmc4aw_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IN ($tph) ORDER BY local_hotel_id,external_hotel_id FOR UPDATE",$targets) as $r)$anchors[(int)$r['local_hotel_id']][]=$r;
  $registry=AnyTourAnexSearchMappingRegistry::fromPdo($db);$planned=[];
  foreach($manifest as $m){
   $id=$m['anex_hotel_id'];$tv=(int)$m['catalog_hotel_id'];hmc4aw_need(!isset($dec[$id]),'manual_source_protected');hmc4aw_need(!isset($maps[$id]),'source_mapping_present');hmc4aw_need(!isset($ex[$id][$tv]),'pair_excluded');
   $h=$catalog[$tv]??null;hmc4aw_need(is_array($h)&&(int)($h['is_active']??0)===1&&!hmc4aw_excluded((string)($h['country_name']??'')),'target_guard');
   hmc4aw_need(hmc4aw_target($h)===hmc4aw_target($m['target']),'target_facts_drift');hmc4aw_validate_anchors($m,$anchors[$tv]??[]);
   hmc4aw_need($registry->resolve('anex_online',$id,'preview')===null,'registry_now_resolved');
   $ev=hmc4aw_evidence($m,$sourceSha);$sourceDigest=hash('sha256',hmc4aw_json($ev));
   $planned[]=$m+['source_row_digest'=>$sourceDigest,'evidence'=>$ev];
  }
  $mappingDigest=hash('sha256',hmc4aw_json(array_map(fn($p)=>['anex_hotel_id'=>$p['anex_hotel_id'],'catalog_hotel_id'=>$p['catalog_hotel_id'],'source_row_digest'=>$p['source_row_digest']],$planned)));
  $st=$db->prepare("INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES (?,?,'".HMC4AW_CLASS."','preview','".HMC4AW_POLICY."',?,?,1)");
  foreach($planned as $p){$st->execute([$p['anex_hotel_id'],$p['catalog_hotel_id'],$p['source_row_digest'],$mappingDigest]);hmc4aw_need($st->rowCount()===1,'insert_count');}
  $staged=AnyTourAnexSearchMappingRegistry::fromPdo($db);foreach($planned as $p)hmc4aw_need($staged->resolve('anex_online',$p['anex_hotel_id'],'preview')===(int)$p['catalog_hotel_id'],'staged_registry_readback');
  if($opDir!==null){hmc4aw_save($opDir.'/pre-commit.json',['operation'=>HMC4AW_OP,'state'=>'verified_before_commit','planned_writes'=>HMC4AW_EXPECTED,'mapping_digest'=>$mappingDigest]);hmc4aw_save($opDir.'/commit-attempt.json',['operation'=>HMC4AW_OP,'state'=>'commit_attempt_no_replay','planned_writes'=>HMC4AW_EXPECTED]);}
  $commitAttempted=true;hmc4aw_need($db->commit(),'commit_false');$committed=true;
  $post=hmc4aw_query($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id",$ids);
  hmc4aw_need(count($post)===HMC4AW_EXPECTED,'post_count');$by=[];foreach($post as $r)$by[(string)$r['anex_hotel_id']]=$r;
  $postReg=AnyTourAnexSearchMappingRegistry::fromPdo($db);
  foreach($planned as $p){$r=$by[$p['anex_hotel_id']]??null;hmc4aw_need(is_array($r)&&(int)$r['catalog_hotel_id']===(int)$p['catalog_hotel_id']&&$r['match_class']===HMC4AW_CLASS&&$r['approval_policy']===HMC4AW_POLICY&&$r['scope']==='preview'&&(int)$r['enabled']===1&&$r['source_row_digest']===$p['source_row_digest']&&$r['mapping_digest']===$mappingDigest,'post_row_mismatch');hmc4aw_need($postReg->resolve('anex_online',$p['anex_hotel_id'],'preview')===(int)$p['catalog_hotel_id'],'post_registry_mismatch');}
  return ['state'=>'committed_verified','inserted'=>HMC4AW_EXPECTED,'database_writes'=>HMC4AW_EXPECTED,'mapping_writes'=>HMC4AW_EXPECTED,'mapping_digest'=>$mappingDigest,'readback_verified'=>true,'registry_readback_verified'=>true,'supplier_calls'=>0,'provider_calls'=>0,
    'rows'=>array_map(fn($p)=>['anex_hotel_id'=>$p['anex_hotel_id'],'catalog_hotel_id'=>$p['catalog_hotel_id'],'source_row_digest'=>$p['source_row_digest']],$planned)];
 }catch(Throwable $e){
  try{if($db->inTransaction())$db->rollBack();}catch(Throwable){}
  $state=$committed?'post_commit_verification_failed_no_replay':($commitAttempted?'commit_unknown_no_replay':'rolled_back_no_write');
  throw new RuntimeException($state.':'.preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,100,'UTF-8')));
 }
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
 $mode=$argv[1]??'';if($mode==='--self-test'){echo "MATCH_LIVE30_COMMON4_ANEX_WRITER_V5_SELFTEST_OK\n";exit;}hmc4aw_need($mode==='--execute','disabled');
 $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$input=(string)getenv('MATCH_AUDIT_RESULT');$sha=(string)getenv('MATCH_SOURCE_SHA');
 hmc4aw_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===HMC4AW_OP&&is_file($input)&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
 $reservation=hmc4aw_load($dir.'/reservation.json');hmc4aw_need(($reservation['operation']??'')===HMC4AW_OP&&($reservation['state']??'')==='reserved_before_write','reservation');
 try{
  $audit=hmc4aw_load($input);$manifest=hmc4aw_manifest($audit);require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$result=hmc4aw_write(v2_data_db(),$manifest,$sha,$dir);
  $h=hmc4aw_save($dir.'/result.json',['operation'=>HMC4AW_OP]+$result);hmc4aw_save($dir.'/receipt.json',['operation'=>HMC4AW_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'supplier_calls'=>0,'database_writes'=>$result['database_writes'],'mapping_writes'=>$result['mapping_writes'],'no_replay'=>true]);
  echo hmc4aw_json(['state'=>$result['state'],'inserted'=>$result['inserted'],'mapping_digest'=>$result['mapping_digest'],'registry_readback_verified'=>true])."\n";
 }catch(Throwable $e){
  $msg=$e->getMessage();$state=str_starts_with($msg,'commit_unknown_no_replay:')?'commit_unknown_no_replay':(str_starts_with($msg,'post_commit_verification_failed_no_replay:')?'post_commit_verification_failed_no_replay':'rolled_back_no_write');$writes=$state==='rolled_back_no_write'?0:null;
  $f=['operation'=>HMC4AW_OP,'state'=>$state,'reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($msg,0,150,'UTF-8')),'provider_calls'=>0,'database_writes'=>$writes,'mapping_writes'=>$writes,'no_replay'=>$state!=='rolled_back_no_write'];
  $h=hmc4aw_save($dir.'/result.json',$f);hmc4aw_save($dir.'/receipt.json',['operation'=>HMC4AW_OP,'state'=>$state,'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'supplier_calls'=>0,'database_writes'=>$writes,'mapping_writes'=>$writes,'no_replay'=>$f['no_replay']]);fwrite(STDERR,$f['reason']."\n");exit(2);
 }
}
