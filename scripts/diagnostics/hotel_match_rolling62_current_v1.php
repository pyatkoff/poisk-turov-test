<?php
declare(strict_types=1);
// Read-only receiving audit. This diagnostic has no mapping write authority.
const R62_OP='hotel-match-rolling62-current-1971-20260919-v1';
const R62_RESULT='d8373742943c465dede9cbbe0a8dbab2b8b5bdf12d3c32980da73d99be0ef1f9';
function r62_json(array $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function r62_save(string $p,array $x):string{
 $raw=r62_json($x);$f=@fopen($p,'xb');if(!$f)throw new RuntimeException('immutable_output_exists');
 try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('short_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('fsync');}finally{fclose($f);}
 if(file_get_contents($p)!==$raw)throw new RuntimeException('readback');return hash('sha256',$raw);
}
function r62_rows(PDO $d,string $q,array $p=[]):array{$s=$d->prepare($q);$s->execute(array_values($p));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function r62_dist(array $a,array $b):?float{
 foreach(['latitude','longitude'] as $k)if(!isset($a[$k],$b[$k])||!is_numeric($a[$k])||!is_numeric($b[$k]))return null;
 $la=(float)$a['latitude'];$lb=(float)$b['latitude'];$oa=(float)$a['longitude'];$ob=(float)$b['longitude'];
 if(abs($la)>90||abs($lb)>90||abs($oa)>180||abs($ob)>180||($la==0&&$oa==0)||($lb==0&&$ob==0))return null;
 return 12742000*asin(min(1,sqrt(sin(deg2rad($lb-$la)/2)**2+cos(deg2rad($la))*cos(deg2rad($lb))*sin(deg2rad($ob-$oa)/2)**2)));
}
function r62_country(string $s):string{
 $s=function_exists('mb_strtolower')?mb_strtolower(trim($s),'UTF-8'):strtr(strtolower(trim($s)),array_combine(preg_split('//u','АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ',-1,PREG_SPLIT_NO_EMPTY),preg_split('//u','абвгдеёжзийклмнопрстуфхцчшщъыьэюя',-1,PREG_SPLIT_NO_EMPTY)));foreach(['turkey'=>['турция','turkey','türkiye'],'egypt'=>['египет','egypt'],'vietnam'=>['вьетнам','vietnam','viet nam'],'uae'=>['оаэ','uae','united arab emirates','объединенные арабские эмираты'],'qatar'=>['катар','qatar']] as $key=>$names)if(in_array($s,$names,true))return $key;return $s;
}
if(($argv[1]??'')==='--self-test'){
 if(r62_dist(['latitude'=>36,'longitude'=>30],['latitude'=>36,'longitude'=>30])!==0.0||r62_country('ОАЭ')!==r62_country('United Arab Emirates'))throw new RuntimeException('test');
 if(r62_dist([],[])!==null||r62_dist(['latitude'=>90,'longitude'=>0],['latitude'=>-90,'longitude'=>0])<5000)throw new RuntimeException('distance_test');
 echo "ROLLING62_CURRENT_SELFTEST_OK\n";exit;
}
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');
$dir=realpath((string)getenv('MATCH_OPERATION_DIR'));$root=realpath((string)getenv('ANYTOUR_ROOT'));
if(!$dir||!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('paths');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,512,JSON_THROW_ON_ERROR);
if(($res['operation']??'')!==R62_OP||($res['state']??'')!=='reserved_before_db')throw new RuntimeException('reservation');
$base=['operation'=>R62_OP,'source_sha'=>$res['source_sha'],'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,'no_replay'=>true];$db=null;
try{
 $input=$dir.'/payload/input.json';if(hash_file('sha256',$input)!==$res['input_sha256'])throw new RuntimeException('input_digest');
 $in=json_decode((string)file_get_contents($input),true,512,JSON_THROW_ON_ERROR);$pairs=$in['pairs'];
 if($in['source_result_sha256']!==R62_RESULT||count($pairs)!==62||count(array_unique(array_column($pairs,'native_anex_id')))!==62||count(array_unique(array_column($pairs,'hotel_id')))!==62)throw new RuntimeException('pair_set');
 foreach($pairs as $p)if($p['operator_id']!==13||$p['raw_signed_tokens']!==[(string)$p['native_anex_id']]||$p['safe_to_write_now']!==false)throw new RuntimeException('pair_binding');
 $rf=$dir.'/payload/anex-search-mapping-registry.php';$raw=(string)file_get_contents($rf);if(sha1('blob '.strlen($raw)."\0".$raw)!=='cc135a95d2a6e0f73ce50be2141c9b8a26fddc58')throw new RuntimeException('registry_digest');require_once $rf;
 require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
 $a=array_column($pairs,'native_anex_id');$l=array_column($pairs,'hotel_id');$ph=implode(',',array_fill(0,62,'?'));$both=array_merge($a,$l);
 $maps=r62_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($ph)",$both);
 $dec=r62_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($ph)",$both);
 $exc=r62_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($ph)",$both);
 $locals=r62_rows($db,"SELECT id,name,country_id,country_name,region_name,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE id IN ($ph)",$l);
 $native=r62_rows($db,"SELECT anex_hotel_id,xml_name,xml_alternate_name,api_name,api_country,api_region,api_town,latitude,longitude,source_fingerprint FROM anex_hotels WHERE anex_hotel_id IN ($ph)",$a);
 $auto=r62_rows($db,"SELECT anex_hotel_id,row_digest,source_fingerprint,original_status,automated_status,automated_reason,suggested_catalog_hotel_id,candidate_count FROM anex_hotel_auto_matches WHERE anex_hotel_id IN ($ph)",$a);
 $and=r62_rows($db,"SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IN ($ph)",$l);
 $byLocal=array_column($locals,null,'id');$byNative=array_column($native,null,'anex_hotel_id');$byAuto=array_column($auto,null,'anex_hotel_id');$andIds=array_fill_keys(array_column($and,'local_hotel_id'),true);$reg=AnyTourAnexSearchMappingRegistry::fromPdo($db);
 $counts=['already_accepted'=>0,'current_hold'=>0,'unoccupied_needs_identity_review'=>0];$reasonCounts=[];$out=[];
 foreach($pairs as $p){$aid=$p['native_anex_id'];$lid=$p['hotel_id'];$h=$byLocal[$lid]??null;$n=$byNative[$aid]??null;$au=$byAuto[$aid]??null;$holds=[];
  $m=array_values(array_filter($maps,fn($r)=>(int)$r['anex_hotel_id']===$aid||(int)$r['catalog_hotel_id']===$lid));
  $ds=array_values(array_filter($dec,fn($r)=>(int)$r['anex_hotel_id']===$aid||(int)$r['catalog_hotel_id']===$lid));
  $xs=array_values(array_filter($exc,fn($r)=>(int)$r['anex_hotel_id']===$aid||(int)$r['catalog_hotel_id']===$lid));
  if(!$h||(int)$h['is_active']!==1)$holds[]='local_missing_inactive';if(!$h||(int)$h['country_id']!==$p['country_id'])$holds[]='country_mismatch';
  foreach($m as $r){if((int)$r['anex_hotel_id']===$aid&&(int)$r['catalog_hotel_id']!==$lid)$holds[]='source_occupied_other';if((int)$r['catalog_hotel_id']===$lid&&(int)$r['anex_hotel_id']!==$aid)$holds[]='target_occupied_other';if((int)$r['anex_hotel_id']===$aid&&(int)$r['catalog_hotel_id']===$lid)$holds[]='existing_row_preserved';}
  if($ds)$holds[]='manual_decision_preserved';foreach($xs as $x)if((int)$x['anex_hotel_id']===$aid&&(int)$x['catalog_hotel_id']===$lid)$holds[]='pair_excluded';
  $tvDist=$h?r62_dist($p['tv_hotel'],$h):null;$nativeDist=$h&&$n?r62_dist($n,$h):null;
  if($tvDist!==null&&$tvDist>5000)$holds[]='tv_current_geo_over_5km';if($nativeDist!==null&&$nativeDist>5000)$holds[]='native_geo_over_5km';
  if($n&&trim((string)$n['api_country'])!==''&&$h&&r62_country((string)$n['api_country'])!==r62_country((string)$h['country_name']))$holds[]='native_country_mismatch';
  if($au&&preg_match('/conflict|exclu|reject|manual|competing|ambig|mismatch|review/i',implode(' ',array_map('strval',$au))))$holds[]='saved_protected_native_review';
  $effective=$reg->resolve('anex_online',(string)$aid,'preview');$route=$effective===$lid?'already_accepted':($holds?'current_hold':'unoccupied_needs_identity_review');$counts[$route]++;
  $holds=array_values(array_unique($holds));foreach($holds as $hold)$reasonCounts[$hold]=($reasonCounts[$hold]??0)+1;
  $out[]=$p+['current_local'=>$h,'native_catalog'=>$n,'native_auto_review'=>$au,'mapping_rows'=>$m,'manual_rows'=>$ds,'exclusion_rows'=>$xs,'has_accepted_andromeda'=>isset($andIds[$lid]),'effective_accepted_target'=>$effective,'tv_current_distance_m'=>$tvDist,'native_current_distance_m'=>$nativeDist,'holds'=>$holds,'route'=>$route];
 }
 $clock=r62_rows($db,'SELECT UTC_TIMESTAMP AS db_utc_timestamp')[0];$db->exec('ROLLBACK');$result=$base+['state'=>'completed_read_only','captured_at'=>$clock['db_utc_timestamp'],'counts'=>$counts,'hold_reasons'=>$reasonCounts,'dossiers'=>$out,'input_sha256'=>$res['input_sha256']];
}catch(Throwable $e){try{if($db&&$db->inTransaction())$db->rollBack();}catch(Throwable $ignored){}$result=$base+['state'=>'blocked','reason_class'=>get_class($e),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null,'driver_code'=>$e instanceof PDOException?($e->errorInfo[1]??null):null,'reason'=>preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'runtime_or_database_error'];}
$sha=r62_save($dir.'/result.json',$result);r62_save($dir.'/receipt.json',['operation'=>R62_OP,'state'=>$result['state'],'result_sha256'=>$sha,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$sha,'no_replay'=>true]);
echo r62_json(['state'=>$result['state'],'counts'=>$result['counts']??null,'result_sha256'=>$sha]);exit($result['state']==='completed_read_only'?0:2);
