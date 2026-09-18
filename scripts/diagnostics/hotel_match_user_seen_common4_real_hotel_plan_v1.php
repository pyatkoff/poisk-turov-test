<?php
declare(strict_types=1);

const HMCR_OPERATION='hotel-match-user-seen-common4-real-hotel-plan-1971-20260918-v1';
const HMCR_TRANCHE=300;
const HMCR_ROW_LIMIT=100000;
const HMCR_V3_DICTIONARY_RESULT_SHA256='79812e2b71e8d0c201a7e5ff3132d1b68f3d6d00961c1dc26d7bca438e8d3d10';
const HMCR_V3_DICTIONARY_ARTIFACT=10525638655;

function hmcr_rows(PDO $pdo,string $sql,array $params=[]): array {
    $s=$pdo->prepare($sql);$s->execute(array_values($params));$r=$s->fetchAll(PDO::FETCH_ASSOC)?:[];
    if(count($r)>HMCR_ROW_LIMIT)throw new RuntimeException('row_budget');
    return $r;
}
function hmcr_table(PDO $pdo,string $t): bool {
    $s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $s->execute([$t]);return $s->fetchColumn()!==false;
}
function hmcr_scalar(mixed $v,int $max=255): string {return is_scalar($v)?mb_substr(trim((string)$v),0,$max,'UTF-8'):'';}
function hmcr_norm(string $v): string {
    $n=mb_strtolower(trim($v),'UTF-8');$n=strtr($n,['ё'=>'е','&'=>' ','+'=>' ','_'=>' ','-'=>' ']);
    $n=preg_replace('/[^\p{L}\p{N}]+/u',' ',$n)??$n;return trim(preg_replace('/\s+/u',' ',$n)??$n);
}
function hmcr_product_hold(string $name): ?string {
    $raw=mb_strtolower(trim($name),'UTF-8');$n=hmcr_norm($name);
    if(preg_match('/^(roulette|рулетка)(\s|$)/u',$n))return 'roulette_product_marker';
    if(!preg_match('/^(fortuna|фортуна)(\s|$)/u',$n))return null;
    if(preg_match('/^(fortuna|фортуна)\s+[1-5]\s*[*★]/u',$raw))return 'fortuna_star_product_marker';
    $tokens=preg_split('/\s+/u',$n,-1,PREG_SPLIT_NO_EMPTY)?:[];
    $hasPhysicalWord=(bool)preg_match('/\b(hotel|hotels|otel|отель|resort)\b/u',$n);
    if($hasPhysicalWord&&count($tokens)>=3)return null;
    return 'fortuna_product_marker';
}
function hmcr_durable(string $path,array $v): string {
    $raw=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    $f=@fopen($path,'x+b');if(!$f)throw new RuntimeException('durable_exists');
    try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('durable_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('durable_sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('durable_readback');}finally{fclose($f);}return hash('sha256',$raw);
}

if(in_array('--self-test',$argv??[],true)){
    $cases=[
        'FORTUNA 4* SSH'=>'fortuna_star_product_marker',
        'FORTUNA MARMARIS'=>'fortuna_product_marker',
        'ROULETTE 5*'=>'roulette_product_marker',
        'РУЛЕТКА 4*'=>'roulette_product_marker',
        'FORTUNA HOTEL PHU QUOC'=>null,
        'FORTUNA BEACH HOTEL PHU QUOC'=>null,
        'SUNRISE DIAMOND BEACH RESORT'=>null,
    ];
    foreach($cases as $name=>$want)if(hmcr_product_hold($name)!==$want)throw new RuntimeException('product_filter_'.preg_replace('/\W+/u','_',hmcr_norm($name)));
    echo "MATCH_USER_SEEN_COMMON4_REAL_HOTEL_SELFTEST_OK\n";exit(0);
}
if(PHP_SAPI!=='cli')exit(2);

$root=realpath((string)getenv('ANYTOUR_ROOT'));$opDir=(string)getenv('MATCH_OPERATION_DIR');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');$registryPath=realpath((string)getenv('MATCH_MAPPING_REGISTRY_PATH'));
if(!$root||$opDir===''||!preg_match('/^[a-f0-9]{40}$/D',$sourceSha)||!is_string($registryPath)||!is_file($registryPath))throw new RuntimeException('runtime_guard');
$registrySha=hash_file('sha256',$registryPath);if(!is_string($registrySha)||!preg_match('/^[a-f0-9]{64}$/D',$registrySha))throw new RuntimeException('mapping_registry_hash');
if(is_dir($opDir)||!mkdir($opDir,0700,true))throw new RuntimeException('operation_exists');
hmcr_durable($opDir.'/reservation.json',['operation'=>HMCR_OPERATION,'source_sha'=>$sourceSha,'state'=>'reserved_before_db_read','provider_access'=>false,'mapping_registry_sha256'=>$registrySha,'no_replay'=>true]);

require_once $registryPath;
$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbf;
$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach(['tour_price_observations','catalog_hotels','anex_hotel_search_mappings','anex_hotel_decisions','andromeda_hotel_identities'] as $t)if(!hmcr_table($pdo,$t))throw new RuntimeException('missing_'.$t);

$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION READ ONLY');
try{
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);$anexTargets=[];
    foreach(hmcr_rows($pdo,'SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions') as $r){$x=$registry->resolve('anex_online',(string)$r['anex_hotel_id'],'preview');if(is_int($x)&&$x>0)$anexTargets[$x]=true;}
    $andTargets=[];foreach(hmcr_rows($pdo,"SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL") as $r){$x=(int)$r['local_hotel_id'];if($x>0)$andTargets[$x]=true;}
    $seen=[];foreach(hmcr_rows($pdo,"SELECT hotel_id,MAX(observed_at) last_observed_at,COUNT(*) observation_rows FROM tour_price_observations WHERE source='user_search' GROUP BY hotel_id") as $r){$id=(int)$r['hotel_id'];if($id>0)$seen[$id]=['last_observed_at'=>(string)$r['last_observed_at'],'observation_rows'=>(int)$r['observation_rows']];}
    $ids=array_keys($seen);if(!$ids)throw new RuntimeException('no_seen');$ph=implode(',',array_fill(0,count($ids),'?'));
    $frontier=[];foreach(hmcr_rows($pdo,"SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,is_active FROM catalog_hotels WHERE id IN ($ph)",$ids) as $r){$id=(int)$r['id'];if((int)$r['is_active']!==1||isset($anexTargets[$id])||isset($andTargets[$id]))continue;$frontier[$id]=['hotel_id'=>$id,'hotel_name'=>(string)$r['name'],'country_id'=>(int)$r['country_id'],'country_name'=>(string)$r['country_name'],'region_id'=>$r['region_id']===null?null:(int)$r['region_id'],'region_name'=>(string)($r['region_name']??''),'subregion_id'=>$r['subregion_id']===null?null:(int)$r['subregion_id'],'subregion_name'=>(string)($r['subregion_name']??''),'user_observation_rows'=>$seen[$id]['observation_rows'],'last_user_observed_at'=>$seen[$id]['last_observed_at']];}
    $fids=array_keys($frontier);if(!$fids)throw new RuntimeException('frontier_empty');$fh=implode(',',array_fill(0,count($fids),'?'));
    $priceRows=hmcr_rows($pdo,"SELECT hotel_id,operator_id,tour_id,search_id,observed_at,COUNT(*) OVER (PARTITION BY hotel_id,operator_id) AS hotel_operator_rows FROM tour_price_observations WHERE source='user_search' AND hotel_id IN ($fh) AND tour_id IS NOT NULL AND tour_id<>'' AND operator_id IS NOT NULL ORDER BY observed_at DESC",$fids);

    $trusted=[13=>['operator_name'=>'Anex','operator_family'=>'anex'],18=>['operator_name'=>'Biblioglobus','operator_family'=>'biblio'],25=>['operator_name'=>'Fun&Sun (RU)','operator_family'=>'funsun'],43=>['operator_name'=>'Интурист','operator_family'=>'intourist']];
    $best=[];$tourIds=[];$localAny=[];
    foreach($priceRows as $r){$hid=(int)$r['hotel_id'];$oid=(int)$r['operator_id'];$tour=hmcr_scalar($r['tour_id']??'',220);if(!isset($frontier[$hid])||$oid<1||$tour==='')continue;$key=$hid.'|'.$oid;if(!isset($best[$key]))$best[$key]=$r;$tourIds[$tour]=true;$localAny[$hid]=true;}

    $familyRaw=[];$familyReal=[];$rawLocals=[];$realLocals=[];$holdLocals=[];$holdFamilies=[];$seeds=[];
    foreach($best as $r){
        $hid=(int)$r['hotel_id'];$oid=(int)$r['operator_id'];$meta=$trusted[$oid]??null;if($meta===null)continue;$fam=$meta['operator_family'];$reason=hmcr_product_hold($frontier[$hid]['hotel_name']);
        $familyRaw[$fam][$hid]=true;$rawLocals[$hid]=true;
        if($reason!==null){$holdLocals[$hid]=$reason;$holdFamilies[$hid][$fam]=true;continue;}
        $familyReal[$fam][$hid]=true;$realLocals[$hid]=true;
        $seeds[]=$frontier[$hid]+['operator_id'=>$oid,'operator_name'=>$meta['operator_name'],'operator_family'=>$fam,'tour_id'=>hmcr_scalar($r['tour_id'],220),'search_id'=>$r['search_id']===null?null:hmcr_scalar($r['search_id'],80),'tour_observed_at'=>(string)$r['observed_at'],'hotel_operator_observation_rows'=>(int)$r['hotel_operator_rows']];
    }
    $priority=['anex'=>4,'biblio'=>3,'funsun'=>2,'intourist'=>1];
    usort($seeds,function($a,$b)use($priority){$pa=$priority[$a['operator_family']]??0;$pb=$priority[$b['operator_family']]??0;return $pb<=>$pa ?: $b['user_observation_rows']<=>$a['user_observation_rows'] ?: $b['hotel_operator_observation_rows']<=>$a['hotel_operator_observation_rows'] ?: strcmp($b['tour_observed_at'],$a['tour_observed_at']) ?: $a['hotel_id']<=>$b['hotel_id'];});
    $tranche=[];$picked=[];foreach($seeds as $s){$hid=(int)$s['hotel_id'];if(isset($picked[$hid]))continue;$picked[$hid]=true;$tranche[]=$s;if(count($tranche)>=HMCR_TRANCHE)break;}
    $holds=[];$reasonCounts=[];foreach($holdLocals as $hid=>$reason){$families=array_keys($holdFamilies[$hid]??[]);sort($families,SORT_STRING);$holds[]=$frontier[$hid]+['reason'=>$reason,'common4_families'=>$families];$reasonCounts[$reason]=($reasonCounts[$reason]??0)+1;}
    usort($holds,fn($a,$b)=>$b['user_observation_rows']<=>$a['user_observation_rows'] ?: $a['hotel_id']<=>$b['hotel_id']);ksort($reasonCounts,SORT_STRING);

    $pdo->rollBack();
    $result=[
        'operation'=>HMCR_OPERATION,'status'=>'read_only_complete','source_sha'=>$sourceSha,
        'frontier_count'=>count($frontier),'price_rows_with_saved_tour'=>count($priceRows),'distinct_saved_tour_ids'=>count($tourIds),'hotel_operator_seed_count'=>count($best),'eligible_unique_local_hotels'=>count($localAny),
        'operator_dictionary_provenance'=>['artifact_id'=>HMCR_V3_DICTIONARY_ARTIFACT,'result_sha256'=>HMCR_V3_DICTIONARY_RESULT_SHA256,'tourvisor_to_common4'=>['13'=>'anex','18'=>'biblio','25'=>'funsun','43'=>'intourist']],
        'raw_common4_family_unique_hotels'=>array_map('count',$familyRaw),'raw_common4_unique_local_hotels'=>count($rawLocals),
        'product_hold_unique_local_hotels'=>count($holdLocals),'product_hold_reason_counts'=>$reasonCounts,'product_holds'=>$holds,
        'real_hotel_common4_family_unique_hotels'=>array_map('count',$familyReal),'real_hotel_common4_unique_local_hotels'=>count($realLocals),
        'real_hotel_execution_tranche_count'=>count($tranche),'real_hotel_execution_tranche'=>$tranche,
        'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'provider_access'=>false
    ];
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$result=['operation'=>HMCR_OPERATION,'status'=>'failed_read_only','source_sha'=>$sourceSha,'reason'=>preg_replace('/[^a-z0-9_\-]/i','_',mb_substr($e->getMessage(),0,80)),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'provider_access'=>false];}
$resultSha=hmcr_durable($opDir.'/result.json',$result);hmcr_durable($opDir.'/receipt.json',['operation'=>HMCR_OPERATION,'state'=>$result['status'],'result_sha256'=>$resultSha,'provider_access'=>false,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit(($result['status']??'')==='read_only_complete'?0:2);
