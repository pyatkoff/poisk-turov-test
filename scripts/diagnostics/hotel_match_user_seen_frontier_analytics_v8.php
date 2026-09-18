<?php
declare(strict_types=1);

const HMOP_OPERATION='hotel-match-user-seen-frontier-analytics-1971-20260918-v8';
const HMOP_TRANCHE=300;
const HMOP_ROW_LIMIT=100000;
const HMOP_V3_DICTIONARY_RESULT_SHA256='79812e2b71e8d0c201a7e5ff3132d1b68f3d6d00961c1dc26d7bca438e8d3d10';
const HMOP_V3_DICTIONARY_ARTIFACT=10525638655;

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
    $n=mb_strtolower(trim($v),'UTF-8');$n=strtr($n,['ё'=>'е','&'=>' ','+'=>' ','_'=>' ','-'=>' ']);
    $n=preg_replace('/[^\p{L}\p{N}]+/u',' ',$n)??$n;return trim(preg_replace('/\s+/u',' ',$n)??$n);
}
function hmop_opaque_family(string $v): ?string {
    $n=hmop_norm($v);
    if(preg_match('/(?:^| )(?:fortuna|фортуна)(?: |$)/u',$n))return 'fortuna';
    if(preg_match('/(?:^| )(?:roulette|рулетка|рулет)(?: |$)/u',$n))return 'roulette';
    return null;
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
    if(hmop_opaque_family('FORTUNA 4* SSH')!=='fortuna'||hmop_opaque_family('ROULETTE BODRUM')!=='roulette'||hmop_opaque_family('HOTEL SU')!==null)throw new RuntimeException('opaque_family');
    echo "MATCH_USER_SEEN_FRONTIER_ANALYTICS_SELFTEST_OK\n";exit(0);
}
if(PHP_SAPI!=='cli')exit(2);

$root=realpath((string)getenv('ANYTOUR_ROOT'));$opDir=(string)getenv('MATCH_OPERATION_DIR');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');$registryPath=realpath((string)getenv('MATCH_MAPPING_REGISTRY_PATH'));
if(!$root||$opDir===''||!preg_match('/^[a-f0-9]{40}$/D',$sourceSha)||!is_string($registryPath)||!is_file($registryPath))throw new RuntimeException('runtime_guard');
$registrySha=hash_file('sha256',$registryPath);if(!is_string($registrySha)||!preg_match('/^[a-f0-9]{64}$/D',$registrySha))throw new RuntimeException('mapping_registry_hash');
if(is_dir($opDir)||!mkdir($opDir,0700,true))throw new RuntimeException('operation_exists');
hmop_durable($opDir.'/reservation.json',['operation'=>HMOP_OPERATION,'source_sha'=>$sourceSha,'state'=>'reserved_before_db_read','provider_access'=>false,'mapping_registry_sha256'=>$registrySha,'no_replay'=>true]);

require_once $registryPath;
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

    $trustedDictionary=[
        13=>['operator_name'=>'Anex','operator_family'=>'anex'],
        18=>['operator_name'=>'Biblioglobus','operator_family'=>'biblio'],
        25=>['operator_name'=>'Fun&Sun (RU)','operator_family'=>'funsun'],
        43=>['operator_name'=>'Интурист','operator_family'=>'intourist'],
    ];
    foreach($trustedDictionary as $oid=>$meta){
        if(!in_array($oid,$operatorIds,true))continue;
        if(isset($ambiguous[$oid]))throw new RuntimeException('trusted_dictionary_ambiguous_'.$oid);
        if(isset($resolved[$oid])){
            $existingFamily=$resolved[$oid]['operator_family']??null;
            if($existingFamily!==null&&$existingFamily!==$meta['operator_family'])throw new RuntimeException('trusted_dictionary_conflict_'.$oid);
        }
        $resolved[$oid]=[
            'operator_id'=>$oid,'operator_name'=>$meta['operator_name'],'operator_family'=>$meta['operator_family'],
            'all_names'=>[$meta['operator_name']],
            'sources'=>['immutable_common4_v3_artifact_'.HMOP_V3_DICTIONARY_ARTIFACT],
        ];
    }
    $unresolved=array_values(array_filter($unresolved,fn($oid)=>!isset($trustedDictionary[(int)$oid])));

    $best=[];$tourIds=[];$localAny=[];$familyHotels=[];$seeds=[];
    foreach($priceRows as $r){$hid=(int)$r['hotel_id'];$oid=(int)$r['operator_id'];$tour=hmop_scalar($r['tour_id']??'',220);if(!isset($frontier[$hid])||$oid<1||$tour==='')continue;$key=$hid.'|'.$oid;if(!isset($best[$key]))$best[$key]=$r;$tourIds[$tour]=true;$localAny[$hid]=true;}
    foreach($best as $r){$hid=(int)$r['hotel_id'];$oid=(int)$r['operator_id'];$meta=$resolved[$oid]??null;$fam=$meta['operator_family']??null;if($fam!==null)$familyHotels[$fam][$hid]=true;$seeds[]=$frontier[$hid]+['operator_id'=>$oid,'operator_name'=>$meta['operator_name']??null,'operator_family'=>$fam,'tour_id'=>hmop_scalar($r['tour_id'],220),'search_id'=>$r['search_id']===null?null:hmop_scalar($r['search_id'],80),'tour_observed_at'=>(string)$r['observed_at'],'hotel_operator_observation_rows'=>(int)$r['hotel_operator_rows']];}
    $seedSort=static function($a,$b){
        return $b['user_observation_rows']<=>$a['user_observation_rows']
            ?: $b['hotel_operator_observation_rows']<=>$a['hotel_operator_observation_rows']
            ?: strcmp($b['tour_observed_at'],$a['tour_observed_at'])
            ?: $a['hotel_id']<=>$b['hotel_id'];
    };
    usort($seeds,$seedSort);

    $frontierResorts=[];$frontierSubresorts=[];
    foreach($frontier as $h){
        $rk=(string)$h['country_id'].'|'.(string)($h['region_id']??0);
        if(!isset($frontierResorts[$rk]))$frontierResorts[$rk]=['country_id'=>$h['country_id'],'country_name'=>$h['country_name'],'region_id'=>$h['region_id'],'region_name'=>$h['region_name'],'hotel_ids'=>[],'user_observation_rows'=>0];
        $frontierResorts[$rk]['hotel_ids'][(int)$h['hotel_id']]=true;$frontierResorts[$rk]['user_observation_rows']+=(int)$h['user_observation_rows'];
        $sk=$rk.'|'.(string)($h['subregion_id']??0);
        if(!isset($frontierSubresorts[$sk]))$frontierSubresorts[$sk]=['country_id'=>$h['country_id'],'country_name'=>$h['country_name'],'region_id'=>$h['region_id'],'region_name'=>$h['region_name'],'subregion_id'=>$h['subregion_id'],'subregion_name'=>$h['subregion_name'],'hotel_ids'=>[],'user_observation_rows'=>0];
        $frontierSubresorts[$sk]['hotel_ids'][(int)$h['hotel_id']]=true;$frontierSubresorts[$sk]['user_observation_rows']+=(int)$h['user_observation_rows'];
    }
    $finishAgg=static function(array $rows): array {
        foreach($rows as &$x){$x['hotel_count']=count($x['hotel_ids']);unset($x['hotel_ids']);}unset($x);
        $rows=array_values($rows);
        usort($rows,fn($a,$b)=>$b['hotel_count']<=>$a['hotel_count'] ?: $b['user_observation_rows']<=>$a['user_observation_rows'] ?: strcmp((string)$a['region_name'],(string)$b['region_name']));
        return $rows;
    };
    $frontierResorts=$finishAgg($frontierResorts);$frontierSubresorts=$finishAgg($frontierSubresorts);

    $common4All=[];$common4Resorts=[];$hotelFamilies=[];$familyQueues=['biblio'=>[],'anex'=>[],'funsun'=>[],'intourist'=>[]];
    foreach($seeds as $s){
        $fam=$s['operator_family']??null;if(!isset($familyQueues[$fam]))continue;
        $hid=(int)$s['hotel_id'];$common4All[$hid]=true;$hotelFamilies[$hid][$fam]=true;$familyQueues[$fam][]=$s;
        $rk=(string)$s['country_id'].'|'.(string)($s['region_id']??0);
        if(!isset($common4Resorts[$rk]))$common4Resorts[$rk]=['country_id'=>$s['country_id'],'country_name'=>$s['country_name'],'region_id'=>$s['region_id'],'region_name'=>$s['region_name'],'hotel_ids'=>[],'family_hotel_ids'=>['anex'=>[],'biblio'=>[],'funsun'=>[],'intourist'=>[]]];
        $common4Resorts[$rk]['hotel_ids'][$hid]=true;$common4Resorts[$rk]['family_hotel_ids'][$fam][$hid]=true;
    }
    foreach($familyQueues as &$q)usort($q,$seedSort);unset($q);
    foreach($common4Resorts as &$x){
        $x['hotel_count']=count($x['hotel_ids']);unset($x['hotel_ids']);
        $fc=[];foreach($x['family_hotel_ids'] as $fam=>$ids)$fc[$fam]=count($ids);
        $x['family_unique_hotels']=$fc;unset($x['family_hotel_ids']);
    }unset($x);
    $common4Resorts=array_values($common4Resorts);
    usort($common4Resorts,fn($a,$b)=>$b['hotel_count']<=>$a['hotel_count'] ?: strcmp((string)$a['region_name'],(string)$b['region_name']));

    $balanced=[];$balancedPicked=[];$balancedFamilyCounts=['biblio'=>0,'anex'=>0,'funsun'=>0,'intourist'=>0];$idx=['biblio'=>0,'anex'=>0,'funsun'=>0,'intourist'=>0];$order=['biblio','anex','funsun','intourist'];
    while(count($balanced)<HMOP_TRANCHE){
        $added=false;
        foreach($order as $fam){
            while(isset($familyQueues[$fam][$idx[$fam]])&&isset($balancedPicked[(int)$familyQueues[$fam][$idx[$fam]]['hotel_id']]))$idx[$fam]++;
            if(!isset($familyQueues[$fam][$idx[$fam]]))continue;
            $s=$familyQueues[$fam][$idx[$fam]++];$hid=(int)$s['hotel_id'];
            if(isset($balancedPicked[$hid]))continue;
            $balancedPicked[$hid]=true;$balanced[]=$s;$balancedFamilyCounts[$fam]++;$added=true;
            if(count($balanced)>=HMOP_TRANCHE)break;
        }
        if(!$added)break;
    }

    $opaque=[];
    foreach($frontier as $h){
        $of=hmop_opaque_family((string)$h['hotel_name']);if($of===null)continue;
        $nameKey=hmop_norm((string)$h['hotel_name']);
        $key=$of.'|'.$h['country_id'].'|'.(string)($h['region_id']??0).'|'.$nameKey;
        if(!isset($opaque[$key]))$opaque[$key]=['product_family'=>$of,'normalized_name'=>$nameKey,'display_names'=>[],'country_id'=>$h['country_id'],'country_name'=>$h['country_name'],'region_id'=>$h['region_id'],'region_name'=>$h['region_name'],'local_hotel_ids'=>[],'operator_families'=>[],'user_observation_rows'=>0];
        $opaque[$key]['display_names'][(string)$h['hotel_name']]=true;$opaque[$key]['local_hotel_ids'][(int)$h['hotel_id']]=true;$opaque[$key]['user_observation_rows']+=(int)$h['user_observation_rows'];
        foreach(array_keys($hotelFamilies[(int)$h['hotel_id']]??[]) as $fam)$opaque[$key]['operator_families'][$fam]=true;
    }
    $opaqueDuplicateReduction=0;
    foreach($opaque as &$x){
        $x['display_names']=array_values(array_keys($x['display_names']));sort($x['display_names'],SORT_STRING);
        $x['local_hotel_ids']=array_values(array_keys($x['local_hotel_ids']));sort($x['local_hotel_ids'],SORT_NUMERIC);
        $x['operator_families']=array_values(array_keys($x['operator_families']));sort($x['operator_families'],SORT_STRING);
        $x['local_card_count']=count($x['local_hotel_ids']);$opaqueDuplicateReduction+=max(0,$x['local_card_count']-1);
    }unset($x);
    $opaque=array_values($opaque);
    usort($opaque,fn($a,$b)=>$b['local_card_count']<=>$a['local_card_count'] ?: $b['user_observation_rows']<=>$a['user_observation_rows'] ?: strcmp($a['normalized_name'],$b['normalized_name']));

    $pdo->rollBack();
    $result=['operation'=>HMOP_OPERATION,'status'=>'read_only_complete','source_sha'=>$sourceSha,'frontier_count'=>count($frontier),'price_rows_with_saved_tour'=>count($priceRows),'distinct_saved_tour_ids'=>count($tourIds),'hotel_operator_seed_count'=>count($best),'eligible_unique_local_hotels'=>count($localAny),'operator_ids_in_frontier'=>$operatorIds,'resolved_operator_dictionary'=>$resolved,'ambiguous_operator_dictionary'=>$ambiguous,'unresolved_operator_ids'=>$unresolved,'operator_dictionary_provenance'=>['artifact_id'=>HMOP_V3_DICTIONARY_ARTIFACT,'result_sha256'=>HMOP_V3_DICTIONARY_RESULT_SHA256],'frontier_resorts'=>$frontierResorts,'frontier_subresorts'=>$frontierSubresorts,'common4_family_unique_hotels'=>array_map('count',$familyHotels),'common4_total_unique_local_hotels'=>count($common4All),'common4_resorts'=>$common4Resorts,'balanced_execution_tranche_count'=>count($balanced),'balanced_execution_tranche_family_counts'=>$balancedFamilyCounts,'balanced_execution_tranche'=>$balanced,'opaque_product_cluster_count'=>count($opaque),'opaque_product_duplicate_reduction'=>$opaqueDuplicateReduction,'opaque_product_clusters'=>$opaque,'relation_inventory'=>$relationInventory,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0];
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$result=['operation'=>HMOP_OPERATION,'status'=>'failed_read_only','source_sha'=>$sourceSha,'reason'=>preg_replace('/[^a-z0-9_\-]/i','_',mb_substr($e->getMessage(),0,80)),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0];}
$resultSha=hmop_durable($opDir.'/result.json',$result);hmop_durable($opDir.'/receipt.json',['operation'=>HMOP_OPERATION,'state'=>$result['status'],'result_sha256'=>$resultSha,'provider_access'=>false,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit(($result['status']??'')==='read_only_complete'?0:2);
