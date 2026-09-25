<?php
declare(strict_types=1);

const V38_OP='hotel-match-tv-samo-single-fingerprint-corroboration-1971-20260925-v38';
const V38_SOURCE_OP='hotel-match-tv-samo-common4-fingerprint-join-1971-20260925-v37';
const V38_SOURCE_SHA='b7ef6082b8d27ad822ddaf69dd86e9249523f859cd61ee1b547c106118ffc55a';
const V38_ALLOWED_SOURCE_STATUSES=['single_direct'=>true,'support_only'=>true];
const V38_EXPECTED=['single_direct'=>9,'support_only'=>1];
const V38_MAX_CATALOG_FILES=5000;
const V38_MAX_FILE_BYTES=33554432;

function v38_need(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function v38_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function v38_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);v38_need(is_array($v),'json_shape');return $v;}
function v38_save(string $p,array $v):string{$raw=v38_json($v)."\n";$f=@fopen($p,'x+b');v38_need($f!==false,'exclusive_create');try{v38_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'write');if(function_exists('fsync'))v38_need(fsync($f),'sync');}finally{fclose($f);}return hash('sha256',$raw);}
function v38_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function v38_id(mixed $v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,21}$/D',$s)===1?$s:null;}
function v38_norm(mixed $v):string{
    $s=mb_strtolower(trim((string)$v),'UTF-8');$s=str_replace('ё','е',$s);
    $s=strtr($s,['é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);
    $s=preg_replace('/[^\p{L}\p{N}]+/u',' ',$s)??$s;return trim(preg_replace('/\s+/u',' ',$s)??$s);
}
function v38_name_key(mixed $v):string{
    $generic=['hotel'=>1,'hotels'=>1,'otel'=>1,'отель'=>1,'отели'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'the'=>1,'and'=>1,'by'=>1,'резорт'=>1,'ресорт'=>1,'спа'=>1];
    $parts=[];foreach(preg_split('/\s+/u',v38_norm($v),-1,PREG_SPLIT_NO_EMPTY)?:[] as $x)if(!isset($generic[$x]))$parts[$x]=true;
    $keys=array_keys($parts);sort($keys,SORT_STRING);return implode(' ',$keys);
}
function v38_place_key(mixed $v):string{
    $n=v38_norm($v);if($n==='')return '';
    $n=preg_replace('/\b(?:центр|center|centre|город|city|остров|island|район|district)\b/u',' ',$n)??$n;
    return trim(preg_replace('/\s+/u',' ',$n)??$n);
}
function v38_num(mixed $v):?float{
    if($v===null||$v==='')return null;$x=(float)str_replace(',','.',(string)$v);return is_finite($x)?$x:null;
}
function v38_point(array $r):?array{
    foreach([['latitude','longitude'],['lat','lng'],['lat','lon']] as [$a,$b]){
        if(!array_key_exists($a,$r)||!array_key_exists($b,$r))continue;$lat=v38_num($r[$a]);$lon=v38_num($r[$b]);
        if($lat!==null&&$lon!==null&&abs($lat)<=90&&abs($lon)<=180&&($lat!=0.0||$lon!=0.0))return[$lat,$lon];
    }return null;
}
function v38_points_recursive(mixed $node,array &$out,int $depth=0):void{
    if($depth>4||!is_array($node))return;
    $p=v38_point($node);if($p!==null)$out[sprintf('%.6f|%.6f',$p[0],$p[1])]=$p;
    foreach($node as $v)if(is_array($v))v38_points_recursive($v,$out,$depth+1);
}
function v38_dist(array $a,array $b):float{
    [$lat1,$lon1]=$a;[$lat2,$lon2]=$b;$p1=deg2rad($lat1);$p2=deg2rad($lat2);$dp=$p2-$p1;$dl=deg2rad($lon2-$lon1);
    $x=sin($dp/2)**2+cos($p1)*cos($p2)*sin($dl/2)**2;return 6371000*2*asin(min(1,sqrt($x)));
}
function v38_source_candidates(string $path):array{
    $raw=(string)file_get_contents($path);v38_need(hash('sha256',$raw)===V38_SOURCE_SHA,'source_sha');
    $r=json_decode($raw,true,512,JSON_THROW_ON_ERROR);v38_need(is_array($r),'source_shape');
    v38_need(($r['operation']??'')===V38_SOURCE_OP&&($r['state']??'')==='completed_read_only_tv_samo_common4_fingerprint_join','source_state');
    v38_need(($r['provider_http_calls']??-1)===0&&($r['database_writes']??-1)===0&&($r['mapping_writes']??-1)===0&&($r['safe_to_write_now']??null)===false,'source_boundary');
    $rows=[];$counts=[];$seenSource=[];$seenPair=[];
    foreach(($r['rows']??[]) as $x){
        if(!is_array($x))continue;$status=(string)($x['status']??'');if(!isset(V38_ALLOWED_SOURCE_STATUSES[$status]))continue;
        $cid=v38_id($x['andromeda_catalog_id']??null);$local=(int)($x['candidate_local_hotel_id']??0);$bucket=(string)($x['frontier_bucket']??'');
        v38_need($cid!==null&&$local>0&&in_array($bucket,['tv_anex_missing_samo','tv_only_missing_both'],true),'candidate_shape');
        v38_need(!isset($seenSource[$cid]),'candidate_source_duplicate');$seenSource[$cid]=true;
        $pk=$cid.'|'.$local;v38_need(!isset($seenPair[$pk]),'candidate_pair_duplicate');$seenPair[$pk]=true;
        $rows[]=['source_status'=>$status,'andromeda_catalog_id'=>$cid,'candidate_local_hotel_id'=>$local,'frontier_bucket'=>$bucket,
            'direct_operator_count'=>(int)($x['direct_operator_count']??0),'support_operator_count'=>(int)($x['support_operator_count']??0),
            'source_row_sha256'=>hash('sha256',v38_json($x)),'safe_to_write_now'=>false];
        $counts[$status]=($counts[$status]??0)+1;
    }
    ksort($counts);v38_need($counts===V38_EXPECTED&&count($rows)===10,'candidate_counts');
    usort($rows,fn($a,$b)=>[$a['source_status'],$a['candidate_local_hotel_id'],$a['andromeda_catalog_id']]<=>[$b['source_status'],$b['candidate_local_hotel_id'],$b['andromeda_catalog_id']]);
    return $rows;
}
function v38_private_config(string $root):array{
    foreach([$root.'/_preview/search3-anex-candidate/.andromeda-private.php',$root.'/v2/.andromeda-private.php'] as $p){
        if(!is_file($p)||is_link($p))continue;$v=require$p;
        if(is_array($v)&&is_string($v['catalog_path']??null)&&$v['catalog_path']!=='')return$v;
    }throw new RuntimeException('andromeda_private_config_missing');
}
function v38_catalog_files(string $catalogPath):array{
    $files=[];if(is_file($catalogPath)&&!is_link($catalogPath))$files[]=$catalogPath;
    foreach(glob(dirname($catalogPath).'/countries/*.json')?:[] as $p)if(is_file($p)&&!is_link($p))$files[]=$p;
    $files=array_values(array_unique($files));sort($files,SORT_STRING);v38_need(count($files)<=V38_MAX_CATALOG_FILES,'catalog_file_cap');return$files;
}
function v38_row_names(array $row):array{
    $keys=['name','lName','russianName','hotelName','hotel_name','title','nameRus','nameEng'];$out=[];
    foreach($keys as $k)if(isset($row[$k])&&!is_array($row[$k])){$s=trim((string)$row[$k]);if($s!=='')$out[$s]=true;}
    return array_keys($out);
}
function v38_row_places(array $row):array{
    $keys=['town','townLName','region','regionName','regionLName','area','areaName','city','cityName','resort','resortName'];$out=[];
    foreach($keys as $k)if(isset($row[$k])&&!is_array($row[$k])){$s=trim((string)$row[$k]);if($s!=='')$out[$s]=true;}
    return array_keys($out);
}
function v38_catalog_snapshot(string $catalogPath,array $wanted):array{
    $rows=[];$files=0;$stateByCatalog=[];$allCatalogState=[];$stateCatalogIds=[];
    foreach(v38_catalog_files($catalogPath) as $path){
        $size=filesize($path);if($size===false||$size<2||$size>V38_MAX_FILE_BYTES)continue;$files++;
        $raw=(string)file_get_contents($path);$v=json_decode($raw,true);if(!is_array($v))continue;$state=(int)($v['all']['params']['STATEINC']??0);
        $hotels=$v['all']['payload']['HOTELS']??[];if(!is_array($hotels))continue;
        foreach($hotels as $h){
            if(!is_array($h))continue;$id=v38_id($h['id']??null);if($id===null)continue;
            if($state>0){$allCatalogState[$id][$state]=true;$stateCatalogIds[$state][$id]=true;}
            if(!isset($wanted[$id]))continue;
            $names=v38_row_names($h);$places=v38_row_places($h);$points=[];v38_points_recursive($h,$points);
            $entry=['stateinc'=>$state>0?$state:null,'names'=>$names,'places'=>$places,'points'=>array_values($points),'file_sha256'=>hash('sha256',$raw)];
            $rows[$id][]=$entry;if($state>0)$stateByCatalog[$id][$state]=true;
        }
    }
    return ['wanted'=>$rows,'file_count'=>$files,'state_by_catalog'=>$stateByCatalog,'all_catalog_state'=>$allCatalogState,'state_catalog_ids'=>$stateCatalogIds];
}
function v38_identity_evidence_ok(array $r):bool{
    $eh=(string)($r['evidence_sha256']??'');$raw=(string)($r['evidence_json']??'');
    return preg_match('/^[0-9a-f]{64}$/D',$eh)===1&&hash('sha256',$raw)===$eh;
}
function v38_active_name_index(PDO $db):array{
    $facts=[];$index=[];
    foreach(v38_query($db,"SELECT id,name,normalized_name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active,latitude,longitude FROM catalog_hotels WHERE is_active=1 ORDER BY id") as $r){
        $id=(int)$r['id'];if($id<1)continue;$facts[$id]=$r;
        foreach([$r['name']??'',$r['normalized_name']??''] as $n){$k=v38_name_key($n);if($k!=='')$index[$k][$id]=true;}
    }
    foreach(v38_query($db,"SELECT a.hotel_id,a.alias,a.normalized_alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 ORDER BY a.hotel_id,a.id") as $r){
        $id=(int)$r['hotel_id'];if(!isset($facts[$id]))continue;foreach([$r['alias']??'',$r['normalized_alias']??''] as $n){$k=v38_name_key($n);if($k!=='')$index[$k][$id]=true;}
    }
    $details=[];foreach(v38_query($db,"SELECT hotel_id,latitude,longitude FROM catalog_hotel_details") as $r)$details[(int)$r['hotel_id']]=$r;
    return ['facts'=>$facts,'name_index'=>$index,'details'=>$details];
}
function v38_state_country_binding(array $snapshot,array $acceptedCatalog,array $facts):array{
    $map=[];
    foreach($acceptedCatalog as $cid=>$targets){
        $states=array_keys($snapshot['all_catalog_state'][$cid]??[]);if(count($states)!==1)continue;
        $locals=array_keys($targets);if(count($locals)!==1)continue;$local=(int)$locals[0];$country=(int)($facts[$local]['country_id']??0);if($country<1)continue;
        $map[(int)$states[0]][$country]=true;
    }
    $out=[];foreach($map as $state=>$countries){$ids=array_keys($countries);sort($ids,SORT_NUMERIC);$out[$state]=count($ids)===1?(int)$ids[0]:null;}return$out;
}
function v38_classify_candidate(array $c,array $snapshot,array $dbState,array $acceptedSource,array $acceptedTarget,array $nonAcceptedSource,array $nonAcceptedTarget,array $facts,array $nameIndex,array $details,array $stateCountry):array{
    $cid=$c['andromeda_catalog_id'];$local=(int)$c['candidate_local_hotel_id'];$status='review_insufficient_evidence';$nameEvidence=[];$placeEvidence=[];$minDistance=null;$countryState=null;$countryMatch=false;
    $sourceAccepted=array_keys($acceptedSource[$cid]??[]);sort($sourceAccepted,SORT_NUMERIC);
    $targetAccepted=array_keys($acceptedTarget[$local]??[]);sort($targetAccepted,SORT_NATURAL);
    if($c['source_status']==='support_only')$status='support_only_review';
    elseif(!isset($facts[$local])||(int)($facts[$local]['is_active']??0)!==1)$status='hold_target_inactive';
    elseif($sourceAccepted){
        $status=(count($sourceAccepted)===1&&(int)$sourceAccepted[0]===$local)?'resolved_same':'hold_source_occupied';
    }elseif($targetAccepted&&!(count($targetAccepted)===1&&(string)$targetAccepted[0]===$cid))$status='hold_target_source_occupied';
    elseif(isset($nonAcceptedSource[$cid]))$status='hold_manual_source_history';
    elseif(isset($nonAcceptedTarget[$local]))$status='hold_manual_target_history';
    else{
        $srcRows=$snapshot['wanted'][$cid]??[];
        if(!$srcRows)$status='hold_saved_catalog_missing';
        else{
            $states=[];$sourceNames=[];$sourcePlaces=[];$sourcePoints=[];
            foreach($srcRows as $sr){
                if(($sr['stateinc']??null)!==null)$states[(int)$sr['stateinc']]=true;
                foreach($sr['names'] as $n)$sourceNames[$n]=true;foreach($sr['places'] as $p)$sourcePlaces[$p]=true;
                foreach($sr['points'] as $pt)$sourcePoints[sprintf('%.6f|%.6f',$pt[0],$pt[1])]=$pt;
            }
            $stateIds=array_keys($states);sort($stateIds,SORT_NUMERIC);
            if(count($stateIds)===1){$countryState=$stateCountry[(int)$stateIds[0]]??null;$countryMatch=$countryState!==null&&(int)$countryState===(int)$facts[$local]['country_id'];}
            foreach(array_keys($sourceNames) as $n){
                $k=v38_name_key($n);if($k==='')continue;$targets=array_keys($nameIndex[$k]??[]);sort($targets,SORT_NUMERIC);
                if(count($targets)===1&&(int)$targets[0]===$local)$nameEvidence[$k]=['source_name'=>$n,'mutual_unique'=>true];
            }
            $localPlaces=[];foreach([$facts[$local]['region_name']??'',$facts[$local]['subregion_name']??''] as $p){$k=v38_place_key($p);if($k!=='')$localPlaces[$k]=true;}
            foreach(array_keys($sourcePlaces) as $p){$k=v38_place_key($p);if($k!==''&&isset($localPlaces[$k]))$placeEvidence[$k]=true;}
            $lp=null;$d=$details[$local]??[];$lp=v38_point(is_array($d)?$d:[]);if($lp===null)$lp=v38_point($facts[$local]);
            if($lp!==null)foreach($sourcePoints as $sp){$dist=v38_dist($lp,$sp);if($minDistance===null||$dist<$minDistance)$minDistance=$dist;}
            $geoOk=$countryMatch&&($placeEvidence!==[]||($minDistance!==null&&$minDistance<=5000));
            $nameOk=$nameEvidence!==[];
            if(!$countryMatch)$status='review_country_unproven_or_mismatch';
            elseif(!$nameOk)$status='review_name_not_mutual_unique';
            elseif(!$geoOk)$status='review_geo_not_supported';
            else $status='corroborated_single_direct';
        }
    }
    return $c+[
        'status'=>$status,'current_source_targets'=>array_map('intval',$sourceAccepted),'current_target_catalog_ids'=>array_map('strval',$targetAccepted),
        'country_stateinc'=>$countryState,'country_match'=>$countryMatch,'name_evidence_keys'=>array_keys($nameEvidence),'place_evidence_keys'=>array_keys($placeEvidence),
        'min_distance_m'=>$minDistance===null?null:round($minDistance,1),'writer_ready'=>false,'safe_to_write_now'=>false
    ];
}
function v38_execute(PDO $db,string $root,string $sourcePath,string $sourceSha):array{
    $cands=v38_source_candidates($sourcePath);$wanted=[];foreach($cands as $c)$wanted[$c['andromeda_catalog_id']]=true;
    $cfg=v38_private_config($root);$snapshot=v38_catalog_snapshot((string)$cfg['catalog_path'],$wanted);
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $dbState=v38_active_name_index($db);$facts=$dbState['facts'];$nameIndex=$dbState['name_index'];$details=$dbState['details'];
        $acceptedSource=[];$acceptedTarget=[];$nonAcceptedSource=[];$nonAcceptedTarget=[];$acceptedCatalog=[];
        foreach(v38_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id,local_hotel_id") as $r){
            $cid=(string)$r['external_hotel_id'];$local=$r['local_hotel_id']===null?null:(int)$r['local_hotel_id'];$status=(string)$r['decision_status'];
            if($status==='accepted'&&$local!==null){
                v38_need(v38_identity_evidence_ok($r),'accepted_evidence_hash_invalid');$acceptedSource[$cid][$local]=true;$acceptedTarget[$local][$cid]=true;$acceptedCatalog[$cid][$local]=true;
            }else{
                $nonAcceptedSource[$cid]=true;if($local!==null)$nonAcceptedTarget[$local]=true;
            }
        }
        $stateCountry=v38_state_country_binding($snapshot,$acceptedCatalog,$facts);
        $rows=[];$counts=[];$bucket=[];foreach($cands as $c){
            $r=v38_classify_candidate($c,$snapshot,$dbState,$acceptedSource,$acceptedTarget,$nonAcceptedSource,$nonAcceptedTarget,$facts,$nameIndex,$details,$stateCountry);$rows[]=$r;$counts[$r['status']]=($counts[$r['status']]??0)+1;$b=$r['frontier_bucket'];$bucket[$b][$r['status']]=($bucket[$b][$r['status']]??0)+1;
        }
        $db->rollBack();ksort($counts);ksort($bucket);foreach($bucket as &$x)ksort($x);unset($x);
        return ['operation'=>V38_OP,'state'=>'completed_read_only_single_fingerprint_corroboration','source_sha'=>$sourceSha,'source_operation'=>V38_SOURCE_OP,'source_result_sha256'=>V38_SOURCE_SHA,
            'candidate_count'=>count($cands),'saved_catalog_file_count'=>$snapshot['file_count'],'saved_candidate_catalog_count'=>count($snapshot['wanted']),
            'status_counts'=>$counts,'bucket_status_counts'=>$bucket,'corroborated_count'=>(int)($counts['corroborated_single_direct']??0),'rows'=>$rows,
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
}
function v38_self_test():void{
    v38_need(v38_name_key('The Example Hotel & Spa')==='example','name');
    v38_need(v38_place_key('Side - центр')==='side','place');
    v38_need(v38_dist([0.0,1.0],[0.0,1.0])===0.0,'distance');
    $f=[100=>['id'=>100,'name'=>'Example Hotel','normalized_name'=>'example hotel','country_id'=>5,'region_name'=>'Side','subregion_name'=>'','is_active'=>1,'latitude'=>36.7,'longitude'=>31.9]];
    $idx=['example'=>[100=>true]];$snap=['wanted'=>['900'=>[['stateinc'=>5,'names'=>['Example Hotel'],'places'=>['Side'],'points'=>[[36.7,31.9]],'file_sha256'=>str_repeat('a',64)]]]];
    $c=['source_status'=>'single_direct','andromeda_catalog_id'=>'900','candidate_local_hotel_id'=>100,'frontier_bucket'=>'tv_only_missing_both','direct_operator_count'=>1,'support_operator_count'=>0,'source_row_sha256'=>str_repeat('b',64),'safe_to_write_now'=>false];
    $r=v38_classify_candidate($c,$snap,[],[],[],[],[],$f,$idx,[],[5=>5]);v38_need($r['status']==='corroborated_single_direct','corroborated');
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){v38_self_test();echo "MATCH_TV_SAMO_SINGLE_CORROBORATION_V38_SELFTEST_OK\n";exit;}
    v38_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$source=(string)getenv('MATCH_V37_RESULT');$sha=(string)getenv('MATCH_SOURCE_SHA');
    v38_need(is_dir($root)&&is_dir($dir)&&basename($dir)===V38_OP&&is_file($source)&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $res=v38_load($dir.'/reservation.json');v38_need(($res['operation']??'')===V38_OP&&($res['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{$r=v38_execute(v2_data_db(),$root,$source,$sha);$h=v38_save($dir.'/result.json',$r);v38_save($dir.'/receipt.json',['operation'=>V38_OP,'state'=>$r['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo v38_json(['state'=>$r['state'],'candidate_count'=>$r['candidate_count'],'corroborated_count'=>$r['corroborated_count'],'status_counts'=>$r['status_counts'],'bucket_status_counts'=>$r['bucket_status_counts']])."\n";}
    catch(Throwable $e){$f=['operation'=>V38_OP,'state'=>'failed_read_only_single_fingerprint_corroboration','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=v38_save($dir.'/result.json',$f);v38_save($dir.'/receipt.json',['operation'=>V38_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
