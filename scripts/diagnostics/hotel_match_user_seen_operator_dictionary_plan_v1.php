<?php
declare(strict_types=1);

const HMOP_OPERATION='hotel-match-user-seen-operator-dictionary-plan-1971-20260918-v1';
const HMOP_TRANCHE=300;
const HMOP_ROW_LIMIT=20000;

function hmop_rows(PDO $pdo,string $sql,array $params=[]): array {
    $s=$pdo->prepare($sql);$s->execute(array_values($params));$r=$s->fetchAll(PDO::FETCH_ASSOC)?:[];
    if(count($r)>HMOP_ROW_LIMIT)throw new RuntimeException('row_budget');
    return $r;
}
function hmop_table(PDO $pdo,string $t): bool {
    $s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $s->execute([$t]);return $s->fetchColumn()!==false;
}
function hmop_cols(PDO $pdo,string $t): array {
    $s=$pdo->prepare('SELECT COLUMN_NAME,DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
    $s->execute([$t]);$o=[];foreach($s->fetchAll(PDO::FETCH_ASSOC)?:[] as $r)$o[(string)$r['COLUMN_NAME']]=(string)$r['DATA_TYPE'];return $o;
}
function hmop_ident(string $v): string {if(!preg_match('/^[A-Za-z0-9_]+$/D',$v))throw new RuntimeException('bad_identifier');return '`'.$v.'`';}
function hmop_scalar(mixed $v,int $max=255): string {return is_scalar($v)?mb_substr(trim((string)$v),0,$max,'UTF-8'):'';}
function hmop_norm(string $v): string {
    $n=mb_strtolower(trim($v),'UTF-8');$n=str_replace(['ё'=>'е','&'=>' ','+'=>' ','_'=>' ','-'=>' '],$n);
    $n=preg_replace('/[^\p{L}\p{N}]+/u',' ',$n)??$n;return trim(preg_replace('/\s+/u',' ',$n)??$n);
}
function hmop_family(string $v): ?string {
    $n=hmop_norm($v);
    $aliases=[
        'anex'=>['anex','anex tour','anextour','анекс','анекс тур','анекс туризм'],
        'biblio'=>['biblio globus','biblioglobus','biblio globus tour','библио глобус','библио глобус тур'],
        'funsun'=>['fun sun','funsun','fun and sun','фан сан','фан энд сан'],
        'intourist'=>['intourist','интурист','ntk intourist','нтк интурист'],
    ];
    foreach($aliases as $f=>$xs)if(in_array($n,$xs,true))return $f;
    return null;
}
function hmop_durable(string $path,array $v): string {
    $raw=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    $f=@fopen($path,'x+b');if(!$f)throw new RuntimeException('durable_exists');
    try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('durable_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('durable_sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('durable_readback');}finally{fclose($f);}return hash('sha256',$raw);
}
function hmop_add_name(array &$dict,int $id,string $name,string $source): void {
    $name=hmop_scalar($name,180);if($id<1||$name==='')return;$key=hmop_norm($name);if($key==='')return;
    $dict[$id][$key]['names'][$name]=true;$dict[$id][$key]['sources'][$source]=true;
}

if(in_array('--self-test',$argv??[],true)){
    foreach(['ANEX'=>'anex','Библио-Глобус'=>'biblio','FUN&SUN'=>'funsun','НТК Интурист'=>'intourist'] as $x=>$f)if(hmop_family($x)!==$f)throw new RuntimeException('family_'.$f);
    echo "MATCH_USER_SEEN_OPERATOR_DICTIONARY_SELFTEST_OK\n";exit(0);
}
if(PHP_SAPI!=='cli')exit(2);

$root=realpath((string)getenv('ANYTOUR_ROOT'));$opDir=(string)getenv('MATCH_OPERATION_DIR');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
if(!$root||$opDir===''||!preg_match('/^[a-f0-9]{40}$/D',$sourceSha))throw new RuntimeException('runtime_guard');
if(is_dir($opDir)||!mkdir($opDir,0700,true))throw new RuntimeException('operation_exists');
hmop_durable($opDir.'/reservation.json',['operation'=>HMOP_OPERATION,'source_sha'=>$sourceSha,'state'=>'reserved_before_db_read','provider_access'=>false,'no_replay'=>true]);

require_once $root.'/app/integrations/anex-search-mapping-registry.php';
$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbf;
$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach(['tour_price_observations','catalog_hotels','anex_hotel_search_mappings','anex_hotel_decisions','andromeda_hotel_identities','tour_operator_identity_observations'] as $t)if(!hmop_table($pdo,$t))throw new RuntimeException('missing_'.$t);

$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION READ ONLY');
try{
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);$anexTargets=[];
    foreach(hmop_rows($pdo,'SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions') as $r){$x=$registry->resolve('anex_online',(string)$r['anex_hotel_id'],'preview');if(is_int($x)&&$x>0)$anexTargets[$x]=true;}
    $andTargets=[];foreach(hmop_rows($pdo,"SELECT DISTINCT i.local_hotel_id FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL") as $r){$x=(int)$r['local_hotel_id'];if($x>0)$andTargets[$x]=true;}
    $seen=[];foreach(hmop_rows($pdo,"SELECT hotel_id,MAX(observed_at) last_observed_at,COUNT(*) observation_rows FROM tour_price_observations WHERE source='user_search' GROUP BY hotel_id") as $r){$id=(int)$r['hotel_id'];if($id>0)$seen[$id]=['last_observed_at'=>(string)$r['last_observed_at'],'observation_rows'=>(int)$r['observation_rows']];}
    $ids=array_keys($seen);if(!$ids)throw new RuntimeException('no_seen');$ph=implode(',',array_fill(0,count($ids),'?'));
    $frontier=[];foreach(hmop_rows($pdo,"SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,is_active FROM catalog_hotels WHERE id IN ($ph)",$ids) as $r){$id=(int)$r['id'];if((int)$r['is_active']!==1||isset($anexTargets[$id])||isset($andTargets[$id]))continue;$frontier[$id]=['hotel_id'=>$id,'hotel_name'=>(string)$r['name'],'country_id'=>(int)$r['country_id'],'country_name'=>(string)$r['country_name'],'region_id'=>$r['region_id']===null?null:(int)$r['region_id'],'region_name'=>(string)($r['region_name']??''),'subregion_id'=>$r['subregion_id']===null?null:(int)$r['subregion_id'],'subregion_name'=>(string)($r['subregion_name']??''),'user_observation_rows'=>$seen[$id]['observation_rows'],'last_user_observed_at'=>$seen[$id]['last_observed_at']];}
    $fids=array_keys($frontier);if(!$fids)throw new RuntimeException('frontier_empty');$fh=implode(',',array_fill(0,count($fids),'?'));

    $priceRows=hmop_rows($pdo,"SELECT hotel_id,operator_id,tour_id,search_id,observed_at,COUNT(*) OVER (PARTITION BY hotel_id,operator_id) AS hotel_operator_rows FROM tour_price_observations WHERE source='user_search' AND hotel_id IN ($fh) AND tour_id IS NOT NULL AND tour_id<>'' AND operator_id IS NOT NULL ORDER BY observed_at DESC",$fids);
    $operatorIds=[];foreach($priceRows as $r){$oid=(int)$r['operator_id'];if($oid>0)$operatorIds[$oid]=true;}$operatorIds=array_keys($operatorIds);sort($operatorIds,SORT_NUMERIC);
    if(!$operatorIds)throw new RuntimeException('no_operators');$oh=implode(',',array_fill(0,count($operatorIds),'?'));

    $dict=[];$relationInventory=[];
    foreach(hmop_rows($pdo,"SELECT TABLE_NAME,TABLE_TYPE,COALESCE(ENGINE,'') ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND (LOWER(TABLE_NAME) LIKE '%operator%' OR LOWER(TABLE_NAME) LIKE '%tourvisor%') ORDER BY TABLE_NAME") as $meta){
        $t=(string)$meta['TABLE_NAME'];$cols=hmop_cols($pdo,$t);$relationInventory[$t]=['table_type'=>(string)$meta['TABLE_TYPE'],'engine'=>(string)$meta['ENGINE'],'columns'=>array_keys($cols),'dictionary_queries'=>[]];
        $idCol=null;foreach(['operator_id','tour_operator_id','operatorid','id'] as $c)if(isset($cols[$c])){$idCol=$c;break;}
        $nameCols=[];foreach(['operator_name','tour_operator_name','name','title','label'] as $c)if(isset($cols[$c]))$nameCols[]=$c;
        if($idCol===null||!$nameCols)continue;
        if($idCol==='id'&&!preg_match('/operator/i',$t))continue;
        foreach($nameCols as $nameCol){
            $relationInventory[$t]['dictionary_queries'][]=$idCol.'→'.$nameCol;
            $sql='SELECT '.hmop_ident($idCol).' oid,'.hmop_ident($nameCol).' oname FROM '.hmop_ident($t).' WHERE '.hmop_ident($idCol).' IN ('.$oh.') AND '.hmop_ident($nameCol).' IS NOT NULL LIMIT 20000';
            try{foreach(hmop_rows($pdo,$sql,$operatorIds) as $r)hmop_add_name($dict,(int)$r['oid'],(string)$r['oname'],$t.'.'.$nameCol);}catch(Throwable $e){$relationInventory[$t]['query_error']='read_failed';}
        }
    }
    foreach(hmop_rows($pdo,"SELECT operator_id,operator_name FROM tour_operator_identity_observations WHERE operator_id IN ($oh) AND operator_name IS NOT NULL AND TRIM(operator_name)<>''",$operatorIds) as $r)hmop_add_name($dict,(int)$r['operator_id'],(string)$r['operator_name'],'tour_operator_identity_observations.operator_name');

    $resolved=[];$ambiguous=[];$unresolved=[];
    foreach($operatorIds as $oid){
        $groups=$dict[$oid]??[];
        if(count($groups)===1){$g=reset($groups);$names=array_keys($g['names']);sort($names,SORT_STRING);$name=$names[0];$resolved[$oid]=['operator_id'=>$oid,'operator_name'=>$name,'operator_family'=>hmop_family($name),'all_names'=>$names,'sources'=>array_values(array_keys($g['sources']))];}
        elseif(count($groups)>1){$vals=[];foreach($groups as $key=>$g)$vals[]=['normalized'=>$key,'names'=>array_values(array_keys($g['names'])),'sources'=>array_values(array_keys($g['sources']))];$ambiguous[$oid]=$vals;}
        else $unresolved[]=$oid;
    }

    $best=[];$tourIds=[];$localAny=[];$familyHotels=[];$seeds=[];
    foreach($priceRows as $r){$hid=(int)$r['hotel_id'];$oid=(int)$r['operator_id'];$tour=hmop_scalar($r['tour_id']??'',220);if(!isset($frontier[$hid])||$oid<1||$tour==='')continue;$key=$hid.'|'.$oid;if(!isset($best[$key]))$best[$key]=$r;$tourIds[$tour]=true;$localAny[$hid]=true;}
    foreach($best as $r){$hid=(int)$r['hotel_id'];$oid=(int)$r['operator_id'];$meta=$resolved[$oid]??null;$fam=$meta['operator_family']??null;if($fam!==null)$familyHotels[$fam][$hid]=true;$seeds[]=$frontier[$hid]+['operator_id'=>$oid,'operator_name'=>$meta['operator_name']??null,'operator_family'=>$fam,'tour_id'=>hmop_scalar($r['tour_id'],220),'search_id'=>$r['search_id']===null?null:hmop_scalar($r['search_id'],80),'tour_observed_at'=>(string)$r['observed_at'],'hotel_operator_observation_rows'=>(int)$r['hotel_operator_rows']];}
    $priority=['anex'=>4,'biblio'=>3,'funsun'=>2,'intourist'=>1];
    usort($seeds,function($a,$b)use($priority){$pa=$priority[$a['operator_family']]??0;$pb=$priority[$b['operator_family']]??0;return $pb<=>$pa ?: $b['user_observation_rows']<=>$a['user_observation_rows'] ?: $b['hotel_operator_observation_rows']<=>$a['hotel_operator_observation_rows'] ?: strcmp($b['tour_observed_at'],$a['tour_observed_at']) ?: $a['hotel_id']<=>$b['hotel_id'];});
    $common=[];$picked=[];foreach($seeds as $s){if($s['operator_family']===null)continue;$hid=(int)$s['hotel_id'];if(isset($picked[$hid]))continue;$picked[$hid]=true;$common[]=$s;if(count($common)>=HMOP_TRANCHE)break;}
    $fallback=[];$fp=[];foreach($seeds as $s){$hid=(int)$s['hotel_id'];if(isset($fp[$hid]))continue;$fp[$hid]=true;$fallback[]=$s;if(count($fallback)>=HMOP_TRANCHE)break;}

    $pdo->rollBack();
    $result=['operation'=>HMOP_OPERATION,'status'=>'read_only_complete','source_sha'=>$sourceSha,'frontier_count'=>count($frontier),'price_rows_with_saved_tour'=>count($priceRows),'distinct_saved_tour_ids'=>count($tourIds),'hotel_operator_seed_count'=>count($best),'eligible_unique_local_hotels'=>count($localAny),'operator_ids_in_frontier'=>$operatorIds,'resolved_operator_dictionary'=>$resolved,'ambiguous_operator_dictionary'=>$ambiguous,'unresolved_operator_ids'=>$unresolved,'common4_family_unique_hotels'=>array_map('count',$familyHotels),'common4_unique_local_hotels'=>count($picked),'common4_execution_tranche_count'=>count($common),'common4_execution_tranche'=>$common,'fallback_execution_tranche_count'=>count($fallback),'fallback_execution_tranche'=>$fallback,'relation_inventory'=>$relationInventory,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0];
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$result=['operation'=>HMOP_OPERATION,'status'=>'failed_read_only','source_sha'=>$sourceSha,'reason'=>preg_replace('/[^a-z0-9_\-]/i','_',mb_substr($e->getMessage(),0,80)),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0];}
$resultSha=hmop_durable($opDir.'/result.json',$result);hmop_durable($opDir.'/receipt.json',['operation'=>HMOP_OPERATION,'state'=>$result['status'],'result_sha256'=>$resultSha,'provider_access'=>false,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit(($result['status']??'')==='read_only_complete'?0:2);
