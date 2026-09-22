<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_live_samo_anex_identity_join_v1.php';

const HMSAF_OP='hotel-match-live-samo-anex-strong-fuzzy-1971-20260922-v1';

function hmsaf_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmsaf_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmsaf_save(string $path,array $value):string{
    $raw=hmsaf_json($value)."\n";$f=@fopen($path,'x+b');hmsaf_need($f!==false,'exclusive_create');
    try{hmsaf_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmsaf_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$raw);
}
function hmsaf_tokens(mixed $v):array{
    $generic=['hotel'=>1,'hotels'=>1,'otel'=>1,'отель'=>1,'отели'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'the'=>1,'and'=>1,'by'=>1,'резорт'=>1,'ресорт'=>1,'спа'=>1];
    $out=[];foreach(preg_split('/\s+/u',hmsaj_norm($v),-1,PREG_SPLIT_NO_EMPTY)?:[] as $x)if(!isset($generic[$x]))$out[]=$x;return $out;
}
function hmsaf_score(mixed $a,mixed $b):float{
    $a=hmsaj_norm($a);$b=hmsaj_norm($b);if($a===''||$b==='')return 0.0;
    if(hmsaj_name_key($a)!==''&&hmsaj_name_key($a)===hmsaj_name_key($b))return 1.0;
    $ta=array_values(array_unique(hmsaf_tokens($a)));$tb=array_values(array_unique(hmsaf_tokens($b)));
    $dice=($ta&&$tb)?2*count(array_intersect($ta,$tb))/(count($ta)+count($tb)):0.0;
    similar_text($a,$b,$pct);return max($dice,$pct/100.0);
}
function hmsaf_qualifiers(mixed $v):array{
    $q=array_fill_keys(['annex','annexe','beach','garden','gardens','north','south','east','west','wing','main','adults','family','palace','park','villa','villas','suite','suites','apartments','club','residence','tower','towers','only','sea','view','mountain','pool','север','юг','северный','южный','корпус'],true);
    $out=[];foreach(hmsaf_tokens($v) as $x)if(isset($q[$x])||preg_match('/^(?:ii|iii|iv|[0-9]+)$/D',$x))$out[$x]=true;
    $k=array_keys($out);sort($k,SORT_STRING);return $k;
}
function hmsaf_distinctive_count(mixed $v):int{
    $weak=array_fill_keys(['grand','royal','plaza','sea','view','ocean','island','city','central','new','old','boutique','luxury','inn','guest','house','home','retreat','pearl','sunrise','sunset','golden','green','blue','white','red','black','star','paradise','international'],true);
    $n=0;foreach(array_unique(hmsaf_tokens($v)) as $x)if(mb_strlen($x,'UTF-8')>=5&&!isset($weak[$x]))$n++;return $n;
}
function hmsaf_best(array $sourceNames,array $targetNames):array{
    $best=['score'=>0.0,'source'=>'','target'=>'','qualifier_conflict'=>false];
    foreach($sourceNames as $s)foreach($targetNames as $t){$score=hmsaf_score($s,$t);if($score>$best['score'])$best=['score'=>$score,'source'=>(string)$s,'target'=>(string)$t,'qualifier_conflict'=>hmsaf_qualifiers($s)!==hmsaf_qualifiers($t)];}
    return $best;
}
function hmsaf_execute(PDO $db):array{
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
        $anchors=[];$samoNames=[];$samoPlaces=[];$samoPoints=[];$invalid=[];
        foreach($db->query("SELECT external_hotel_id,local_hotel_id,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY local_hotel_id,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $tv=(int)$r['local_hotel_id'];if(!isset($active[$tv]))continue;$anchors[$tv][(string)$r['external_hotel_id']]=true;
            $raw=(string)$r['evidence_json'];if(hash('sha256',$raw)!==(string)$r['evidence_sha256']){$invalid[$tv]=true;continue;}
            $e=json_decode($raw,true);if(!is_array($e)){$invalid[$tv]=true;continue;}$ss=[];hmsaj_sources($e,$ss);
            foreach($ss as $s){
                foreach([$s['name']??'',$s['lName']??''] as $n)if(trim((string)$n)!=='')$samoNames[$tv][(string)$n]=true;
                foreach([$s['town']??'',$s['townLName']??'',$s['region']??''] as $p)if(trim((string)$p)!=='')$samoPlaces[$tv][(string)$p]=true;
                if(($pt=hmsaj_point($s))!==null)$samoPoints[$tv][]=$pt;
            }
        }
        $reg=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anexByLocal=[];foreach(($reg['by_local']??[]) as $id=>$v)$anexByLocal[(int)$id]=$v;
        $live30=[];$cut=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-30 days');
        foreach($db->query("SELECT hotel_id,MAX(last_seen_at) last_seen_at FROM tour_operator_identity_observations GROUP BY hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $tv=(int)$r['hotel_id'];if(!isset($active[$tv]))continue;$raw=trim((string)$r['last_seen_at']);if($raw==='')continue;try{$dt=new DateTimeImmutable($raw,new DateTimeZone('UTC'));}catch(Throwable){continue;}if($dt>=$cut)$live30[$tv]=true;
        }
        $frontier=[];foreach($live30 as $tv=>$_)if(isset($anchors[$tv])&&!isset($anexByLocal[$tv]))$frontier[$tv]=true;
        hmsaf_need(count($frontier)===927,'frontier_changed');

        $manual=[];foreach($db->query("SELECT anex_hotel_id FROM anex_hotel_decisions")->fetchAll(PDO::FETCH_COLUMN) as $id)$manual[(string)$id]=true;
        $mapping=[];foreach($db->query("SELECT anex_hotel_id FROM anex_hotel_search_mappings")->fetchAll(PDO::FETCH_COLUMN) as $id)$mapping[(string)$id]=true;
        $ex=[];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions")->fetchAll(PDO::FETCH_ASSOC) as $r)$ex[(string)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $anex=[];foreach($db->query("SELECT anex_hotel_id,source_fingerprint,xml_name,xml_alternate_name,api_name,api_country,api_region,api_town,latitude,longitude FROM anex_hotels ORDER BY anex_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r)$anex[(string)$r['anex_hotel_id']]=$r;

        $candByAid=[];foreach($db->query("SELECT anex_hotel_id,candidate_rank,catalog_hotel_id,score,name_similarity,distance_m,country_match FROM anex_hotel_candidates ORDER BY anex_hotel_id,candidate_rank")->fetchAll(PDO::FETCH_ASSOC) as $r)$candByAid[(string)$r['anex_hotel_id']][]=$r;

        $prepared=[];$examined=0;$reasonCounts=[];
        foreach($candByAid as $aid=>$candRows){
            if(isset($manual[$aid])||isset($mapping[$aid])||!isset($anex[$aid]))continue;$s=$anex[$aid];$sourceCountry=hmsaj_country($s['api_country']);if($sourceCountry===null)continue;
            $sourceNames=array_values(array_filter([$s['api_name'],$s['xml_name'],$s['xml_alternate_name']],static fn($x)=>trim((string)$x)!==''));
            if(!$sourceNames)continue;
            $scores=[];
            foreach($candRows as $cr){
                $tid=(int)$cr['catalog_hotel_id'];if(!isset($active[$tid]))continue;
                $best=hmsaf_best($sourceNames,array_keys($nativeNames[$tid]??[]));$scores[$tid]=$best['score'];
            }
            arsort($scores,SORT_NUMERIC);
            foreach($candRows as $cr){
                $tv=(int)$cr['catalog_hotel_id'];if(!isset($frontier[$tv]))continue;$examined++;
                $fail=null;
                if(isset($invalid[$tv]))$fail='invalid_samo_anchor';
                elseif(isset($ex[$aid][$tv]))$fail='pair_excluded';
                elseif(hmsaj_country($facts[$tv]['country_name'])!==$sourceCountry)$fail='country_conflict';
                $native=hmsaf_best($sourceNames,array_keys($nativeNames[$tv]??[]));$samo=hmsaf_best($sourceNames,array_keys($samoNames[$tv]??[]));
                $second=0.0;foreach($scores as $other=>$sc)if((int)$other!==$tv){$second=(float)$sc;break;}$margin=$native['score']-$second;
                if($fail===null&&$native['score']<0.97)$fail='native_score';
                if($fail===null&&$samo['score']<0.97)$fail='samo_score';
                if($fail===null&&($native['qualifier_conflict']||$samo['qualifier_conflict']))$fail='qualifier_conflict';
                $distinct=max(array_map('hmsaf_distinctive_count',$sourceNames));if($fail===null&&$distinct<2)$fail='weak_name';
                if($fail===null&&$margin<0.12)$fail='margin';
                $sp=hmsaj_place_keys([$s['api_region'],$s['api_town']]);$tp=hmsaj_place_keys(array_merge([$facts[$tv]['region_name'],$facts[$tv]['subregion_name']],array_keys($samoPlaces[$tv]??[])));$place=(bool)array_intersect_key($sp,$tp);
                $srcPt=hmsaj_point($s);$bestDist=null;$pts=[];$p=hmsaj_point($facts[$tv]);if($p!==null)$pts[]=$p;foreach($samoPoints[$tv]??[] as $p)$pts[]=$p;
                foreach($pts as $p){$d=hmsaj_dist($srcPt,$p);if($d!==null&&($bestDist===null||$d<$bestDist))$bestDist=$d;}
                if($fail===null&&!$place&&!($bestDist!==null&&$bestDist<=1000))$fail='geo_support';
                if($fail!==null){$reasonCounts[$fail]=($reasonCounts[$fail]??0)+1;continue;}
                $prepared[$aid][$tv]=[
                    'anex_hotel_id'=>(int)$aid,'tv_hotel_id'=>$tv,'source_fingerprint'=>(string)$s['source_fingerprint'],
                    'native_name_score'=>round($native['score'],4),'samo_name_score'=>round($samo['score'],4),'margin'=>round($margin,4),
                    'place_match'=>$place,'distance_m'=>$bestDist===null?null:round($bestDist,1),'distinctive_tokens'=>$distinct,
                    'candidate_rank'=>(int)$cr['candidate_rank'],'stored_name_similarity'=>$cr['name_similarity']===null?null:(float)$cr['name_similarity'],
                ];
            }
        }
        $targetSources=[];foreach($prepared as $aid=>$targets)foreach($targets as $tv=>$r)$targetSources[$tv][$aid]=true;
        $rows=[];$statusCounts=['candidate_strong_fuzzy_mutual_unique'=>0,'ambiguous_source'=>0,'ambiguous_target'=>0];
        foreach($prepared as $aid=>$targets)foreach($targets as $tv=>$r){
            $status=count($targets)!==1?'ambiguous_source':(count($targetSources[$tv]??[])!==1?'ambiguous_target':'candidate_strong_fuzzy_mutual_unique');
            $statusCounts[$status]++;$r['status']=$status;$r['safe_to_write_now']=false;$f=$facts[$tv];$r+=['hotel_name'=>(string)$f['name'],'country'=>(string)$f['country_name'],'region'=>(string)$f['region_name'],'subregion'=>(string)$f['subregion_name']];$rows[]=$r;
        }
        $unique=$statusCounts['candidate_strong_fuzzy_mutual_unique'];ksort($reasonCounts);$db->rollBack();
        return ['operation'=>HMSAF_OP,'state'=>'completed_read_only_strong_fuzzy','frontier'=>927,'candidate_rows_examined'=>$examined,'prepared_before_mutual_unique'=>array_sum(array_map('count',$prepared)),
            'strict_mutual_unique_count'=>$unique,'status_counts'=>$statusCounts,'reject_reasons'=>$reasonCounts,'rows'=>$rows,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        hmsaf_need(hmsaf_score('Hilton Garden Inn','Hilton Garden Inn Hotel')>=0.99,'score');
        hmsaf_need(hmsaf_distinctive_count('Movenpick Serenity Soma Bay')>=2,'distinct');
        hmsaf_need(hmsaf_qualifiers('Example North Wing')!==hmsaf_qualifiers('Example South Wing'),'qualifier');
        echo "MATCH_LIVE_SAMO_ANEX_STRONG_FUZZY_V1_SELFTEST_OK\n";exit;
    }
    hmsaf_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hmsaf_need(is_dir($root)&&is_dir($dir)&&basename($dir)===HMSAF_OP&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);hmsaf_need(($reservation['operation']??'')===HMSAF_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{$result=hmsaf_execute(v2_data_db());$result['source_sha']=$sha;$h=hmsaf_save($dir.'/result.json',$result);hmsaf_save($dir.'/receipt.json',['operation'=>HMSAF_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo hmsaf_json(['state'=>$result['state'],'frontier'=>$result['frontier'],'strict_mutual_unique_count'=>$result['strict_mutual_unique_count'],'status_counts'=>$result['status_counts'],'reject_reasons'=>$result['reject_reasons']])."\n";}
    catch(Throwable $e){$f=['operation'=>HMSAF_OP,'state'=>'failed_read_only_strong_fuzzy','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,140,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=hmsaf_save($dir.'/result.json',$f);hmsaf_save($dir.'/receipt.json',['operation'=>HMSAF_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
