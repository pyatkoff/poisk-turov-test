<?php
declare(strict_types=1);
// Read-only related-identity inventory; candidate proposals are not acceptance authority.
const R22_OP='hotel-match-rolling22-related-evidence-1971-20260919-v1';
const R22_PLAN_SHA='4b959f0796909cc3556d0ecb76d52aa5308d258586b96c7df2b669f564a414e3';
function r22_json(array $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function r22_put(string $p,array $x):string{$s=r22_json($x);$f=@fopen($p,'xb');if(!$f)throw new RuntimeException('output_exists');try{if(fwrite($f,$s)!==strlen($s)||!fflush($f))throw new RuntimeException('write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('sync');}finally{fclose($f);}if(file_get_contents($p)!==$s)throw new RuntimeException('readback');return hash('sha256',$s);}
function r22_rows(PDO $d,string $q,array $p=[]):array{$s=$d->prepare($q);$s->execute(array_values($p));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function r22_input(string $p):array{
 if(hash_file('sha256',$p)!==R22_PLAN_SHA)throw new RuntimeException('plan_digest');$x=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);
 if($x['state']!=='planned_read_only'||$x['mapping_writes']!==0||$x['eligible_count']!==32||$x['held_count']!==22)throw new RuntimeException('plan_state');
 $v=array_values(array_filter($x['dossiers'],fn($r)=>$r['holds']!==[]&&$r['eligible_for_guarded_append']===false));
 if(count($v)!==22||count(array_unique(array_column($v,'native_anex_id')))!==22||count(array_unique(array_column($v,'hotel_id')))!==22)throw new RuntimeException('held_set');return $v;
}
function r22_prefix(string $name):string{$name=trim(preg_replace('/[^\p{L}\p{N}]+/u',' ',$name)??'');$tokens=preg_split('/\s+/u',$name,-1,PREG_SPLIT_NO_EMPTY)?:[];return implode(' ',array_slice($tokens,0,2));}
function r22_ids(array $held):array{$ids=[];foreach($held as $h){$ids[(int)$h['native_anex_id']]=true;foreach($h['saved_candidates'] as $c)$ids[(int)$c['anex_hotel_id']]=true;}$v=array_keys($ids);sort($v,SORT_NUMERIC);if(count($v)>200)throw new RuntimeException('related_id_bound');return $v;}
if(($argv[1]??'')==='--self-test'){
 if(r22_prefix('SWISSOTEL RESORT EL QUSIER (EX. RADISSON)')!=='SWISSOTEL RESORT'||r22_prefix('100%_TEST HOTEL')!=='100 TEST')throw new RuntimeException('prefix_test');
 if(isset($argv[2])){$h=r22_input($argv[2]);if(count(r22_ids($h))<22)throw new RuntimeException('id_test');echo 'RELATED_IDS '.count(r22_ids($h))."\n";}
 echo "ROLLING22_RELATED_SELFTEST_OK\n";exit;
}
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');
$dir=realpath((string)getenv('MATCH_OPERATION_DIR'));$root=realpath((string)getenv('ANYTOUR_ROOT'));if(!$dir||!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('paths');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,512,JSON_THROW_ON_ERROR);if(($res['operation']??'')!==R22_OP||($res['state']??'')!=='reserved_before_db'||!preg_match('/^[0-9a-f]{40}$/D',$res['source_sha']??''))throw new RuntimeException('reservation');
$base=['operation'=>R22_OP,'source_sha'=>$res['source_sha'],'plan_sha256'=>R22_PLAN_SHA,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true];$db=null;
try{
 r22_put($dir.'/execution-reservation.json',$res);$held=r22_input($dir.'/payload/plan.json');$ids=r22_ids($held);$lids=array_column($held,'hotel_id');$ph=implode(',',array_fill(0,count($ids),'?'));$lh=implode(',',array_fill(0,count($lids),'?'));
 $rf=$dir.'/payload/anex-search-mapping-registry.php';$b=(string)file_get_contents($rf);if(sha1('blob '.strlen($b)."\0".$b)!=='cc135a95d2a6e0f73ce50be2141c9b8a26fddc58')throw new RuntimeException('registry_digest');require_once $rf;
 require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
 $maps=r22_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($lh) ORDER BY anex_hotel_id",array_merge($ids,$lids));
 $dec=r22_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($lh) ORDER BY anex_hotel_id",array_merge($ids,$lids));
 $ex=r22_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($lh) ORDER BY anex_hotel_id,catalog_hotel_id",array_merge($ids,$lids));
 $nat=r22_rows($db,"SELECT anex_hotel_id,xml_name,xml_alternate_name,api_name,api_country,api_region,api_town,latitude,longitude,source_fingerprint FROM anex_hotels WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id",$ids);
 $auto=r22_rows($db,"SELECT anex_hotel_id,row_digest,original_status,automated_status,automated_reason,suggested_catalog_hotel_id,candidate_count FROM anex_hotel_auto_matches WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id",$ids);
 $obs=r22_rows($db,"SELECT anex_hotel_id,hotel_name,country_id,anex_country_id,last_catalog_hotel_id,last_seen_utc,last_source_sha FROM anex_search_hotel_observations WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id",$ids);
 $allLocal=$lids;foreach(array_merge($maps,$dec) as $m)if((int)($m['catalog_hotel_id']??0)>0)$allLocal[]=(int)$m['catalog_hotel_id'];$allLocal=array_values(array_unique($allLocal));sort($allLocal,SORT_NUMERIC);$allPH=implode(',',array_fill(0,count($allLocal),'?'));
 $local=r22_rows($db,"SELECT id,name,country_id,country_name,region_name,subregion_name,latitude,longitude,is_active FROM catalog_hotels WHERE id IN ($allPH) ORDER BY id",$allLocal);$byLocal=array_column($local,null,'id');
 $reg=AnyTourAnexSearchMappingRegistry::fromPdo($db);$profiles=[];foreach($ids as $aid){$by=function(array $rows)use($aid):array{return array_values(array_filter($rows,fn($x)=>(int)$x['anex_hotel_id']===$aid));};$target=$reg->resolve('anex_online',(string)$aid,'preview');$profiles[$aid]=['native_anex_id'=>$aid,'effective_accepted_local_id'=>$target,'effective_local'=>$target?($byLocal[$target]??null):null,'native_catalog'=>$by($nat),'auto_review'=>$by($auto),'observations'=>$by($obs),'mapping_rows'=>$by($maps),'manual_rows'=>$by($dec),'exclusion_rows'=>$by($ex)];}
 $dossiers=[];$allRivalsElsewhere=0;
 foreach($held as $h){$rivals=[];foreach($h['saved_candidates'] as $c){$aid=(int)$c['anex_hotel_id'];if($aid!==$h['native_anex_id'])$rivals[$aid]=$profiles[$aid];}$different=$rivals!==[];foreach($rivals as $r)if(!$r['effective_accepted_local_id']||$r['effective_accepted_local_id']===$h['hotel_id'])$different=false;if($different)$allRivalsElsewhere++;
  $alternatives=[];$truncated=false;if(in_array('observed_name_needs_review',$h['holds'],true)){$prefixes=array_unique([r22_prefix($h['hotel_name']),r22_prefix($h['saved_observation']['hotel_name']??'')]);foreach($prefixes as $prefix){if($prefix==='')continue;$rows=r22_rows($db,'SELECT id,name,country_id,region_name,subregion_name,latitude,longitude FROM catalog_hotels WHERE is_active=1 AND country_id=? AND name LIKE ? ORDER BY id LIMIT 201',[$h['country_id'],$prefix.'%']);if(count($rows)>200)$truncated=true;foreach(array_slice($rows,0,200) as $row)$alternatives[(int)$row['id']]=$row;}}
  $dossiers[]=['native_anex_id'=>$h['native_anex_id'],'hotel_id'=>$h['hotel_id'],'prior_holds'=>$h['holds'],'direct_operator_link'=>$h['operator_link'],'target_local'=>$byLocal[$h['hotel_id']]??null,'native_profile'=>$profiles[$h['native_anex_id']],'rival_native_profiles'=>array_values($rivals),'all_rivals_currently_accepted_elsewhere'=>$different,'same_country_prefix_alternatives'=>array_values($alternatives),'prefix_alternatives_truncated'=>$truncated,'safe_to_write_now'=>false];
 }
 $clock=r22_rows($db,'SELECT UTC_TIMESTAMP AS db_utc_timestamp')[0]['db_utc_timestamp'];$db->exec('ROLLBACK');$out=$base+['state'=>'completed_read_only','captured_at_utc'=>$clock,'related_native_ids'=>$ids,'held_dossiers'=>count($dossiers),'all_rivals_accepted_elsewhere_count'=>$allRivalsElsewhere,'dossiers'=>$dossiers];
}catch(Throwable $e){try{if($db&&$db->inTransaction())$db->rollBack();}catch(Throwable $ignore){}$out=$base+['state'=>'blocked','reason'=>preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'database_or_runtime_error','sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null,'driver_code'=>$e instanceof PDOException?($e->errorInfo[1]??null):null];}
$sha=r22_put($dir.'/result.json',$out);r22_put($dir.'/receipt.json',['operation'=>R22_OP,'state'=>$out['state'],'result_sha256'=>$sha,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$sha,'no_replay'=>true]);echo r22_json(['state'=>$out['state'],'result_sha256'=>$sha,'all_rivals_accepted_elsewhere_count'=>$out['all_rivals_accepted_elsewhere_count']??null]);exit($out['state']==='completed_read_only'?0:2);
