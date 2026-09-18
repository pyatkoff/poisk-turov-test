<?php
declare(strict_types=1);

const HMTS_OP='hotel-match-tv-native-anex-semantic-current-reconcile-1971-20260918-v1';
const HMTS_SOURCE_SHA='e53a2c3857d1421ea0a819c07455a8057c1e90cbd49cf8999d59eb3b558129ff';
const HMTS_SOURCE_ARTIFACT=10559674434;

function hmts_rows(PDO $db,string $sql,array $p=[]):array{$s=$db->prepare($sql);$s->execute(array_values($p));return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hmts_norm(string $v):string{
  $v=mb_strtolower(trim($v),'UTF-8');
  $v=strtr($v,['ё'=>'е','&'=>' ','+'=>' ','_'=>' ','-'=>' ','’'=>"'",'/' =>' ']);
  $v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v;
  return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function hmts_tokens(string $v):array{
  $drop=['hotel'=>1,'hotels'=>1,'otel'=>1,'отель'=>1,'гостиница'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'the'=>1,'and'=>1,'ex'=>1,'by'=>1,'a'=>1,'an'=>1,'concept'=>1];
  $o=[];foreach(preg_split('/\s+/u',hmts_norm($v),-1,PREG_SPLIT_NO_EMPTY)?:[]as$t)if(!isset($drop[$t]))$o[(string)$t]=true;
  return array_map('strval',array_keys($o));
}
function hmts_variants(string $v):array{
  $out=[];$raw=trim($v);if($raw!=='')$out[$raw]=true;
  $a=preg_replace('/\s*\((?:ex\.?|ex\s)[^)]*\)\s*$/ui','',$raw)??$raw;if(trim($a)!=='')$out[trim($a)]=true;
  $b=preg_replace('/\s*\([^)]*\)\s*$/u','',$raw)??$raw;if(trim($b)!=='')$out[trim($b)]=true;
  return array_keys($out);
}
function hmts_nums(string $v):array{$o=[];foreach(hmts_tokens($v)as$t)if(preg_match('/^[0-9]+$/D',$t))$o[$t]=true;$x=array_keys($o);sort($x,SORT_STRING);return$x;}
function hmts_quals(string $v):array{
  $q=['north'=>'north','south'=>'south','pool'=>'pool','beach'=>'beach','family'=>'family','adult'=>'adult','adults'=>'adult','aquamarine'=>'aquamarine','garden'=>'garden','gardens'=>'garden','sea'=>'sea','view'=>'view','club'=>'club','royal'=>'royal','premium'=>'premium','deluxe'=>'deluxe','villa'=>'villa','villas'=>'villa'];
  $o=[];foreach(hmts_tokens($v)as$t)if(isset($q[$t]))$o[$q[$t]]=true;$x=array_keys($o);sort($x,SORT_STRING);return$x;
}
function hmts_compact(string $v):string{return implode('',hmts_tokens($v));}
function hmts_name_corroborated(string $detail,array $localNames):array{
  if(trim($detail)==='')return['ok'=>false,'reason'=>'detail_name_empty','score'=>0.0];
  $best=0.0;$bestPair=null;$hard=null;
  foreach($localNames as$local){if(!is_string($local)||trim($local)==='')continue;foreach(hmts_variants($local)as$lv){
    $dn=hmts_norm($detail);$ln=hmts_norm($lv);if($dn===''||$ln==='')continue;
    $dnums=hmts_nums($detail);$lnums=hmts_nums($lv);if($dnums&&$lnums&&$dnums!==$lnums){$hard='number_conflict';continue;}
    $dq=hmts_quals($detail);$lq=hmts_quals($lv);
    $dd=array_values(array_intersect($dq,['north','south']));$ld=array_values(array_intersect($lq,['north','south']));
    if($dd&&$ld&&$dd!==$ld){$hard='direction_qualifier_conflict';continue;}
    $da=array_values(array_intersect($dq,['adult']));$la=array_values(array_intersect($lq,['adult']));
    if((bool)$da!==(bool)$la&&($da||$la)){$hard='adult_qualifier_conflict';continue;}
    if($dq&&$lq&&array_intersect($dq,$lq)===[]){$hard='qualifier_conflict';continue;}
    if($dn===$ln)return['ok'=>true,'reason'=>'exact_normalized','score'=>1.0,'pair'=>[$lv,$detail]];
    $dc=hmts_compact($detail);$lc=hmts_compact($lv);
    if($dc!==''&&$lc!==''&&min(strlen($dc),strlen($lc))>=4&&($dc===$lc||str_contains($dc,$lc)||str_contains($lc,$dc))){
      return['ok'=>true,'reason'=>'joined_split_or_containment','score'=>0.95,'pair'=>[$lv,$detail]];
    }
    $dt=array_fill_keys(hmts_tokens($detail),true);$lt=array_fill_keys(hmts_tokens($lv),true);if(!$dt||!$lt)continue;
    $common=count(array_intersect_key($dt,$lt));$coverage=$common/min(count($dt),count($lt));$dice=2*$common/(count($dt)+count($lt));$score=max($coverage,$dice);if($score>$best){$best=$score;$bestPair=[$lv,$detail];}
    if($common>=1&&$coverage>=0.5)return['ok'=>true,'reason'=>'meaningful_token_overlap','score'=>$score,'pair'=>[$lv,$detail]];
  }}
  return['ok'=>false,'reason'=>$hard??'name_uncorroborated','score'=>$best,'pair'=>$bestPair];
}
function hmts_country_key(string $v):string{$n=hmts_norm($v);$m=['турция'=>'turkey','turkey'=>'turkey','turkiye'=>'turkey','türkiye'=>'turkey','египет'=>'egypt','egypt'=>'egypt','танзания'=>'tanzania','tanzania'=>'tanzania','таиланд'=>'thailand','тайланд'=>'thailand','thailand'=>'thailand','вьетнам'=>'vietnam','vietnam'=>'vietnam','катар'=>'qatar','qatar'=>'qatar','индия'=>'india','india'=>'india'];return$m[$n]??$n;}
function hmts_dist($a,$b,$c,$d):?float{foreach([$a,$b,$c,$d]as$v)if(!is_numeric($v))return null;$lat1=deg2rad((float)$a);$lon1=deg2rad((float)$b);$lat2=deg2rad((float)$c);$lon2=deg2rad((float)$d);$x=sin(($lat2-$lat1)/2)**2+cos($lat1)*cos($lat2)*sin(($lon2-$lon1)/2)**2;return 6371000*2*asin(min(1,sqrt($x)));}
function hmts_place(array $detail,array $local):bool{$src=[];foreach(['state','region','town','address']as$k){$n=hmts_norm((string)($detail[$k]??''));if($n!=='')$src[]=$n;}$dst=[];foreach(['region_name','subregion_name']as$k){$n=hmts_norm((string)($local[$k]??''));if($n!=='')$dst[]=$n;}foreach($src as$a)foreach($dst as$b)if($a===$b||str_contains($a,$b)||str_contains($b,$a))return true;return false;}
if(in_array('--self-test',$argv??[],true)){
  foreach([
    ['SEA GULL','Seagull Hotel'],['BLUE BAY BEACH RESORT','Bluebay Beach Resort & Spa'],['SUN BAY (EX. SUN MARIS PARK)','Sunbay Park Hotel'],
    ['AMARINA JANNAH RESORT & AQUAPARK','Amarina Jannah Resort & Aqua Park'],['ROSAKA','Rosaka Nha Trang Hotel']
  ]as$p)if(!hmts_name_corroborated($p[1],[$p[0]])['ok'])throw new RuntimeException('name_case');
  if(hmts_name_corroborated('Royal South Hotel',['Royal North Hotel'])['ok'])throw new RuntimeException('qualifier_guard');
  echo "TV_NATIVE_ANEX_SEMANTIC_RECONCILE_SELFTEST_OK\n";exit;
}

$root=realpath((string)getenv('ANYTOUR_ROOT'));$sourcePath=realpath((string)getenv('MATCH_SOURCE_RESULT'));
if(!$root||!$sourcePath||!is_file($sourcePath))throw new RuntimeException('runtime');
$raw=(string)file_get_contents($sourcePath);if(hash('sha256',$raw)!==HMTS_SOURCE_SHA)throw new RuntimeException('source_sha');
$src=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
if(($src['operation']??'')!=='hotel-match-tv-native-anex-currentize-1971-20260918-v1'||($src['input_count']??null)!==40||($src['detail_calls']??null)!==40)throw new RuntimeException('source_contract');
require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
$rows=[];$counts=[];$safe=[];
try{
  foreach($src['rows']as$r){
    $lid=(int)($r['local_hotel_id']??0);$aid=(int)($r['native_anex_hotel_id']??0);$sourceBucket=(string)($r['bucket']??'');
    if($sourceBucket==='protected_hold'){$out=$r+['reconcile_bucket'=>'protected_hold','reconcile_reason'=>'source_protected_hold'];$rows[]=$out;$counts['protected_hold']=($counts['protected_hold']??0)+1;continue;}
    if($sourceBucket!=='needs_second_confirmation'){$out=$r+['reconcile_bucket'=>'residual_hold','reconcile_reason'=>'source_bucket_not_candidate'];$rows[]=$out;$counts['residual_hold']=($counts['residual_hold']??0)+1;continue;}
    $s=$db->prepare('SELECT id,country_id,country_name,name,region_name,subregion_name,latitude,longitude,is_active FROM catalog_hotels WHERE id=? LIMIT 1');$s->execute([$lid]);$local=$s->fetch(PDO::FETCH_ASSOC);
    if(!$local||(int)$local['is_active']!==1){$rows[]=$r+['reconcile_bucket'=>'protected_hold','reconcile_reason'=>'local_inactive_or_missing'];$counts['protected_hold']=($counts['protected_hold']??0)+1;continue;}
    $u=$db->prepare("SELECT COUNT(*) FROM tour_price_observations WHERE source='user_search' AND hotel_id=?");$u->execute([$lid]);$uc=(int)$u->fetchColumn();if($uc<1){$rows[]=$r+['reconcile_bucket'=>'protected_hold','reconcile_reason'=>'no_current_user_search'];$counts['protected_hold']=($counts['protected_hold']??0)+1;continue;}
    $same=false;$protected=null;foreach(hmts_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1 AND (anex_hotel_id=? OR catalog_hotel_id=?)",[$aid,$lid])as$m){
      if((int)$m['anex_hotel_id']===$aid&&(int)$m['catalog_hotel_id']===$lid)$same=true;
      elseif((int)$m['anex_hotel_id']===$aid)$protected='external_occupied_other_local';elseif((int)$m['catalog_hotel_id']===$lid)$protected='local_occupied_other_external';
    }
    if($same){$rows[]=$r+['reconcile_bucket'=>'already_same','reconcile_reason'=>'current_enabled_mapping'];$counts['already_same']=($counts['already_same']??0)+1;continue;}
    if($protected!==null){$rows[]=$r+['reconcile_bucket'=>'protected_hold','reconcile_reason'=>$protected];$counts['protected_hold']=($counts['protected_hold']??0)+1;continue;}
    $dec=hmts_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id=? OR catalog_hotel_id=?",[$aid,$lid]);
    if($dec){$rows[]=$r+['reconcile_bucket'=>'protected_hold','reconcile_reason'=>'current_manual_or_decision_present'];$counts['protected_hold']=($counts['protected_hold']??0)+1;continue;}
    $ex=$db->prepare('SELECT 1 FROM anex_review_pair_exclusions WHERE anex_hotel_id=? AND catalog_hotel_id=? LIMIT 1');$ex->execute([$aid,$lid]);if($ex->fetchColumn()){$rows[]=$r+['reconcile_bucket'=>'protected_hold','reconcile_reason'=>'pair_excluded'];$counts['protected_hold']=($counts['protected_hold']??0)+1;continue;}
    $d=is_array($r['detail']??null)?$r['detail']:null;if(!$d){$rows[]=$r+['reconcile_bucket'=>'residual_hold','reconcile_reason'=>'details_empty'];$counts['residual_hold']=($counts['residual_hold']??0)+1;continue;}
    $explicitCountry=trim((string)($d['country']??''));if($explicitCountry!==''&&hmts_country_key($explicitCountry)!==hmts_country_key((string)$local['country_name'])){$rows[]=$r+['reconcile_bucket'=>'protected_hold','reconcile_reason'=>'explicit_country_conflict'];$counts['protected_hold']=($counts['protected_hold']??0)+1;continue;}
    $dist=hmts_dist($d['latitude']??null,$d['longitude']??null,$local['latitude']??null,$local['longitude']??null);$place=hmts_place($d,$local);
    if($dist!==null&&$dist>5000){$rows[]=$r+['reconcile_bucket'=>'protected_hold','reconcile_reason'=>'coordinate_conflict_gt_5km','reconcile_distance_m'=>(int)round($dist)];$counts['protected_hold']=($counts['protected_hold']??0)+1;continue;}
    if($dist!==null&&$dist>1500){$rows[]=$r+['reconcile_bucket'=>'residual_hold','reconcile_reason'=>'coordinate_1_5_to_5km','reconcile_distance_m'=>(int)round($dist)];$counts['residual_hold']=($counts['residual_hold']??0)+1;continue;}
    $geoStrong=($dist!==null&&$dist<=1500)||($dist===null&&$place);if(!$geoStrong){$rows[]=$r+['reconcile_bucket'=>'residual_hold','reconcile_reason'=>'geography_unproven'];$counts['residual_hold']=($counts['residual_hold']??0)+1;continue;}
    $aliases=[];foreach(hmts_rows($db,'SELECT alias FROM hotel_aliases WHERE hotel_id=?',[$lid])as$a){$v=trim((string)$a['alias']);if($v!=='')$aliases[]=$v;}
    $name=hmts_name_corroborated((string)($d['name']??''),array_merge([(string)$local['name']],$aliases));
    if(!$name['ok']){$rows[]=$r+['reconcile_bucket'=>'residual_hold','reconcile_reason'=>$name['reason'],'name_corroboration'=>$name,'reconcile_distance_m'=>$dist===null?null:(int)round($dist),'reconcile_place_match'=>$place];$counts['residual_hold']=($counts['residual_hold']??0)+1;continue;}
    $out=$r+['reconcile_bucket'=>'current_safe_native','reconcile_reason'=>'tourvisor_hotellist_plus_direct_anex_details_plus_current_geo_name','name_corroboration'=>$name,'reconcile_distance_m'=>$dist===null?null:(int)round($dist),'reconcile_place_match'=>$place,'current_user_search_rows'=>$uc];
    $rows[]=$out;$safe[]=$out;$counts['current_safe_native']=($counts['current_safe_native']??0)+1;
  }
  $db->rollBack();
}catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
ksort($counts);usort($safe,fn($a,$b)=>(int)$b['current_user_search_rows']<=>(int)$a['current_user_search_rows']?:$a['local_hotel_id']<=>$b['local_hotel_id']);
echo json_encode([
 'operation'=>HMTS_OP,'status'=>'completed_read_only','source_artifact_id'=>HMTS_SOURCE_ARTIFACT,'source_result_sha256'=>HMTS_SOURCE_SHA,
 'input_count'=>count($src['rows']),'bucket_counts'=>$counts,'current_safe_native_count'=>count($safe),'current_safe_native'=>$safe,'rows'=>$rows,
 'tourvisor_calls'=>0,'direct_anex_calls'=>0,'andromeda_calls'=>0,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,
 'guards'=>['native_hotellist_identity_authority'=>true,'direct_anex_details_currentization'=>true,'known_coordinate_gt_5km_blocks'=>true,'coordinate_le_1500_strong_geo'=>true,'mid_distance_requires_second_confirmation'=>true,'semantic_name_corroboration'=>true,'current_occupancy_manual_exclusion_rechecked'=>true]
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
