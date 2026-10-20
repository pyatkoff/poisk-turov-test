<?php
declare(strict_types=1);
/** Bounded MATCH identity GETs; no booking, price changes, DB writes or hidden retries. */
const TAL_OP='hotel-match-tv-anex-live-1971-20260916-v1';
const TAL_PREFLIGHT='hotel-match-tv-account-preflight-1971-20260916-v1';
const TAL_PREFLIGHT_SHA='6b9369ebcc46c19844bc0a56d85be867ff4f8ce6d271b8bd037555a6cb23fb37';
const TAL_DATE='2026-10-16';
const TAL_CAP=40;
const TAL_PAIRS=[37048=>97122,28396=>293,37719=>132075,4131=>488,990=>162145,45330=>130621,29557=>73343,34804=>81578,8550=>1283,44139=>67042,8319=>1124,9384=>35297,11756=>996,12901=>1474,15046=>1217,21696=>1070,29332=>60388,31515=>1501,35319=>1346,35511=>28581,35898=>1221,37538=>1461,39875=>1071,27777=>17561,29430=>71266];
function tal_require(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function tal_json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}
function tal_write(string $p,array $v):string{
 $raw=tal_json($v);$f=@fopen($p,'x+b');tal_require(is_resource($f),'exclusive_output');
 try{tal_require(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))tal_require(fsync($f),'durable_sync');rewind($f);tal_require(stream_get_contents($f)===$raw,'durable_readback');}finally{fclose($f);}return hash('sha256',$raw);
}
function tal_read(string $p):array{tal_require(is_file($p)&&!is_link($p)&&filesize($p)<=64000000,'retained_file');$x=json_decode((string)file_get_contents($p),true,64,JSON_THROW_ON_ERROR);tal_require(is_array($x),'retained_object');return$x;}
function tal_select(PDO $db,string $sql,array $args=[]):array{tal_require(preg_match('/^SELECT\b/',$sql)===1,'select_only');$q=$db->prepare($sql);$q->execute($args);$r=$q->fetchAll(PDO::FETCH_ASSOC);tal_require(count($r)<=150000,'read_limit');return$r;}
function tal_id($v):?int{if(is_bool($v)||!is_scalar($v)||!preg_match('/^[1-9][0-9]{0,14}$/D',(string)$v))return null;return(int)$v;}
function tal_lower(string $s):string{return function_exists('mb_strtolower')?mb_strtolower($s,'UTF-8'):strtolower(strtr($s,array_combine(preg_split('//u','АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ',-1,PREG_SPLIT_NO_EMPTY),preg_split('//u','абвгдеёжзийклмнопрстуфхцчшщъыьэюя',-1,PREG_SPLIT_NO_EMPTY))));}
function tal_name($v):string{if(is_scalar($v)&&!is_bool($v)){preg_match('/^.{0,300}/us',trim((string)$v),$m);return$m[0]??'';}if(!is_array($v))return'';foreach(['name','fullName','russianName','title','label']as$k)if(is_scalar($v[$k]??null))return tal_name($v[$k]);return'';}
function tal_find(array $p,array $needles):?array{
 $found=[];$walk=static function($v,int$d=0)use(&$walk,&$found,$needles){if($d>10||!is_array($v))return;$id=tal_id($v['id']??null);$name=tal_name($v);if($id&&$name!==''){foreach($needles as$n)if(strpos(tal_lower($name),tal_lower($n))!==false)$found[$id]=['id'=>$id,'name'=>$name];}foreach($v as$x)if(is_array($x))$walk($x,$d+1);};$walk($p);return count($found)===1?array_values($found)[0]:null;
}
function tal_anex($v):bool{return in_array(tal_lower(trim(tal_name($v))),['anex','anex tour','anextour','анекс','анекс тур'],true);}
function tal_search_id($v,int$d=0):?int{if($d>10||!is_array($v))return null;foreach($v as$k=>$x)if(is_string($k)&&preg_match('/^(?:search|request).*id$/i',$k)&&($id=tal_id($x)))return$id;foreach($v as$x)if(is_array($x)&&($id=tal_search_id($x,$d+1)))return$id;return null;}
function tal_complete($v,int$d=0):bool{if($d>10||!is_array($v))return false;if((int)($v['progress']??0)>=100||in_array(strtolower((string)($v['status']??'')),['complete','completed','done','ready'],true))return true;foreach($v as$x)if(is_array($x)&&tal_complete($x,$d+1))return true;return false;}
function tal_flatten(array $p):array{
 $r=$p;if(!array_is_list($r))foreach(['hotels','results','tours','items','data']as$k)if(is_array($r[$k]??null)){$r=$r[$k];break;}
 $out=[];foreach($r as$v){if(!is_array($v))continue;if(isset($v['tours'])&&is_array($v['tours'])&&!isset($v['hotel'])){$h=$v;unset($h['tours']);foreach($v['tours']as$t)if(is_array($t)){$t['hotel']=$t['hotel']??$h;$out[]=$t;}}elseif(is_array($v['hotel']??null))$out[]=$v;}return$out;
}
function tal_query(array $params):string{
 $parts=[];foreach($params as$key=>$value){if($value===null||$value==='')continue;if(is_bool($value))$value=$value?'true':'false';foreach(is_array($value)?$value:[$value]as$item){if($item===null||$item==='')continue;$parts[]=rawurlencode((string)$key).'='.rawurlencode((string)$item);}}return implode('&',$parts);
}
function tal_params(int $dep,int $country,int $op,array $ids):array{
 tal_require($ids&&count($ids)<=30&&count(array_unique($ids))===count($ids),'nonempty_target_batch');foreach($ids as$id)tal_require(is_int($id)&&$id>0,'target_id');
 return ['departureId'=>$dep,'countryId'=>$country,'dateFrom'=>TAL_DATE,'dateTo'=>TAL_DATE,'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'operatorIds'=>[$op],'currency'=>'RUB','onlyCharter'=>false,'hotelIds'=>array_values($ids)];
}
function tal_point(array $v):?array{foreach([['latitude','longitude'],['lat','lng'],['lat','lon'],['api_latitude','api_longitude']]as[$a,$b]){if(!is_numeric($v[$a]??null)||!is_numeric($v[$b]??null))continue;$x=(float)$v[$a];$y=(float)$v[$b];if(is_finite($x)&&is_finite($y)&&abs($x)<=90&&abs($y)<=180&&($x!=0||$y!=0))return[$x,$y];}return null;}
function tal_km(array $a,array $b):float{[$x,$y,$u,$v]=array_map('deg2rad',[$a[0],$a[1],$b[0],$b[1]]);$h=sin(($u-$x)/2)**2+cos($x)*cos($u)*sin(($v-$y)/2)**2;return 12742.0176*asin(sqrt(min(1,max(0,$h))));}
function tal_native(string $url):?array{
 if(strlen($url)>4096)return null;$p=parse_url($url);if(!is_array($p)||($p['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass'])||isset($p['port']))return null;
 $host=strtolower((string)($p['host']??''));$path=$p['path']??'';$key=null;
 if($host==='agent.anextour.ru'&&$path==='/search/tour')$key='HOTELLIST';
 if($host==='online.anextour.ru'&&in_array($path,['/search','/search/'],true))$key='hotelCode';
 if(!$key)return null;$values=[];foreach(explode('&',$p['query']??'')as$part){[$k,$v]=array_pad(explode('=',$part,2),2,'');if(strcasecmp(urldecode($k),$key)===0)$values[]=tal_id(urldecode($v));}
 if(count($values)!==1||$values[0]===null)return null;
 return ['anex_hotel_id'=>$values[0],'key'=>$key,'host'=>$host,'path'=>$path,'identity_url'=>'https://'.$host.$path.'?'.$key.'='.$values[0],'operator_link_sha256'=>hash('sha256',$url)];
}
function tal_call(string $stage,string $path,array $params,string $token,array &$ctx):array{
 tal_require(preg_match('#^/(?:departures|countries|operators|tours/search(?:/[1-9][0-9]*(?:/status)?)?|tours/[1-9][0-9]*)$#D',$path)===1,'endpoint_allowlist');
 if($path==='/tours/search')tal_require(!empty($params['hotelIds'])&&count($params['hotelIds'])<=30&&!empty($params['operatorIds']),'no_broad_search');
 tal_require($ctx['attempts']<TAL_CAP,'operation_http_cap');$remaining=5.0-(microtime(true)-$ctx['last_started']);if($remaining>0)usleep((int)ceil($remaining*1000000));
 $number=$ctx['attempts']+1;$stem=$ctx['dir'].'/request-'.str_pad((string)$number,3,'0',STR_PAD_LEFT);$query=tal_query($params);$url='https://api.tourvisor.ru/search/api/v1'.$path.($query!==''?'?'.$query:'');
 tal_write($stem.'-reservation.json',['operation_id'=>TAL_OP,'source_sha'=>$ctx['sha'],'sequence'=>$number,'stage'=>$stage,'path'=>$path,'params'=>$params,'method'=>'GET','request_sha256'=>hash('sha256',$url),'state'=>'reserved_before_http','credential_identifier'=>'TOURVISOR_ANEX_JWT','max_attempts'=>1,'no_replay'=>true]);
 $ctx['attempts']++;$ctx['last_started']=microtime(true);$retryAfter=null;$ch=curl_init($url);tal_require($ch!==false,'curl_init');
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json'],CURLOPT_HEADERFUNCTION=>static function($c,string $h)use(&$retryAfter):int{if(stripos($h,'Retry-After:')===0){$s=trim(substr($h,12));if(ctype_digit($s))$retryAfter=(int)$s;}return strlen($h);}]);
 $body=curl_exec($ch);$errno=curl_errno($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 $raw=is_string($body)?$body:'';$digest=is_string($body)?hash('sha256',$body):null;
 $meta=['operation_id'=>TAL_OP,'source_sha'=>$ctx['sha'],'sequence'=>$number,'stage'=>$stage,'http_status'=>$status,'curl_errno'=>$errno,'response_sha256'=>$digest,'response_bytes'=>strlen($raw),'retry_after_seconds'=>$retryAfter,'received_at_utc'=>gmdate('c'),'no_replay'=>true];
 tal_write($stem.'-response.json',$meta);$ctx['requests'][]=$meta;$ctx['last_response_sha256']=$digest;
 tal_require($errno===0&&is_string($body),'transport_unknown');if($status===429)throw new RuntimeException('rate_limited');if(in_array($status,[401,403],true))throw new RuntimeException('auth_rejected');tal_require($status>=200&&$status<300,'http_error_stop');tal_require(strlen($raw)<=16000000,'response_limit');$decoded=json_decode($raw,true,64,JSON_THROW_ON_ERROR);tal_require(is_array($decoded),'response_shape');return$decoded;
}
function tal_main():void{
 tal_require(PHP_SAPI==='cli'&&getenv('MATCH_OPERATION_ID')===TAL_OP,'operation_guard');$sha=(string)getenv('MATCH_SOURCE_SHA');tal_require(preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'source_guard');
 $base=(string)getenv('HOME').'/.anytoour-match/operations';$dir=$base.'/'.TAL_OP;$res=tal_read($dir.'/reservation.json');tal_require(($res['operation_id']??'')===TAL_OP&&($res['source_sha']??'')===$sha&&($res['state']??'')==='reserved_before_private_access','reservation_binding');
 $ctx=['dir'=>$dir,'sha'=>$sha,'attempts'=>0,'last_started'=>0.0,'requests'=>[]];$db=null;
 $out=['operation_id'=>TAL_OP,'source_sha'=>$sha,'credential_identifier'=>'TOURVISOR_ANEX_JWT','preflight_result_sha256'=>TAL_PREFLIGHT_SHA,'date'=>TAL_DATE,'http_attempt_cap'=>TAL_CAP,'provider_daily_remaining'=>null,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'andromeda_calls'=>0,'no_replay'=>true,'safe_to_write_now'=>false,'query_candidates_are_identity_proof'=>false,'rows'=>[],'batches'=>[],'holds'=>[]];
 ob_start();try{
  $root=realpath(getcwd());tal_require(is_string($root)&&basename($root)==='anytoour.ru','project_root');
  $pfPath=$base.'/'.TAL_PREFLIGHT.'/result.json';tal_require(hash_file('sha256',$pfPath)===TAL_PREFLIGHT_SHA,'preflight_hash');$pf=tal_read($pfPath);$pfr=tal_read($base.'/'.TAL_PREFLIGHT.'/receipt.json');tal_require(($pfr['result_sha256']??'')===TAL_PREFLIGHT_SHA&&($pfr['readback_verified']??false)===true,'preflight_receipt');
  $oldObs=[];foreach($pf['observations']as$r)$oldObs[(int)$r['anex_hotel_id']]=$r;$oldLoc=[];foreach($pf['local_hotels']as$r)$oldLoc[(int)$r['id']]=$r;
  $proved=[];foreach($pf['retained_account_telemetry']['operations']as$op){if(!$op['verified_terminal_receipt']||!preg_match('/^[a-zA-Z0-9_.-]+$/D',$op['operation_id']))continue;$p=$base.'/'.$op['operation_id'].'/result.json';if(!is_file($p)||is_link($p)||hash_file('sha256',$p)!==($op['sha256']['result.json']??null))continue;$saved=tal_read($p);foreach($saved['rows']??[]as$row)if(isset($row['anex_hotel_id'],$row['tourvisor_hotel_id'])&&in_array($row['identity_mode']??'',['legacy_hotellist','online_hotelcode'],true))$proved[(int)$row['anex_hotel_id'].':'.(int)$row['tourvisor_hotel_id']]=true;}
  $cfg=is_file($root.'/v2/config.php')?$root.'/v2/config.php':$root.'/config.php';tal_require(is_file($cfg)&&!is_link($cfg),'config_path');require_once$cfg;tal_require(defined('TOURVISOR_ANEX_JWT')&&trim((string)TOURVISOR_ANEX_JWT)!=='','account_unconfigured');$token=trim((string)TOURVISOR_ANEX_JWT);
  $bp=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';tal_require(!is_link($bp),'db_path');require_once$bp;$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
  $mapped=[];foreach(tal_select($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')as$r)$mapped[(int)$r['anex_hotel_id']]=(int)$r['catalog_hotel_id'];$manual=[];foreach(tal_select($db,'SELECT anex_hotel_id FROM anex_hotel_decisions')as$r)$manual[(int)$r['anex_hotel_id']]=true;
  $ex=[];foreach(tal_select($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')as$r)$ex[(int)$r['anex_hotel_id'].':'.(int)$r['catalog_hotel_id']]=true;
  $obs=[];foreach(tal_select($db,'SELECT anex_hotel_id,hotel_name,country_id,search_count FROM anex_search_hotel_observations')as$r)$obs[(int)$r['anex_hotel_id']]=$r;
  $staging=[];foreach(tal_select($db,'SELECT anex_hotel_id,latitude,longitude FROM anex_hotels')as$r)$staging[(int)$r['anex_hotel_id']]=$r;
  $locals=[];foreach(tal_select($db,'SELECT id,country_id,name,latitude,longitude FROM catalog_hotels WHERE is_active=1 AND country_id IN (1,4)')as$r)$locals[(int)$r['id']]=$r;
  $batches=[];foreach(TAL_PAIRS as$aid=>$lid){$reason=null;$s=$obs[$aid]??null;$h=$locals[$lid]??null;
   if(isset($mapped[$aid])||isset($manual[$aid])||isset($ex[$aid.':'.$lid]))$reason='current_protected_or_resolved';
   elseif(isset($proved[$aid.':'.$lid]))$reason='direct_pair_previously_proven';
   elseif(!$s||!$h||(int)$s['country_id']!==(int)$h['country_id'])$reason='country_or_active';
   elseif($s['hotel_name']!==($oldObs[$aid]['hotel_name']??null)||$h['name']!==($oldLoc[$lid]['name']??null))$reason='names_changed_since_preflight';
   else{$sp=tal_point($staging[$aid]??[]);$hp=tal_point($h);if($sp&&$hp&&tal_km($sp,$hp)>5)$reason='coordinate_conflict_gt5km';}
   if($reason){$out['holds'][]=['anex_hotel_id'=>$aid,'local_hotel_id'=>$lid,'reason'=>$reason];continue;}
   $batches[(int)$s['country_id']][]=['anex_hotel_id'=>$aid,'local_hotel_id'=>$lid,'source_name'=>$s['hotel_name'],'local_name'=>$h['name'],'search_count'=>(int)$s['search_count']];
  }
  $db->exec('ROLLBACK');$out['current_read_at_utc']=gmdate('c');$out['current_query_targets']=array_merge(...array_values($batches?:[[]]));tal_write($dir.'/current-query-plan.json',['operation_id'=>TAL_OP,'source_sha'=>$sha,'batches'=>$batches,'holds'=>$out['holds'],'no_replay'=>true,'mapping_writes'=>0]);tal_require($batches!==[],'no_current_query_targets');
  $dep=tal_find(tal_call('departures','/departures',[],$token,$ctx),['моск','moscow']);tal_require($dep!==null,'departure_unresolved');$countries=tal_call('countries','/countries',['departureId'=>$dep['id'],'onlyCharter'=>false,'onlyDirect'=>false],$token,$ctx);
  foreach($batches as$cid=>$targets){$country=tal_find($countries,$cid===1?['егип','egypt']:['турц','turkey']);tal_require($country!==null,'country_unresolved');$ops=tal_call('operators','/operators',['departureId'=>$dep['id'],'countryId'=>$country['id']],$token,$ctx);$operator=tal_find($ops,['anex','анекс']);tal_require($operator!==null&&tal_anex($operator),'operator_unresolved');
   $ids=array_column($targets,'local_hotel_id');$params=tal_params($dep['id'],$country['id'],$operator['id'],$ids);$search=tal_call('search_create','/tours/search',$params,$token,$ctx);$sid=tal_search_id($search);tal_require($sid!==null,'search_id_missing');tal_write($dir.'/search-'.$cid.'.json',['operation_id'=>TAL_OP,'source_sha'=>$sha,'search_id'=>$sid,'country_id'=>$cid,'local_hotel_ids'=>$ids,'response_sha256'=>$ctx['last_response_sha256'],'no_replay'=>true]);
   $complete=false;foreach([8,12,15]as$wait){sleep($wait);$status=tal_call('search_status','/tours/search/'.$sid.'/status',['operatorStatus'=>false],$token,$ctx);if(tal_complete($status)){$complete=true;break;}}
   if(!$complete){$out['batches'][]=['country_id'=>$cid,'search_id'=>$sid,'target_count'=>count($ids),'state'=>'not_complete_bounded'];continue;}
   $payload=tal_call('search_results','/tours/search/'.$sid,['limit'=>500],$token,$ctx);$flat=tal_flatten($payload);$seen=[];$selected=[];foreach($flat as$t){$h=is_array($t['hotel']??null)?$t['hotel']:[];$lid=tal_id($h['id']??null);$tid=tal_id($t['id']??null);if(!$lid||!$tid||isset($seen[$lid])||!in_array($lid,$ids,true)||!tal_anex($t['operator']??null))continue;$seen[$lid]=true;$selected[]=['local_hotel_id'=>$lid,'tour_id'=>$tid,'search_hotel_name'=>tal_name($h)];}
   $out['batches'][]=['country_id'=>$cid,'search_id'=>$sid,'target_count'=>count($ids),'flattened_rows'=>count($flat),'selected_hotel_count'=>count($selected),'response_sha256'=>$ctx['last_response_sha256'],'state'=>'results_read'];
   foreach($selected as$s){$detail=tal_call('tour_detail','/tours/'.$s['tour_id'],['currency'=>'RUB'],$token,$ctx);$h=is_array($detail['hotel']??null)?$detail['hotel']:[];$proof=tal_native(is_string($detail['operatorLink']??null)?$detail['operatorLink']:'');$reason=null;
    if(tal_id($h['id']??null)!==$s['local_hotel_id'])$reason='detail_local_id_mismatch';elseif(!tal_anex($detail['operator']??null))$reason='detail_operator_mismatch';elseif(!$proof)$reason='explicit_native_link_missing';
    $row=$s+['country_id'=>$cid,'detail_hotel_name'=>tal_name($h),'operator_name'=>tal_name($detail['operator']??null),'detail_response_sha256'=>$ctx['last_response_sha256'],'request_sequence'=>$ctx['attempts'],'native_link'=>$proof,'safe_to_write_now'=>false,'requires_current_acceptance'=>true];
    if($proof){$aid=$proof['anex_hotel_id'];$lid=$s['local_hotel_id'];$row['anex_hotel_id']=$aid;$row['observed_source_name']=$obs[$aid]['hotel_name']??null;$row['proposal_native_agrees']=(TAL_PAIRS[$aid]??null)===$lid;$row['existing_mapping_local_id']=$mapped[$aid]??null;
     if(isset($manual[$aid])||isset($ex[$aid.':'.$lid]))$reason='returned_native_protected';elseif(isset($mapped[$aid]))$reason=$mapped[$aid]===$lid?'existing_same_pair':'existing_mapping_conflict';
     if(isset($obs[$aid])&&(int)$obs[$aid]['country_id']!==$cid)$reason='returned_native_country_conflict';$sp=tal_point($staging[$aid]??[]);$hp=tal_point($h)??tal_point(is_array($h['common']??null)?$h['common']:[]);$row['distance_km']=$sp&&$hp?tal_km($sp,$hp):null;if($row['distance_km']!==null&&$row['distance_km']>5)$reason='coordinate_conflict_gt5km';
    }
    $row['route']=$reason??'direct_identity_evidence_prepared';$out['rows'][]=$row;tal_write($dir.'/detail-'.$s['local_hotel_id'].'.json',['operation_id'=>TAL_OP,'source_sha'=>$sha,'evidence'=>$row,'no_replay'=>true]);
   }
  }
  $out['state']='completed_read_only';
 }catch(Throwable$e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$out['state']='stopped_no_replay';$out['reason']=preg_match('/^[a-z_]{3,80}$/D',$e->getMessage())?$e->getMessage():'sanitized_failure';}
 while(ob_get_level())ob_end_clean();$out['tourvisor_http_attempts']=$ctx['attempts'];$out['supplier_calls']=$ctx['attempts'];$out['request_results']=$ctx['requests'];$out['completed_at_utc']=gmdate('c');$out['direct_identities']=count(array_filter($out['rows'],fn($r)=>$r['native_link']!==null));$out['prepared_direct_identities']=count(array_filter($out['rows'],fn($r)=>$r['route']==='direct_identity_evidence_prepared'));
 $hash=tal_write($dir.'/result.json',$out);tal_write($dir.'/receipt.json',['operation_id'=>TAL_OP,'source_sha'=>$sha,'state'=>$out['state'],'result_sha256'=>$hash,'readback_verified'=>true,'tourvisor_http_attempts'=>$ctx['attempts'],'mapping_writes'=>0,'no_replay'=>true]);echo tal_json(['state'=>$out['state'],'reason'=>$out['reason']??null,'http_attempts'=>$ctx['attempts'],'direct_identities'=>$out['direct_identities'],'prepared_direct_identities'=>$out['prepared_direct_identities'],'result_sha256'=>$hash]);if($out['state']!=='completed_read_only')exit(2);
}
if(in_array('--self-test',$argv??[],true)){
 $n=0;$ok=static function(bool $v)use(&$n){tal_require($v,'self_test');$n++;};$ok(count(TAL_PAIRS)===25&&count(array_unique(TAL_PAIRS))===25);
 $p=tal_params(1,1,13,[97122,293]);$ok($p===['departureId'=>1,'countryId'=>1,'dateFrom'=>TAL_DATE,'dateTo'=>TAL_DATE,'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'operatorIds'=>[13],'currency'=>'RUB','onlyCharter'=>false,'hotelIds'=>[97122,293]]);
 $ok(tal_query(['a'=>[1,2],'b'=>false,'c'=>null,'d'=>''])==='a=1&a=2&b=false');foreach([[],[1,1],range(1,31)]as$ids){$refused=false;try{tal_params(1,1,13,$ids);}catch(Throwable$e){$refused=true;}$ok($refused);}
 $ok(tal_id(true)===null&&tal_id('001')===null&&tal_id('123')===123);
 foreach(['https://agent.anextour.ru/search/tour?HOTELLIST=4158','https://online.anextour.ru/search?hotelCode=4158']as$url)$ok(tal_native($url)['anex_hotel_id']===4158);
 foreach(['https://evil.test/search/tour?HOTELLIST=1','http://agent.anextour.ru/search/tour?HOTELLIST=1','https://agent.anextour.ru/search/tour?HOTELLIST=1&HOTELLIST=2','https://agent.anextour.ru/search/tour?HOTELLIST=1,2','https://u:p@agent.anextour.ru/search/tour?HOTELLIST=1']as$url)$ok(tal_native($url)===null);
 $proof=tal_native('https://agent.anextour.ru/search/tour?token=private&HOTELLIST=42');$ok($proof['identity_url']==='https://agent.anextour.ru/search/tour?HOTELLIST=42'&&!str_contains(tal_json($proof),'private'));
 $ok(tal_find([['id'=>1,'name'=>'Москва']],['моск'])['id']===1);$ok(tal_find([['id'=>1,'name'=>'Москва'],['id'=>2,'name'=>'Москва2']],['моск'])===null);$ok(tal_anex(['name'=>'ANEX Tour'])&&!tal_anex(['name'=>'Another']));
 $ok(tal_point(['latitude'=>0,'longitude'=>0])===null);$ok(tal_km([36,31],[37,31])>100);$ok(tal_complete(['data'=>['progress'=>100]])&&!tal_complete(['progress'=>10]));$ok(tal_search_id(['searchId'=>123])===123);
 $ok(count(tal_flatten(['hotels'=>[['id'=>3,'name'=>'A','tours'=>[['id'=>7,'operator'=>['name'=>'ANEX']]]]]]))===1);
 $d=sys_get_temp_dir().'/tal-'.bin2hex(random_bytes(6));mkdir($d);tal_write($d.'/x.json',['ok'=>true]);$failed=false;try{tal_write($d.'/x.json',['ok'=>false]);}catch(Throwable$e){$failed=true;}$ok($failed);unlink($d.'/x.json');rmdir($d);echo "$n Tourvisor read checks PASS\n";exit;
}
tal_main();
