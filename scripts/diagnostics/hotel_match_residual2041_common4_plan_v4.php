<?php
declare(strict_types=1);
const OP='hotel-match-residual2041-common4-nonanex-detail-1971-20260921-v4';
const NS=[18=>'operator_115',25=>'operator_315',43=>'operator_342'];
function rows(PDO $db,string $sql,array $p=[]):array{$s=$db->prepare($sql);$s->execute(array_values($p));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function out(array $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
if(($argv[1]??'')==='--self-test'){if(NS!==[18=>'operator_115',25=>'operator_315',43=>'operator_342'])throw new RuntimeException('ns');echo "RESIDUAL_COMMON4_PLAN_SELFTEST_OK\n";exit;}
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');
$root=realpath((string)getenv('ANYTOUR_ROOT'));$input=realpath((string)getenv('MATCH_INPUT_PATH'));
if(!$root||!$input||basename($root)!=='anytoour.ru')throw new RuntimeException('paths');
$src=json_decode((string)file_get_contents($input),true,512,JSON_THROW_ON_ERROR);
if(($src['operation_id']??'')!=='hotel-match-userseen-coverage-1971-20260920-v1')throw new RuntimeException('input_operation');
$residual=[];
foreach(($src['rows']??[]) as $r){
 if(!is_array($r)||empty($r['is_active'])||!empty($r['excluded_market']))continue;
 $b=(string)($r['bucket']??'');if(!in_array($b,['anex_only','samo_only','neither'],true))continue;
 $id=(int)($r['tv_hotel_id']??0);if($id<1||isset($residual[$id]))throw new RuntimeException('input_id');
 $residual[$id]=['tv_hotel_id'=>$id,'name'=>(string)($r['name']??''),'country_id'=>(int)($r['country_id']??0),'country_name'=>(string)($r['country_name']??''),'bucket'=>$b];
}
if(count($residual)!==2041)throw new RuntimeException('input_2041');
require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
try{
 $ids=array_keys($residual);$active=[];
 foreach(array_chunk($ids,500) as $chunk){
  $ph=implode(',',array_fill(0,count($chunk),'?'));
  foreach(rows($db,"SELECT id,name,country_id,country_name,is_active FROM catalog_hotels WHERE id IN ($ph)",$chunk) as $h){
   $id=(int)$h['id'];if((int)$h['is_active']===1&&!in_array(mb_strtolower((string)$h['country_name'],'UTF-8'),['россия','абхазия'],true))$active[$id]=$h;
  }
 }
 $accepted=[];
 foreach(rows($db,"SELECT supplier_namespace,local_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL AND supplier_namespace IN ('operator_115','operator_315','operator_342')") as $r){
  $accepted[(int)$r['local_hotel_id'].'|'.(string)$r['supplier_namespace']]=true;
 }
 $hasObs=(bool)$db->query("SHOW TABLES LIKE 'tour_operator_identity_observations'")->fetchColumn();
 $saved=[];$savedRows=0;
 if($hasObs){
  foreach(rows($db,"SELECT hotel_id,operator_id,tour_id,operator_link,last_seen_at FROM tour_operator_identity_observations WHERE source='user_search' AND operator_id IN (18,25,43) AND operator_link IS NOT NULL AND operator_link<>'' ORDER BY last_seen_at DESC,id DESC") as $r){
   $id=(int)$r['hotel_id'];$op=(int)$r['operator_id'];if(!isset($residual[$id])||!isset(NS[$op]))continue;$k="$id|$op";if(isset($saved[$k]))continue;
   $saved[$k]=['tour_id'=>(string)$r['tour_id'],'operator_link'=>(string)$r['operator_link'],'last_seen_at'=>(string)$r['last_seen_at']];$savedRows++;
  }
 }
 $latest=[];$futureRows=0;
 foreach(rows($db,"SELECT hotel_id,operator_id,tour_id,departure_date,nights,search_id,observed_at FROM tour_price_observations WHERE source='user_search' AND operator_id IN (18,25,43) AND departure_date>=CURRENT_DATE() AND tour_id IS NOT NULL AND tour_id<>'' ORDER BY observed_at DESC") as $r){
  $id=(int)$r['hotel_id'];$op=(int)$r['operator_id'];if(!isset($residual[$id])||!isset(NS[$op]))continue;$futureRows++;$k="$id|$op";if(isset($latest[$k]))continue;
  $latest[$k]=['tour_id'=>(string)$r['tour_id'],'departure_date'=>(string)$r['departure_date'],'nights'=>(int)$r['nights'],'search_id'=>(string)$r['search_id'],'observed_at'=>(string)$r['observed_at']];
 }
 $queue=[];$stats=['input_residual'=>count($residual),'current_active'=>count($active),'saved_link_skip'=>0,'accepted_provider_skip'=>0,'no_future_tour'=>0,'detail_ready'=>0,'by_operator'=>[]];
 foreach($residual as $id=>$base){
  if(!isset($active[$id]))continue;
  foreach(NS as $op=>$ns){
   $k="$id|$op";
   if(isset($accepted["$id|$ns"])){$stats['accepted_provider_skip']++;continue;}
   if(isset($saved[$k])){$stats['saved_link_skip']++;continue;}
   if(!isset($latest[$k])){$stats['no_future_tour']++;continue;}
   $q=$base+$latest[$k]+['operator_id'=>$op,'supplier_namespace'=>$ns,'safe_to_write_now'=>false];
   $queue[]=$q;$stats['detail_ready']++;$stats['by_operator'][(string)$op]=($stats['by_operator'][(string)$op]??0)+1;
  }
 }
 usort($queue,fn($a,$b)=>$a['operator_id']<=>$b['operator_id']?:strcmp($b['observed_at'],$a['observed_at'])?:$a['tv_hotel_id']<=>$b['tv_hotel_id']);
 $clock=rows($db,'SELECT UTC_TIMESTAMP AS db_utc_timestamp')[0]['db_utc_timestamp'];$db->rollBack();
 echo out(['operation'=>OP,'state'=>'current_plan_complete','captured_at'=>$clock,'input_residual_count'=>count($residual),'current_active_count'=>count($active),'saved_link_rows'=>$savedRows,'future_price_rows'=>$futureRows,'stats'=>$stats,'detail_ready'=>$queue,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
