<?php
/** MATCH #1971: fresh server-CURRENT evidence matrix for the full PR2412 schema-v3 queue. */
declare(strict_types=1);

const HM_OP='hotel-match-pr2412-evidence-current-1971-20260915-v1';

const HM_DIRECT=[
45225=>153743,19226=>14140,36906=>21636,780=>464,1771=>190,1772=>191,
28563=>73342,45226=>163543,1769=>186,43850=>151784,8366=>17595,14812=>5535
];
const HM_OFFICIAL=[
32880=>17443,35275=>81810,16605=>115500,44562=>159,32724=>17586,
32567=>82420,32742=>132803,32822=>82758,32937=>76112,34961=>27553
];
const HM_CROSS=[
16330=>28426,34680=>37404,32783=>54633,44939=>77557,44413=>21838,
32572=>68169,835=>196,5215=>121626,32355=>21838,33110=>104168,
15072=>77400,9384=>77574,20470=>101227,29556=>15693,37985=>77759
];
const HM_SAVED=[28882=>43528,44573=>59085,30424=>17617];
const HM_CAPTURED=[42927=>131347,37719=>132075];
const HM_NATIVE=[27777=>17561];
const HM_MAPPED=[4158=>37412,1767=>182,1768=>183,44138=>53531];
const HM_HOLDS=[
32743=>0,32692=>70291,37048=>97122,1361=>125,32745=>0,30600=>0,4131=>488,
32686=>17390,1328=>128,28933=>17390,9126=>0,29430=>71266,29557=>73343
];
const HM_PROVIDER=[
23894,43984,28396,1655,37012,37885,30599,29446,990,8550,34804,39389,43077,
44139,45166,45249,45259,45330,8319,8355,8460,8537,8538,8666,10115,11241,
11756,12143,12901,15046,21696,29036,29332,31515,34858,35319,35511,35898,
37344,37538,39875
];

function hmj(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmw(string $p,array $v):string{$raw=hmj($v)."\n";$f=fopen($p,'x');if(!$f)throw new RuntimeException('create_failed');fwrite($f,$raw);fflush($f);fclose($f);if((string)file_get_contents($p)!==$raw)throw new RuntimeException('readback_failed');return hash('sha256',$raw);}
function one(PDO $db,string $q,array $p):?array{$s=$db->prepare($q);$s->execute($p);$r=$s->fetch(PDO::FETCH_ASSOC);return $r===false?null:$r;}
function many(PDO $db,string $q,array $p):array{$s=$db->prepare($q);$s->execute($p);return $s->fetchAll(PDO::FETCH_ASSOC);}
function first(array $r,array $ks):string{foreach($ks as $k){if(isset($r[$k])&&is_scalar($r[$k])&&trim((string)$r[$k])!=='')return trim((string)$r[$k]);}return '';}
function number(mixed $v):?float{if($v===null||!is_scalar($v)||!is_numeric((string)$v))return null;$n=(float)$v;return is_finite($n)?$n:null;}
function coords(array $r):array{foreach([['latitude','longitude'],['lat','lng'],['lat','lon'],['api_latitude','api_longitude'],['hotelLatitude','hotelLongitude']] as [$a,$b]){$x=number($r[$a]??null);$y=number($r[$b]??null);if($x!==null&&$y!==null&&abs($x)<=90&&abs($y)<=180)return[$x,$y];}return[null,null];}
function distance(?float $a,?float $b,?float $c,?float $d):?float{if($a===null||$b===null||$c===null||$d===null)return null;$R=6371.0088;$p1=deg2rad($a);$p2=deg2rad($c);$dp=deg2rad($c-$a);$dl=deg2rad($d-$b);$x=sin($dp/2)**2+cos($p1)*cos($p2)*sin($dl/2)**2;return round(2*$R*asin(min(1,sqrt($x))),3);}
function addLane(array &$inputs,string $lane,array $pairs):void{foreach($pairs as $aid=>$lid)$inputs[]=['anex_hotel_id'=>(int)$aid,'lane'=>$lane,'candidate_local_id'=>(int)$lid];}

$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
if(!preg_match('/^[0-9a-f]{40}$/',$sourceSha))throw new RuntimeException('source_sha_required');
$root=(string)realpath(getcwd());if($root===''||basename($root)!=='anytoour.ru')throw new RuntimeException('root_guard');

$inputs=[];
addLane($inputs,'direct_hotellist_safe_current_recheck',HM_DIRECT);
addLane($inputs,'official_native_hotelcode_needs_local_bridge',HM_OFFICIAL);
addLane($inputs,'cross_provider_bridge_then_operatorlink',HM_CROSS);
addLane($inputs,'saved_tv_candidate_then_anex_card_hotelcode',HM_SAVED);
addLane($inputs,'captured_detail_then_anex_card_hotelcode',HM_CAPTURED);
addLane($inputs,'native_identity_current_reconcile',HM_NATIVE);
addLane($inputs,'no_replay_already_mapped',HM_MAPPED);
addLane($inputs,'guarded_hold_no_auto',HM_HOLDS);
foreach(HM_PROVIDER as $aid)$inputs[]=['anex_hotel_id'=>(int)$aid,'lane'=>'tourvisor_operatorlink_detail_then_anex_card_hotelcode','candidate_local_id'=>0];
if(count($inputs)!==101)throw new RuntimeException('input_count');
$seen=[];foreach($inputs as $x){$id=$x['anex_hotel_id'];if(isset($seen[$id]))throw new RuntimeException('duplicate_input_'.$id);$seen[$id]=true;}

$base=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations';
$dir=$base.'/'.HM_OP;
if(!is_dir($base)||file_exists($dir)||!mkdir($dir,0700))throw new RuntimeException('operation_exists');
hmw($dir.'/reservation.json',[
 'operation_id'=>HM_OP,'source_sha'=>$sourceSha,'state'=>'reserved_before_db_access','input_rows'=>101,
 'supplier_calls'=>0,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true
]);

$dbp=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
require_once $dbp;
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
$rows=[];
try{
 foreach($inputs as $in){
  $aid=(int)$in['anex_hotel_id'];$cand=(int)$in['candidate_local_id'];$lane=(string)$in['lane'];
  $stage=one($db,'SELECT * FROM anex_hotels WHERE anex_hotel_id=? LIMIT 1',[$aid])??[];
  $obs=many($db,'SELECT * FROM anex_search_hotel_observations WHERE anex_hotel_id=? ORDER BY search_count DESC,last_seen_utc DESC LIMIT 50',[$aid]);
  $maps=many($db,'SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id=? ORDER BY catalog_hotel_id',[$aid]);
  $manual=one($db,'SELECT * FROM anex_hotel_decisions WHERE anex_hotel_id=? LIMIT 1',[$aid]);
  $ex=$cand>0?one($db,'SELECT * FROM anex_review_pair_exclusions WHERE anex_hotel_id=? AND catalog_hotel_id=? LIMIT 1',[$aid,$cand]):null;
  $cat=$cand>0?(one($db,'SELECT * FROM catalog_hotels WHERE id=? LIMIT 1',[$cand])??[]):[];
  $reverse=$cand>0?many($db,'SELECT anex_hotel_id FROM anex_hotel_search_mappings WHERE catalog_hotel_id=? ORDER BY anex_hotel_id',[$cand]):[];

  $name=first($stage,['name','hotel_name','name_ru','title','hotelName']);
  if($name==='')foreach($obs as $o){$name=first($o,['hotel_name','name','source_name','title','hotelName']);if($name!=='')break;}
  $country=null;foreach(array_merge([$stage],$obs) as $r){foreach(['country_id','countryId'] as $k){if(isset($r[$k])&&(int)$r[$k]>0){$country=(int)$r[$k];break 2;}}}
  [$slat,$slon]=coords($stage);if($slat===null)foreach($obs as $o){[$slat,$slon]=coords($o);if($slat!==null)break;}
  [$tlat,$tlon]=coords($cat);$dist=distance($slat,$slon,$tlat,$tlon);

  $targets=[];foreach($maps as $m){$t=(int)($m['catalog_hotel_id']??$m['local_hotel_id']??0);if($t>0)$targets[$t]=1;}
  $mappedTargets=array_keys($targets);sort($mappedTargets,SORT_NUMERIC);
  $occupants=[];foreach($reverse as $m){$x=(int)($m['anex_hotel_id']??0);if($x>0)$occupants[$x]=1;}
  $occupants=array_keys($occupants);sort($occupants,SORT_NUMERIC);
  $otherOccupants=$cand>0?array_values(array_diff($occupants,[$aid])):[];
  $targetCountry=isset($cat['country_id'])?(int)$cat['country_id']:null;
  $countryConflict=$cand>0&&$country!==null&&$targetCountry!==null&&$country>0&&$targetCountry>0&&$country!==$targetCountry;
  $distanceConflict=$dist!==null&&$dist>5.0;
  $mappedSame=$cand>0&&in_array($cand,$mappedTargets,true);
  $mappedOther=count(array_diff($mappedTargets,$cand>0?[$cand]:[]))>0;

  $guard='pending';
  if($lane==='no_replay_already_mapped'){
    $guard=$mappedSame?'already_mapped_same':'mapped_evidence_drift_hold';
  }elseif($lane==='guarded_hold_no_auto'){
    $guard='guarded_hold_no_auto';
  }elseif($lane==='tourvisor_operatorlink_detail_then_anex_card_hotelcode'){
    $guard=count($mappedTargets)>0?'already_mapped_no_provider':'needs_provider_evidence';
  }elseif($mappedSame){
    $guard='already_mapped_same';
  }elseif($mappedOther){
    $guard='existing_other_mapping_hold';
  }elseif($manual!==null){
    $guard='manual_protected_hold';
  }elseif($ex!==null){
    $guard='pair_excluded_hold';
  }elseif($cand<=0||$cat===[]){
    $guard='candidate_missing_hold';
  }elseif($countryConflict){
    $guard='country_conflict_hold';
  }elseif($distanceConflict){
    $guard='coordinate_conflict_over_5km_hold';
  }elseif(count($otherOccupants)>0){
    $guard='occupied_by_other_hold';
  }elseif($lane==='direct_hotellist_safe_current_recheck'||$lane==='native_identity_current_reconcile'){
    $guard=($name!==''&&$country!==null)?'direct_identity_current_guards_clean':'direct_identity_missing_current_source_guard';
  }elseif($lane==='official_native_hotelcode_needs_local_bridge'){
    $guard='native_id_current_guards_clean_still_needs_local_bridge';
  }elseif($lane==='cross_provider_bridge_then_operatorlink'){
    $guard='current_candidate_clean_still_needs_operatorlink';
  }elseif($lane==='saved_tv_candidate_then_anex_card_hotelcode'||$lane==='captured_detail_then_anex_card_hotelcode'){
    $guard='current_candidate_clean_still_needs_native_identity';
  }

  $rows[]=[
   'anex_hotel_id'=>$aid,'lane'=>$lane,'candidate_local_id'=>$cand,'guard'=>$guard,
   'source_name'=>$name,'source_country_id'=>$country,'stage_present'=>$stage!==[],
   'observation_count'=>count($obs),'live_search_count'=>array_sum(array_map(static fn(array $r):int=>(int)($r['search_count']??0),$obs)),
   'mapped_targets'=>$mappedTargets,'manual_protected'=>$manual!==null,'pair_excluded'=>$ex!==null,
   'candidate_name'=>first($cat,['name','hotel_name','title']),'candidate_country_id'=>$targetCountry,
   'candidate_distance_km'=>$dist,'reverse_anex_occupants'=>$occupants,'occupied_by_other'=>count($otherOccupants)>0
  ];
 }
 $db->exec('ROLLBACK');
}catch(Throwable $e){if($db->inTransaction())$db->exec('ROLLBACK');throw $e;}

$guardCounts=[];$laneCounts=[];$unresolvedLive=0;
foreach($rows as $r){$g=$r['guard'];$guardCounts[$g]=($guardCounts[$g]??0)+1;$l=$r['lane'];$laneCounts[$l]=($laneCounts[$l]??0)+1;if(!str_starts_with($g,'already_mapped'))$unresolvedLive+=(int)$r['live_search_count'];}
ksort($guardCounts);ksort($laneCounts);
$result=[
 'operation_id'=>HM_OP,'source_sha'=>$sourceSha,'status'=>'completed_read_only','input_rows'=>count($rows),
 'guard_counts'=>$guardCounts,'lane_counts'=>$laneCounts,'unresolved_live_frequency_sum'=>$unresolvedLive,'rows'=>$rows,
 'supplier_calls'=>0,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true,
 'status_note'=>'CURRENT evidence matrix only; direct/native clean guards are not write authority without transaction-time semantic acceptance'
];
$rh=hmw($dir.'/result.json',$result);
hmw($dir.'/receipt.json',[
 'operation_id'=>HM_OP,'source_sha'=>$sourceSha,'state'=>'completed_read_only','result_sha256'=>$rh,
 'readback_verified'=>hash('sha256',(string)file_get_contents($dir.'/result.json'))===$rh,
 'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true
]);
echo hmj(['input_rows'=>count($rows),'guard_counts'=>$guardCounts,'lane_counts'=>$laneCounts,'unresolved_live_frequency_sum'=>$unresolvedLive])."\n";
