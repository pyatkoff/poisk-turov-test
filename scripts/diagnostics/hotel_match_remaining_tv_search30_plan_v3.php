<?php
declare(strict_types=1);
const OP='hotel-match-remaining-tv-search30-1971-20260921-v3';
const OPS=[13=>'anex',18=>'operator_115',25=>'operator_315',43=>'operator_342'];
const PROTECTED_IDS=[420,1244,81154,68705,72755];
function rr(PDO $db,string $sql,array $p=[]):array{$s=$db->prepare($sql);$s->execute(array_values($p));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function loadp(string $env):array{$p=realpath((string)getenv($env));if(!$p)throw new RuntimeException('missing_'.$env);$x=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);if(!is_array($x))throw new RuntimeException('shape_'.$env);return$x;}
function attempted(array $r):array{$o=[];foreach(($r['batches']??[])as$b)foreach(($b['hotel_ids']??[])as$id)$o[(int)$id]=true;foreach(($r['attempted_batches']??[])as$b)foreach(($b['hotel_ids']??[])as$id)$o[(int)$id]=true;return$o;}
if(($argv[1]??'')==='--self-test'){if(count(array_chunk(range(1,61),30))!==3)throw new RuntimeException('chunk');$x=attempted(['attempted_batches'=>[['hotel_ids'=>[1,2]]]]);if(array_keys($x)!==[1,2])throw new RuntimeException('attempted');echo "REMAINING_SEARCH30_PLAN_V3_SELFTEST_OK\n";exit;}
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');
$root=realpath((string)getenv('ANYTOUR_ROOT'));if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');
$r20=loadp('MATCH_REVERSE20_PATH');$r21=loadp('MATCH_REVERSE21_PATH');$prior=loadp('MATCH_SEARCH30_PATH');
if(($r20['operation']??'')!=='hotel-match-reverse686-live-anex-batches-1971-20260920-v1'||($r21['operation']??'')!=='hotel-match-reverse110-live-anex-continuation-1971-20260921-v3'||($prior['operation']??'')!=='hotel-match-residual2041-search30-common4-1971-20260921-v3')throw new RuntimeException('input_operation');
$sent=attempted($r20)+attempted($r21)+attempted($prior);foreach(PROTECTED_IDS as$id)$sent[$id]=true;
require_once __DIR__.'/hotel_match_anex_effective_coverage.php';
require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
try{
 $seen=rr($db,"SELECT hotel_id,MAX(observed_at) last_seen FROM tour_price_observations WHERE source='user_search' AND hotel_id>0 GROUP BY hotel_id ORDER BY hotel_id");
 $ids=array_values(array_unique(array_map(fn($r)=>(int)$r['hotel_id'],$seen)));$hot=[];
 foreach(array_chunk($ids,500)as$c){$ph=implode(',',array_fill(0,count($c),'?'));foreach(rr($db,"SELECT id,name,country_id,country_name,is_active FROM catalog_hotels WHERE id IN ($ph)",$c)as$h)$hot[(int)$h['id']]=$h;}
 $anex=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$accepted=[];foreach($anex['by_local']as$local=>$native)if($native!==[])$accepted[(int)$local.'|13']=true;
 foreach(rr($db,"SELECT supplier_namespace,local_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL AND supplier_namespace IN ('operator_115','operator_315','operator_342')")as$r){$op=array_search((string)$r['supplier_namespace'],OPS,true);if($op!==false)$accepted[(int)$r['local_hotel_id'].'|'.$op]=true;}
 $saved=[];if((bool)$db->query("SHOW TABLES LIKE 'tour_operator_identity_observations'")->fetchColumn())foreach(rr($db,"SELECT hotel_id,operator_id FROM tour_operator_identity_observations WHERE source='user_search' AND operator_id IN (13,18,25,43) AND operator_link IS NOT NULL AND operator_link<>''")as$r)$saved[(int)$r['hotel_id'].'|'.(int)$r['operator_id']]=true;
 $targets=[];$stats=['current_userseen'=>count($ids),'prior_or_protected'=>0,'inactive_or_excluded'=>0,'all_edges_evidenced'=>0,'search_targets'=>0,'missing_edges'=>[]];
 foreach($ids as$id){if(isset($sent[$id])){$stats['prior_or_protected']++;continue;}$h=$hot[$id]??null;if(!$h||(int)$h['is_active']!==1||in_array(mb_strtolower((string)$h['country_name'],'UTF-8'),['россия','абхазия','russia','russian federation','abkhazia'],true)){$stats['inactive_or_excluded']++;continue;}$missing=[];foreach(array_keys(OPS)as$op)if(!isset($accepted["$id|$op"])&&!isset($saved["$id|$op"])){$missing[]=$op;$stats['missing_edges'][(string)$op]=($stats['missing_edges'][(string)$op]??0)+1;}if(!$missing){$stats['all_edges_evidenced']++;continue;}$targets[]=['tv_hotel_id'=>$id,'name'=>(string)$h['name'],'country_id'=>(int)$h['country_id'],'country_name'=>(string)$h['country_name'],'missing_operator_ids'=>$missing,'safe_to_write_now'=>false];$stats['search_targets']++;}
 $db->rollBack();$by=[];foreach($targets as$t)$by[$t['country_id']][]=$t;$groups=[];$n=0;ksort($by,SORT_NUMERIC);foreach($by as$cid=>$rows){usort($rows,fn($a,$b)=>$a['tv_hotel_id']<=>$b['tv_hotel_id']);foreach(array_chunk($rows,30)as$c){$groups[]=['batch'=>++$n,'country_id'=>(int)$cid,'country_name'=>$c[0]['country_name'],'hotel_ids'=>array_column($c,'tv_hotel_id'),'targets'=>$c];}}
 echo json_encode(['operation'=>OP,'state'=>'current_plan_complete','stats'=>$stats,'attempted_search_ids'=>count($sent),'group_count'=>count($groups),'groups'=>$groups,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
