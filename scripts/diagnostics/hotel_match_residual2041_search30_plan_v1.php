<?php
declare(strict_types=1);
const OP='hotel-match-residual2041-search30-common4-1971-20260921-v2';
const OPS=[13=>'anex',18=>'operator_115',25=>'operator_315',43=>'operator_342'];
const PROTECTED_HOTEL_IDS=[420,1244,81154,68705,72755];
function rr(PDO $db,string $sql,array $p=[]):array{$s=$db->prepare($sql);$s->execute(array_values($p));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function js(array $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function loadp(string $env):array{$p=realpath((string)getenv($env));if(!$p)throw new RuntimeException('missing_'.$env);$x=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);if(!is_array($x))throw new RuntimeException('shape_'.$env);return$x;}
function attempted(array $r):array{$o=[];foreach(($r['batches']??[])as$b)foreach(($b['hotel_ids']??[])as$id)$o[(int)$id]=true;return$o;}
if(($argv[1]??'')==='--self-test'){if(count(array_chunk(range(1,61),30))!==3)throw new RuntimeException('chunk');$x=attempted(['batches'=>[['hotel_ids'=>[1,2]],['hotel_ids'=>[3]]]]);if(array_keys($x)!==[1,2,3])throw new RuntimeException('attempted');echo "SEARCH30_PLAN_SELFTEST_OK\n";exit;}
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');
$root=realpath((string)getenv('ANYTOUR_ROOT'));if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');
$census=loadp('MATCH_CENSUS_PATH');$r20=loadp('MATCH_REVERSE20_PATH');$r21=loadp('MATCH_REVERSE21_PATH');$detail=loadp('MATCH_DETAIL_PATH');
if(($census['operation_id']??'')!=='hotel-match-userseen-coverage-1971-20260920-v1'||($r21['operation']??'')!=='hotel-match-reverse110-live-anex-continuation-1971-20260921-v2'||($detail['operation']??'')!=='hotel-match-residual2041-common4-nonanex-detail-1971-20260921-v4')throw new RuntimeException('input_operation');
$residual=[];foreach(($census['rows']??[])as$r){if(!is_array($r)||empty($r['is_active'])||!empty($r['excluded_market'])||!in_array((string)($r['bucket']??''),['anex_only','samo_only','neither'],true))continue;$id=(int)$r['tv_hotel_id'];if($id<1||isset($residual[$id]))throw new RuntimeException('census_id');$residual[$id]=['tv_hotel_id'=>$id,'name'=>(string)$r['name'],'country_id'=>(int)$r['country_id'],'country_name'=>(string)$r['country_name'],'bucket'=>(string)$r['bucket']];}
if(count($residual)!==2041)throw new RuntimeException('census_2041');
$attempted=attempted($r20)+attempted($r21);foreach(PROTECTED_HOTEL_IDS as$id)$attempted[$id]=true;
$priorDetails=[];$detailLinks=[];foreach(($detail['edges']??[])as$e){if(!is_array($e))continue;$id=(int)($e['tv_hotel_id']??0);$op=(int)($e['operator_id']??0);$tid=(string)($e['tour_id']??'');if($id>0&&isset(OPS[$op])&&preg_match('/^[1-9][0-9]{0,31}$/D',$tid))$priorDetails["$id|$op|$tid"]=true;if($id>0&&isset(OPS[$op])&&str_starts_with((string)($e['link_state']??''),'captured'))$detailLinks["$id|$op"]=true;}
require_once $root.'/scripts/diagnostics/hotel_match_anex_effective_coverage.php';
require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
try{
 $ids=array_keys($residual);$active=[];
 foreach(array_chunk($ids,500)as$c){$ph=implode(',',array_fill(0,count($c),'?'));foreach(rr($db,"SELECT id,name,country_id,country_name,is_active FROM catalog_hotels WHERE id IN ($ph)",$c)as$h){$id=(int)$h['id'];if((int)$h['is_active']===1&&!in_array(mb_strtolower((string)$h['country_name'],'UTF-8'),['россия','абхазия'],true))$active[$id]=$h;}}
 $anex=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$accepted=[];foreach($anex['by_local']as$local=>$native)if($native!==[])$accepted[(int)$local.'|13']=true;
 foreach(rr($db,"SELECT supplier_namespace,local_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL AND supplier_namespace IN ('operator_115','operator_315','operator_342')")as$r){$op=array_search((string)$r['supplier_namespace'],OPS,true);if($op!==false)$accepted[(int)$r['local_hotel_id'].'|'.$op]=true;}
 $saved=[];$has=(bool)$db->query("SHOW TABLES LIKE 'tour_operator_identity_observations'")->fetchColumn();
 if($has)foreach(rr($db,"SELECT hotel_id,operator_id FROM tour_operator_identity_observations WHERE source='user_search' AND operator_id IN (13,18,25,43) AND operator_link IS NOT NULL AND operator_link<>''")as$r)$saved[(int)$r['hotel_id'].'|'.(int)$r['operator_id']]=true;
 $targets=[];$stats=['input'=>2041,'prior_search_attempted'=>0,'protected'=>0,'inactive_or_excluded'=>0,'all_edges_already_evidenced'=>0,'search_targets'=>0,'missing_edges'=>[]];
 foreach($residual as$id=>$base){
  if(in_array($id,PROTECTED_HOTEL_IDS,true)){$stats['protected']++;continue;}
  if(isset($attempted[$id])){$stats['prior_search_attempted']++;continue;}
  if(!isset($active[$id])){$stats['inactive_or_excluded']++;continue;}
  $missing=[];foreach(array_keys(OPS)as$op)if(!isset($accepted["$id|$op"])&&!isset($saved["$id|$op"])&&!isset($detailLinks["$id|$op"])){$missing[]=$op;$stats['missing_edges'][(string)$op]=($stats['missing_edges'][(string)$op]??0)+1;}
  if($missing===[]){$stats['all_edges_already_evidenced']++;continue;}
  $h=$active[$id];$targets[]=['tv_hotel_id'=>$id,'name'=>(string)$h['name'],'country_id'=>(int)$h['country_id'],'country_name'=>(string)$h['country_name'],'missing_operator_ids'=>$missing,'safe_to_write_now'=>false];$stats['search_targets']++;
 }
 $db->rollBack();$by=[];foreach($targets as$t)$by[$t['country_id']][]=$t;$groups=[];$n=0;ksort($by,SORT_NUMERIC);foreach($by as$cid=>$rows){usort($rows,fn($a,$b)=>$a['tv_hotel_id']<=>$b['tv_hotel_id']);foreach(array_chunk($rows,30)as$c){$n++;$groups[]=['batch'=>$n,'country_id'=>(int)$cid,'country_name'=>$c[0]['country_name'],'hotel_ids'=>array_column($c,'tv_hotel_id'),'targets'=>$c];}}
 foreach($groups as$g)if(count($g['hotel_ids'])>30||count($g['hotel_ids'])<1||count($g['hotel_ids'])!==count(array_unique($g['hotel_ids'])))throw new RuntimeException('batch_guard');
 echo js(['operation'=>OP,'state'=>'current_plan_complete','stats'=>$stats,'attempted_search_ids'=>count($attempted),'detail_saved_edges'=>count($detailLinks),'prior_detail_attempts'=>array_keys($priorDetails),'group_count'=>count($groups),'groups'=>$groups,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
