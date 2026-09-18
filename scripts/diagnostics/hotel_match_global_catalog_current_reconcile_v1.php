<?php
declare(strict_types=1);

const HMGC_OPERATION='hotel-match-global-catalog-current-reconcile-1971-20260918-v1';
const HMGC_SOURCE_RESULT_SHA='7516046dd2a0d052ea806ca07b337400d7d3769e2ff3fd7832e3479eecde1324';
const HMGC_SOURCE_OPERATION='hotel-match-user-seen-common4-samo-catalog-acquire-1971-20260918-v2';
const HMGC_SOURCE_CANDIDATES=102;
const HMGC_ROW_LIMIT=100000;

function hmgc_scalar(mixed $v,int $max=255): string {
    return is_scalar($v)?mb_substr(trim((string)$v),0,$max,'UTF-8'):'';
}
function hmgc_norm(string $v): string {
    $n=mb_strtolower(trim($v),'UTF-8');
    $n=strtr($n,['ё'=>'е','&'=>' ','+'=>' ','_'=>' ','-'=>' ','’'=>"'"]);
    $n=preg_replace('/[^\p{L}\p{N}]+/u',' ',$n)??$n;
    return trim(preg_replace('/\s+/u',' ',$n)??$n);
}
function hmgc_variants(string $name): array {
    $out=[];
    $add=static function(string $v,string $why)use(&$out): void {
        $n=hmgc_norm($v);if($n!=='')$out[$n][$why]=true;
    };
    $add($name,'canonical_full');
    if(preg_match('/^(.+?)\s*\((?:EX\.?|FORMERLY|БЫВШ\.?)\s*[^)]*\)/iu',$name,$m))$add($m[1],'canonical_current');
    if(preg_match_all('/\((?:EX\.?|FORMERLY|БЫВШ\.?)\s*([^)]*)\)/iu',$name,$ms))
        foreach($ms[1] as $inside)foreach(preg_split('/[;|\/]+/u',$inside)?:[] as $part)$add($part,'explicit_former');
    $flat=[];foreach($out as $k=>$why){$v=array_keys($why);sort($v,SORT_STRING);$flat[$k]=$v;}return $flat;
}
function hmgc_geo_compatible(string $supplier,string $region,string $subregion): bool {
    $s=hmgc_norm($supplier);if($s==='')return false;
    foreach([$region,$subregion] as $local){
        $l=hmgc_norm($local);if($l==='')continue;
        if($s===$l)return true;
        if(mb_strlen($s,'UTF-8')>=4&&mb_strlen($l,'UTF-8')>=4&&(str_contains($s,$l)||str_contains($l,$s)))return true;
    }
    return false;
}
function hmgc_rows(PDO $pdo,string $sql,array $params=[]): array {
    $s=$pdo->prepare($sql);$s->execute(array_values($params));$r=$s->fetchAll(PDO::FETCH_ASSOC)?:[];
    if(count($r)>HMGC_ROW_LIMIT)throw new RuntimeException('row_budget');return $r;
}
function hmgc_table(PDO $pdo,string $t): bool {
    $s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $s->execute([$t]);return $s->fetchColumn()!==false;
}
function hmgc_cols(PDO $pdo,string $t): array {
    $s=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
    $s->execute([$t]);return array_values(array_map('strval',$s->fetchAll(PDO::FETCH_COLUMN)?:[]));
}
function hmgc_durable(string $path,array $v): string {
    $raw=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    $f=@fopen($path,'x+b');if(!$f)throw new RuntimeException('durable_exists');
    try{
        if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('durable_write');
        if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('durable_sync');
        rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('durable_readback');
    }finally{fclose($f);}
    return hash('sha256',$raw);
}

if(in_array('--self-test',$argv??[],true)){
    $v=hmgc_variants('SANYA SEACUBE RESORT (EX. SEACUBE RESORT)');
    if(!isset($v['sanya seacube resort'],$v['seacube resort']))throw new RuntimeException('variants');
    if(!hmgc_geo_compatible('Sanya','Hainan','Sanya')||hmgc_geo_compatible('Dubai','Hainan','Sanya'))throw new RuntimeException('geo');
    echo "MATCH_GLOBAL_CATALOG_CURRENT_RECONCILE_SELFTEST_OK\n";exit(0);
}
if(PHP_SAPI!=='cli')exit(2);

$input=(string)($argv[1]??'');
$root=realpath((string)getenv('ANYTOUR_ROOT'));
$opDir=(string)getenv('MATCH_OPERATION_DIR');
$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
if(!$root||$opDir===''||!preg_match('/^[a-f0-9]{40}$/D',$sourceSha)||!is_file($input))throw new RuntimeException('runtime_guard');
$raw=file_get_contents($input);
if(!is_string($raw)||!hash_equals(HMGC_SOURCE_RESULT_SHA,hash('sha256',$raw)))throw new RuntimeException('source_hash');
$source=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
if(($source['operation']??null)!==HMGC_SOURCE_OPERATION||($source['state']??null)!=='completed_read_only'||($source['no_replay']??null)!==true
    ||(int)($source['strict_catalog_candidate_count']??-1)!==HMGC_SOURCE_CANDIDATES
    ||!is_array($source['strict_catalog_candidates']??null)||count($source['strict_catalog_candidates'])!==HMGC_SOURCE_CANDIDATES)
    throw new RuntimeException('source_contract');
if(is_dir($opDir)||!mkdir($opDir,0700,true))throw new RuntimeException('operation_exists');
hmgc_durable($opDir.'/reservation.json',[
    'operation'=>HMGC_OPERATION,'state'=>'reserved_before_db_read','source_sha'=>$sourceSha,
    'source_operation'=>HMGC_SOURCE_OPERATION,'source_result_sha256'=>HMGC_SOURCE_RESULT_SHA,
    'candidate_count'=>HMGC_SOURCE_CANDIDATES,'provider_access'=>false,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true,
]);

$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
require_once $dbf;
$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach(['catalog_hotels','andromeda_hotel_identities'] as $t)if(!hmgc_table($pdo,$t))throw new RuntimeException('missing_'.$t);

$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION READ ONLY');
try{
    $candidates=[];$localIds=[];$externalIds=[];
    foreach($source['strict_catalog_candidates'] as $row){
        if(!is_array($row))throw new RuntimeException('candidate_shape');
        $hid=(int)($row['hotel_id']??0);$ext=hmgc_scalar($row['external_hotel_id']??'',64);
        if($hid<1||!preg_match('/^[1-9][0-9]{0,31}$/D',$ext)||isset($candidates[$hid])||isset($externalIds[$ext]))throw new RuntimeException('candidate_identity');
        $candidates[$hid]=$row;$localIds[$hid]=true;$externalIds[$ext]=true;
    }
    $lids=array_keys($localIds);$lph=implode(',',array_fill(0,count($lids),'?'));
    $locals=[];foreach(hmgc_rows($pdo,"SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,is_active FROM catalog_hotels WHERE id IN ($lph)",$lids) as $r)$locals[(int)$r['id']]=$r;

    $exts=array_keys($externalIds);$eph=implode(',',array_fill(0,count($exts),'?'));
    $identities=hmgc_rows($pdo,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND (external_hotel_id IN ($eph) OR local_hotel_id IN ($lph))",array_merge($exts,$lids));
    $byExt=[];$byLocal=[];
    foreach($identities as $r){
        $ext=hmgc_scalar($r['external_hotel_id']??'',64);$local=$r['local_hotel_id']===null?null:(int)$r['local_hotel_id'];
        if($ext!=='')$byExt[$ext][]=$r;if($local!==null&&$local>0)$byLocal[$local][]=$r;
    }

    $obsTable=hmgc_table($pdo,'andromeda_search_hotel_observations');
    $obsCols=$obsTable?hmgc_cols($pdo,'andromeda_search_hotel_observations'):[];
    $needed=['supplier_namespace','external_hotel_id','hotel_name','country_id','country_name','region_name','observed_at_utc'];
    $obsUsable=$obsTable&&count(array_diff($needed,$obsCols))===0;
    $obsByExt=[];
    if($obsUsable){
        foreach(hmgc_rows($pdo,"SELECT external_hotel_id,hotel_name,country_id,country_name,region_name,observed_at_utc FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN ($eph) ORDER BY observed_at_utc DESC",$exts) as $r){
            $ext=hmgc_scalar($r['external_hotel_id']??'',64);if($ext!=='')$obsByExt[$ext][]=$r;
        }
    }

    $rows=[];$counts=[];$cleanGeo=[];$needsGeo=[];$held=[];$alreadySame=[];
    foreach($candidates as $hid=>$src){
        $ext=hmgc_scalar($src['external_hotel_id'],64);$route='current_clean_needs_geo';$reasons=[];$local=$locals[$hid]??null;
        if(!$local||(int)($local['is_active']??0)!==1){$route='held';$reasons[]='local_missing_or_inactive';}
        else{
            if((int)$local['country_id']!==(int)$src['country_id']){$route='held';$reasons[]='country_drift';}
            $variants=hmgc_variants((string)$local['name']);$match=hmgc_norm((string)($src['match_key']??''));
            if($match===''||!isset($variants[$match])){$route='held';$reasons[]='name_evidence_drift';}
            if(hmgc_norm((string)$src['supplier_name'])!==$match){$route='held';$reasons[]='supplier_name_drift';}
        }

        foreach($byExt[$ext]??[] as $idrow){
            $status=hmgc_scalar($idrow['decision_status']??'',60);$target=$idrow['local_hotel_id']===null?null:(int)$idrow['local_hotel_id'];
            if($status==='accepted'&&$target===$hid){$route='already_same';$reasons[]='already_accepted_same';}
            elseif($status==='accepted'&&$target!==$hid){$route='held';$reasons[]='external_accepted_elsewhere';}
            elseif(in_array($status,['conflict','rejected','excluded'],true)){$route='held';$reasons[]='external_current_'.$status;}
        }
        foreach($byLocal[$hid]??[] as $idrow){
            $status=hmgc_scalar($idrow['decision_status']??'',60);$other=hmgc_scalar($idrow['external_hotel_id']??'',64);
            if($status==='accepted'&&$other!==$ext){$route='held';$reasons[]='same_provider_local_occupied';}
        }

        $geoRows=$obsByExt[$ext]??[];$geoRegions=[];$geoCountries=[];$geoNames=[];
        foreach($geoRows as $o){
            $cid=(int)($o['country_id']??0);if($cid>0)$geoCountries[$cid]=true;
            $reg=hmgc_scalar($o['region_name']??'',180);if($reg!=='')$geoRegions[hmgc_norm($reg)]=$reg;
            $nm=hmgc_scalar($o['hotel_name']??'',220);if($nm!=='')$geoNames[hmgc_norm($nm)]=$nm;
        }
        if($route!=='held'&&$route!=='already_same'){
            if($geoCountries&&(!isset($geoCountries[(int)$src['country_id']])||count($geoCountries)>1)){
                $route='held';$reasons[]='observation_country_conflict';
            }elseif($geoRegions){
                $allCompatible=true;
                foreach($geoRegions as $reg)if(!hmgc_geo_compatible($reg,(string)($local['region_name']??''),(string)($local['subregion_name']??''))){$allCompatible=false;break;}
                if($allCompatible){$route='current_clean_geo';$reasons[]='observation_geo_match';}
                else{$route='held';$reasons[]='observation_geo_conflict';}
            }else{$reasons[]='observation_geo_missing';}
        }

        $row=[
            'hotel_id'=>$hid,'external_hotel_id'=>$ext,'hotel_name'=>$local['name']??$src['hotel_name'],
            'supplier_name'=>$src['supplier_name'],'country_id'=>(int)$src['country_id'],'country_name'=>$src['country_name'],
            'region_name'=>$local['region_name']??$src['region_name'],'subregion_name'=>$local['subregion_name']??$src['subregion_name'],
            'supplier_town_key'=>$src['town_key']??null,'supplier_star_key'=>$src['star_key']??null,
            'match_key'=>$src['match_key'],'match_reasons'=>$src['match_reasons'],'user_observation_rows'=>$src['user_observation_rows'],
            'route'=>$route,'reasons'=>array_values(array_unique($reasons)),
            'observation_count'=>count($geoRows),'observed_region_names'=>array_values($geoRegions),'observed_country_ids'=>array_map('intval',array_keys($geoCountries)),
            'source_independent_context_count'=>(int)($src['independent_context_count']??0),
        ];
        $rows[]=$row;$counts[$route]=($counts[$route]??0)+1;
        if($route==='current_clean_geo')$cleanGeo[]=$row;elseif($route==='current_clean_needs_geo')$needsGeo[]=$row;elseif($route==='held')$held[]=$row;else$alreadySame[]=$row;
    }
    usort($rows,static fn($a,$b)=>strcmp($a['route'],$b['route'])?:($b['user_observation_rows']<=>$a['user_observation_rows'])?:($a['hotel_id']<=>$b['hotel_id']));
    ksort($counts,SORT_STRING);
    $pdo->rollBack();
    $result=[
        'operation'=>HMGC_OPERATION,'status'=>'read_only_complete','source_sha'=>$sourceSha,'source_result_sha256'=>HMGC_SOURCE_RESULT_SHA,
        'candidate_count'=>count($rows),'route_counts'=>$counts,
        'current_clean_geo_count'=>count($cleanGeo),'current_clean_needs_geo_count'=>count($needsGeo),'held_count'=>count($held),'already_same_count'=>count($alreadySame),
        'observation_table_present'=>$obsTable,'observation_columns'=>$obsCols,'observation_geo_usable'=>$obsUsable,
        'rows'=>$rows,'provider_access'=>false,'supplier_calls'=>0,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,
    ];
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    $result=['operation'=>HMGC_OPERATION,'status'=>'failed_read_only','source_sha'=>$sourceSha,'source_result_sha256'=>HMGC_SOURCE_RESULT_SHA,
        'reason'=>preg_replace('/[^a-z0-9_\-]/i','_',mb_substr($e->getMessage(),0,100)),
        'provider_access'=>false,'supplier_calls'=>0,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
}
$sha=hmgc_durable($opDir.'/result.json',$result);
hmgc_durable($opDir.'/receipt.json',['operation'=>HMGC_OPERATION,'state'=>$result['status'],'result_sha256'=>$sha,'provider_access'=>false,'supplier_calls'=>0,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit(($result['status']??'')==='read_only_complete'?0:2);
