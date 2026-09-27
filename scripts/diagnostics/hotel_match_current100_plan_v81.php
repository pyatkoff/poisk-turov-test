<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_live30_common4_gap_matrix_v1.php';
const M80_IDS=[1691,1693,1704,1709,2103,2119,2123,2158,2172,2178,2181,2182,2191,2200,2206,2210,2213,2214,2216,2223,2225,2231,2240,2252,2266,2270,2271,3407,3418,3422,3438,3445,3465,3469,5523,5526,5530,9242,9243,9251,9273,9283,9287,9312,9318,9320,9323,9325,9334,9352,9358,9427,9442,9443,9448,9450,9451,9454,15870,15877,15890,15896,15897,15902,15913,15916,16776,16790,16934,16938,16944,16945,17206,17282,17323,17338,17345,17371,17377,17383,17390,17442,17443,17489,17507,17562,17564,17603,17664,17671,17675,21641,21644,21669,21678,21679,21683,21684,21699,21701];
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
