<?php
declare(strict_types=1);
// MATCH acquisition eligibility only. No mapping acceptance or provider access.
const R30_OP = 'hotel-match-anex-rolling30-1971-20260919-v2';
const R30_EXCLUDED = [112358,60006,81356,60957,92738,130835,60968,70035,89073,114210,59991,81750,92745,60985,99759,60940,60947,70424,95553,60257,69289,69290,81197,100475,127325,68625,99760,101025,101026,59990,1063,1441,66037,950,1580,42576,2191,75538,72865,1244,115349,75791,152266,132017,42596,103212,42592,131058,42581,75871,156593,9443,147573,9427,99582,157197,62868,967,132716,1151,1546,1500,44669,2559,74083,2590,32230,2522,73509,55495,73055,63906,2586,113007,2565,2582,66192,133061,88323,54603,2634,49851,11745,11751,11754,11756,11758,11761,11770,11775,11788,11789,26841,64582,11769,11771,11792,11802,11805,15788,15794,26842,28452,35164,35170,44413,60234,68977,106703,159,344,367,1001,1255,1557,1709,2513,4453,4492,4493,8943,8953,8970,13830,32401,37919,43060,43517,50623,50627,57059,57645,58479,58485,60023,60944,60960,60979,60981,64195,65266,65896,66187,66290,67304,69111,69291,69442,69451,69454,70984,71258,73056,76295,77323,80872,81400,81951,83099,83227,97122,102139,106204,109304,113292,116228,124758,126153,127817,128538,141586,141976,143442,143790,146665,148230,148539,156439,159008,159711,161982,162479,163929,163937];
const R30_PRIOR_RESIDUAL = [293,358,963,1558,1600,8972,17569,21641,55721,64774,74258,78331,117935,132075];

function r30_rows(PDO $db,string $sql,array $p=[]):array {
 $s=$db->prepare($sql);$s->execute(array_values($p));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];
}
function r30_product(string $s):bool {
 return preg_match('/(?:^|[^\p{L}])(?:FORTUNA|ROULETTE|ФОРТУНА|РУЛЕТКА)(?:$|[^\p{L}])/iu',$s)===1;
}
function r30_json(array $x):string {return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
if(($argv[1]??'')==='--self-test') {
 if(!r30_product('FORTUNA 5*')||r30_product('HOTEL SU')||count(R30_EXCLUDED)!==184||count(R30_PRIOR_RESIDUAL)!==14)throw new RuntimeException('planner_test');
 if(count(array_intersect(R30_EXCLUDED,R30_PRIOR_RESIDUAL))!==0)throw new RuntimeException('residual_excluded');
 echo "ROLLING30_V2_PLANNER_SELFTEST_OK\n";exit;
}
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');
$dir=realpath((string)getenv('MATCH_OPERATION_DIR'));$root=realpath((string)getenv('ANYTOUR_ROOT'));
if(!$dir||!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('paths');
$r=json_decode((string)file_get_contents($dir.'/reservation.json'),true,512,JSON_THROW_ON_ERROR);
if(($r['operation']??'')!==R30_OP||($r['state']??'')!=='reserved_before_db_provider')throw new RuntimeException('reservation');
require_once $dir.'/payload/anex-search-mapping-registry.php';
require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
try {
 $reg=AnyTourAnexSearchMappingRegistry::fromPdo($db);$accepted=[];$protected=[];$and=[];
 foreach(r30_rows($db,'SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions') as $m){$lid=$reg->resolve('anex_online',(string)$m['anex_hotel_id'],'preview');if($lid!==null)$accepted[$lid]=true;}
 foreach(r30_rows($db,'SELECT catalog_hotel_id FROM anex_hotel_decisions UNION SELECT catalog_hotel_id FROM anex_review_pair_exclusions') as $m)if((int)$m['catalog_hotel_id']>0)$protected[(int)$m['catalog_hotel_id']]=true;
 foreach(r30_rows($db,"SELECT local_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL") as $m)$and[(int)$m['local_hotel_id']]=true;
 $rows=r30_rows($db,"SELECT o.hotel_id,o.departure_id,o.country_id,o.departure_date,o.nights,o.search_id,o.tour_id,o.observed_at,
 h.name,h.country_name,h.region_name,h.subregion_name,h.category,h.latitude,h.longitude
 FROM tour_price_observations o JOIN catalog_hotels h ON h.id=o.hotel_id AND h.country_id=o.country_id
 WHERE o.source='user_search' AND o.operator_id=13 AND h.is_active=1
 AND o.departure_date>=CURRENT_DATE() AND o.nights BETWEEN 7 AND 10 AND o.adults=2 AND o.children_count=0
 AND o.tour_id IS NOT NULL AND o.tour_id<>'' ORDER BY o.observed_at DESC");
 $excluded=array_fill_keys(R30_EXCLUDED,true);$priorResidual=array_fill_keys(R30_PRIOR_RESIDUAL,true);
 $stats=['observation_rows'=>count($rows),'accepted'=>0,'protected'=>0,'prior_or_foreign_claim'=>0,'product_or_country'=>0,'eligible_residual_rows'=>0,'eligible_fresh_rows'=>0];
 $groups=[];$front=[];
 foreach($rows as $h){$id=(int)$h['hotel_id'];
  if(isset($accepted[$id])){$stats['accepted']++;continue;}if(isset($protected[$id])){$stats['protected']++;continue;}
  if(isset($excluded[$id])){$stats['prior_or_foreign_claim']++;continue;}
  if(r30_product((string)$h['name'])||in_array(mb_strtolower((string)$h['country_name'],'UTF-8'),['россия','абхазия'],true)){$stats['product_or_country']++;continue;}
  $h['hotel_id']=$id;$h['departure_id']=(int)$h['departure_id'];$h['country_id']=(int)$h['country_id'];$h['nights']=(int)$h['nights'];
  if($h['departure_id']<1||$h['country_id']<1)continue;
  $h['source']='user_search';$h['operator_id']=13;$h['has_accepted_andromeda']=isset($and[$id]);$h['prior_attempts']=isset($priorResidual[$id])?1:0;
  if($h['prior_attempts'])$stats['eligible_residual_rows']++;else $stats['eligible_fresh_rows']++;
  $front[$id]=true;$key=$h['departure_id'].'|'.$h['country_id'];$groups[$key][]=$h;
 }
 $contexts=[];
 foreach($groups as $g){$dates=array_values(array_unique(array_column($g,'departure_date')));sort($dates,SORT_STRING);
  foreach($dates as $start){$end=(new DateTimeImmutable($start))->modify('+6 days')->format('Y-m-d');$by=[];
   foreach($g as $h){if($h['departure_date']<$start||$h['departure_date']>$end)continue;$id=$h['hotel_id'];if(!isset($by[$id])){$by[$id]=$h;$by[$id]['observations']=0;}$by[$id]['observations']++;}
   if(!$by)continue;$hs=array_values($by);usort($hs,fn($a,$b)=>(int)$b['prior_attempts']<=>(int)$a['prior_attempts']?:(int)$b['has_accepted_andromeda']<=>(int)$a['has_accepted_andromeda']?:$b['observations']<=>$a['observations']?:$a['hotel_id']<=>$b['hotel_id']);
   $contexts[]=['departure_id'=>$hs[0]['departure_id'],'country_id'=>$hs[0]['country_id'],'country_name'=>$hs[0]['country_name'],'date_from'=>$start,'date_to'=>$end,'nights_from'=>7,'nights_to'=>10,'adults'=>2,'children_count'=>0,'hotels'=>$hs,'bridge_count'=>count(array_filter($hs,fn($h)=>$h['has_accepted_andromeda'])),'residual_count'=>count(array_filter($hs,fn($h)=>$h['prior_attempts']===1))];
  }
 }
 usort($contexts,fn($a,$b)=>count($b['hotels'])<=>count($a['hotels'])?:$b['residual_count']<=>$a['residual_count']?:$b['bridge_count']<=>$a['bridge_count']?:strcmp($a['date_from'],$b['date_from'])?:$a['country_id']<=>$b['country_id']);
 $clock=r30_rows($db,'SELECT UTC_TIMESTAMP AS db_utc_timestamp')[0];$db->exec('ROLLBACK');
 echo r30_json(['operation'=>R30_OP,'state'=>'current_read_only_complete','captured_at'=>$clock['db_utc_timestamp'],'frontier_unique'=>count($front),'stats'=>$stats,'contexts'=>$contexts,'excluded_ids'=>R30_EXCLUDED,'prior_residual_ids'=>R30_PRIOR_RESIDUAL,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
