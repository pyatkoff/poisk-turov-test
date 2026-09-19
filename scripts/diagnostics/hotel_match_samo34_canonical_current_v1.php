<?php
declare(strict_types=1);
const HMCC_OPERATION='hotel-match-samo34-canonical-current-1971-20260919-v1';
const HMCC_INPUT_SHA='3c084e5e5c672dc7f6ffae5abe3eacccd6c0a57463ac79b0a6a2c1d6e2c39953';
function hmcc_require(bool $ok,string $why):void {if(!$ok)throw new RuntimeException($why);}
function hmcc_json(array $v):string {return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}
function hmcc_save(string $path,array $v):string {
 $raw=hmcc_json($v);$h=fopen($path,'xb');hmcc_require(is_resource($h),'exclusive_record');
 try{hmcc_require(fwrite($h,$raw)===strlen($raw)&&fflush($h),'write_record');if(function_exists('fsync'))hmcc_require(fsync($h),'sync_record');}finally{fclose($h);}return hash('sha256',$raw);
}
function hmcc_norm(string $v):string {
 $v=function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower(strtr($v,array_combine(preg_split('//u','АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ',-1,PREG_SPLIT_NO_EMPTY),preg_split('//u','абвгдеёжзийклмнопрстуфхцчшщъыьэюя',-1,PREG_SPLIT_NO_EMPTY))));$v=strtr($v,['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','ö'=>'o','ü'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);
 return trim(preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??'');
}
function hmcc_names(string $v):array {
 $parts=[$v];$parts[]=preg_split('/\s*\(\s*(?:ex\.?|formerly|бывш\.?)\s*/iu',$v)[0];
 preg_match_all('/\(\s*(?:ex\.?|formerly|бывш\.?)\s*([^)]*)/iu',$v,$m);
 foreach($m[1] as $s)foreach(preg_split('/[;|\/]/u',$s) as $x)$parts[]=$x;
 $out=[];foreach($parts as $s){$tokens=array_values(array_filter(explode(' ',hmcc_norm($s)),fn($w)=>$w!==''&&!in_array($w,['hotel','отель'],true)));sort($tokens,SORT_STRING);if($tokens)$out[implode(' ',$tokens)]=true;}return array_keys($out);
}
function hmcc_namespace(array $f):string {
 hmcc_require(in_array($f['is_operator_hotel_key']??null,['0','1'],true),'namespace_flag');
 hmcc_require(preg_match('/^[1-9][0-9]*$/D',(string)($f['operator_id']??''))===1,'namespace_operator');
 return $f['is_operator_hotel_key']==='0'?'andromeda_catalog':'operator_'.$f['operator_id'];
}
function hmcc_star(mixed $v):?int {return is_scalar($v)&&preg_match('/^([1-5])\*?$/D',trim((string)$v),$m)?(int)$m[1]:null;}
function hmcc_rows(PDO $db,string $sql,array $params=[]):array {$s=$db->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC);}
if(in_array('--self-test',$argv??[],true)) {
 hmcc_require(hmcc_namespace(['is_operator_hotel_key'=>'0','operator_id'=>'315'])==='andromeda_catalog','test_catalog');
 hmcc_require(hmcc_namespace(['is_operator_hotel_key'=>'1','operator_id'=>'315'])==='operator_315','test_operator');
 $failed=false;try{hmcc_namespace(['operator_id'=>'315']);}catch(RuntimeException $e){$failed=true;}hmcc_require($failed,'test_unknown_namespace');
 hmcc_require(!array_intersect(hmcc_names('AKRA V'),hmcc_names('Akra Kemer')),'test_significant_v');
 hmcc_require((bool)array_intersect(hmcc_names('AKRA V (EX. BARUT AKRA PARK)'),hmcc_names('Akra V Hotel')),'test_correct_v');
 hmcc_require(!array_intersect(hmcc_names('LARA PARK HOTEL'),hmcc_names('Grand Park Lara')),'test_grand_qualifier');
 hmcc_require(hmcc_star('5*')===5&&hmcc_star('5')===5&&hmcc_star('8')===null,'test_star_label');
 echo "HMCC_SELFTEST_OK checks=7\n";exit(0);
}
hmcc_require(PHP_SAPI==='cli','cli_only');$db=null;$dir=(string)getenv('MATCH_OPERATION_DIR');$source=(string)getenv('MATCH_SOURCE_SHA');
hmcc_require($dir!==''&&is_dir($dir)&&!is_link($dir)&&preg_match('/^[a-f0-9]{40}$/D',$source)===1,'operation_path');
$base=['operation'=>HMCC_OPERATION,'source_sha'=>$source,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true];
try{
 $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
 hmcc_require(($reservation['operation']??'')===HMCC_OPERATION&&($reservation['source_sha']??'')===$source,'reservation');
 $raw=(string)file_get_contents($dir.'/input.json');hmcc_require(hash('sha256',$raw)===HMCC_INPUT_SHA,'input_digest');$input=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
 hmcc_require(count($input['edges'])===39&&count($input['catalog'])===3260,'input_shape');
 $rootValue=(string)getenv('ANYTOOUR_ROOT');$root=$rootValue!==''?realpath($rootValue):false;
 hmcc_require(is_string($root)&&basename($root)==='anytoour.ru','root');
 $dbfile=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');hmcc_require(is_file($dbfile),'db_bootstrap');require_once $dbfile;
 require_once $dir.'/andromeda-hotel-resolver.php';require_once $dir.'/anex-search-mapping-registry.php';
 $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
 $schemas=[];foreach(['andromeda_hotel_identities','catalog_hotels','hotel_aliases','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions'] as $t)$schemas[$t]=hmcc_rows($db,'SHOW COLUMNS FROM '.$t);
 $hs=hmcc_rows($db,'SELECT id,name,country_id,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE country_id=4 ORDER BY id LIMIT 30001');hmcc_require(count($hs)<30001,'catalog_cap');
 $as=hmcc_rows($db,'SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id=4 AND h.is_active=1 ORDER BY a.hotel_id,a.alias LIMIT 100001');hmcc_require(count($as)<100001,'aliases_cap');
 $hotels=[];$localIndex=[];$localKeys=[];
 foreach($hs as $h){$id=(int)$h['id'];$hotels[$id]=$h;if((int)$h['is_active']!==1)continue;foreach(hmcc_names($h['name']) as $k){$localIndex[$k][$id]=true;$localKeys[$id][$k]=true;}}
 foreach($as as $a){$id=(int)$a['hotel_id'];foreach(hmcc_names($a['alias']) as $k){$localIndex[$k][$id]=true;$localKeys[$id][$k]=true;}}
 $cat=[];$sourceIndex=[];$foreign=0;
 foreach($input['catalog'] as $h){$id=(string)$h['id'];hmcc_require(!isset($cat[$id]),'duplicate_supplier_id');$cat[$id]=$h;if((string)$h['stateKey']!=='5'){$foreign++;continue;}foreach([$h['name'],$h['lName']??''] as $n)foreach(hmcc_names($n) as $k)$sourceIndex[$k][$id]=true;}
 $all=hmcc_rows($db,'SELECT i.*,h.id AS existing_catalog_hotel_id FROM andromeda_hotel_identities i LEFT JOIN catalog_hotels h ON h.id=i.local_hotel_id ORDER BY i.supplier_namespace,i.external_hotel_id LIMIT 50001');hmcc_require(count($all)<50001,'identity_cap');
 $identity=[];$occupied=[];$projection=[];$statuses=[];
 foreach($all as $r){$key=$r['supplier_namespace'].':'.$r['external_hotel_id'];hmcc_require(!isset($identity[$key]),'duplicate_identity');$identity[$key]=$r;$statuses[$r['decision_status']]=($statuses[$r['decision_status']]??0)+1;
  if($r['decision_status']==='accepted'&&$r['local_hotel_id']!==null)$occupied[(int)$r['local_hotel_id']][]=$key;
  if(in_array($r['decision_status'],['accepted','rejected'],true))$projection[]=['supplier_namespace'=>$r['supplier_namespace'],'external_hotel_id'=>$r['external_hotel_id'],'decision_status'=>$r['decision_status'],'catalog_hotel_id'=>$r['local_hotel_id'],'existing_catalog_hotel_id'=>$r['existing_catalog_hotel_id']];
 }
 $resolver=AnyTourAndromedaHotelResolver::fromRows($projection,hash('sha256',hmcc_json($projection)));$anex=AnyTourAnexSearchMappingRegistry::fromPdo($db);
 $edges=[];$summary=[];$unique=[];
 foreach($input['edges'] as $e){$hid=$e['tv_hotel_id'];$facts=[];$good=[];
  foreach($e['facts'] as $f){$ns=hmcc_namespace($f);hmcc_require($ns===$f['supplier_namespace'],'namespace_contract');$page=['provider'=>'andromeda','selection_enabled'=>false,'offers'=>[['provider'=>'andromeda','selection_enabled'=>false,'local_hotel_id'=>null,'supplier_namespace'=>$ns,'external_hotel_id'=>$f['external_hotel_id']]]];$effective=$resolver->apply($page)['offers'][0]['local_hotel_id'];
   $f['effective_current_local_id']=$effective;$f['current_status']=$identity[$ns.':'.$f['external_hotel_id']]['decision_status']??null;$facts[]=$f;if($f['native_anchor_equal']&&$effective===$hid)$good[]=true;
  }
  $state=$good?'already_effectively_mapped':(array_filter($facts,fn($f)=>$f['native_anchor_equal'])?'canonical_mapping_missing_or_protected':'native_anchor_mismatch');
  $summary[$state]=($summary[$state]??0)+1;if($good)$unique[$hid]=true;
  $edges[]=['tv_hotel_id'=>$hid,'target_name'=>$e['target_name'],'operator'=>$e['operator'],'state'=>$state,'facts'=>$facts];
 }
 $plans=[];$selected=[];
 foreach($input['new_candidates'] as $candidate){$hid=$candidate['tv_hotel_id'];$sid=$candidate['external_hotel_id'];$h=$hotels[$hid]??null;$s=$cat[$sid]??null;$reasons=[];$rivals=[];$supplierRivals=[];
  if(!$h||(int)$h['is_active']!==1||(int)$h['country_id']!==4)$reasons[]='target_inactive_or_country';
  if(!$s||(string)$s['stateKey']!=='5')$reasons[]='supplier_country';
  if($h&&$s){foreach([$s['name'],$s['lName']??''] as $n)foreach(hmcc_names($n) as $k)foreach(array_keys($localIndex[$k]??[]) as $id)$rivals[$id]=true;
   foreach(array_keys($localKeys[$hid]??[]) as $k)foreach(array_keys($sourceIndex[$k]??[]) as $id)$supplierRivals[(string)$id]=true;
   if(count($rivals)!==1||!isset($rivals[$hid]))$reasons[]='local_name_competition';if(count($supplierRivals)!==1||!isset($supplierRivals[$sid]))$reasons[]='supplier_name_competition';
   if(!in_array(hmcc_norm((string)($s['town']??'')),array_filter([hmcc_norm((string)$h['region_name']),hmcc_norm((string)$h['subregion_name'])]),true))$reasons[]='geography_unproven';
   if(hmcc_star($h['category'])===null||hmcc_star($s['star'])===null||hmcc_star($h['category'])!==hmcc_star($s['star']))$reasons[]='category_discrepancy';
  }
  $old=$identity['andromeda_catalog:'.$sid]??null;if($old)$reasons[]='source_row_exists';if(isset($occupied[$hid]))$reasons[]='target_occupied';
  $anchor=$anex->resolve('anex_online',(string)$candidate['anex_id'],'preview');if($anchor!==$hid)$reasons[]='anex_effective_anchor';
  $selected[$hid]=$h;
  $plans[]=$candidate+['source'=>$s,'target'=>$h,'source_existing'=>$old,'target_occupancy'=>$occupied[$hid]??[],'full_country_local_competitors'=>array_keys($rivals),'full_country_supplier_competitors'=>array_keys($supplierRivals),'effective_anex_target'=>$anchor,'reasons'=>$reasons,'read_only_eligible'=>$reasons===[],'safe_to_write_now'=>false];
 }
 $extra=[];foreach($input['additional_catalog_ids'] as $id)$extra[$id]=$identity['andromeda_catalog:'.$id]??null;
 $anexGuards=[];$aids=array_column($input['new_candidates'],'anex_id');$marks=implode(',',array_fill(0,count($aids),'?'));
 foreach(['anex_search_hotel_observations','anex_hotel_decisions','anex_hotel_search_mappings','anex_review_pair_exclusions'] as $t)$anexGuards[$t]=hmcc_rows($db,'SELECT * FROM '.$t.' WHERE anex_hotel_id IN ('.$marks.') ORDER BY anex_hotel_id',$aids);
 $allDigest=hash('sha256',hmcc_json($all));$db->rollBack();
 $result=$base+['state'=>'completed_read_only','read_at_utc'=>gmdate('c'),'input_sha256'=>HMCC_INPUT_SHA,'identity_count'=>count($all),'identity_status_counts'=>$statuses,'identity_snapshot_sha256'=>$allDigest,'full_country_local_count'=>count($hs),'full_country_alias_count'=>count($as),'supplier_catalog_count'=>count($cat),'foreign_catalog_rows_excluded'=>$foreign,'edge_counts'=>$summary,'already_effective_unique_local'=>count($unique),'edges'=>$edges,'new_candidate_plans'=>$plans,'additional_current_identities'=>$extra,'anex_guards'=>$anexGuards,'schemas'=>$schemas,'resolver_source_sha256'=>hash_file('sha256',$dir.'/andromeda-hotel-resolver.php'),'anex_registry_source_sha256'=>hash_file('sha256',$dir.'/anex-search-mapping-registry.php')];
}catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$result=$base+['state'=>'failed_no_replay','reason'=>preg_replace('/[^a-z0-9_]/i','_',substr($e->getMessage(),0,120))];}
$hash=hmcc_save($dir.'/result.json',$result);hmcc_save($dir.'/receipt.json',$base+['state'=>$result['state'],'result_sha256'=>$hash,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash]);
echo hmcc_json(['state'=>$result['state'],'edge_counts'=>$result['edge_counts']??null,'new_candidate_reasons'=>array_map(fn($p)=>['tv'=>$p['tv_hotel_id'],'reasons'=>$p['reasons']],$result['new_candidate_plans']??[]),'result_sha256'=>$hash]);exit($result['state']==='completed_read_only'?0:2);
