<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_anex_effective_coverage.php';
require_once __DIR__.'/hotel_match_live_samo_anchor_anex_bridge_v1.php';
require_once __DIR__.'/hotel_match_live_samo_missing_anex_saved_direct_v1.php';
const M948_OP='hotel-match-mass948-saved-context-1971-20260927-v1';
const M948_CENSUS_SHA='411596e321ba5f128335a29d10181688dacd5e34eee5e7cefdd4436b26d468b2';
function m948_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function m948_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function m948_save(string $p,array $v):string{$s=m948_json($v)."\n";$f=@fopen($p,'x+b');m948_need($f!==false,'exclusive_create');try{m948_need(fwrite($f,$s)===strlen($s)&&fflush($f),'write');if(function_exists('fsync'))m948_need(fsync($f),'sync');}finally{fclose($f);}return hash('sha256',$s);}
function m948_query(PDO $db,string $sql,array $args=[]):array{$q=$db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC)?:[];}
function m948_manifest(array $m):array{
 m948_need(($m['census_result_sha256']??'')===M948_CENSUS_SHA,'census_pin');$rows=$m['rows']??[];m948_need(is_array($rows)&&count($rows)===948,'scope948');$ids=[];$counts=['SAMO'=>0,'ANEX'=>0];
 $done=array_fill_keys([11742,11748,11771,11773,27691,45455,60000,64351,64355,64722,71376,113617,117800,119844],true);
 foreach($rows as $r){$id=$r['tv_hotel_id']??null;m948_need(is_int($id)&&$id>0,'scope_id');m948_need(!isset($ids[$id])&&!isset($done[$id]),'duplicate_or_completed');$s=$r['needs_samo']??null;$a=$r['needs_anex']??null;m948_need(is_bool($s)&&is_bool($a)&&($s!==$a),'single_missing');$ids[$id]=$r;$counts[$s?'SAMO':'ANEX']++;}
 m948_need($counts===['SAMO'=>220,'ANEX'=>728],'scope_counts');ksort($ids,SORT_NUMERIC);return $ids;
}
/** Hotel fields only. Credential/session URLs are never exported. */
function m948_safe(mixed $v,int $depth=0):mixed{
 if($depth>16)return null;if(!is_array($v))return is_scalar($v)||$v===null?$v:null;
 if(array_is_list($v)){m948_need(count($v)<=100000,'list_cap');$o=[];foreach($v as$x){$y=m948_safe($x,$depth+1);if($y!==null&&$y!==[])$o[]=$y;}return$o;}
 $allow=['id','name','lName','hotel_name','hotelName','state','stateKey','stateLName','country','country_id','country_name','town','townKey','townLName','region','region_name','subregion_name','star','starKey','category','latitude','longitude','lat','lon','lng','operatorKey','operator_key','hotelKey','hotel_key','originalHotelId','original_hotel_id','original_hotel_key','original','anex_bridges','anex_id','source','sources','hotel','hotels','HOTEL','images','image','imageUrl','image_url','hotelUrl','hotel_url','url','link','operator_link','supplier_namespace','external_hotel_id','local_hotel_id','decision_status','reason','reason_code','status','evidence_sha256','catalog_sha256','operation','operation_id','source_sha','source_operation','target_local_id','catalog_id','native_id','native_ids','lanes','operator_5','operator_115','operator_315','operator_342','bgoperator','proofs','retained_proofs'];
 $o=[];foreach($allow as$k){if(!array_key_exists($k,$v))continue;$x=$v[$k];if(is_string($x)&&preg_match('/(?:url|link|image)/i',$k)){
   if(strlen($x)>4096||preg_match('/(?:token|session|password|login|auth|secret|api.?key|bearer)=/i',$x))continue;
   if(preg_match('~^https?://~i',$x)){$p=parse_url($x);if(!$p||isset($p['user'])||isset($p['pass']))continue;}
  }if(is_string($x)&&strlen($x)>20000)continue;$y=m948_safe($x,$depth+1);if($y!==null)$o[$k]=$y;}
 return$o;
}
function m948_hotels(mixed $node,array &$rows,int $depth=0):void{
 if($depth>12||!is_array($node))return;
 foreach($node as$k=>$v){if(($k==='HOTEL'||$k==='hotels')&&is_array($v)){foreach($v as$r)if(is_array($r)&&isset($r['id'])&&(isset($r['name'])||isset($r['lName']))){$safe=m948_safe($r);$key=hash('sha256',m948_json($safe));$rows[$key]=$safe;}}elseif(is_array($v))m948_hotels($v,$rows,$depth+1);}
}
function m948_cached(string $path):array{
 m948_need(is_file($path)&&!is_link($path),'catalog_file');$dir=dirname($path);$files=[$path=>true];
 foreach(new DirectoryIterator($dir)as$e)if(!$e->isDot()&&!$e->isLink()&&$e->isFile()&&preg_match('/(?:catalog|country|dictionary).*\.json$/i',$e->getFilename()))$files[$e->getPathname()]=true;
 m948_need(count($files)<=512,'cache_file_cap');$out=[];$rows=[];$bytes=0;
 foreach(array_keys($files)as$p){$size=filesize($p);if($size===false||$size<2||$size>134217728){$out[]=['file'=>basename($p),'state'=>'size_skipped'];continue;}$bytes+=$size;m948_need($bytes<=536870912,'cache_bytes_cap');$raw=(string)file_get_contents($p);$hash=hash('sha256',$raw);try{$x=json_decode($raw,true,64,JSON_THROW_ON_ERROR);}catch(Throwable){$out[]=['file'=>basename($p),'sha256'=>$hash,'state'=>'invalid_json'];continue;}$found=[];m948_hotels($x,$found);foreach($found as$k=>$r){$rows[$k]=['row'=>$r,'row_sha256'=>$k,'file_sha256'=>$hash,'file'=>basename($p)];}$out[]=['file'=>basename($p),'sha256'=>$hash,'bytes'=>$size,'top_keys'=>is_array($x)?array_slice(array_keys($x),0,30):[],'hotels_found'=>count($found)];}
 return['files'=>$out,'hotel_rows'=>array_values($rows),'provider_http_calls'=>0];
}
function m948_execute(PDO $db,array $selected):array{
 $ids=array_keys($selected);$ph=implode(',',array_fill(0,count($ids),'?'));$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
 try{
  $facts=[];foreach(m948_query($db,"SELECT h.id,h.name,h.normalized_name,h.country_id,h.country_name,h.region_name,h.subregion_name,h.category,h.is_active,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.id IN ($ph) ORDER BY h.id",$ids)as$r)$facts[(int)$r['id']]=$r;
  $aliases=m948_query($db,"SELECT hotel_id,alias,normalized_alias FROM hotel_aliases WHERE hotel_id IN ($ph) ORDER BY hotel_id,id",$ids);
  $all=m948_query($db,'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');m948_need(count($all)<=100000,'registry_cap');
  $registry=[];$sources=[];$anchorIds=[];$badAnchors=[];$samo=[];
  foreach($all as$r){$local=(int)($r['local_hotel_id']??0);$raw=(string)$r['evidence_json'];$valid=hash('sha256',$raw)===(string)$r['evidence_sha256'];unset($r['evidence_json']);$r['evidence_valid']=$valid;$registry[]=$r;
   if($r['supplier_namespace']==='andromeda_catalog'&&$r['decision_status']==='accepted')$samo[$local][(string)$r['external_hotel_id']]=true;
   if(isset($selected[$local])||$r['decision_status']!=='accepted'){
    $e=null;try{$e=json_decode($raw,true,128,JSON_THROW_ON_ERROR);}catch(Throwable){$valid=false;}
    if(!$valid&&isset($selected[$local]))$badAnchors[$local]=true;
    if(is_array($e)){$sources[]=['identity'=>$r,'hotel_evidence'=>m948_safe($e),'raw_evidence_sha256'=>hash('sha256',$raw)];if($r['supplier_namespace']==='andromeda_catalog'&&$r['decision_status']==='accepted'&&$valid){$found=[];hmsab_collect_ids($e,$found);foreach($found as$n=>$_)$anchorIds[$local][(string)$n]=true;}}
   }
  }
  $effective=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$native=[];$byLocal=$effective['by_local']??[];foreach($byLocal as$l=>$ns)foreach($ns as$n=>$yes)if($yes)$native[(string)$n][]=(int)$l;
  $stage=m948_query($db,'SELECT anex_hotel_id,source_fingerprint,xml_name,xml_alternate_name,api_name,api_country,api_region,api_town,latitude,longitude,checked_at FROM anex_hotels ORDER BY anex_hotel_id');m948_need(count($stage)<=100000,'anex_cap');
  $manual=m948_query($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id');$maps=m948_query($db,'SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings ORDER BY anex_hotel_id');$ex=m948_query($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id');
  $obs=m948_query($db,'SELECT id,hotel_id,hotel_name,last_seen_at,observation_count,operator_id,operator_name,operator_link,operator_link_host,operator_link_path,operator_link_query FROM tour_operator_identity_observations ORDER BY hotel_id,id');m948_need(count($obs)<=500000,'observations_cap');$links=[];$live=[];$directGlobal=[];
  foreach($obs as$r){$l=(int)$r['hotel_id'];$parse=hmsma_direct_id($r);if(($parse['status']??'')==='direct_id')$directGlobal[(string)$parse['anex_hotel_id']][$l]=true;if(!isset($selected[$l]))continue;
   if((string)$r['last_seen_at']>=gmdate('Y-m-d H:i:s',time()-30*86400))$live[$l]=true;
   $url=(string)$r['operator_link'];$query=(string)$r['operator_link_query'];if(preg_match('/(?:token|session|password|login|auth|secret|api.?key|bearer)=/i',$url.' '.$query)){unset($r['operator_link'],$r['operator_link_query']);$r['link_redacted']=true;}$r['anex_parse']=$parse;$links[]=$r;
  }
  $rows=[];$drift=[];foreach($selected as$l=>$input){$hasS=isset($samo[$l]);$hasA=!empty($byLocal[$l]);$stable=isset($facts[$l],$live[$l])&&(int)$facts[$l]['is_active']===1&&!hmsma_excluded((string)$facts[$l]['country_name'])&&$hasS===!$input['needs_samo']&&$hasA===!$input['needs_anex'];if(!$stable)$drift[]=$l;
   $rows[]=['tv_hotel_id'=>$l,'requested_missing'=>$input['needs_samo']?'SAMO':'ANEX','current_scope_unchanged'=>$stable,'hotel'=>$facts[$l]??null,'current_samo_catalog_ids'=>array_map('strval',array_keys($samo[$l]??[])),'current_anex_ids'=>array_map('strval',array_keys($byLocal[$l]??[])),'saved_anchor_anex_ids'=>array_map('strval',array_keys($anchorIds[$l]??[])),'invalid_selected_evidence'=>isset($badAnchors[$l])];
  }
  $db->rollBack();return['rows'=>$rows,'scope_drift_ids'=>$drift,'aliases'=>$aliases,'identities'=>$registry,'selected_and_unaccepted_hotel_evidence'=>$sources,'anex_staged_hotels'=>$stage,'effective_anex_native_targets'=>$native,'manual_anex'=>$manual,'anex_mappings'=>$maps,'anex_pair_exclusions'=>$ex,'saved_operator_observations'=>$links,'global_saved_direct_anex_targets'=>array_map('array_keys',$directGlobal),'database_writes'=>0,'mapping_writes'=>0,'provider_http_calls'=>0];
 }catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
 m948_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');m948_need(is_dir($root)&&!is_link($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&!is_link($dir)&&basename($dir)===M948_OP&&preg_match('/^[0-9a-f]{40}$/D',$head)===1,'runtime_scope');
 $m=json_decode((string)file_get_contents(__DIR__.'/../../manifest.json'),true,64,JSON_THROW_ON_ERROR);$selected=m948_manifest($m);$reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);m948_need(($reservation['operation']??'')===M948_OP&&($reservation['source_sha']??'')===$head&&($reservation['state']??'')==='reserved_before_db_read','reservation');foreach(['execution-started.json','result.json','receipt.json']as$n)m948_need(!file_exists($dir.'/'.$n),'no_replay');m948_save($dir.'/execution-started.json',['operation'=>M948_OP,'source_sha'=>$head,'scope'=>948,'provider_http_calls'=>0]);
 $out=['operation'=>M948_OP,'source_sha'=>$head,'census_result_sha256'=>M948_CENSUS_SHA,'database_writes'=>0,'mapping_writes'=>0,'provider_http_calls'=>0,'no_replay'=>true,'safe_to_write_now'=>false];
 try{require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$out['context']=m948_execute(v2_data_db(),$selected);$cfg=$root.'/_preview/search3-anex-candidate/.andromeda-private.php';m948_need(is_file($cfg)&&!is_link($cfg),'config');$c=require$cfg;m948_need(is_array($c)&&is_string($c['catalog_path']??null),'catalog_path');$out['cached_catalog']=m948_cached($c['catalog_path']);$out['state']='completed_read_only_mass948_context';}
 catch(Throwable$e){$out['state']='failed_read_only_mass948_context';$out['error_class']=get_class($e);$out['error_code']=preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160,'UTF-8'));}
 $out['generated_at_utc']=gmdate('c');$hash=m948_save($dir.'/result.json',$out);m948_save($dir.'/receipt.json',['operation'=>M948_OP,'source_sha'=>$head,'state'=>$out['state'],'result_sha256'=>$hash,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'database_writes'=>0,'mapping_writes'=>0,'provider_http_calls'=>0,'no_replay'=>true]);echo m948_json(['state'=>$out['state'],'rows'=>count($out['context']['rows']??[]),'catalog_rows'=>count($out['cached_catalog']['hotel_rows']??[]),'error_code'=>$out['error_code']??null])."\n";exit($out['state']==='completed_read_only_mass948_context'?0:2);
}
