<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_live30_common4_gap_matrix_v1.php';
const M80_IDS=[950,960,963,967,974,980,984,986,987,988,994,1006,1013,1015,1037,1041,1049,1059,1063,1070,1071,1078,1090,1097,1104,1107,1123,1124,1136,1150,1151,1157,1164,1175,1181,1223,1229,1244,1247,1259,1264,1266,1267,1268,1278,1285,1286,1341,1343,1348,1361,1363,1386,1395,1404,1416,1417,1418,1422,1428,1435,1451,1454,1461,1463,1470,1477,1483,1491,1500,1507,1514,1520,1523,1527,1528,1533,1534,1540,1546,1552,1554,1557,1562,1572,1573,1576,1580,1584,1587,1589,1590,1597,1600,1606,1643,1648,1652,1670,1677];
function m80_need(bool $x,string $m):void{if(!$x)throw new RuntimeException($m);}
function m80_ctx(PDO $db,array $ids):array{
 $out=[];foreach(array_chunk($ids,250) as $c){$ph=implode(',',array_fill(0,count($c),'?'));$q=$db->prepare("SELECT hotel_id,departure_id,country_id,departure_date,nights,adults,children_count,child_ages_signature,observed_at,source FROM tour_price_observations WHERE hotel_id IN ($ph) ORDER BY hotel_id,(departure_date>=CURDATE()) DESC,(source='user_search') DESC,observed_at DESC");$q->execute($c);foreach($q->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$i=(int)$r['hotel_id'];if(!isset($out[$i]))$out[$i]=$r;}}return$out;
}
function m80_plan(PDO $db):array{
 $m=hmc4_execute($db);m80_need(($m['state']??'')==='completed_read_only_common4_gap_matrix','matrix');
 $by=[];foreach($m['rows'] as $r)$by[(int)$r['tv_hotel_id']]=$r;
 foreach(M80_IDS as $i)m80_need(isset($by[$i]),'scope_drift_'.$i);
 $ctx=m80_ctx($db,M80_IDS);$facts=[];$ph=implode(',',array_fill(0,count(M80_IDS),'?'));$q=$db->prepare("SELECT id,country_id,country_name,name FROM catalog_hotels WHERE id IN ($ph) AND is_active=1");$q->execute(M80_IDS);foreach($q->fetchAll(PDO::FETCH_ASSOC)?:[] as $r)$facts[(int)$r['id']]=$r;
 $deps=[];foreach($db->query("SELECT country_id,departure_id FROM catalog_departure_countries WHERE is_active=1 ORDER BY country_id,(departure_id=1) DESC,departure_id")->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$c=(int)$r['country_id'];if(!isset($deps[$c]))$deps[$c]=(int)$r['departure_id'];}
 $map=['anex'=>13,'biblio'=>18,'funsun'=>25,'intourist'=>43];$rows=[];
 foreach(M80_IDS as $id){$r=$by[$id];$f=$facts[$id];$missing=[];foreach($map as $k=>$op)if(empty($r['lanes'][$k]['exact_evidence']))$missing[]=$op;m80_need($missing!==[],'no_missing_'.$id);
  $c=$ctx[$id]??null;$cid=(int)$f['country_id'];if(!$c)$c=['departure_id'=>$deps[$cid]??1,'country_id'=>$cid,'departure_date'=>(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+14 days')->format('Y-m-d'),'nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>''];
  if((string)$c['departure_date']<gmdate('Y-m-d'))$c['departure_date']=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+14 days')->format('Y-m-d');
  $rows[]=['tv_hotel_id'=>$id,'hotel_name'=>(string)$f['name'],'country_id'=>$cid,'country'=>(string)$f['country_name'],'gap_bucket'=>$r['gap_bucket'],'missing_operator_ids'=>$missing,'departure_id'=>(int)$c['departure_id'],'departure_date'=>(string)$c['departure_date'],'nights'=>max(1,min(28,(int)$c['nights'])),'adults'=>max(1,min(6,(int)$c['adults'])),'children_count'=>max(0,min(3,(int)$c['children_count'])),'child_ages_signature'=>(string)$c['child_ages_signature']];
 }
 return ['state'=>'match_current100_ready','generated_at_utc'=>gmdate('c'),'tv_live30_total'=>$m['tv_live30_total'],'current_nontriple_total'=>$m['live30_non_triple_total'],'scope_count'=>count($rows),'rows'=>$rows,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
}
if(($argv[1]??'')==='--self-test'){m80_need(count(M80_IDS)===100&&count(array_unique(M80_IDS))===100,'ids');echo "M80_PLAN_OK\n";exit;}
m80_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');echo json_encode(m80_plan(v2_data_db()),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
