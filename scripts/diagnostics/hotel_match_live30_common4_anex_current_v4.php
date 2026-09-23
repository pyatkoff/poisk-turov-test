<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/integrations/anex-search-mapping-registry.php';

const HMC4A_OP='hotel-match-live30-common4-anex-current-1971-20260923-v4';
const HMC4A_EXPECTED=28;
const HMC4A_CHILDREN=[
 ['offset'=>0,'count'=>100,'sha'=>'1d10e02a1a541a242b7466b3eab99887203c005ee270469f2c351179a3387faa'],
 ['offset'=>100,'count'=>100,'sha'=>'e78c23bce102bfb07dd45bcef82f65c88585160747f68c0222dfd82a1f929f82'],
 ['offset'=>200,'count'=>100,'sha'=>'6fe13e366ab5fd6b130389fafd0c769b3bc80ce676b46a82f9d402f7296179f4'],
 ['offset'=>300,'count'=>80,'sha'=>'c6aa57404a3261ed0d9e82d93fba522cd326e000be26b5bf42abd319e28e9257'],
 ['offset'=>380,'count'=>40,'sha'=>'109b819ab7845c9e50242e607d275e1c4dc1c5b6e6851960596a3f3aa01748d4'],
 ['offset'=>420,'count'=>30,'sha'=>'6186f7441a2f9af365117927c6f98c1c8afd0d5c5db23a77c66304a46d965572'],
];

function hmc4a_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function hmc4a_sort(mixed $v):mixed{if(!is_array($v))return $v;if(array_is_list($v))return array_map('hmc4a_sort',$v);ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=hmc4a_sort($x);return $v;}
function hmc4a_json(mixed $v):string{return json_encode(hmc4a_sort($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmc4a_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);hmc4a_need(is_array($v),'json_shape');return $v;}
function hmc4a_save(string $p,array $v):string{$raw=hmc4a_json($v)."\n";$f=@fopen($p,'x+b');hmc4a_need($f!==false,'exclusive_create');try{hmc4a_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmc4a_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function hmc4a_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hmc4a_excluded(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}
function hmc4a_child_name(int $o,int $n):string{return 'hotel-match-live30-common4-acquire-1971-20260923-o'.$o.'-n'.$n.'-v1';}
function hmc4a_target(array $h):array{$out=[];foreach(['id','name','country_id','country_name','region_id','region_name','subregion_id','subregion_name','category','is_active'] as $k)$out[$k]=$h[$k]??null;return $out;}
function hmc4a_anchor_projection(array $a):array{$out=[];foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256'] as $k)$out[$k]=$a[$k]??null;return $out;}

function hmc4a_saved(string $root):array{
 $rows=[];$sourceTargets=[];$targetSources=[];$children=[];
 foreach(HMC4A_CHILDREN as $c){
  $o=(int)$c['offset'];$n=(int)$c['count'];$dir=$root.'/'.hmc4a_child_name($o,$n);
  hmc4a_need(is_dir($dir)&&!is_link($dir),'child_missing_'.$o);
  $rp=$dir.'/result.json';$cp=$dir.'/receipt.json';hmc4a_need(is_file($rp)&&is_file($cp)&&!is_link($rp)&&!is_link($cp),'child_terminal_'.$o);
  $raw=(string)file_get_contents($rp);$sha=hash('sha256',$raw);hmc4a_need($sha===$c['sha'],'child_hash_'.$o);
  $r=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$q=hmc4a_load($cp);
  hmc4a_need(($q['result_sha256']??'')===$sha&&($r['state']??'')==='completed_read_only'&&($q['state']??'')==='completed_read_only','child_state_'.$o);
  hmc4a_need((int)($r['frontier_count']??0)===1799&&(int)($r['scope_offset']??-1)===$o&&(int)($r['scope_count']??0)===$n,'child_scope_'.$o);
  hmc4a_need(($r['operator_ids']??null)===[13,18,25,43]&&(int)($r['database_writes']??-1)===0&&(int)($r['mapping_writes']??-1)===0,'child_authority_'.$o);
  $children[]=['operation'=>hmc4a_child_name($o,$n),'result_sha256'=>$sha,'scope_offset'=>$o,'scope_count'=>$n];
  foreach(($r['edges']??[]) as $e){
   if(!is_array($e)||($e['state']??'')!=='detail_identity_verified'||($e['namespace']??'')!=='anex'||($e['link_state']??'')!=='captured_single_native'||(int)($e['operator_id']??0)!==13)continue;
   $ids=$e['positive_native_candidates']??null;hmc4a_need(is_array($ids)&&count($ids)===1,'single_native_shape');
   $native=(string)$ids[0];$tv=(int)($e['tv_hotel_id']??0);$link=(string)($e['operator_link_sha256']??'');
   hmc4a_need(preg_match('/^[1-9][0-9]{0,19}$/D',$native)===1&&$tv>0&&preg_match('/^[0-9a-f]{64}$/D',$link)===1,'saved_identity');
   $row=['anex_hotel_id'=>$native,'tv_hotel_id'=>$tv,'operator_id'=>13,'source_operation'=>hmc4a_child_name($o,$n),
      'source_result_sha256'=>$sha,'batch'=>(int)($e['batch']??0),'search_id_sha256'=>(string)($e['search_id_sha256']??''),
      'tour_id_sha256'=>(string)($e['tour_id_sha256']??''),'operator_link_sha256'=>$link,'operator_link_host'=>(string)($e['operator_link_host']??''),
      'query_keys'=>$e['query_keys']??[],'safe_to_write_now'=>false];
   foreach(['search_id_sha256','tour_id_sha256'] as $f)hmc4a_need(preg_match('/^[0-9a-f]{64}$/D',(string)$row[$f])===1,'saved_'.$f);
   $rows[]=$row;$sourceTargets[$native][$tv]=true;$targetSources[$tv][$native]=true;
  }
 }
 hmc4a_need(count($rows)===HMC4A_EXPECTED,'anex_count');
 return ['rows'=>$rows,'source_targets'=>$sourceTargets,'target_sources'=>$targetSources,'children'=>$children];
}
function hmc4a_anchor_state(array $aa):array{
 if(!$aa)return ['state'=>'canonical_anchor_missing','catalog_sha256'=>null,'anchors'=>[]];
 $cats=[];$out=[];
 foreach($aa as $a){
  if(($a['decision_status']??'')!=='accepted'||$a['local_hotel_id']===null)return ['state'=>'canonical_anchor_invalid','catalog_sha256'=>null,'anchors'=>[]];
  $ej=(string)($a['evidence_json']??'');$eh=(string)($a['evidence_sha256']??'');$cat=(string)($a['catalog_sha256']??'');
  if(preg_match('/^[0-9a-f]{64}$/D',$eh)!==1||preg_match('/^[0-9a-f]{64}$/D',$cat)!==1||hash('sha256',$ej)!==$eh)return ['state'=>'canonical_anchor_evidence_invalid','catalog_sha256'=>null,'anchors'=>[]];
  $cats[$cat]=true;$out[]=hmc4a_anchor_projection($a);
 }
 if(count($cats)!==1)return ['state'=>'canonical_anchor_catalog_conflict','catalog_sha256'=>null,'anchors'=>$out];
 usort($out,fn($a,$b)=>strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));
 return ['state'=>'canonical_anchor_ok','catalog_sha256'=>array_key_first($cats),'anchors'=>$out];
}
function hmc4a_classify(array $e,array $catalog,array $mapping,array $decision,array $exclusions,array $anchors,array $sourceTargets,AnyTourAnexSearchMappingRegistry $registry,array $staged,array $observed):array{
 $native=(string)$e['anex_hotel_id'];$tv=(int)$e['tv_hotel_id'];$status=null;
 if(preg_match('/^[1-9][0-9]{0,7}$/D',$native)!==1)$status='registry_id_invalid';
 elseif(count($sourceTargets[$native]??[])!==1)$status='saved_source_collision';
 elseif(isset($decision[$native])){
  $resolved=$registry->resolve('anex_online',$native,'preview');$d=$decision[$native];
  $status=(($d['decision_status']??'')==='accepted'&&(int)($d['catalog_hotel_id']??0)===$tv&&$resolved===$tv)?'already_resolved_same_manual':'manual_source_protected';
 }elseif(isset($mapping[$native])){
  $resolved=$registry->resolve('anex_online',$native,'preview');
  $status=$resolved===$tv?'already_resolved_same_mapping':'source_mapping_occupied';
 }elseif(isset($exclusions[$native][$tv]))$status='pair_excluded';
 else{
  $h=$catalog[$tv]??null;
  if(!$h||(int)($h['is_active']??0)!==1)$status='target_missing_or_inactive';
  elseif(hmc4a_excluded((string)($h['country_name']??'')))$status='excluded_country';
  else $status='current_missing_exact_key';
 }
 $a=hmc4a_anchor_state($anchors[$tv]??[]);
 $ready=$status==='current_missing_exact_key'&&$a['state']==='canonical_anchor_ok';
 return $e+['status'=>$status,'anchor_state'=>$a['state'],'unanimous_catalog_sha256'=>$a['catalog_sha256'],'anchors'=>$a['anchors'],
  'catalog_hotel'=>$catalog[$tv]??null,'staged_anex_hotel_present'=>isset($staged[$native]),'search_observation_present'=>isset($observed[$native]),
  'target_saved_native_count'=>count(array_filter($sourceTargets,fn($x)=>isset($x[$tv]))),'writer_ready'=>$ready,'safe_to_write_now'=>false];
}
function hmc4a_execute(PDO $db,array $saved,string $sourceSha):array{
 $ids=array_values(array_unique(array_map(fn($r)=>(int)$r['tv_hotel_id'],$saved['rows'])));sort($ids,SORT_NUMERIC);$ph=implode(',',array_fill(0,count($ids),'?'));
 $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
 try{
  $catalog=[];foreach(hmc4a_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$ids) as $r)$catalog[(int)$r['id']]=$r;
  $mapping=[];foreach(hmc4a_query($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings ORDER BY anex_hotel_id") as $r)$mapping[(string)$r['anex_hotel_id']]=$r;
  $decision=[];foreach(hmc4a_query($db,"SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions ORDER BY anex_hotel_id") as $r)$decision[(string)$r['anex_hotel_id']]=$r;
  $exclusions=[];foreach(hmc4a_query($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id") as $r)$exclusions[(string)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
  $anchors=[];foreach(hmc4a_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY local_hotel_id,external_hotel_id") as $r)$anchors[(int)$r['local_hotel_id']][]=$r;
  $staged=[];try{foreach(hmc4a_query($db,"SELECT anex_hotel_id FROM anex_hotels ORDER BY anex_hotel_id") as $r)$staged[(string)$r['anex_hotel_id']]=true;}catch(Throwable){}
  $observed=[];try{foreach(hmc4a_query($db,"SELECT anex_hotel_id FROM anex_search_hotel_observations ORDER BY anex_hotel_id") as $r)$observed[(string)$r['anex_hotel_id']]=true;}catch(Throwable){}
  $registry=AnyTourAnexSearchMappingRegistry::fromPdo($db);
  $rows=[];$status=[];$anchorsCount=[];$stagedCount=0;$obsCount=0;
  foreach($saved['rows'] as $e){
   $r=hmc4a_classify($e,$catalog,$mapping,$decision,$exclusions,$anchors,$saved['source_targets'],$registry,$staged,$observed);$rows[]=$r;
   $status[$r['status']]=($status[$r['status']]??0)+1;$anchorsCount[$r['anchor_state']]=($anchorsCount[$r['anchor_state']]??0)+1;
   if($r['staged_anex_hotel_present'])$stagedCount++;if($r['search_observation_present'])$obsCount++;
  }
  ksort($status);ksort($anchorsCount);$db->rollBack();
  $writer=count(array_filter($rows,fn($r)=>$r['writer_ready']===true));
  return ['operation'=>HMC4A_OP,'state'=>'completed_read_only_anex_current_audit','source_sha'=>$sourceSha,'input_count'=>count($rows),
   'unique_native_ids'=>count($saved['source_targets']),'unique_targets'=>count($saved['target_sources']),'target_multi_native_count'=>count(array_filter($saved['target_sources'],fn($x)=>count($x)>1)),
   'status_counts'=>$status,'anchor_state_counts'=>$anchorsCount,'staged_anex_hotel_present_count'=>$stagedCount,'search_observation_present_count'=>$obsCount,
   'writer_ready_count'=>$writer,'children'=>$saved['children'],'rows'=>$rows,'supplier_calls'=>0,'provider_http_calls'=>0,'database_reads'=>1,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
 $mode=$argv[1]??'';
 if($mode==='--self-test'){
  $raw='{"x":1}';$a=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'7','local_hotel_id'=>100,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];
  $e=['anex_hotel_id'=>'5844','tv_hotel_id'=>100,'operator_id'=>13,'safe_to_write_now'=>false];$cat=[100=>['id'=>100,'is_active'=>1,'country_name'=>'Турция']];
  $ref=new ReflectionClass(AnyTourAnexSearchMappingRegistry::class);$ctor=$ref->getConstructor();$ctor->setAccessible(true);$reg=$ref->newInstanceWithoutConstructor();$prop=$ref->getProperty('index');$prop->setAccessible(true);$prop->setValue($reg,[]);
  $r=hmc4a_classify($e,$cat,[],[],[],[100=>[$a]],['5844'=>[100=>true]],$reg,[],[]);
  hmc4a_need($r['writer_ready']===true&&$r['status']==='current_missing_exact_key','self_ready');
  $e['anex_hotel_id']='123456789';$r=hmc4a_classify($e,$cat,[],[],[],[100=>[$a]],['123456789'=>[100=>true]],$reg,[],[]);hmc4a_need($r['status']==='registry_id_invalid','self_id');
  echo "MATCH_LIVE30_COMMON4_ANEX_CURRENT_V4_SELFTEST_OK\n";exit;
 }
 hmc4a_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$sha=(string)getenv('MATCH_SOURCE_SHA');
 hmc4a_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===HMC4A_OP&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
 $res=hmc4a_load($dir.'/reservation.json');hmc4a_need(($res['operation']??'')===HMC4A_OP&&($res['state']??'')==='reserved_before_db_read','reservation');
 try{
  require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$saved=hmc4a_saved(dirname($dir));$result=hmc4a_execute(v2_data_db(),$saved,$sha);
  $h=hmc4a_save($dir.'/result.json',$result);hmc4a_save($dir.'/receipt.json',['operation'=>HMC4A_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
  echo hmc4a_json(['state'=>$result['state'],'input'=>$result['input_count'],'status_counts'=>$result['status_counts'],'anchor_state_counts'=>$result['anchor_state_counts'],'writer_ready'=>$result['writer_ready_count'],'staged_present'=>$result['staged_anex_hotel_present_count'],'observed_present'=>$result['search_observation_present_count']])."\n";
 }catch(Throwable $e){
  $f=['operation'=>HMC4A_OP,'state'=>'failed_read_only_anex_current_audit','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,120,'UTF-8')),'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
  $h=hmc4a_save($dir.'/result.json',$f);hmc4a_save($dir.'/receipt.json',['operation'=>HMC4A_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);
 }
}
