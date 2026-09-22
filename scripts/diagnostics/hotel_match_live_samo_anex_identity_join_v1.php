<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const HMSAJ_OP='hotel-match-live-samo-anex-identity-join-1971-20260922-v1';

function hmsaj_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmsaj_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmsaj_save(string $path,array $value):string{
    $raw=hmsaj_json($value)."\n";$f=@fopen($path,'x+b');hmsaj_need($f!==false,'exclusive_create');
    try{hmsaj_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmsaj_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$raw);
}
function hmsaj_norm(mixed $v):string{
    $s=mb_strtolower(trim((string)$v),'UTF-8');$s=str_replace('ё','е',$s);
    $s=strtr($s,['é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);
    $s=preg_replace('/[^\p{L}\p{N}]+/u',' ',$s)??$s;
    return trim(preg_replace('/\s+/u',' ',$s)??$s);
}
function hmsaj_name_key(mixed $v):string{
    $generic=['hotel'=>1,'hotels'=>1,'otel'=>1,'отель'=>1,'отели'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'the'=>1,'and'=>1,'by'=>1,'резорт'=>1,'ресорт'=>1,'спа'=>1];
    $parts=[];foreach(preg_split('/\s+/u',hmsaj_norm($v),-1,PREG_SPLIT_NO_EMPTY)?:[] as $x)if(!isset($generic[$x]))$parts[$x]=true;
    $keys=array_keys($parts);sort($keys,SORT_STRING);return implode(' ',$keys);
}
function hmsaj_country(mixed $v):?string{
    $n=hmsaj_norm($v);if($n==='')return null;
    $groups=[
        'egypt'=>['egypt','египет'],'thailand'=>['thailand','таиланд','тайланд'],'turkey'=>['turkey','turkiye','türkiye','турция'],
        'maldives'=>['maldives','мальдивы'],'uae'=>['united arab emirates','uae','оаэ','эмираты'],'cuba'=>['cuba','куба'],
        'sri_lanka'=>['sri lanka','sri-lanka','шри ланка'],'vietnam'=>['vietnam','viet nam','вьетнам'],
        'qatar'=>['qatar','катар'],'china'=>['china','китай'],'mauritius'=>['mauritius','маврикий'],'seychelles'=>['seychelles','сейшелы'],
        'morocco'=>['morocco','марокко'],'tunisia'=>['tunisia','тунис'],'tanzania'=>['tanzania','танзания'],'uzbekistan'=>['uzbekistan','узбекистан'],
        'philippines'=>['philippines','филиппины'],'india'=>['india','индия'],'indonesia'=>['indonesia','индонезия'],'greece'=>['greece','греция'],
        'cyprus'=>['cyprus','кипр'],'georgia'=>['georgia','грузия'],'armenia'=>['armenia','армения'],'spain'=>['spain','испания'],
        'italy'=>['italy','италия'],'france'=>['france','франция'],'austria'=>['austria','австрия'],'germany'=>['germany','германия'],
    ];
    foreach($groups as $k=>$vals)foreach($vals as $x)if($n===hmsaj_norm($x))return $k;
    return $n;
}
function hmsaj_excluded(string $country):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($country))===1;}
function hmsaj_num(mixed $v):?float{
    if($v===null||$v==='')return null;$x=(float)str_replace(',','.',(string)$v);
    return is_finite($x)&&$x!=0.0?$x:null;
}
function hmsaj_point(array $r):?array{
    foreach([['latitude','longitude'],['lat','lng'],['lat','lon']] as [$a,$b]){
        if(!array_key_exists($a,$r)||!array_key_exists($b,$r))continue;$lat=hmsaj_num($r[$a]);$lon=hmsaj_num($r[$b]);
        if($lat!==null&&$lon!==null&&abs($lat)<=90&&abs($lon)<=180)return[$lat,$lon];
    }return null;
}
function hmsaj_dist(?array $a,?array $b):?float{
    if($a===null||$b===null)return null;[$lat1,$lon1]=$a;[$lat2,$lon2]=$b;
    $p1=deg2rad($lat1);$p2=deg2rad($lat2);$dp=$p2-$p1;$dl=deg2rad($lon2-$lon1);
    $x=sin($dp/2)**2+cos($p1)*cos($p2)*sin($dl/2)**2;return 6371000*2*asin(min(1,sqrt($x)));
}
function hmsaj_place_keys(array $values):array{
    $out=[];foreach($values as $v){$n=hmsaj_norm($v);if($n==='')continue;$n=preg_replace('/\b(?:центр|center|centre|город|city|остров|island)\b/u',' ',$n)??$n;$n=trim(preg_replace('/\s+/u',' ',$n)??$n);if($n!=='')$out[$n]=true;}return $out;
}
function hmsaj_sources(mixed $node,array &$out,int $depth=0):void{
    if($depth>24||!is_array($node))return;
    foreach($node as $k=>$v){
        if($k==='source'&&is_array($v)){
            $name=trim((string)($v['name']??''));$lname=trim((string)($v['lName']??''));
            if($name!==''||$lname!=='')$out[]=array_intersect_key($v,array_flip(['id','name','lName','state','stateLName','town','townLName','region','latitude','longitude','lat','lon','lng','star','starKey']));
        }
        if(is_array($v))hmsaj_sources($v,$out,$depth+1);
    }
}
function hmsaj_execute(PDO $db):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
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
                if(($pt=hmsaj_point($s))!==null)$samoPoints[$tv][]= $pt;
            }
        }
        $anex=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anexByLocal=[];foreach(($anex['by_local']??[]) as $local=>$ids)$anexByLocal[(int)$local]=$ids;
        $live30=[];$cut=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-30 days');
        foreach($db->query("SELECT hotel_id,MAX(last_seen_at) last_seen_at FROM tour_operator_identity_observations GROUP BY hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $tv=(int)$r['hotel_id'];if(!isset($active[$tv]))continue;$raw=trim((string)$r['last_seen_at']);if($raw==='')continue;
            try{$dt=new DateTimeImmutable($raw,new DateTimeZone('UTC'));}catch(Throwable){continue;}if($dt>=$cut)$live30[$tv]=true;
        }
        $frontier=[];foreach($live30 as $tv=>$_)if(isset($anchors[$tv])&&!isset($anexByLocal[$tv]))$frontier[$tv]=true;
        hmsaj_need(count($frontier)===942,'frontier_changed');

        $manual=[];foreach($db->query("SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions")->fetchAll(PDO::FETCH_ASSOC) as $r)$manual[(string)$r['anex_hotel_id']]=$r;
        $mapping=[];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings")->fetchAll(PDO::FETCH_ASSOC) as $r)$mapping[(string)$r['anex_hotel_id']][]=$r;
        $ex=[];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions")->fetchAll(PDO::FETCH_ASSOC) as $r)$ex[(string)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;

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

        $rawCandidates=[];$anexRows=0;$protected=0;
        foreach($db->query("SELECT anex_hotel_id,source_fingerprint,xml_name,xml_alternate_name,api_name,api_country,api_region,api_town,latitude,longitude,checked_at FROM anex_hotels ORDER BY anex_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $s){
            $anexRows++;$aid=(string)$s['anex_hotel_id'];if(isset($manual[$aid])||isset($mapping[$aid])){$protected++;continue;}
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
                $rawCandidates[$aid][$tv]=[
                    'anex_hotel_id'=>(int)$aid,'tv_hotel_id'=>$tv,'name_via_tv'=>(bool)($channels[$tv]['tv']??false),'name_via_samo'=>(bool)($channels[$tv]['samo']??false),
                    'geo_class'=>$geoClass,'distance_m'=>$bestDist===null?null:round($bestDist,1),'place_match'=>$place,
                    'source_fingerprint'=>(string)$s['source_fingerprint'],'checked_at'=>$s['checked_at'],
                ];
            }
        }
        $targetSources=[];foreach($rawCandidates as $aid=>$targets)foreach($targets as $tv=>$row)$targetSources[$tv][$aid]=true;
        $rows=[];$counts=[];$geo=[];$candidateStrict=0;
        foreach(array_keys($frontier) as $tv){
            if(isset($invalidAnchor[$tv])){
                $status='invalid_samo_anchor_evidence';$counts[$status]=($counts[$status]??0)+1;$f=$facts[$tv];$g=implode('|',[$f['country_name'],$f['region_name'],$f['subregion_name']]);$geo[$status][$g]=($geo[$status][$g]??0)+1;
                $rows[]=['tv_hotel_id'=>$tv,'hotel_name'=>(string)$f['name'],'status'=>$status,'safe_to_write_now'=>false];continue;
            }
            $srcs=array_keys($targetSources[$tv]??[]);
            if(!$srcs){
                $status='no_strict_identity_candidate';$counts[$status]=($counts[$status]??0)+1;$f=$facts[$tv];$g=implode('|',[$f['country_name'],$f['region_name'],$f['subregion_name']]);$geo[$status][$g]=($geo[$status][$g]??0)+1;
                $rows[]=['tv_hotel_id'=>$tv,'hotel_name'=>(string)$f['name'],'country'=>(string)$f['country_name'],'region'=>(string)$f['region_name'],'subregion'=>(string)$f['subregion_name'],'samo_anchor_count'=>count($anchors[$tv]),'status'=>$status,'safe_to_write_now'=>false];continue;
            }
            foreach($srcs as $aid){
                $r=$rawCandidates[(string)$aid][$tv];$sourceUnique=count($rawCandidates[(string)$aid])===1;$targetUnique=count($targetSources[$tv])===1;
                $status=$sourceUnique&&$targetUnique?'candidate_strict_mutual_unique':(!$sourceUnique?'ambiguous_anex_source':'ambiguous_tv_target');
                if($status==='candidate_strict_mutual_unique')$candidateStrict++;
                $counts[$status]=($counts[$status]??0)+1;$f=$facts[$tv];$g=implode('|',[$f['country_name'],$f['region_name'],$f['subregion_name']]);$geo[$status][$g]=($geo[$status][$g]??0)+1;
                $rows[]=[
                    'tv_hotel_id'=>$tv,'hotel_name'=>(string)$f['name'],'country'=>(string)$f['country_name'],'region'=>(string)$f['region_name'],'subregion'=>(string)$f['subregion_name'],'category'=>(string)$f['category'],
                    'samo_anchor_count'=>count($anchors[$tv]),'anex_hotel_id'=>(int)$aid,'status'=>$status,
                    'name_via_tv'=>$r['name_via_tv'],'name_via_samo'=>$r['name_via_samo'],'geo_class'=>$r['geo_class'],'distance_m'=>$r['distance_m'],'place_match'=>$r['place_match'],
                    'source_fingerprint'=>$r['source_fingerprint'],'safe_to_write_now'=>false,
                ];
            }
        }
        foreach($geo as &$m){arsort($m);$m=array_slice($m,0,50,true);}unset($m);ksort($counts);
        usort($rows,static fn($a,$b)=>[$a['status'],$a['country']??'',$a['region']??'',$a['subregion']??'',$a['tv_hotel_id'],$a['anex_hotel_id']??0]<=>[$b['status'],$b['country']??'',$b['region']??'',$b['subregion']??'',$b['tv_hotel_id'],$b['anex_hotel_id']??0]);
        $db->rollBack();
        return [
            'operation'=>HMSAJ_OP,'state'=>'completed_read_only_identity_join','frontier_samo_present_anex_missing'=>count($frontier),
            'anex_staging_rows'=>$anexRows,'protected_anex_rows'=>$protected,'raw_anex_sources_with_candidates'=>count($rawCandidates),'strict_mutual_unique_count'=>$candidateStrict,
            'status_counts'=>$counts,'top_geography_by_status'=>$geo,'rows'=>$rows,
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,
        ];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        hmsaj_need(hmsaj_name_key('The Example Hotel & Spa')==='example','name_key');
        hmsaj_need(hmsaj_country('Турция')==='turkey'&&hmsaj_country('Turkey')==='turkey','country');
        hmsaj_need(hmsaj_dist([0.0,1.0],[0.0,1.0])===0.0,'distance');
        $x=[];hmsaj_sources(['prior'=>['source'=>['id'=>1,'name'=>'ABC','town'=>'Side']]],$x);hmsaj_need(count($x)===1&&$x[0]['name']==='ABC','sources');
        echo "MATCH_LIVE_SAMO_ANEX_IDENTITY_JOIN_V1_SELFTEST_OK\n";exit;
    }
    hmsaj_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hmsaj_need(is_dir($root)&&is_dir($dir)&&basename($dir)===HMSAJ_OP&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);hmsaj_need(($reservation['operation']??'')===HMSAJ_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{
        $result=hmsaj_execute(v2_data_db());$result['source_sha']=$sha;$h=hmsaj_save($dir.'/result.json',$result);
        hmsaj_save($dir.'/receipt.json',['operation'=>HMSAJ_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
        echo hmsaj_json(['state'=>$result['state'],'frontier'=>$result['frontier_samo_present_anex_missing'],'strict_mutual_unique_count'=>$result['strict_mutual_unique_count'],'status_counts'=>$result['status_counts']])."\n";
    }catch(Throwable $e){
        $f=['operation'=>HMSAJ_OP,'state'=>'failed_read_only_identity_join','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,120,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
        $h=hmsaj_save($dir.'/result.json',$f);hmsaj_save($dir.'/receipt.json',['operation'=>HMSAJ_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);
    }
}
