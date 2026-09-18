<?php
declare(strict_types=1);

const HMPV2_OPERATION='hotel-match-user-seen-price-tour-detail-plan-1971-20260918-v2';
const HMPV2_TRANCHE=300;

function hmpv2_rows(PDO $pdo,string $sql,array $params=[]): array {$s=$pdo->prepare($sql);$s->execute(array_values($params));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hmpv2_table(PDO $pdo,string $t): bool {$s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');$s->execute([$t]);return $s->fetchColumn()!==false;}
function hmpv2_family(string $v): ?string {
    $n=mb_strtolower(trim($v),'UTF-8');$n=str_replace(['&','+','_','-'],' ',$n);$n=preg_replace('/[^\p{L}\p{N}]+/u',' ',$n)??$n;$n=trim(preg_replace('/\s+/u',' ',$n)??$n);
    foreach(['anex'=>['anex','anex tour','анекс','анекс тур'],'biblio'=>['biblio globus','biblioglobus','библио глобус'],'funsun'=>['fun sun','funsun','фан сан'],'intourist'=>['intourist','интурист']] as $f=>$a)if(in_array($n,$a,true))return $f;
    return null;
}
function hmpv2_scalar(mixed $v,int $max=255): string {return is_scalar($v)?mb_substr(trim((string)$v),0,$max,'UTF-8'):'';}
if(in_array('--self-test',$argv??[],true)){if(hmpv2_family('FUN&SUN')!=='funsun'||hmpv2_family('Библио-Глобус')!=='biblio'||HMPV2_TRANCHE!==300)throw new RuntimeException('self_test');echo "MATCH_PRICE_TOUR_DETAIL_PLAN_V2_SELFTEST_OK\n";exit(0);}
if(PHP_SAPI!=='cli')exit(2);
$root=realpath((string)getenv('ANYTOUR_ROOT'));$opDir=realpath((string)getenv('MATCH_OPERATION_DIR'));if(!$root||!$opDir)throw new RuntimeException('runtime_paths');
require_once $opDir.'/payload/anex-search-mapping-registry.php';
$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbf;$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach(['tour_price_observations','catalog_hotels','anex_hotel_search_mappings','anex_hotel_decisions','andromeda_hotel_identities','tour_operator_identity_observations'] as $t)if(!hmpv2_table($pdo,$t))throw new RuntimeException('missing_'.$t);
$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION READ ONLY');
try{
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);$anexTargets=[];
    foreach(hmpv2_rows($pdo,'SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions') as $r){$x=$registry->resolve('anex_online',(string)$r['anex_hotel_id'],'preview');if(is_int($x)&&$x>0)$anexTargets[$x]=true;}
    $andTargets=[];foreach(hmpv2_rows($pdo,"SELECT DISTINCT i.local_hotel_id FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL") as $r){$x=(int)$r['local_hotel_id'];if($x>0)$andTargets[$x]=true;}
    $seen=[];foreach(hmpv2_rows($pdo,"SELECT hotel_id,MAX(observed_at) last_observed_at,COUNT(*) observation_rows FROM tour_price_observations WHERE source='user_search' GROUP BY hotel_id") as $r){$id=(int)$r['hotel_id'];if($id>0)$seen[$id]=['last_observed_at'=>(string)$r['last_observed_at'],'observation_rows'=>(int)$r['observation_rows']];}
    if(!$seen)throw new RuntimeException('no_user_search');$ids=array_keys($seen);$ph=implode(',',array_fill(0,count($ids),'?'));
    $frontier=[];foreach(hmpv2_rows($pdo,"SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,is_active FROM catalog_hotels WHERE id IN ($ph)",$ids) as $r){$id=(int)$r['id'];if((int)$r['is_active']!==1||isset($anexTargets[$id])||isset($andTargets[$id]))continue;$frontier[$id]=['hotel_id'=>$id,'hotel_name'=>(string)$r['name'],'country_id'=>(int)$r['country_id'],'country_name'=>(string)$r['country_name'],'region_id'=>$r['region_id']===null?null:(int)$r['region_id'],'region_name'=>(string)($r['region_name']??''),'subregion_id'=>$r['subregion_id']===null?null:(int)$r['subregion_id'],'subregion_name'=>(string)($r['subregion_name']??''),'user_observation_rows'=>$seen[$id]['observation_rows'],'last_user_observed_at'=>$seen[$id]['last_observed_at']];}
    $fids=array_keys($frontier);if(!$fids)throw new RuntimeException('frontier_empty');$fh=implode(',',array_fill(0,count($fids),'?'));

    $opNames=[];foreach(hmpv2_rows($pdo,"SELECT operator_id,operator_name,SUM(observation_count) n,MAX(last_seen_at) last_seen FROM tour_operator_identity_observations WHERE operator_id IS NOT NULL GROUP BY operator_id,operator_name ORDER BY operator_id,n DESC,last_seen DESC") as $r){$id=(int)$r['operator_id'];$name=hmpv2_scalar($r['operator_name']??'',180);if($id>0&&$name!==''&&!isset($opNames[$id]))$opNames[$id]=$name;}

    $priceRows=hmpv2_rows($pdo,"SELECT hotel_id,operator_id,tour_id,search_id,observed_at,COUNT(*) OVER (PARTITION BY hotel_id,operator_id) AS hotel_operator_rows FROM tour_price_observations WHERE source='user_search' AND hotel_id IN ($fh) AND tour_id IS NOT NULL AND tour_id<>'' AND operator_id IS NOT NULL ORDER BY observed_at DESC",$fids);
    $best=[];$tourIds=[];$families=[];$localWithAny=[];$localWithCommon=[];
    foreach($priceRows as $r){$hid=(int)$r['hotel_id'];$oid=(int)$r['operator_id'];$tour=hmpv2_scalar($r['tour_id']??'',220);if(!isset($frontier[$hid])||$oid<1||$tour==='')continue;$key=$hid.'|'.$oid;if(!isset($best[$key]))$best[$key]=$r;$tourIds[$tour]=true;$localWithAny[$hid]=true;$name=$opNames[$oid]??'';$fam=hmpv2_family($name);if($fam!==null){$families[$fam][$hid]=true;$localWithCommon[$hid]=true;}}
    $seeds=[];foreach($best as $r){$hid=(int)$r['hotel_id'];$oid=(int)$r['operator_id'];$name=$opNames[$oid]??'';$fam=hmpv2_family($name);$seeds[]=$frontier[$hid]+['operator_id'=>$oid,'operator_name'=>$name!==''?$name:null,'operator_family'=>$fam,'tour_id'=>hmpv2_scalar($r['tour_id'],220),'search_id'=>$r['search_id']===null?null:hmpv2_scalar($r['search_id'],80),'tour_observed_at'=>(string)$r['observed_at'],'hotel_operator_observation_rows'=>(int)$r['hotel_operator_rows']];}
    $priority=['anex'=>4,'biblio'=>3,'funsun'=>2,'intourist'=>1];
    usort($seeds,function($a,$b)use($priority){$pa=$priority[$a['operator_family']]??0;$pb=$priority[$b['operator_family']]??0;return $b['user_observation_rows']<=>$a['user_observation_rows'] ?: $pb<=>$pa ?: $b['hotel_operator_observation_rows']<=>$a['hotel_operator_observation_rows'] ?: strcmp($b['tour_observed_at'],$a['tour_observed_at']) ?: $a['hotel_id']<=>$b['hotel_id'];});
    $tranche=[];$picked=[];foreach($seeds as $s){$hid=(int)$s['hotel_id'];if(isset($picked[$hid]))continue;$picked[$hid]=true;$tranche[]=$s;if(count($tranche)>=HMPV2_TRANCHE)break;}
    $commonTranche=[];$commonPicked=[];foreach($seeds as $s){if($s['operator_family']===null)continue;$hid=(int)$s['hotel_id'];if(isset($commonPicked[$hid]))continue;$commonPicked[$hid]=true;$commonTranche[]=$s;if(count($commonTranche)>=HMPV2_TRANCHE)break;}
    $pdo->rollBack();
    $result=['operation'=>HMPV2_OPERATION,'status'=>'read_only_complete','frontier_count'=>count($frontier),'user_search_rows_with_saved_tour'=>count($priceRows),'distinct_saved_tour_ids'=>count($tourIds),'eligible_hotel_operator_seeds'=>count($seeds),'eligible_unique_local_hotels'=>count($localWithAny),'common4_unique_local_hotels'=>count($localWithCommon),'common4_family_unique_hotels'=>array_map('count',$families),'operator_names'=>$opNames,'first_execution_tranche_count'=>count($tranche),'first_execution_tranche'=>$tranche,'common4_execution_tranche_count'=>count($commonTranche),'common4_execution_tranche'=>$commonTranche,'all_seeds'=>$seeds,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0];
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
