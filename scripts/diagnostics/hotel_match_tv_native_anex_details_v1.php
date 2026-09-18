<?php
declare(strict_types=1);

const HMTNAD_OPERATION = 'hotel-match-tv-native-anex-details-1971-20260918-v1';
const HMTNAD_SOURCE_RESULT_SHA256 = '06f1ad32e17e72bd5b2c1ad4fce69d3d765ea455926378710dfe8fc63954930b';
const HMTNAD_MAX_DETAILS = 40;

function hmtnad_norm(mixed $v): string {
    $v = mb_strtolower(trim(is_scalar($v) ? (string)$v : ''), 'UTF-8');
    $v = strtr($v, ['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i','&'=>' ','+'=>' ','_'=>' ','-'=>' ']);
    $v = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $v) ?? $v;
    return trim(preg_replace('/\s+/u', ' ', $v) ?? $v);
}
function hmtnad_tokens(mixed $v): array {
    $drop=['hotel'=>1,'hotels'=>1,'otel'=>1,'отель'=>1,'отели'=>1,'гостиница'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'the'=>1,'and'=>1,'ex'=>1];
    $out=[]; foreach (preg_split('/\s+/u', hmtnad_norm($v), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $t) if (!isset($drop[$t])) $out[$t]=true;
    return array_map('strval', array_keys($out));
}
function hmtnad_key(mixed $v): string { $t=hmtnad_tokens($v); sort($t,SORT_STRING); return implode(' ',$t); }
function hmtnad_nums(mixed $v): array { $o=[]; foreach(hmtnad_tokens($v) as $t) if(preg_match('/^[0-9]+$/D',$t)) $o[$t]=true; $k=array_map('strval',array_keys($o)); sort($k,SORT_STRING); return $k; }
function hmtnad_quals(mixed $v): array {
    $q=['family'=>1,'beach'=>1,'garden'=>1,'gardens'=>1,'aqua'=>1,'aquapark'=>1,'aquamarine'=>1,'club'=>1,'grand'=>1,'select'=>1,'adult'=>1,'adults'=>1,'north'=>1,'south'=>1,'boutique'=>1,'palace'=>1,'royal'=>1,'premium'=>1,'deluxe'=>1,'suite'=>1,'suites'=>1,'sea'=>1,'view'=>1];
    $o=[]; foreach(hmtnad_tokens($v) as $t) if(isset($q[$t])) $o[$t]=true; $k=array_map('strval',array_keys($o)); sort($k,SORT_STRING); return $k;
}
function hmtnad_score(mixed $a,mixed $b): int {
    $ak=hmtnad_key($a); $bk=hmtnad_key($b); if($ak===''||$bk==='') return 0; if($ak===$bk) return 100;
    $aa=array_fill_keys(hmtnad_tokens($a),true); $bb=array_fill_keys(hmtnad_tokens($b),true); $c=count(array_intersect_key($aa,$bb)); if($c===0) return 0;
    $dice=(2*$c)/(count($aa)+count($bb)); $lev=0.0; $an=hmtnad_norm($a); $bn=hmtnad_norm($b);
    if(strlen($an)<240&&strlen($bn)<240){$m=max(strlen($an),strlen($bn));if($m>0)$lev=max(0,1-(levenshtein($an,$bn)/$m));}
    return (int)round(100*max($dice,$lev));
}
function hmtnad_country_key(mixed $v): string {
    $n=hmtnad_norm($v); $map=['turkey'=>'turkey','turkiye'=>'turkey','türkiye'=>'turkey','турция'=>'turkey','egypt'=>'egypt','египет'=>'egypt','thailand'=>'thailand','таиланд'=>'thailand','тайланд'=>'thailand','maldives'=>'maldives','мальдивы'=>'maldives','uae'=>'uae','оаэ'=>'uae','united arab emirates'=>'uae','vietnam'=>'vietnam','вьетнам'=>'vietnam','china'=>'china','китай'=>'china','india'=>'india','индия'=>'india','cuba'=>'cuba','куба'=>'cuba','qatar'=>'qatar','катар'=>'qatar','mauritius'=>'mauritius','маврикий'=>'mauritius','sri lanka'=>'srilanka','шри ланка'=>'srilanka','uzbekistan'=>'uzbekistan','узбекистан'=>'uzbekistan','tanzania'=>'tanzania','танзания'=>'tanzania','indonesia'=>'indonesia','индонезия'=>'indonesia']; return $map[$n]??$n;
}
function hmtnad_dist($a,$b,$c,$d): ?float {
    foreach([$a,$b,$c,$d] as $v) if(!is_numeric($v)) return null;
    $lat1=deg2rad((float)$a);$lon1=deg2rad((float)$b);$lat2=deg2rad((float)$c);$lon2=deg2rad((float)$d);
    $x=sin(($lat2-$lat1)/2)**2+cos($lat1)*cos($lat2)*sin(($lon2-$lon1)/2)**2; return 6371000*2*asin(min(1,sqrt($x)));
}
function hmtnad_place(array $detail,array $local): bool {
    $src=[]; foreach(['state','region','town','address'] as $k){$n=hmtnad_norm($detail[$k]??'');if($n!=='')$src[]=$n;}
    $dst=[]; foreach(['region_name','subregion_name'] as $k){$n=hmtnad_norm($local[$k]??'');if($n!=='')$dst[]=$n;}
    foreach($src as $a) foreach($dst as $b) if($a===$b || (mb_strlen($a,'UTF-8')>=5 && mb_strlen($b,'UTF-8')>=5 && (str_contains($a,$b)||str_contains($b,$a)))) return true;
    return false;
}
function hmtnad_rows(PDO $db,string $sql,array $p=[]): array { $s=$db->prepare($sql);$s->execute(array_values($p));return$s->fetchAll(PDO::FETCH_ASSOC)?:[]; }
function hmtnad_scalar(array $a,string $k): string { $v=$a[$k]??''; return is_scalar($v)?trim((string)$v):''; }
function hmtnad_detail(array $p): array {
    return [
      'id'=>hmtnad_scalar($p,'id') ?: hmtnad_scalar($p,'hotelKey'),
      'name'=>hmtnad_scalar($p,'name') ?: hmtnad_scalar($p,'hotel'),
      'country'=>hmtnad_scalar($p,'country'),
      'state'=>hmtnad_scalar($p,'state'),
      'region'=>hmtnad_scalar($p,'region'),
      'town'=>hmtnad_scalar($p,'town'),
      'address'=>hmtnad_scalar($p,'address'),
      'latitude'=>(isset($p['latitude'])&&is_numeric($p['latitude']))?(float)$p['latitude']:null,
      'longitude'=>(isset($p['longitude'])&&is_numeric($p['longitude']))?(float)$p['longitude']:null,
      'townKey'=>hmtnad_scalar($p,'townKey'),
      'starKey'=>hmtnad_scalar($p,'starKey'),
    ];
}
function hmtnad_save(string $path,array $v): string { $raw=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n"; $f=@fopen($path,'x+b'); if(!$f) throw new RuntimeException('durable_exists'); try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('durable_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('durable_sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('durable_readback');} finally{fclose($f);} return hash('sha256',$raw); }

if(in_array('--self-test',$argv??[],true)){
    if(hmtnad_key('HOTEL SU (EX. SU & AQUALAND; HILLSIDE SU)')==='')throw new RuntimeException('key');
    if(hmtnad_nums('Royal 16+')!==['16'])throw new RuntimeException('nums');
    if(hmtnad_quals('FAMILY SEA VIEW DELUXE')!==['deluxe','family','sea','view'])throw new RuntimeException('quals');
    if(hmtnad_country_key('Танзания')!=='tanzania')throw new RuntimeException('country');
    $d=['state'=>'Занзибар','region'=>'','town'=>'Нунгви','address'=>''];$l=['region_name'=>'Занзибар','subregion_name'=>'Нунгви'];
    if(!hmtnad_place($d,$l))throw new RuntimeException('place');
    echo "MATCH_TV_NATIVE_ANEX_DETAILS_V1_SELFTEST_OK\n"; exit(0);
}
if(PHP_SAPI!=='cli') exit(2);

$root=realpath((string)getenv('ANYTOUR_ROOT')); $opdir=(string)getenv('MATCH_OPERATION_DIR'); $source=(string)getenv('MATCH_SOURCE_RESULT'); $sourceSha=(string)getenv('MATCH_SOURCE_RESULT_SHA256');
if(!$root||$opdir===''||!is_dir($opdir)||!is_file($opdir.'/reservation.json')||!is_file($source)||$sourceSha!==HMTNAD_SOURCE_RESULT_SHA256) throw new RuntimeException('runtime_guard');
$raw=(string)file_get_contents($source); if(hash('sha256',$raw)!==HMTNAD_SOURCE_RESULT_SHA256) throw new RuntimeException('source_hash');
$src=json_decode($raw,true,128,JSON_THROW_ON_ERROR); if(($src['status']??'')!=='completed_read_only'||(int)($src['needs_saved_geo_or_name_count']??-1)!==40) throw new RuntimeException('source_contract');
$targets=[]; foreach(($src['rows']??[]) as $r){ if(!is_array($r)||($r['bucket']??'')!=='needs_saved_geo_or_name')continue; $aid=(string)($r['native_anex_hotel_id']??'');$lid=(int)($r['local_hotel_id']??0);if(!preg_match('/^[1-9][0-9]{0,7}$/D',$aid)||$lid<1)throw new RuntimeException('source_identity');if(isset($targets[$aid])&&$targets[$aid]!==$lid)throw new RuntimeException('source_collision');$targets[$aid]=$lid; }
if(count($targets)!==40) throw new RuntimeException('source_target_count');

require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
$payloadDir=is_file($opdir.'/payload/anex-client.php')?$opdir.'/payload':$opdir; require_once $payloadDir.'/anex-client.php';
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
try{
  $lids=array_values(array_unique(array_values($targets)));$ph=implode(',',array_fill(0,count($lids),'?'));
  $locals=[];foreach(hmtnad_rows($db,"SELECT id,country_id,country_name,name,region_name,subregion_name,latitude,longitude,is_active FROM catalog_hotels WHERE id IN ($ph)",$lids) as $r)$locals[(int)$r['id']]=$r;
  $aliases=[];foreach(hmtnad_rows($db,"SELECT hotel_id,alias FROM hotel_aliases WHERE hotel_id IN ($ph)",$lids) as $r){$v=trim((string)$r['alias']);if($v!=='')$aliases[(int)$r['hotel_id']][]=$v;}
  $seen=[];foreach(hmtnad_rows($db,"SELECT hotel_id,COUNT(*) n,MAX(observed_at) last_seen FROM tour_price_observations WHERE source='user_search' AND hotel_id IN ($ph) GROUP BY hotel_id",$lids) as $r)$seen[(int)$r['hotel_id']]=['n'=>(int)$r['n'],'last_seen'=>(string)$r['last_seen']];
  $and=[];foreach(hmtnad_rows($db,"SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IN ($ph)",$lids) as $r)$and[(int)$r['local_hotel_id']]=true;
  $exts=array_keys($targets);$eph=implode(',',array_fill(0,count($exts),'?'));
  $maps=[];$localMaps=[];foreach(hmtnad_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1 AND scope='preview' AND (anex_hotel_id IN ($eph) OR catalog_hotel_id IN ($ph))",array_merge($exts,$lids)) as $r){$aid=(string)$r['anex_hotel_id'];$lid=(int)$r['catalog_hotel_id'];$maps[$aid][$lid]=true;$localMaps[$lid][$aid]=true;}
  $dec=[];foreach(hmtnad_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id IN ($eph)",$exts) as $r)$dec[(string)$r['anex_hotel_id']][]=$r;
  $ex=[];foreach(hmtnad_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($eph) AND catalog_hotel_id IN ($ph)",array_merge($exts,$lids)) as $r)$ex[(string)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
  $db->rollBack();
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}

$eligible=[];$preHolds=[];
foreach($targets as $aid=>$lid){$h=$locals[$lid]??null;$reasons=[];
  if(!$h||(int)$h['is_active']!==1)$reasons[]='local_inactive_or_missing';
  if(!isset($seen[$lid])||$seen[$lid]['n']<1)$reasons[]='no_current_user_search';
  if(isset($and[$lid]))$reasons[]='covered_by_andromeda';
  if(isset($ex[$aid][$lid]))$reasons[]='pair_excluded';
  foreach(array_keys($maps[$aid]??[]) as $other) if((int)$other!==$lid)$reasons[]='external_mapped_other_local';
  foreach(array_keys($localMaps[$lid]??[]) as $otherAid) if((string)$otherAid!==$aid)$reasons[]='local_mapped_other_external';
  foreach($dec[$aid]??[] as $d){$dl=$d['catalog_hotel_id']===null?null:(int)$d['catalog_hotel_id'];$st=(string)$d['decision_status'];if($st==='accepted'&&$dl===$lid){$reasons[]='already_same_decision';}else{$reasons[]='manual_or_decision_protected';}}
  if(isset($maps[$aid][$lid])||in_array('already_same_decision',$reasons,true))$reasons[]='already_same';
  $reasons=array_values(array_unique($reasons));
  if($reasons){$preHolds[]=['native_anex_hotel_id'=>$aid,'local_hotel_id'=>$lid,'local_hotel_name'=>(string)($h['name']??''),'reasons'=>$reasons];continue;}
  $eligible[$aid]=['local'=>$h,'aliases'=>$aliases[$lid]??[],'seen'=>$seen[$lid]];
}

$token=trim((string)fgets(STDIN));if($token==='')throw new RuntimeException('token_missing');
$home=(string)getenv('HOME');$rateDir=rtrim($home,'/').'/.anytour-anex-rate';if(is_link($rateDir))throw new RuntimeException('rate_dir_link');if(!is_dir($rateDir)&&!mkdir($rateDir,0700,true)&&!is_dir($rateDir))throw new RuntimeException('rate_dir_create');@chmod($rateDir,0700);
$details=[];$attempts=0;$calls=0;$stopReason=null;$providerErrors=[];
foreach(array_keys($eligible) as $i=>$aid){if($attempts>=HMTNAD_MAX_DETAILS)break;try{if($i%12===0)$client=new AnyTourAnexClient($token,null,$rateDir);$attempts++;$p=$client->request('Hotels_DETAILS',['HOTELINC'=>(int)$aid]);$calls++;$details[$aid]=hmtnad_detail($p);}catch(Throwable $e){$diag=isset($client)?$client->lastRequestDiagnostics():[];$providerErrors[$aid]=['reason'=>$e->getMessage(),'http_status'=>$diag['http_status']??null,'supplier_code'=>$diag['supplier_code']??null];if(($diag['http_status']??null)===429||in_array($e->getMessage(),['ANEX_TRANSPORT_ERROR','ANEX_TRANSPORT_UNAVAILABLE'],true)){$stopReason=$e->getMessage();break;}}}

$safe=[];$holds=$preHolds;$needs=[];
foreach($eligible as $aid=>$ctx){$h=$ctx['local'];$lid=(int)$h['id'];if(!isset($details[$aid])){$needs[]=['native_anex_hotel_id'=>$aid,'local_hotel_id'=>$lid,'local_hotel_name'=>(string)$h['name'],'reason'=>isset($providerErrors[$aid])?'provider_error':'not_attempted','provider_error'=>$providerErrors[$aid]??null];continue;}$d=$details[$aid];
  $names=array_values(array_unique(array_filter(array_merge([(string)$h['name']],$ctx['aliases']),fn($v)=>trim((string)$v)!=='')));
  $best=['score'=>0,'local_name'=>''];foreach($names as $ln){if(hmtnad_nums($ln)!==hmtnad_nums($d['name']))continue;$lq=hmtnad_quals($ln);$sq=hmtnad_quals($d['name']);if($lq&&$sq&&$lq!==$sq)continue;$sc=hmtnad_score($ln,$d['name']);if($sc>$best['score'])$best=['score'=>$sc,'local_name'=>$ln];}
  $exact=$best['local_name']!==''&&hmtnad_key($best['local_name'])===hmtnad_key($d['name']);
  $countryExplicit=$d['country']!=='';$countryOk=!$countryExplicit||hmtnad_country_key($d['country'])===hmtnad_country_key($h['country_name']);
  $dist=hmtnad_dist($d['latitude'],$d['longitude'],$h['latitude'],$h['longitude']);$place=hmtnad_place($d,$h);$far=$dist!==null&&$dist>5000;
  $geoStrong=($dist!==null&&$dist<=1500)||$place;
  $identityOk=$exact||($best['score']>=90&&$dist!==null&&$dist<=1000);
  $row=['native_anex_hotel_id'=>$aid,'local_hotel_id'=>$lid,'local_hotel_name'=>(string)$h['name'],'country_name'=>(string)$h['country_name'],'region_name'=>(string)($h['region_name']??''),'subregion_name'=>$h['subregion_name']===null?null:(string)$h['subregion_name'],'supplier_detail'=>$d,'name_score'=>$best['score'],'matched_local_name'=>$best['local_name'],'exact_or_alias'=>$exact,'explicit_country_present'=>$countryExplicit,'country_match'=>$countryOk,'place_match'=>$place,'distance_m'=>$dist===null?null:(int)round($dist),'user_search_rows'=>$ctx['seen']['n'],'last_user_seen'=>$ctx['seen']['last']];
  if($far){$row['reason']='coordinate_conflict_gt5km';$holds[]=$row;continue;}
  if(!$countryOk){$row['reason']='explicit_country_conflict';$holds[]=$row;continue;}
  if($identityOk&&$geoStrong){$row['evidence_status']='safe_native_detail';$safe[]=$row;continue;}
  $row['reason']=!$identityOk?'name_not_strong_enough':'geography_unproven';$needs[]=$row;
}
usort($safe,fn($a,$b)=>$b['user_search_rows']<=>$a['user_search_rows']?:$a['local_hotel_id']<=>$b['local_hotel_id']);
$result=['operation'=>HMTNAD_OPERATION,'status'=>'completed_read_only','source_result_sha256'=>HMTNAD_SOURCE_RESULT_SHA256,'source_target_count'=>count($targets),'current_eligible_count'=>count($eligible),'pre_provider_hold_count'=>count($preHolds),'anex_detail_attempts'=>$attempts,'anex_detail_calls'=>$calls,'details_returned'=>count($details),'provider_error_count'=>count($providerErrors),'stop_reason'=>$stopReason,'safe_native_detail_count'=>count($safe),'hold_count'=>count($holds),'needs_more_evidence_count'=>count($needs),'safe_native_details'=>$safe,'holds'=>$holds,'needs_more_evidence'=>$needs,'provider_errors'=>$providerErrors,'tourvisor_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'request_accounting'=>['direct_anex_attempts'=>$attempts,'tourvisor_attempts'=>0,'direct_anex_limit_per_minute'=>60,'direct_anex_daily_cap'=>null]];
$sha=hmtnad_save($opdir.'/result.json',$result);hmtnad_save($opdir.'/receipt.json',['operation'=>HMTNAD_OPERATION,'status'=>'completed_read_only','result_sha256'=>$sha,'source_result_sha256'=>HMTNAD_SOURCE_RESULT_SHA256,'provider_access'=>true,'anex_detail_attempts'=>$attempts,'anex_detail_calls'=>$calls,'tourvisor_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
