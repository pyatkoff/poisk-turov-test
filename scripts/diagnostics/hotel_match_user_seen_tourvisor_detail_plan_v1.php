<?php
declare(strict_types=1);

const HMTDP_OPERATION='hotel-match-user-seen-tourvisor-detail-plan-1971-20260918-v1';
const HMTDP_TRANCHE=300;

function hmtdp_rows(PDO $pdo,string $sql,array $params=[]): array {
    $s=$pdo->prepare($sql);$s->execute(array_values($params));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];
}
function hmtdp_table(PDO $pdo,string $t): bool {
    $s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $s->execute([$t]);return $s->fetchColumn()!==false;
}
function hmtdp_cols(PDO $pdo,string $t): array {
    $s=$pdo->prepare('SELECT COLUMN_NAME,DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
    $s->execute([$t]);$out=[];foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r)$out[(string)$r['COLUMN_NAME']]=(string)$r['DATA_TYPE'];return $out;
}
function hmtdp_pick(array $cols,array $names): ?string {foreach($names as $n)if(isset($cols[$n]))return $n;return null;}
function hmtdp_scalar(mixed $v,int $max=300): string {return is_scalar($v)?mb_substr(trim((string)$v),0,$max,'UTF-8'):'';}
function hmtdp_int(mixed $v): int {return is_numeric($v)?max(0,(int)$v):0;}
function hmtdp_family(string $v): ?string {
    $n=mb_strtolower(trim($v),'UTF-8');$n=str_replace(['&','+','_','-'],' ',$n);$n=preg_replace('/[^\p{L}\p{N}]+/u',' ',$n)??$n;$n=trim(preg_replace('/\s+/u',' ',$n)??$n);
    if($n==='')return null;
    foreach([
        'anex'=>['anex','anex tour','анекс','анекс тур'],
        'biblio'=>['biblio globus','biblioglobus','библио глобус'],
        'funsun'=>['fun sun','funsun','фан сан'],
        'intourist'=>['intourist','интурист'],
    ] as $f=>$a)if(in_array($n,$a,true))return $f;
    return null;
}
function hmtdp_score_row(array $r,?string $obsCol,?string $lastCol): array {
    return [hmtdp_int($obsCol!==null?($r[$obsCol]??0):0),$lastCol!==null?hmtdp_scalar($r[$lastCol]??'',64):''];
}
function hmtdp_better(array $a,array $b,?string $obsCol,?string $lastCol): bool {
    [$ao,$al]=hmtdp_score_row($a,$obsCol,$lastCol);[$bo,$bl]=hmtdp_score_row($b,$obsCol,$lastCol);
    if($ao!==$bo)return $ao>$bo;if($al!==$bl)return strcmp($al,$bl)>0;return false;
}
if(in_array('--self-test',$argv??[],true)){
    if(hmtdp_family('FUN&SUN')!=='funsun'||hmtdp_family('Библио-Глобус')!=='biblio'||HMTDP_TRANCHE!==300)throw new RuntimeException('self_test');
    echo "MATCH_USER_SEEN_TV_DETAIL_PLAN_SELFTEST_OK\n";exit(0);
}
if(PHP_SAPI!=='cli')exit(2);
$root=realpath((string)getenv('ANYTOUR_ROOT'));$opDir=realpath((string)getenv('MATCH_OPERATION_DIR'));
if(!$root||!$opDir)throw new RuntimeException('runtime_paths');
require_once $opDir.'/payload/anex-search-mapping-registry.php';
$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
require_once $dbf;$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach(['tour_price_observations','catalog_hotels','anex_hotel_search_mappings','anex_hotel_decisions','andromeda_hotel_identities','tour_operator_identity_observations'] as $t)if(!hmtdp_table($pdo,$t))throw new RuntimeException('missing_'.$t);
$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION READ ONLY');
try{
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
    $anexTargets=[];
    foreach(hmtdp_rows($pdo,'SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions') as $r){
        $eid=(string)$r['anex_hotel_id'];$target=$registry->resolve('anex_online',$eid,'preview');if(is_int($target)&&$target>0)$anexTargets[$target]=true;
    }
    $andTargets=[];
    foreach(hmtdp_rows($pdo,"SELECT DISTINCT i.local_hotel_id FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL") as $r){$id=(int)$r['local_hotel_id'];if($id>0)$andTargets[$id]=true;}
    $obs=hmtdp_rows($pdo,"SELECT hotel_id,MAX(observed_at) last_observed_at,COUNT(*) observation_rows FROM tour_price_observations WHERE source='user_search' GROUP BY hotel_id");
    $seen=[];foreach($obs as $r){$id=(int)$r['hotel_id'];if($id>0)$seen[$id]=['last_observed_at'=>(string)$r['last_observed_at'],'observation_rows'=>(int)$r['observation_rows']];}
    if(!$seen)throw new RuntimeException('no_user_search_observations');
    $ids=array_keys($seen);$ph=implode(',',array_fill(0,count($ids),'?'));
    $frontier=[];
    foreach(hmtdp_rows($pdo,"SELECT id,country_id,country_name,name,region_name,subregion_name,is_active FROM catalog_hotels WHERE id IN ($ph)",$ids) as $r){
        $id=(int)$r['id'];if((int)$r['is_active']!==1||isset($anexTargets[$id])||isset($andTargets[$id]))continue;
        $frontier[$id]=['hotel_id'=>$id,'country_id'=>(int)$r['country_id'],'country_name'=>(string)$r['country_name'],'hotel_name'=>(string)$r['name'],'region_name'=>(string)$r['region_name'],'subregion_name'=>(string)$r['subregion_name'],'user_observation_rows'=>$seen[$id]['observation_rows'],'last_user_observed_at'=>$seen[$id]['last_observed_at']];
    }
    if(!$frontier)throw new RuntimeException('frontier_empty');

    $cols=hmtdp_cols($pdo,'tour_operator_identity_observations');
    $hotelCol=hmtdp_pick($cols,['hotel_id','local_hotel_id','catalog_hotel_id']);
    $tourCol=hmtdp_pick($cols,['tour_id','tourvisor_tour_id','sample_tour_id','last_tour_id']);
    $operatorCol=hmtdp_pick($cols,['operator_id','tour_operator_id','operator_key']);
    $operatorNameCol=hmtdp_pick($cols,['operator_name','tour_operator_name','operator']);
    $obsCol=hmtdp_pick($cols,['observation_count','search_count','seen_count','occurrence_count']);
    $lastCol=hmtdp_pick($cols,['last_seen_at','last_seen_utc','observed_at','updated_at']);
    $linkCol=hmtdp_pick($cols,['operator_link','operator_url','link']);
    $nativeCol=hmtdp_pick($cols,['native_hotel_id','operator_hotel_id','external_hotel_id']);
    if($hotelCol===null)throw new RuntimeException('identity_hotel_column_missing');

    $fids=array_keys($frontier);$fh=implode(',',array_fill(0,count($fids),'?'));
    $rows=hmtdp_rows($pdo,"SELECT * FROM tour_operator_identity_observations WHERE `$hotelCol` IN ($fh)",$fids);
    $byHotelOperator=[];$rowsWithTour=0;$rowsWithLink=0;$rowsWithNative=0;$operatorCounts=[];
    foreach($rows as $r){
        $hotel=(int)($r[$hotelCol]??0);if(!isset($frontier[$hotel]))continue;
        $tour=$tourCol!==null?hmtdp_scalar($r[$tourCol]??'',220):'';
        $op=$operatorCol!==null?hmtdp_scalar($r[$operatorCol]??'',80):'';
        $opName=$operatorNameCol!==null?hmtdp_scalar($r[$operatorNameCol]??'',200):'';
        if($tour!=='')$rowsWithTour++;
        if($linkCol!==null&&hmtdp_scalar($r[$linkCol]??'',2048)!=='')$rowsWithLink++;
        if($nativeCol!==null&&hmtdp_scalar($r[$nativeCol]??'',120)!=='')$rowsWithNative++;
        $opKey=$op!==''?$op:($opName!==''?'name:'.$opName:'unknown');$operatorCounts[$opKey]=($operatorCounts[$opKey]??0)+1;
        if($tour==='')continue;
        $k=$hotel.'|'.$opKey;
        if(!isset($byHotelOperator[$k])||hmtdp_better($r,$byHotelOperator[$k],$obsCol,$lastCol))$byHotelOperator[$k]=$r;
    }

    $seeds=[];$hotelHasSeed=[];$familyCoverage=[];
    foreach($byHotelOperator as $r){
        $hotel=(int)$r[$hotelCol];$tour=hmtdp_scalar($r[$tourCol],220);$op=$operatorCol!==null?hmtdp_scalar($r[$operatorCol]??'',80):'';$opName=$operatorNameCol!==null?hmtdp_scalar($r[$operatorNameCol]??'',200):'';$family=hmtdp_family($opName);
        [$passiveCount,$passiveLast]=hmtdp_score_row($r,$obsCol,$lastCol);
        $seed=$frontier[$hotel]+['tour_id'=>$tour,'operator_id'=>$op!==''?$op:null,'operator_name'=>$opName!==''?$opName:null,'operator_family'=>$family,'passive_observation_count'=>$passiveCount,'passive_last_seen_at'=>$passiveLast!==''?$passiveLast:null,'existing_operator_link'=>$linkCol!==null?hmtdp_scalar($r[$linkCol]??'',2048):'','existing_native_hotel_id'=>$nativeCol!==null?hmtdp_scalar($r[$nativeCol]??'',120):''];
        $seeds[]=$seed;$hotelHasSeed[$hotel]=true;if($family!==null)$familyCoverage[$family][$hotel]=true;
    }
    usort($seeds,function($a,$b){$priority=['anex'=>4,'biblio'=>3,'funsun'=>2,'intourist'=>1,null=>0];$pa=$priority[$a['operator_family']]??0;$pb=$priority[$b['operator_family']]??0;return $b['user_observation_rows']<=>$a['user_observation_rows'] ?: $pb<=>$pa ?: $b['passive_observation_count']<=>$a['passive_observation_count'] ?: $a['hotel_id']<=>$b['hotel_id'];});

    // First execution tranche: one tour per local hotel, prioritized by current tourist exposure and common4 family.
    $tranche=[];$picked=[];
    foreach($seeds as $s){$hid=(int)$s['hotel_id'];if(isset($picked[$hid]))continue;$picked[$hid]=true;$tranche[]=$s;if(count($tranche)>=HMTDP_TRANCHE)break;}

    $result=['operation'=>HMTDP_OPERATION,'status'=>'read_only_complete','frontier_count'=>count($frontier),'identity_schema'=>$cols,
      'detected_columns'=>['hotel'=>$hotelCol,'tour'=>$tourCol,'operator'=>$operatorCol,'operator_name'=>$operatorNameCol,'observation_count'=>$obsCol,'last_seen'=>$lastCol,'operator_link'=>$linkCol,'native_hotel_id'=>$nativeCol],
      'frontier_identity_rows'=>count($rows),'rows_with_saved_tour_id'=>$rowsWithTour,'rows_with_existing_operator_link'=>$rowsWithLink,'rows_with_existing_native_id'=>$rowsWithNative,
      'eligible_hotel_operator_seeds'=>count($seeds),'eligible_unique_local_hotels'=>count($hotelHasSeed),'common_family_unique_hotels'=>array_map('count',$familyCoverage),
      'operator_row_counts'=>$operatorCounts,'first_execution_tranche_count'=>count($tranche),'first_execution_tranche'=>$tranche,'all_seeds'=>$seeds,
      'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0];
    $pdo->rollBack();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
