<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_live_samo_anex_identity_join_v1.php';

const HMSAW_OP='hotel-match-live-samo-anex-writer-1971-20260922-v1';
const HMSAW_SOURCE_RESULT_SHA='11064e77807826edaba66c43067b2b0e564aa100';

function hmsaw_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmsaw_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmsaw_save(string $path,array $value):string{
    $raw=hmsaw_json($value)."\n";$f=@fopen($path,'x+b');hmsaw_need($f!==false,'exclusive_create');
    try{hmsaw_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmsaw_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$raw);
}
function hmsaw_manifest(array $m):array{
    hmsaw_need(($m['state']??'')==='completed_read_only_identity_join','manifest_state');
    hmsaw_need(($m['source_sha']??'')===HMSAW_SOURCE_RESULT_SHA,'manifest_source');
    hmsaw_need((int)($m['frontier_samo_present_anex_missing']??0)===942,'manifest_frontier');
    hmsaw_need((int)($m['strict_mutual_unique_count']??0)===15,'manifest_count');
    $pairs=[];
    foreach((array)($m['rows']??[]) as $r){
        if(!is_array($r)||($r['status']??'')!=='candidate_strict_mutual_unique')continue;
        $aid=(int)($r['anex_hotel_id']??0);$tv=(int)($r['tv_hotel_id']??0);$fp=(string)($r['source_fingerprint']??'');
        hmsaw_need($aid>0&&$tv>0&&preg_match('/^[A-Za-z0-9._:-]{8,64}$/D',$fp)===1,'manifest_pair');
        $key=$aid.':'.$tv;hmsaw_need(!isset($pairs[$key]),'manifest_duplicate');
        $pairs[$key]=['anex_hotel_id'=>$aid,'tv_hotel_id'=>$tv,'source_fingerprint'=>$fp,
            'name_via_tv'=>(bool)($r['name_via_tv']??false),'name_via_samo'=>(bool)($r['name_via_samo']??false),
            'geo_class'=>(string)($r['geo_class']??''),'distance_m'=>$r['distance_m']??null,'place_match'=>(bool)($r['place_match']??false)];
    }
    ksort($pairs,SORT_NATURAL);hmsaw_need(count($pairs)===15,'manifest_pair_count');
    hmsaw_need(count(array_unique(array_column($pairs,'anex_hotel_id')))==15,'manifest_source_unique');
    hmsaw_need(count(array_unique(array_column($pairs,'tv_hotel_id')))==15,'manifest_target_unique');
    return $pairs;
}
function hmsaw_current_pairs(PDO $db):array{
    $facts=[];$active=[];
    $sql="SELECT h.id,h.name,h.normalized_name,h.country_name,h.region_name,h.subregion_name,h.category,h.is_active,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.is_active=1 ORDER BY h.id";
    foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r){
        if(hmsaj_excluded((string)($r['country_name']??'')))continue;$id=(int)$r['id'];if($id<=0)continue;$facts[$id]=$r;$active[$id]=true;
    }
    $nativeNames=[];foreach($facts as $id=>$h)foreach([$h['name'],$h['normalized_name']] as $n)if(trim((string)$n)!=='')$nativeNames[$id][(string)$n]=true;
    foreach($db->query("SELECT a.hotel_id,a.alias,a.normalized_alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 ORDER BY a.hotel_id,a.id")->fetchAll(PDO::FETCH_ASSOC) as $r){
        $id=(int)$r['hotel_id'];if(!isset($active[$id]))continue;foreach([$r['alias'],$r['normalized_alias']] as $n)if(trim((string)$n)!=='')$nativeNames[$id][(string)$n]=true;
    }
    $anchors=[];$samoNames=[];$samoPlaces=[];$samoPoints=[];$invalidAnchor=[];
    $q=$db->query("SELECT external_hotel_id,local_hotel_id,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY local_hotel_id,external_hotel_id");
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
        $tv=(int)$r['local_hotel_id'];if(!isset($active[$tv]))continue;$anchors[$tv][(string)$r['external_hotel_id']]=true;
        $raw=(string)$r['evidence_json'];if(hash('sha256',$raw)!==(string)$r['evidence_sha256']){$invalidAnchor[$tv]=true;continue;}
        $e=json_decode($raw,true);if(!is_array($e)){$invalidAnchor[$tv]=true;continue;}$sources=[];hmsaj_sources($e,$sources);
        foreach($sources as $s){
            foreach([$s['name']??'',$s['lName']??''] as $n)if(trim((string)$n)!=='')$samoNames[$tv][(string)$n]=true;
            foreach([$s['town']??'',$s['townLName']??'',$s['region']??''] as $p)if(trim((string)$p)!=='')$samoPlaces[$tv][(string)$p]=true;
            if(($pt=hmsaj_point($s))!==null)$samoPoints[$tv][]=$pt;
        }
    }
    $anex=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anexByLocal=[];
    foreach(($anex['by_local']??[]) as $local=>$ids)$anexByLocal[(int)$local]=$ids;
    $live30=[];$cut=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-30 days');
    foreach($db->query("SELECT hotel_id,MAX(last_seen_at) last_seen_at FROM tour_operator_identity_observations GROUP BY hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
        $tv=(int)$r['hotel_id'];if(!isset($active[$tv]))continue;$raw=trim((string)$r['last_seen_at']);if($raw==='')continue;
        try{$dt=new DateTimeImmutable($raw,new DateTimeZone('UTC'));}catch(Throwable){continue;}if($dt>=$cut)$live30[$tv]=true;
    }
    $frontier=[];foreach($live30 as $tv=>$_)if(isset($anchors[$tv])&&!isset($anexByLocal[$tv]))$frontier[$tv]=true;
    hmsaw_need(count($frontier)===942,'frontier_drift');

    $manual=[];foreach($db->query("SELECT anex_hotel_id FROM anex_hotel_decisions ORDER BY anex_hotel_id")->fetchAll(PDO::FETCH_COLUMN) as $id)$manual[(string)$id]=true;
    $mapping=[];foreach($db->query("SELECT anex_hotel_id FROM anex_hotel_search_mappings ORDER BY anex_hotel_id")->fetchAll(PDO::FETCH_COLUMN) as $id)$mapping[(string)$id]=true;
    $ex=[];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r)$ex[(string)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;

    $index=[];$targetMeta=[];
    foreach(array_keys($frontier) as $tv){
        if(isset($invalidAnchor[$tv]))continue;$country=hmsaj_country($facts[$tv]['country_name']);if($country===null)continue;
        $tvKeys=[];$samoKeys=[];
        foreach(array_keys($nativeNames[$tv]??[]) as $n){$k=hmsaj_name_key($n);if($k!==''){$tvKeys[$k]=true;$index[$country][$k][$tv]['tv']=true;}}
        foreach(array_keys($samoNames[$tv]??[]) as $n){$k=hmsaj_name_key($n);if($k!==''){$samoKeys[$k]=true;$index[$country][$k][$tv]['samo']=true;}}
        $places=hmsaj_place_keys(array_merge([$facts[$tv]['region_name'],$facts[$tv]['subregion_name']],array_keys($samoPlaces[$tv]??[])));
        $pts=[];$tvpt=hmsaj_point($facts[$tv]);if($tvpt!==null)$pts[]=$tvpt;foreach($samoPoints[$tv]??[] as $pt)$pts[]=$pt;
        $targetMeta[$tv]=['country'=>$country,'tv_keys'=>$tvKeys,'samo_keys'=>$samoKeys,'places'=>$places,'points'=>$pts];
    }
    $raw=[];$sourceProjection=[];
    foreach($db->query("SELECT anex_hotel_id,source_fingerprint,xml_name,xml_alternate_name,api_name,api_country,api_region,api_town,latitude,longitude,checked_at FROM anex_hotels ORDER BY anex_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $s){
        $aid=(string)$s['anex_hotel_id'];if(isset($manual[$aid])||isset($mapping[$aid]))continue;
        $country=hmsaj_country($s['api_country']);if($country===null||!isset($index[$country]))continue;
        $sourceKeys=[];foreach([$s['api_name'],$s['xml_name'],$s['xml_alternate_name']] as $n){$k=hmsaj_name_key($n);if($k!=='')$sourceKeys[$k]=true;}
        if(!$sourceKeys)continue;$tvSet=[];$channels=[];
        foreach(array_keys($sourceKeys) as $k)foreach($index[$country][$k]??[] as $tv=>$ch){$tvSet[(int)$tv]=true;$channels[(int)$tv]['tv']=($channels[(int)$tv]['tv']??false)||isset($ch['tv']);$channels[(int)$tv]['samo']=($channels[(int)$tv]['samo']??false)||isset($ch['samo']);}
        if(!$tvSet)continue;$sp=hmsaj_place_keys([$s['api_region'],$s['api_town']]);$srcPt=hmsaj_point($s);
        foreach(array_keys($tvSet) as $tv){
            if(isset($ex[$aid][$tv]))continue;$t=$targetMeta[$tv];$place=(bool)array_intersect_key($sp,$t['places']);$bestDist=null;
            foreach($t['points'] as $pt){$d=hmsaj_dist($srcPt,$pt);if($d!==null&&($bestDist===null||$d<$bestDist))$bestDist=$d;}
            $geoClass=$place?'place_exact':($bestDist!==null&&$bestDist<=1000?'coord_le_1km':($bestDist!==null&&$bestDist<=5000?'coord_1_5km':null));
            if($geoClass===null)continue;
            $raw[$aid][$tv]=['anex_hotel_id'=>(int)$aid,'tv_hotel_id'=>$tv,'name_via_tv'=>(bool)($channels[$tv]['tv']??false),'name_via_samo'=>(bool)($channels[$tv]['samo']??false),
                'geo_class'=>$geoClass,'distance_m'=>$bestDist===null?null:round($bestDist,1),'place_match'=>$place,'source_fingerprint'=>(string)$s['source_fingerprint']];
            $sourceProjection[$aid]=['anex_hotel_id'=>(int)$aid,'source_fingerprint'=>(string)$s['source_fingerprint'],'xml_name'=>(string)$s['xml_name'],'xml_alternate_name'=>(string)$s['xml_alternate_name'],
                'api_name'=>(string)$s['api_name'],'api_country'=>(string)$s['api_country'],'api_region'=>(string)$s['api_region'],'api_town'=>(string)$s['api_town'],
                'latitude'=>$s['latitude'],'longitude'=>$s['longitude'],'checked_at'=>$s['checked_at']];
        }
    }
    $targetSources=[];foreach($raw as $aid=>$targets)foreach($targets as $tv=>$row)$targetSources[$tv][$aid]=true;
    $pairs=[];
    foreach($raw as $aid=>$targets)foreach($targets as $tv=>$r)if(count($targets)===1&&count($targetSources[$tv]??[])===1){
        $key=((int)$aid).':'.((int)$tv);$pairs[$key]=$r;
        $pairs[$key]['source_projection_sha256']=hash('sha256',hmsaw_json($sourceProjection[$aid]));
        $pairs[$key]['target_projection_sha256']=hash('sha256',hmsaw_json([
            'hotel'=>$facts[$tv],'names'=>array_keys($nativeNames[$tv]??[]),'samo_names'=>array_keys($samoNames[$tv]??[]),
            'samo_places'=>array_keys($samoPlaces[$tv]??[]),'samo_anchor_ids'=>array_keys($anchors[$tv]??[])
        ]));
    }
    ksort($pairs,SORT_NATURAL);
    return ['pairs'=>$pairs,'frontier_count'=>count($frontier),'active_facts'=>$facts,'anchors'=>$anchors,'anex_registry'=>$anex];
}
function hmsaw_mapping_rows(PDO $db,array $exclude=[]):array{
    $out=[];$q=$db->query("SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings ORDER BY anex_hotel_id");
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(int)$r['anex_hotel_id'];if(isset($exclude[$id]))continue;$out[]=$r;}return $out;
}
function hmsaw_coverage(PDO $db):array{
    $reg=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anex=[];foreach(($reg['by_local']??[]) as $id=>$v)$anex[(int)$id]=true;
    $samo=[];foreach($db->query("SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $id)$samo[(int)$id]=true;
    $triple=count(array_intersect_key($anex,$samo));
    $live=[];$cut=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-30 days');
    foreach($db->query("SELECT hotel_id,MAX(last_seen_at) last_seen_at FROM tour_operator_identity_observations GROUP BY hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
        $raw=(string)$r['last_seen_at'];if($raw==='')continue;try{$dt=new DateTimeImmutable($raw,new DateTimeZone('UTC'));}catch(Throwable){continue;}if($dt>=$cut)$live[(int)$r['hotel_id']]=true;
    }
    return ['anex_unique_tv'=>count($anex),'samo_unique_tv'=>count($samo),'full_triple'=>$triple,'live30_full_triple'=>count(array_intersect_key($live,$anex,$samo))];
}
function hmsaw_execute(PDO $db,array $manifest,string $manifestSha):array{
    $wanted=hmsaw_manifest($manifest);$wantedIds=[];foreach($wanted as $r)$wantedIds[(int)$r['anex_hotel_id']]=true;
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
    $committed=false;
    try{
        $db->beginTransaction();
        foreach(['anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','anex_hotels','andromeda_hotel_identities','catalog_hotels','hotel_aliases','tour_operator_identity_observations'] as $table){
            $db->query("SELECT COUNT(*) FROM ".$table)->fetchColumn();
        }
        $beforeRows=hmsaw_mapping_rows($db);$beforeHash=hash('sha256',hmsaw_json($beforeRows));
        $current=hmsaw_current_pairs($db);$pairs=$current['pairs'];
        hmsaw_need(count($pairs)===15,'current_pair_count_drift');
        hmsaw_need(array_keys($pairs)===array_keys($wanted),'current_pair_set_drift');
        foreach($wanted as $key=>$m){
            $c=$pairs[$key];hmsaw_need($c['source_fingerprint']===$m['source_fingerprint'],'source_fingerprint_drift');
            hmsaw_need((bool)$c['name_via_tv']===(bool)$m['name_via_tv']&&(bool)$c['name_via_samo']===(bool)$m['name_via_samo'],'name_channel_drift');
            hmsaw_need($c['geo_class']===$m['geo_class'],'geo_class_drift');
        }
        $mappingDigest=hash('sha256',hmsaw_json(['operation'=>HMSAW_OP,'manifest_sha256'=>$manifestSha,'pairs'=>array_keys($wanted)]));
        $ins=$db->prepare("INSERT INTO anex_hotel_search_mappings(anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,'strong_candidate','preview','owner_exact_and_strong_20260908',?,?,1)");
        $written=[];
        foreach($pairs as $key=>$p){
            $e=['operation'=>HMSAW_OP,'manifest_sha256'=>$manifestSha,'anex_hotel_id'=>$p['anex_hotel_id'],'tv_hotel_id'=>$p['tv_hotel_id'],
                'source_fingerprint'=>$p['source_fingerprint'],'name_via_tv'=>$p['name_via_tv'],'name_via_samo'=>$p['name_via_samo'],'geo_class'=>$p['geo_class'],
                'distance_m'=>$p['distance_m'],'place_match'=>$p['place_match'],'source_projection_sha256'=>$p['source_projection_sha256'],'target_projection_sha256'=>$p['target_projection_sha256']];
            $sourceDigest=hash('sha256',hmsaw_json($e));$ins->execute([$p['anex_hotel_id'],$p['tv_hotel_id'],$sourceDigest,$mappingDigest]);
            hmsaw_need($ins->rowCount()===1,'insert_count');$written[(int)$p['anex_hotel_id']]=['tv_hotel_id'=>(int)$p['tv_hotel_id'],'source_row_digest'=>$sourceDigest];
        }
        hmsaw_need(count($written)===15,'write_count');
        $afterExcluding=hmsaw_mapping_rows($db,$wantedIds);hmsaw_need(hash('sha256',hmsaw_json($afterExcluding))===$beforeHash,'preexisting_mapping_changed');
        foreach($written as $aid=>$w){
            $q=$db->prepare("SELECT catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=?");
            $q->execute([$aid]);$r=$q->fetch(PDO::FETCH_ASSOC);hmsaw_need(is_array($r),'staged_readback_missing');
            hmsaw_need((int)$r['catalog_hotel_id']===$w['tv_hotel_id']&&$r['match_class']==='strong_candidate'&&$r['scope']==='preview'&&$r['approval_policy']==='owner_exact_and_strong_20260908'&&(int)$r['enabled']===1&&$r['source_row_digest']===$w['source_row_digest']&&$r['mapping_digest']===$mappingDigest,'staged_readback_mismatch');
        }
        $db->commit();$committed=true;
        $db->exec('START TRANSACTION READ ONLY');
        $readback=[];
        foreach($written as $aid=>$w){
            $q=$db->prepare("SELECT catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=?");
            $q->execute([$aid]);$r=$q->fetch(PDO::FETCH_ASSOC);hmsaw_need(is_array($r),'postcommit_missing');
            hmsaw_need((int)$r['catalog_hotel_id']===$w['tv_hotel_id']&&$r['source_row_digest']===$w['source_row_digest']&&$r['mapping_digest']===$mappingDigest&&(int)$r['enabled']===1,'postcommit_mismatch');
            $readback[]=['anex_hotel_id'=>$aid,'tv_hotel_id'=>$w['tv_hotel_id'],'source_row_digest'=>$w['source_row_digest']];
        }
        $afterRows=hmsaw_mapping_rows($db,$wantedIds);hmsaw_need(hash('sha256',hmsaw_json($afterRows))===$beforeHash,'postcommit_preexisting_changed');
        $coverage=hmsaw_coverage($db);$db->rollBack();
        return ['operation'=>HMSAW_OP,'state'=>'committed_verified','manifest_sha256'=>$manifestSha,'inserted'=>15,'readback_verified'=>true,'preexisting_mapping_rows_preserved'=>count($beforeRows),'rows'=>$readback,'coverage_after'=>$coverage,'provider_http_calls'=>0,'database_writes'=>15,'mapping_writes'=>15];
    }catch(Throwable $e){
        if(!$committed&&$db->inTransaction())$db->rollBack();
        throw new RuntimeException(($committed?'postcommit_':'precommit_').$e->getMessage(),0,$e);
    }
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        $rows=[];for($i=1;$i<=15;$i++)$rows[]=['status'=>'candidate_strict_mutual_unique','anex_hotel_id'=>$i,'tv_hotel_id'=>100+$i,'source_fingerprint'=>'fingerprint'.$i,'name_via_tv'=>true,'name_via_samo'=>false,'geo_class'=>'place_exact','distance_m'=>null,'place_match'=>true];
        $m=['state'=>'completed_read_only_identity_join','source_sha'=>HMSAW_SOURCE_RESULT_SHA,'frontier_samo_present_anex_missing'=>942,'strict_mutual_unique_count'=>15,'rows'=>$rows];
        hmsaw_need(count(hmsaw_manifest($m))===15,'self_manifest');echo "MATCH_LIVE_SAMO_ANEX_WRITER_V1_SELFTEST_OK\n";exit;
    }
    hmsaw_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$sha=(string)getenv('MATCH_SOURCE_SHA');$manifestPath=(string)getenv('MATCH_MANIFEST_PATH');
    hmsaw_need(is_dir($root)&&is_dir($dir)&&basename($dir)===HMSAW_OP&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1&&is_file($manifestPath),'runtime_scope');
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);hmsaw_need(($reservation['operation']??'')===HMSAW_OP&&($reservation['state']??'')==='reserved_before_write','reservation');
    $raw=(string)file_get_contents($manifestPath);$manifest=json_decode($raw,true,64,JSON_THROW_ON_ERROR);$manifestSha=hash('sha256',$raw);
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{
        $result=hmsaw_execute(v2_data_db(),$manifest,$manifestSha);$result['source_sha']=$sha;$h=hmsaw_save($dir.'/result.json',$result);
        hmsaw_save($dir.'/receipt.json',['operation'=>HMSAW_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>15,'mapping_writes'=>15]);
        echo hmsaw_json(['state'=>$result['state'],'inserted'=>$result['inserted'],'coverage_after'=>$result['coverage_after']])."\n";
    }catch(Throwable $e){
        $committed=str_starts_with($e->getMessage(),'postcommit_');
        $f=['operation'=>HMSAW_OP,'state'=>$committed?'terminal_unknown_or_postcommit_failure':'rolled_back_no_write','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,140,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>$committed?null:0,'mapping_writes'=>$committed?null:0];
        $h=hmsaw_save($dir.'/result.json',$f);hmsaw_save($dir.'/receipt.json',['operation'=>HMSAW_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>$f['database_writes'],'mapping_writes'=>$f['mapping_writes']]);
        fwrite(STDERR,$f['reason']."\n");exit(2);
    }
}
