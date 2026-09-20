<?php
declare(strict_types=1);

const ILM_OP='hotel-match-ilkay-operator5-anex-materialize-1971-20260920-v1';
const ILM_LOCAL=17483;
const ILM_SAMO='4176';
const ILM_ANEX=32572;
const ILM_EXISTING_ALIAS=28711;
const ILM_EVIDENCE='8b50ba11fd1907d0b43f2152e4fc10d8eb0e555d556e74a97f8065c1501f10dd';
const ILM_REVIEW_SHA='0ccc10258c41496dff9d466354a32696dad9bee4c3a68a863c589124736da61e';
const ILM_REGISTRY_BLOB='cc135a95d2a6e0f73ce50be2141c9b8a26fddc58';
const ILM_POLICY='owner_exact_operator_key_20260912_v2';
const ILM_CLASS='exact_operator_key';

function ilm_req(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function ilm_json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function ilm_once(string $path,array $v):string{
    $raw=ilm_json($v);$f=@fopen($path,'xb');ilm_req(is_resource($f),'immutable_output_exists');
    try{ilm_req(fwrite($f,$raw)===strlen($raw),'durable_write');ilm_req(fflush($f),'durable_flush');if(function_exists('fsync'))ilm_req(fsync($f),'durable_sync');}finally{fclose($f);}
    ilm_req(file_get_contents($path)===$raw,'durable_readback');return hash('sha256',$raw);
}
function ilm_rows(PDO $db,string $sql,array $args=[]):array{$s=$db->prepare($sql);$s->execute(array_values($args));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function ilm_norm(string $s):string{$s=trim($s);if(function_exists('mb_strtolower'))$s=mb_strtolower($s,'UTF-8');else $s=strtolower($s);$s=preg_replace('/[^\p{L}\p{N}]+/u',' ',$s)??$s;return trim(preg_replace('/\s+/u',' ',$s)??$s);}
function ilm_blob_sha1(string $raw):string{return sha1('blob '.strlen($raw)."\0".$raw);}
function ilm_authority(string $path):array{
    ilm_req(is_file($path),'review_missing');ilm_req(hash_file('sha256',$path)===ILM_REVIEW_SHA,'review_digest');
    $r=json_decode((string)file_get_contents($path),true,128,JSON_THROW_ON_ERROR);
    ilm_req(($r['operation_id']??'')==='hotel-match-ilkay-operator5-anex-current-review-1971-20260920-v1','review_operation');
    ilm_req(($r['state']??'')==='completed_read_only'&&($r['safe_to_write_now']??false)===true&&($r['classification']??'')==='safe_materialization_candidate','review_state');
    $c=$r['candidate']??[];ilm_req((int)($c['local_hotel_id']??0)===ILM_LOCAL&&(string)($c['samo_hotel_id']??'')===ILM_SAMO&&(int)($c['native_anex_id']??0)===ILM_ANEX&&(string)($c['operator5_evidence_sha256']??'')===ILM_EVIDENCE,'review_candidate');
    ilm_req((int)($r['canonical_samo_match_count']??0)===1&&(int)($r['operator5_exact_match_count']??0)===1&&(int)($r['operator5_competitor_count']??-1)===0,'review_identity_counts');
    ilm_req(($r['pair_excluded']??true)===false&&($r['accepted_anex_same_pair']??true)===false&&($r['accepted_anex_source_conflict']??true)===false&&($r['accepted_anex_target_occupants']??null)===[],'review_guards');
    $maps=$r['current_maps']??[];$alias=array_values(array_filter($maps,fn($m)=>(int)($m['anex_hotel_id']??0)===ILM_EXISTING_ALIAS&&(int)($m['catalog_hotel_id']??0)===ILM_LOCAL&&(int)($m['enabled']??0)===1&&(string)($m['scope']??'')==='preview'));
    ilm_req(count($alias)===1,'review_existing_alias');return $r;
}
function ilm_current(PDO $db,bool $lock):array{
    $tail=$lock?' FOR UPDATE':'';
    return [
      'catalog'=>ilm_rows($db,'SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id=?'.$tail,[ILM_LOCAL]),
      'andromeda'=>ilm_rows($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE (supplier_namespace='andromeda_catalog' AND (external_hotel_id=? OR local_hotel_id=?)) OR (supplier_namespace='operator_5' AND (external_hotel_id=? OR local_hotel_id=?))".$tail,[ILM_SAMO,ILM_LOCAL,(string)ILM_ANEX,ILM_LOCAL]),
      'maps'=>ilm_rows($db,'SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=? OR catalog_hotel_id=? ORDER BY anex_hotel_id'.$tail,[ILM_ANEX,ILM_LOCAL]),
      'decisions'=>ilm_rows($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id=? OR catalog_hotel_id=? ORDER BY anex_hotel_id'.$tail,[ILM_ANEX,ILM_LOCAL]),
      'exclusions'=>ilm_rows($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id=? OR catalog_hotel_id=? ORDER BY anex_hotel_id,catalog_hotel_id'.$tail,[ILM_ANEX,ILM_LOCAL]),
    ];
}
function ilm_guard(array $s,AnyTourAnexSearchMappingRegistry $reg):array{
    $holds=[];$catalog=$s['catalog'];if(count($catalog)!==1)$holds[]='catalog_cardinality';else{
      $h=$catalog[0];if((int)$h['id']!==ILM_LOCAL||(int)$h['is_active']!==1)$holds[]='catalog_inactive';if((int)$h['country_id']!==4)$holds[]='catalog_country';if(ilm_norm((string)$h['name'])!=='ilkay hotel')$holds[]='catalog_name_drift';
    }
    $canon=array_values(array_filter($s['andromeda'],fn($r)=>(string)$r['supplier_namespace']==='andromeda_catalog'&&(string)$r['external_hotel_id']===ILM_SAMO&&(int)$r['local_hotel_id']===ILM_LOCAL&&(string)$r['decision_status']==='accepted'));
    $op5=array_values(array_filter($s['andromeda'],fn($r)=>(string)$r['supplier_namespace']==='operator_5'&&(string)$r['external_hotel_id']===(string)ILM_ANEX&&(int)$r['local_hotel_id']===ILM_LOCAL&&(string)$r['decision_status']==='accepted'&&(string)$r['evidence_sha256']===ILM_EVIDENCE));
    $op5Comp=array_values(array_filter($s['andromeda'],fn($r)=>(string)$r['supplier_namespace']==='operator_5'&&(string)$r['external_hotel_id']===(string)ILM_ANEX&&(int)$r['local_hotel_id']!==ILM_LOCAL&&in_array((string)$r['decision_status'],['accepted','pending','conflict'],true)));
    if(count($canon)!==1)$holds[]='canonical_samo_drift';if(count($op5)!==1)$holds[]='operator5_evidence_drift';if($op5Comp)$holds[]='operator5_competition';
    $srcMaps=array_values(array_filter($s['maps'],fn($r)=>(int)$r['anex_hotel_id']===ILM_ANEX));if($srcMaps)$holds[]='source_mapping_exists';
    $srcDec=array_values(array_filter($s['decisions'],fn($r)=>(int)$r['anex_hotel_id']===ILM_ANEX));if($srcDec)$holds[]='source_manual_decision';
    $pairEx=array_values(array_filter($s['exclusions'],fn($r)=>(int)$r['anex_hotel_id']===ILM_ANEX&&(int)$r['catalog_hotel_id']===ILM_LOCAL));if($pairEx)$holds[]='pair_excluded';
    if($reg->resolve('anex_online',(string)ILM_ANEX,'preview')!==null)$holds[]='source_already_effective';
    if($reg->resolve('anex_online',(string)ILM_EXISTING_ALIAS,'preview')!==ILM_LOCAL)$holds[]='existing_alias_drift';
    return ['holds'=>array_values(array_unique($holds)),'canonical_count'=>count($canon),'operator5_count'=>count($op5),'operator5_competitors'=>count($op5Comp),'related_maps'=>$s['maps'],'related_decisions'=>$s['decisions'],'related_exclusions'=>$s['exclusions']];
}
function ilm_selftest():void{
    ilm_req(ILM_LOCAL===17483&&ILM_ANEX===32572&&ILM_EXISTING_ALIAS===28711,'constants');
    ilm_req(ILM_POLICY==='owner_exact_operator_key_20260912_v2'&&ILM_CLASS==='exact_operator_key','policy');
    echo "ILKAY_OPERATOR5_ANEX_MATERIALIZE_SELFTEST_OK\n";
}
if(($argv[1]??'')==='--self-test'){ilm_selftest();exit(0);}
ilm_req(PHP_SAPI==='cli'&&(string)getenv('MATCH_OPERATION_ID')===ILM_OP,'operation');
$source=(string)getenv('MATCH_SOURCE_SHA');ilm_req(preg_match('/^[0-9a-f]{40}$/D',$source)===1,'source_sha');
$dir=realpath((string)getenv('MATCH_OPERATION_DIR'));$root=realpath((string)getenv('ANYTOUR_ROOT'));ilm_req(is_string($dir)&&is_string($root)&&basename($root)==='anytoour.ru','runtime_paths');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,64,JSON_THROW_ON_ERROR);ilm_req(($res['operation_id']??'')===ILM_OP&&($res['state']??'')==='reserved_before_db_write'&&($res['source_sha']??'')===$source&&($res['review_result_sha256']??'')===ILM_REVIEW_SHA&&(int)($res['max_mapping_writes']??0)===1,'reservation');
$review=ilm_authority($dir.'/payload/review-result.json');
$registryPath=$dir.'/payload/anex-search-mapping-registry.php';$registryRaw=(string)file_get_contents($registryPath);ilm_req(ilm_blob_sha1($registryRaw)===ILM_REGISTRY_BLOB,'registry_blob');require_once $registryPath;
require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$base=['operation_id'=>ILM_OP,'source_sha'=>$source,'review_result_sha256'=>ILM_REVIEW_SHA,'operator5_evidence_sha256'=>ILM_EVIDENCE,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'andromeda_calls'=>0,'direct_anex_calls'=>0,'no_replay'=>true];
$committed=false;$commitStarted=false;$written=[];$post=[];
try{
    $db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();
    $snapshot=ilm_current($db,true);$registryBefore=AnyTourAnexSearchMappingRegistry::fromPdo($db);$guard=ilm_guard($snapshot,$registryBefore);ilm_req($guard['holds']===[],'current_guard_hold');
    $clock=ilm_rows($db,'SELECT UTC_TIMESTAMP AS db_utc_timestamp')[0]['db_utc_timestamp'];
    $mappingDigest=hash('sha256',ilm_json(['operation_id'=>ILM_OP,'review_result_sha256'=>ILM_REVIEW_SHA,'operator5_evidence_sha256'=>ILM_EVIDENCE,'match_class'=>ILM_CLASS,'approval_policy'=>ILM_POLICY,'scope'=>'preview']));
    $sourceDigest=hash('sha256',ilm_json(['review'=>$review,'current_guard'=>$guard,'checked_at_utc'=>$clock]));
    ilm_once($dir.'/precommit-plan.json',['operation_id'=>ILM_OP,'checked_at_utc'=>$clock,'candidate'=>['anex_hotel_id'=>ILM_ANEX,'catalog_hotel_id'=>ILM_LOCAL,'samo_hotel_id'=>ILM_SAMO],'match_class'=>ILM_CLASS,'approval_policy'=>ILM_POLICY,'source_row_digest'=>$sourceDigest,'mapping_digest'=>$mappingDigest,'current_guard'=>$guard,'max_mapping_writes'=>1,'no_replay'=>true]);
    $ins=$db->prepare('INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES (?,?,?,?,?,?,?,1)');
    $ins->execute([ILM_ANEX,ILM_LOCAL,ILM_CLASS,'preview',ILM_POLICY,$sourceDigest,$mappingDigest]);ilm_req($ins->rowCount()===1,'insert_count');
    $written=['anex_hotel_id'=>ILM_ANEX,'catalog_hotel_id'=>ILM_LOCAL,'match_class'=>ILM_CLASS,'scope'=>'preview','approval_policy'=>ILM_POLICY,'source_row_digest'=>$sourceDigest,'mapping_digest'=>$mappingDigest];
    ilm_once($dir.'/precommit-written.json',['operation_id'=>ILM_OP,'written_uncommitted'=>[$written],'no_replay'=>true]);
    $commitStarted=true;$db->commit();$committed=true;ilm_once($dir.'/commit-returned.json',['operation_id'=>ILM_OP,'committed_count'=>1,'no_replay'=>true]);
    $row=ilm_rows($db,'SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=?',[ILM_ANEX]);ilm_req(count($row)===1,'postcommit_row_count');$post=$row[0];
    foreach($written as $k=>$v)ilm_req((string)$post[$k]===(string)$v,'postcommit_contract');ilm_req((int)$post['enabled']===1,'postcommit_enabled');
    $registryAfter=AnyTourAnexSearchMappingRegistry::fromPdo($db);ilm_req($registryAfter->resolve('anex_online',(string)ILM_ANEX,'preview')===ILM_LOCAL,'postcommit_effective_mapping');ilm_req($registryAfter->resolve('anex_online',(string)ILM_EXISTING_ALIAS,'preview')===ILM_LOCAL,'postcommit_existing_alias_preserved');
    $canon=ilm_rows($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND local_hotel_id=? AND decision_status='accepted'",[ILM_SAMO,ILM_LOCAL]);ilm_req(count($canon)===1,'postcommit_canonical_samo');
    $result=$base+['state'=>'committed_verified','checked_at_utc'=>$clock,'written_count'=>1,'database_writes'=>1,'mapping_writes'=>1,'triple_increment'=>1,'written'=>[$written],'post_commit_readback'=>[$post],'effective_resolver'=>['anex_32572'=>ILM_LOCAL,'existing_alias_28711'=>ILM_LOCAL],'current_guard'=>$guard];
}catch(Throwable $e){
    $rollback=false;try{if($db->inTransaction()){$db->rollBack();$rollback=true;}}catch(Throwable $ignored){}
    $state=$committed?'committed_readback_failed':($commitStarted?'commit_outcome_unknown':'blocked_no_commit');
    $msg=$e->getMessage();$reason=preg_match('/^[a-z0-9_]+$/D',$msg)?$msg:'database_or_runtime_error';
    $result=$base+['state'=>$state,'reason'=>$reason,'error_class'=>get_class($e),'commit_started'=>$commitStarted,'commit_returned'=>$committed,'rollback_returned'=>$rollback,'database_writes'=>$committed?1:($commitStarted?null:0),'mapping_writes'=>$committed?1:($commitStarted?null:0),'attempted_rows'=>$written?[$written]:[],'post_commit_readback'=>$post?[$post]:[]];
}
$sha=ilm_once($dir.'/result.json',$result);ilm_once($dir.'/receipt.json',['operation_id'=>ILM_OP,'source_sha'=>$source,'state'=>$result['state'],'result_sha256'=>$sha,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$sha,'mapping_writes'=>$result['mapping_writes'],'no_replay'=>true]);
echo ilm_json(['state'=>$result['state'],'mapping_writes'=>$result['mapping_writes'],'result_sha256'=>$sha]);exit($result['state']==='committed_verified'?0:2);
