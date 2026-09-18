<?php
declare(strict_types=1);

const HMTC_OPERATION='hotel-match-global-accepted-town-crosswalk-1971-20260918-v1';
const HMTC_GEO_RESULT_SHA='de6aa8b07f71bcb18f1dee7bff9fa117bca6df27fd062437b160122300c4c26c';
const HMTC_SOURCE_CANDIDATES=102;
const HMTC_ROW_LIMIT=100000;

function hmtc_scalar(mixed $v,int $max=255): string {
    return is_scalar($v)?mb_substr(trim((string)$v),0,$max,'UTF-8'):'';
}
function hmtc_norm(string $v): string {
    $n=mb_strtolower(trim($v),'UTF-8');$n=strtr($n,['ё'=>'е','&'=>' ','+'=>' ','_'=>' ','-'=>' ','’'=>"'"]);
    $n=preg_replace('/[^\p{L}\p{N}]+/u',' ',$n)??$n;return trim(preg_replace('/\s+/u',' ',$n)??$n);
}
function hmtc_variants(string $name): array {
    $out=[];$add=static function(string $v)use(&$out){$n=hmtc_norm($v);if($n!=='')$out[$n]=true;};
    $add($name);
    if(preg_match('/^(.+?)\s*\((?:EX\.?|FORMERLY|БЫВШ\.?)\s*[^)]*\)/iu',$name,$m))$add($m[1]);
    if(preg_match_all('/\((?:EX\.?|FORMERLY|БЫВШ\.?)\s*([^)]*)\)/iu',$name,$ms))
        foreach($ms[1] as $inside)foreach(preg_split('/[;|\/]+/u',$inside)?:[] as $part)$add($part);
    return $out;
}
function hmtc_rows(PDO $pdo,string $sql,array $params=[]): array {
    $s=$pdo->prepare($sql);$s->execute(array_values($params));$r=$s->fetchAll(PDO::FETCH_ASSOC)?:[];
    if(count($r)>HMTC_ROW_LIMIT)throw new RuntimeException('row_budget');return $r;
}
function hmtc_table(PDO $pdo,string $t): bool {
    $s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');$s->execute([$t]);return $s->fetchColumn()!==false;
}
function hmtc_durable(string $path,array $v): string {
    $raw=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    $f=@fopen($path,'x+b');if(!$f)throw new RuntimeException('durable_exists');
    try{
        if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('durable_write');
        if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('durable_sync');
        rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('durable_readback');
    }finally{fclose($f);}
    return hash('sha256',$raw);
}
function hmtc_town_keys(mixed $value): array {
    $out=[];
    $walk=function(mixed $v)use(&$walk,&$out): void {
        if(!is_array($v))return;
        foreach($v as $k=>$item){
            $key=is_string($k)?preg_replace('/[^a-z0-9]/','',strtolower($k)):'';
            if($key==='townkey'&&(is_string($item)||is_int($item))){
                $id=trim((string)$item);if(preg_match('/^[1-9][0-9]{0,31}$/D',$id))$out[$id]=true;
            }
            if(is_array($item))$walk($item);
        }
    };
    $walk($value);return array_keys($out);
}

if(in_array('--self-test',$argv??[],true)){
    $x=hmtc_town_keys(['a'=>['townKey'=>'123'],'b'=>['town_key'=>123]]);
    if($x!==['123'])throw new RuntimeException('town_extract');
    $v=hmtc_variants('SUN BAY (EX. SUN MARIS PARK)');if(!isset($v['sun bay'],$v['sun maris park']))throw new RuntimeException('variants');
    echo "MATCH_GLOBAL_ACCEPTED_TOWN_CROSSWALK_SELFTEST_OK\n";exit(0);
}
if(PHP_SAPI!=='cli')exit(2);

$input=(string)($argv[1]??'');$root=realpath((string)getenv('ANYTOUR_ROOT'));$opDir=(string)getenv('MATCH_OPERATION_DIR');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
if(!$root||$opDir===''||!preg_match('/^[a-f0-9]{40}$/D',$sourceSha)||!is_file($input))throw new RuntimeException('runtime_guard');
$raw=file_get_contents($input);if(!is_string($raw)||!hash_equals(HMTC_GEO_RESULT_SHA,hash('sha256',$raw)))throw new RuntimeException('source_hash');
$source=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
if(($source['state']??null)!=='completed_read_only'||(int)($source['candidate_count']??-1)!==HMTC_SOURCE_CANDIDATES||!is_array($source['rows']??null)||count($source['rows'])!==HMTC_SOURCE_CANDIDATES)
    throw new RuntimeException('source_contract');
if(is_dir($opDir)||!mkdir($opDir,0700,true))throw new RuntimeException('operation_exists');
hmtc_durable($opDir.'/reservation.json',['operation'=>HMTC_OPERATION,'state'=>'reserved_before_db_read','source_sha'=>$sourceSha,'source_result_sha256'=>HMTC_GEO_RESULT_SHA,'candidate_count'=>HMTC_SOURCE_CANDIDATES,'provider_access'=>false,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);

$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbf;
$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach(['catalog_hotels','andromeda_hotel_identities'] as $t)if(!hmtc_table($pdo,$t))throw new RuntimeException('missing_'.$t);

$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION READ ONLY');
try{
    $accepted=hmtc_rows($pdo,"SELECT i.external_hotel_id,i.local_hotel_id,i.evidence_json,h.country_id,h.region_id,h.region_name,h.subregion_id,h.subregion_name
        FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id
        WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL AND h.is_active=1");
    $cwRaw=[];$acceptedWithOneTown=0;$acceptedTownAmbiguous=0;
    foreach($accepted as $r){
        $e=hmtc_scalar($r['evidence_json']??'',1000000);if($e==='')continue;
        try{$doc=json_decode($e,true,64,JSON_THROW_ON_ERROR);}catch(Throwable){continue;}
        $keys=hmtc_town_keys($doc);
        if(count($keys)!==1){if(count($keys)>1)$acceptedTownAmbiguous++;continue;}
        $acceptedWithOneTown++;$tk=$keys[0];$cid=(int)$r['country_id'];$k=$cid.'|'.$tk;
        $rid=$r['region_id']===null?null:(int)$r['region_id'];$sid=$r['subregion_id']===null?null:(int)$r['subregion_id'];
        $cwRaw[$k]['country_id']=$cid;$cwRaw[$k]['town_key']=$tk;$cwRaw[$k]['accepted_rows']=($cwRaw[$k]['accepted_rows']??0)+1;
        if($rid!==null)$cwRaw[$k]['region_ids'][$rid]=true;
        if($sid!==null)$cwRaw[$k]['subregion_ids'][$sid]=true;
        $rn=hmtc_scalar($r['region_name']??'',180);$sn=hmtc_scalar($r['subregion_name']??'',180);
        if($rn!=='')$cwRaw[$k]['region_names'][$rn]=true;if($sn!=='')$cwRaw[$k]['subregion_names'][$sn]=true;
    }
    $crosswalk=[];$crosswalkConflicts=[];
    foreach($cwRaw as $k=>$x){
        $rids=array_keys($x['region_ids']??[]);$sids=array_keys($x['subregion_ids']??[]);
        $regionConsensus=count($rids)===1?(int)$rids[0]:null;$subConsensus=count($sids)===1?(int)$sids[0]:null;
        if(count($rids)>1||count($sids)>1){$crosswalkConflicts[$k]=['country_id'=>$x['country_id'],'town_key'=>$x['town_key'],'region_ids'=>array_map('intval',$rids),'subregion_ids'=>array_map('intval',$sids),'accepted_rows'=>$x['accepted_rows']];continue;}
        $crosswalk[$k]=['country_id'=>$x['country_id'],'town_key'=>$x['town_key'],'region_id'=>$regionConsensus,'subregion_id'=>$subConsensus,
            'region_names'=>array_values(array_keys($x['region_names']??[])),'subregion_names'=>array_values(array_keys($x['subregion_names']??[])),'accepted_rows'=>$x['accepted_rows']];
    }

    $sourceBy=[];$localIds=[];$exts=[];
    foreach($source['rows'] as $r){$hid=(int)($r['hotel_id']??0);$ext=hmtc_scalar($r['external_hotel_id']??'',64);if($hid<1||$ext===''||isset($sourceBy[$hid]))throw new RuntimeException('candidate_shape');$sourceBy[$hid]=$r;$localIds[$hid]=true;$exts[$ext]=true;}
    $lids=array_keys($localIds);$lph=implode(',',array_fill(0,count($lids),'?'));
    $locals=[];foreach(hmtc_rows($pdo,"SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,is_active FROM catalog_hotels WHERE id IN ($lph)",$lids) as $r)$locals[(int)$r['id']]=$r;
    $externalIds=array_keys($exts);$eph=implode(',',array_fill(0,count($externalIds),'?'));
    $currentIds=hmtc_rows($pdo,"SELECT external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND (external_hotel_id IN ($eph) OR local_hotel_id IN ($lph))",array_merge($externalIds,$lids));
    $byExt=[];$byLocal=[];foreach($currentIds as $r){$ext=hmtc_scalar($r['external_hotel_id']??'',64);$lid=$r['local_hotel_id']===null?null:(int)$r['local_hotel_id'];if($ext!=='')$byExt[$ext][]=$r;if($lid!==null&&$lid>0)$byLocal[$lid][]=$r;}

    $rows=[];$counts=['geo_clean'=>0,'needs_geo'=>0,'held'=>0,'already_same'=>0];$crosswalkPromoted=0;
    foreach($sourceBy as $hid=>$src){
        $local=$locals[$hid]??null;$ext=hmtc_scalar($src['external_hotel_id']??'',64);$route=(($src['route']??'')==='geo_clean')?'geo_clean':'needs_geo';$reasons=[];
        if(!$local||(int)($local['is_active']??0)!==1){$route='held';$reasons[]='local_missing_or_inactive';}
        else{
            if((int)$local['country_id']!==(int)$src['country_id']){$route='held';$reasons[]='country_drift';}
            $match=hmtc_norm((string)($src['match_key']??''));$variants=hmtc_variants((string)$local['name']);if($match===''||!isset($variants[$match])){$route='held';$reasons[]='name_drift';}
        }
        foreach($byExt[$ext]??[] as $r){
            $status=hmtc_scalar($r['decision_status']??'',60);$target=$r['local_hotel_id']===null?null:(int)$r['local_hotel_id'];
            if($status==='accepted'&&$target===$hid){$route='already_same';$reasons[]='already_same';}
            elseif($status==='accepted'&&$target!==$hid){$route='held';$reasons[]='external_accepted_elsewhere';}
            elseif(in_array($status,['conflict','rejected','excluded'],true)){$route='held';$reasons[]='external_current_'.$status;}
        }
        foreach($byLocal[$hid]??[] as $r){$status=hmtc_scalar($r['decision_status']??'',60);$other=hmtc_scalar($r['external_hotel_id']??'',64);if($status==='accepted'&&$other!==$ext){$route='held';$reasons[]='same_provider_local_occupied';}}

        $cw=null;$cwKey=(int)($src['country_id']??0).'|'.hmtc_scalar($src['supplier_town_key']??'',64);
        if($route==='needs_geo'&&isset($crosswalk[$cwKey])){
            $cw=$crosswalk[$cwKey];$rid=$local['region_id']===null?null:(int)$local['region_id'];$sid=$local['subregion_id']===null?null:(int)$local['subregion_id'];
            $matchSub=$cw['subregion_id']!==null&&$sid!==null&&$cw['subregion_id']===$sid;
            $matchRegion=$cw['region_id']!==null&&$rid!==null&&$cw['region_id']===$rid;
            if($matchSub||$matchRegion){$route='geo_clean';$reasons[]='accepted_town_crosswalk_match';$crosswalkPromoted++;}
            elseif(($cw['region_id']!==null&&$rid!==null)||($cw['subregion_id']!==null&&$sid!==null)){$route='held';$reasons[]='accepted_town_crosswalk_conflict';}
        }elseif($route==='needs_geo'&&isset($crosswalkConflicts[$cwKey])){$reasons[]='accepted_town_crosswalk_ambiguous';}
        elseif($route==='needs_geo'){$reasons[]='accepted_town_crosswalk_missing';}

        $counts[$route]++;
        $rows[]=[
            'hotel_id'=>$hid,'external_hotel_id'=>$ext,'hotel_name'=>$local['name']??$src['hotel_name'],'supplier_name'=>$src['supplier_name'],
            'country_id'=>(int)$src['country_id'],'country_name'=>$src['country_name'],'region_id'=>$local['region_id']??null,'region_name'=>$local['region_name']??$src['region_name'],
            'subregion_id'=>$local['subregion_id']??null,'subregion_name'=>$local['subregion_name']??$src['subregion_name'],
            'supplier_town_key'=>$src['supplier_town_key'],'supplier_town'=>$src['supplier_town'],'match_key'=>$src['match_key'],'match_reasons'=>$src['match_reasons'],
            'user_observation_rows'=>$src['user_observation_rows'],'source_geo_route'=>$src['route'],'route'=>$route,'reasons'=>array_values(array_unique($reasons)),
            'accepted_town_crosswalk'=>$cw,
        ];
    }
    usort($rows,static fn($a,$b)=>strcmp($a['route'],$b['route'])?:($b['user_observation_rows']<=>$a['user_observation_rows'])?:($a['hotel_id']<=>$b['hotel_id']));
    $pdo->rollBack();
    $result=['operation'=>HMTC_OPERATION,'status'=>'read_only_complete','source_sha'=>$sourceSha,'source_geo_result_sha256'=>HMTC_GEO_RESULT_SHA,
        'candidate_count'=>count($rows),'route_counts'=>$counts,'crosswalk_keys'=>count($crosswalk),'crosswalk_conflicts'=>count($crosswalkConflicts),
        'accepted_identity_rows'=>count($accepted),'accepted_with_single_town_key'=>$acceptedWithOneTown,'accepted_multi_town_key'=>$acceptedTownAmbiguous,
        'crosswalk_promoted_count'=>$crosswalkPromoted,'rows'=>$rows,'provider_access'=>false,'supplier_calls'=>0,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    $result=['operation'=>HMTC_OPERATION,'status'=>'failed_read_only','source_sha'=>$sourceSha,'source_geo_result_sha256'=>HMTC_GEO_RESULT_SHA,
        'reason'=>preg_replace('/[^a-z0-9_\-]/i','_',mb_substr($e->getMessage(),0,100)),'provider_access'=>false,'supplier_calls'=>0,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
}
$sha=hmtc_durable($opDir.'/result.json',$result);
hmtc_durable($opDir.'/receipt.json',['operation'=>HMTC_OPERATION,'state'=>$result['status'],'result_sha256'=>$sha,'provider_access'=>false,'supplier_calls'=>0,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit(($result['status']??'')==='read_only_complete'?0:2);
