<?php
declare(strict_types=1);
// MATCH-only receiver: saved positive evidence + CURRENT read. Never writes DB.
const MS5_OP = 'hotel-match-tv30-anex-saved5-current-audit-1971-20260919-v1';
const MS5_PAIRS = [15072=>1244,43090=>115349,24940=>9427,35127=>967,8365=>1441];
const MS5_INPUTS = [
 'turkey-result.json'=>'35bfb2755cbf274be264437cd4cf20ce256e52f12f823bd0629cc52afe2ecfc4',
 'turkey-partial-6.json'=>'faf6c5e31c60f3459673c8784fc3d6fbe53bb5c6c6f3359a0cba78e07bb9200d',
 'one-result.json'=>'14c867a1865d62b161b3bde645cbec055b17a408360d6389a38ab2395cff9feb',
 'one-fresh-results.json'=>'24eab26bc0ecb07a93d2f1a3fdc8b0dbf6b4aedf43451b453e342ed68e549dd9',
 'one-anex-tour-detail.json'=>'9bf10da8d36e3339a75700f4c4ef8c95acd7e58ff889bc55ca97e2a2110920da',
];
function ms5_json(mixed $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function ms5_put(string $p,array $v):string{
 $s=ms5_json($v)."\n";$f=@fopen($p,'x+b');if(!$f)throw new RuntimeException('output_exists');
 try{if(fwrite($f,$s)!==strlen($s)||!fflush($f))throw new RuntimeException('output_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('output_sync');}finally{fclose($f);}
 if(file_get_contents($p)!==$s)throw new RuntimeException('output_readback');return hash('sha256',$s);
}
function ms5_rows(PDO $db,string $sql,array $p=[]):array{$s=$db->prepare($sql);$s->execute(array_values($p));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function ms5_ident(string $s):string{if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D',$s))throw new RuntimeException('sql_identifier');return '`'.$s.'`';}
function ms5_cols(PDO $db,string $t):array{return array_column(ms5_rows($db,'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$t]),'COLUMN_NAME');}
function ms5_link(string $url):int{
 $p=parse_url($url);if(!is_array($p)||($p['scheme']??'')!=='https'||strtolower($p['host']??'')!=='agent.anextour.ru'||($p['path']??'')!=='/search/tour'||(isset($p['user'])||isset($p['pass']))||isset($p['port'])||isset($p['fragment']))throw new RuntimeException('link_origin');
 $ids=[];$count=0;foreach(explode('&',$p['query']??'') as $part){[$k,$v]=array_pad(explode('=',$part,2),2,'');$k=rawurldecode($k);$v=rawurldecode($v);if(preg_match('/token|password|auth|secret|session/i',$k))throw new RuntimeException('link_sensitive');if(strtoupper($k)==='HOTELLIST'){$count++;if(!preg_match('/^[1-9][0-9]{0,7}$/D',$v))throw new RuntimeException('link_ambiguous');$ids[]=(int)$v;}}
 if($count!==1)throw new RuntimeException('link_count');return $ids[0];
}
function ms5_room_key(string $s):string{$s=function_exists('mb_strtoupper')?mb_strtoupper($s,'UTF-8'):strtr(strtoupper($s),array_combine(preg_split('//u','абвгдеёжзийклмнопрстуфхцчшщъыьэюя',-1,PREG_SPLIT_NO_EMPTY),preg_split('//u','АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ',-1,PREG_SPLIT_NO_EMPTY)));$s=preg_replace('/(?<![\p{L}\p{N}])(?:ROOM|НОМЕР|КОМНАТА)(?![\p{L}\p{N}])/u',' ',$s)??$s;return trim(preg_replace('/\s+/u',' ',$s)??$s);}
function ms5_distance(array $a,array $b):?float{
 foreach(['latitude','longitude'] as $k)if(!isset($a[$k],$b[$k])||!is_numeric($a[$k])||!is_numeric($b[$k]))return null;
 $la=(float)$a['latitude'];$lb=(float)$b['latitude'];$oa=(float)$a['longitude'];$ob=(float)$b['longitude'];if(abs($la)>90||abs($lb)>90||abs($oa)>180||abs($ob)>180||($la==0&&$oa==0)||($lb==0&&$ob==0))return null;
 $d=sin(deg2rad($lb-$la)/2)**2+cos(deg2rad($la))*cos(deg2rad($lb))*sin(deg2rad($ob-$oa)/2)**2;return 6371000*2*asin(min(1,sqrt($d)));
}
function ms5_inputs(string $dir):array{
 $in=[];foreach(MS5_INPUTS as $name=>$sha){$p=$dir.'/'.$name;if(!is_file($p)||hash_file('sha256',$p)!==$sha)throw new RuntimeException('input_digest');$in[$name]=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);}
 $t=$in['turkey-result.json'];$one=$in['one-result.json'];$detail=$in['one-anex-tour-detail.json'];
 if(($t['operation']??'')!=='hotel-match-owner-tv30-turkey-anexlink-1971-20260919-v1'||($one['operation']??'')!=='hotel-match-owner-one-anex-operatorlink-1971-20260919-v1')throw new RuntimeException('input_operation');
 $anchors=[];foreach($t['anchors'] as $r)if(in_array((int)$r['local_id'],array_values(MS5_PAIRS),true))$anchors[(int)$r['local_id']]=$r;
 if((int)($detail['hotel']['id']??0)!==1441||(int)($detail['operator']['id']??0)!==13||(string)($detail['id']??'')!==(string)$one['fresh_anex_tour_id'])throw new RuntimeException('detail_tuple');
 $anchors[1441]=['local_id'=>1441,'local_name'=>$detail['hotel']['name'],'fresh_anex_tour_id'=>$one['fresh_anex_tour_id'],'native_ids'=>$one['native_ids'],'operator_link_fields'=>$one['operator_link_fields']];
 $hotels=[];foreach(array_merge($in['turkey-partial-6.json'],$in['one-fresh-results.json']) as $h)if(in_array((int)($h['id']??0),array_values(MS5_PAIRS),true))$hotels[(int)$h['id']]=$h;
 $out=[];foreach(MS5_PAIRS as $native=>$local){
  $a=$anchors[$local]??null;$h=$hotels[$local]??null;if(!$a||!$h||$a['native_ids']!==[$native]||(int)($h['country']['id']??0)!==4||$h['name']!==$a['local_name'])throw new RuntimeException('saved_identity');
  $links=array_values(array_filter($a['operator_link_fields'],fn($x)=>($x['key']??'')==='operatorLink'));
  if(count($links)!==1||ms5_link($links[0]['url'])!==$native)throw new RuntimeException('saved_link');
  $found=false;$rooms=[];foreach($h['tours']??[] as $tour){if((int)($tour['operator']['id']??0)!==13)continue;if((string)($tour['id']??'')===(string)$a['fresh_anex_tour_id'])$found=true;$raw=$tour['roomType']??null;if(!is_string($raw)||trim($raw)==='')continue;$rid=$tour['roomId']??null;$key=ms5_json([$raw,$rid]);$rooms[$key]=['provider'=>'tourvisor','operator_id'=>13,'raw_name'=>$raw,'room_id'=>$rid,'normalized_key'=>ms5_room_key($raw)];}
  if(!$found)throw new RuntimeException('saved_tour_binding');
  $out[]=['anex_hotel_id'=>$native,'catalog_hotel_id'=>$local,'tv_name'=>$h['name'],'tv_country'=>$h['country'],'tv_region'=>$h['region']??null,'tv_subregion'=>$h['subRegion']??null,'tv_coordinates'=>['latitude'=>$h['latitude']??null,'longitude'=>$h['longitude']??null],'operator_link'=>$links[0]['url'],'saved_tour_id'=>(string)$a['fresh_anex_tour_id'],'tv_rooms'=>array_values($rooms)];
 }
 return $out;
}
function ms5_native_projection(mixed $v,string $path='',int $depth=0):array{
 if($depth>8||!is_array($v))return [];$out=[];$keep=['anex_hotel_id','hotel_id','hotelkey','name','hotel','hotel_name','hotelname','country','country_id','country_name','state','statekey','town','townkey','town_id','town_name','region_name','latitude','longitude','lat','lon','lng','star'];
 foreach($v as $k=>$x){if(preg_match('/token|password|auth|secret|session|passport|tourist|booking|contact/i',(string)$k))continue;$p=$path===''?(string)$k:$path.'.'.$k;if(is_array($x)){$out+=ms5_native_projection($x,$p,$depth+1);continue;}if(in_array(strtolower((string)$k),$keep,true)&&is_scalar($x)&&strlen((string)$x)<=500&&!preg_match('/https?:\/\/|bearer\s/i',(string)$x))$out[$p]=$x;}return $out;
}
if(($argv[1]??'')==='--self-test'){
 $n=0;$ok=function(bool $v)use(&$n){$n++;if(!$v)throw new RuntimeException('selftest_'.$n);};
 $ok(ms5_link('https://agent.anextour.ru/search/tour?HOTELLIST=15072')===15072);
 foreach(['https://evil.invalid/search/tour?HOTELLIST=1','https://agent.anextour.ru/search/tour?HOTELLIST=1,2','https://agent.anextour.ru/search/tour?HOTELLIST=1&HOTELLIST=2','https://agent.anextour.ru/search/tour?HOTELLIST=1&token=x'] as $u){try{ms5_link($u);$ok(false);}catch(RuntimeException $e){if(str_starts_with($e->getMessage(),'selftest'))throw $e;$ok(true);}}
 $ok(ms5_room_key('FAMILY DELUXE SEA VIEW ROOM')==='FAMILY DELUXE SEA VIEW');$ok(ms5_room_key('НОМЕР FAMILY SUITE')==='FAMILY SUITE');$ok(ms5_room_key('BEDROOM')==='BEDROOM');$ok(ms5_distance(['latitude'=>36,'longitude'=>30],['latitude'=>36,'longitude'=>30])===0.0);$ok(ms5_distance(['latitude'=>null,'longitude'=>30],['latitude'=>36,'longitude'=>30])===null);
 $ok(ms5_native_projection(['token'=>'hidden','name'=>'Hotel','x'=>['town'=>'Town']])===['name'=>'Hotel','x.town'=>'Town']);
 if(isset($argv[2])){$v=ms5_inputs($argv[2]);$ok(count($v)===5);$ok(count(array_filter($v,fn($x)=>count($x['tv_rooms'])>0))===5);echo ms5_json(['saved_pairs'=>count($v),'hotel_local_tv_room_evidence'=>array_sum(array_map(fn($x)=>count($x['tv_rooms']),$v))])."\n";}
 echo 'MS5_SELFTEST_OK '.$n."\n";exit(0);
}
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');
$sha=(string)getenv('MATCH_SOURCE_SHA');$payload=realpath((string)getenv('MATCH_PAYLOAD_DIR'));$root=realpath((string)getenv('ANYTOUR_ROOT'));
if(getenv('OPERATION_ID')!==MS5_OP||!preg_match('/^[0-9a-f]{40}$/D',$sha)||!$root||basename($root)!=='anytoour.ru'||!$payload)throw new RuntimeException('execution_guard');
$base=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations';if(!is_dir($base))throw new RuntimeException('operations_root');$dir=$base.'/'.MS5_OP;if(!@mkdir($dir,0700))throw new RuntimeException('operation_exists');
ms5_put($dir.'/reservation.json',['operation_id'=>MS5_OP,'source_sha'=>$sha,'state'=>'reserved_before_db','created_at'=>gmdate('c'),'read_only'=>true,'no_replay'=>true]);
$db=null;$result=['operation_id'=>MS5_OP,'source_sha'=>$sha,'input_sha256'=>MS5_INPUTS,'supplier_calls'=>0,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,'no_replay'=>true];
try{
 $evidence=ms5_inputs($payload.'/inputs');$rf=$payload.'/anex-search-mapping-registry.php';$rb=(string)file_get_contents($rf);if(sha1('blob '.strlen($rb)."\0".$rb)!=='cc135a95d2a6e0f73ce50be2141c9b8a26fddc58')throw new RuntimeException('registry_digest');require_once $rf;
 require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
 $aids=array_keys(MS5_PAIRS);$lids=array_values(MS5_PAIRS);$ph=implode(',',array_fill(0,count($aids),'?'));$params=array_merge($aids,$lids);
 $registry=AnyTourAnexSearchMappingRegistry::fromPdo($db);
 $maps=ms5_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,approval_policy,enabled,scope FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($ph)",$params);
 $dec=ms5_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($ph)",$params);
 $exc=ms5_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($ph)",$params);
 $local=ms5_rows($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE id IN ($ph)",$lids);$byLocal=[];foreach($local as $h)$byLocal[(int)$h['id']]=$h;
 $andCols=ms5_cols($db,'andromeda_hotel_identities');$andPick=array_values(array_intersect(['supplier_namespace','supplier_hotel_id','external_hotel_id','hotel_id','local_hotel_id','decision_status'],$andCols));
 $ands=ms5_rows($db,'SELECT '.implode(',',array_map('ms5_ident',$andPick))." FROM andromeda_hotel_identities WHERE local_hotel_id IN ($ph) AND decision_status='accepted'",$lids);
 $schema=ms5_rows($db,"SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'anex%hotel%' ORDER BY TABLE_NAME,ORDINAL_POSITION");$tables=[];foreach($schema as $c)$tables[$c['TABLE_NAME']][]=$c['COLUMN_NAME'];
 $native=[];$nativeFields=['anex_hotel_id','hotel_name','name','country_id','country_name','state_id','state_name','town_id','town_name','region_id','region_name','category','star','latitude','longitude','observed_at','updated_at','evidence_sha256','payload_sha256','source_sha256','evidence_json','payload_json','normalized_json','raw_json'];
 foreach($tables as $t=>$cols){if(!preg_match('/staging|catalog|observation/',$t)||!in_array('anex_hotel_id',$cols,true))continue;$pick=array_values(array_intersect($nativeFields,$cols));$rs=ms5_rows($db,'SELECT '.implode(',',array_map('ms5_ident',$pick)).' FROM '.ms5_ident($t)." WHERE anex_hotel_id IN ($ph) LIMIT 1001",$aids);if(count($rs)>1000)throw new RuntimeException('native_row_cap');foreach($rs as $r){$p=$r;foreach($r as $k=>$v)if(str_ends_with($k,'_json')){unset($p[$k]);$p[$k.'_sha256']=hash('sha256',(string)$v);$j=json_decode((string)$v,true);$p[$k.'_identity_projection']=ms5_native_projection($j);}$native[]=['table'=>$t,'row'=>$p];}}
 $clock=ms5_rows($db,'SELECT CURRENT_TIMESTAMP db_time,UTC_TIMESTAMP utc_time,@@session.time_zone session_timezone')[0]??[];
 $dossiers=[];$counts=['already_accepted'=>0,'current_hold'=>0,'needs_native_identity_review'=>0];
 foreach($evidence as $e){$aid=$e['anex_hotel_id'];$lid=$e['catalog_hotel_id'];$h=$byLocal[$lid]??null;$m=array_values(array_filter($maps,fn($r)=>(int)$r['anex_hotel_id']===$aid||(int)$r['catalog_hotel_id']===$lid));$d=array_values(array_filter($dec,fn($r)=>(int)$r['anex_hotel_id']===$aid||(int)$r['catalog_hotel_id']===$lid));$x=array_values(array_filter($exc,fn($r)=>(int)$r['anex_hotel_id']===$aid||(int)$r['catalog_hotel_id']===$lid));$dist=$h?ms5_distance($e['tv_coordinates'],$h):null;$hold=[];
  if(!$h||(int)$h['is_active']!==1)$hold[]='missing_or_inactive_local';if(!$h||(int)$h['country_id']!==4)$hold[]='country_mismatch';if($dist!==null&&$dist>5000)$hold[]='saved_tv_current_geo_over_5km';
  foreach($m as $r){if((int)$r['anex_hotel_id']===$aid&&(int)$r['catalog_hotel_id']!==$lid)$hold[]='source_occupied_other';if((int)$r['catalog_hotel_id']===$lid&&(int)$r['anex_hotel_id']!==$aid)$hold[]='target_occupied_other';}
  if($d)$hold[]='manual_decision_present';foreach($x as $r)if((int)$r['anex_hotel_id']===$aid&&(int)$r['catalog_hotel_id']===$lid)$hold[]='pair_excluded';
  $effective=$registry->resolve('anex_online',(string)$aid,'preview');$route=$effective===$lid?'already_accepted':($hold?'current_hold':'needs_native_identity_review');$counts[$route]++;
  $dossiers[]=$e+['local'=>$h,'effective_accepted_target'=>$effective,'route'=>$route,'current_holds'=>array_values(array_unique($hold)),'mapping_rows'=>$m,'manual_rows'=>$d,'exclusion_rows'=>$x,'saved_tv_current_geo_distance_m'=>$dist,'accepted_andromeda'=>array_values(array_filter($ands,fn($r)=>(int)$r['local_hotel_id']===$lid)),'native_saved_evidence'=>array_values(array_filter($native,fn($r)=>(int)$r['row']['anex_hotel_id']===$aid)),'safe_to_write_now'=>false];
 }
 $db->exec('ROLLBACK');$result+=['status'=>'completed_read_only','db_clock'=>$clock,'counts'=>$counts,'dossiers'=>$dossiers,'native_schema'=>$schema,'hotel_local_tv_room_evidence'=>array_sum(array_map(fn($x)=>count($x['tv_rooms']),$evidence))];
}catch(Throwable $e){try{if($db&&$db->inTransaction())$db->rollBack();}catch(Throwable $ignored){}$result+=['status'=>'blocked','reason_class'=>get_class($e),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null,'driver_code'=>$e instanceof PDOException?($e->errorInfo[1]??null):null,'reason_code'=>preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'database_or_runtime_error'];}
$hash=ms5_put($dir.'/result.json',$result);ms5_put($dir.'/receipt.json',['operation_id'=>MS5_OP,'source_sha'=>$sha,'state'=>$result['status'],'result_sha256'=>$hash,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'no_replay'=>true]);echo ms5_json(['operation_id'=>MS5_OP,'status'=>$result['status'],'counts'=>$result['counts']??null,'result_sha256'=>$hash])."\n";exit($result['status']==='completed_read_only'?0:2);
