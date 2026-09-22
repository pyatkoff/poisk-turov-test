<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const HMTSAC_OP='hotel-match-tv-samo-anex-coverage-1971-20260922-v1';

function hmtsac_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmtsac_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmtsac_save(string $path,array $value):string{
    $raw=hmtsac_json($value)."\n";$f=@fopen($path,'x+b');hmtsac_need($f!==false,'exclusive_create');
    try{hmtsac_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmtsac_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$raw);
}
function hmtsac_excluded(string $country):bool{
    return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($country))===1;
}
function hmtsac_bucket(array $tvIds,array $samoByLocal,array $anexByLocal,array $facts):array{
    $counts=['tv_total'=>0,'has_samo'=>0,'has_anex'=>0,'full_triple'=>0,'samo_only'=>0,'anex_only'=>0,'neither'=>0];
    $missing=['missing_samo'=>[],'missing_anex'=>[],'missing_both'=>[]];
    $geo=[];
    foreach($tvIds as $id){
        $id=(int)$id;if(!isset($facts[$id]))continue;$counts['tv_total']++;
        $s=isset($samoByLocal[$id])&&count($samoByLocal[$id])>0;
        $a=isset($anexByLocal[$id])&&count($anexByLocal[$id])>0;
        if($s)$counts['has_samo']++;if($a)$counts['has_anex']++;
        if($s&&$a)$counts['full_triple']++;
        elseif($s)$counts['samo_only']++;
        elseif($a)$counts['anex_only']++;
        else $counts['neither']++;
        $f=$facts[$id];
        $g=implode('|',[(string)($f['country_name']??''),(string)($f['region_name']??''),(string)($f['subregion_name']??'')]);
        if(!$s)$geo['missing_samo'][$g]=($geo['missing_samo'][$g]??0)+1;
        if(!$a)$geo['missing_anex'][$g]=($geo['missing_anex'][$g]??0)+1;
        if(!$s&&!$a)$geo['missing_both'][$g]=($geo['missing_both'][$g]??0)+1;
        $row=[
            'tv_hotel_id'=>$id,
            'name'=>(string)($f['name']??''),
            'country'=>(string)($f['country_name']??''),
            'region'=>(string)($f['region_name']??''),
            'subregion'=>(string)($f['subregion_name']??''),
            'category'=>(string)($f['category']??''),
            'samo_anchor_count'=>count($samoByLocal[$id]??[]),
            'anex_native_count'=>count($anexByLocal[$id]??[]),
        ];
        if(!$s)$missing['missing_samo'][]=$row;
        if(!$a)$missing['missing_anex'][]=$row;
        if(!$s&&!$a)$missing['missing_both'][]=$row;
    }
    foreach($geo as &$m){arsort($m);$m=array_slice($m,0,50,true);}unset($m);
    foreach($missing as &$rows){
        usort($rows,static function(array $x,array $y):int{
            $gx=[$x['country'],$x['region'],$x['subregion'],$x['tv_hotel_id']];
            $gy=[$y['country'],$y['region'],$y['subregion'],$y['tv_hotel_id']];
            return $gx<=>$gy;
        });
    }unset($rows);
    return ['counts'=>$counts,'top_missing_geographies'=>$geo,'missing'=>$missing];
}
function hmtsac_execute(PDO $db):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try{
        $facts=[];$active=[];
        $q=$db->query("SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE is_active=1 ORDER BY id");
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
            if(hmtsac_excluded((string)($r['country_name']??'')))continue;
            $id=(int)$r['id'];if($id<=0)continue;$facts[$id]=$r;$active[$id]=true;
        }
        hmtsac_need(count($active)>0,'no_active_tv_hotels');

        $samoByLocal=[];$samoEdges=0;
        $q=$db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' ORDER BY local_hotel_id,external_hotel_id");
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
            $local=(int)($r['local_hotel_id']??0);$ext=trim((string)($r['external_hotel_id']??''));
            if($local<=0||$ext===''||!isset($active[$local]))continue;
            $samoByLocal[$local][$ext]=true;$samoEdges++;
        }

        $anex=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);
        $anexByLocal=[];
        foreach(($anex['by_local']??[]) as $local=>$nativeSet){
            $local=(int)$local;if(!isset($active[$local]))continue;
            foreach($nativeSet as $native=>$flag)if($flag)$anexByLocal[$local][(string)$native]=true;
        }

        $liveAll=[];$live30=[];$live90=[];$obsTable=false;
        $exists=$db->query("SHOW TABLES LIKE 'tour_operator_identity_observations'");
        if($exists&&$exists->fetchColumn()!==false){
            $obsTable=true;
            $q=$db->query("SELECT hotel_id,MAX(last_seen_at) last_seen_at FROM tour_operator_identity_observations GROUP BY hotel_id");
            $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$cut30=$now->modify('-30 days');$cut90=$now->modify('-90 days');
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
                $id=(int)($r['hotel_id']??0);if(!isset($active[$id]))continue;$liveAll[$id]=true;
                $raw=trim((string)($r['last_seen_at']??''));if($raw==='')continue;
                try{$dt=new DateTimeImmutable($raw,new DateTimeZone('UTC'));}catch(Throwable){continue;}
                if($dt>=$cut90)$live90[$id]=true;if($dt>=$cut30)$live30[$id]=true;
            }
        }

        $activeBucket=hmtsac_bucket(array_keys($active),$samoByLocal,$anexByLocal,$facts);
        $liveAllBucket=hmtsac_bucket(array_keys($liveAll),$samoByLocal,$anexByLocal,$facts);
        $live90Bucket=hmtsac_bucket(array_keys($live90),$samoByLocal,$anexByLocal,$facts);
        $live30Bucket=hmtsac_bucket(array_keys($live30),$samoByLocal,$anexByLocal,$facts);
        $db->rollBack();

        return [
            'operation'=>HMTSAC_OP,
            'state'=>'completed_read_only_coverage',
            'generated_at_utc'=>gmdate('c'),
            'definitions'=>[
                'tv'=>'catalog_hotels active and not Russia/Abkhazia',
                'samo'=>'accepted andromeda_hotel_identities with supplier_namespace=andromeda_catalog pointing to TV/local hotel',
                'anex'=>'current owner-approved ANEX preview registry after manual decisions and pair exclusions',
                'full_triple'=>'same TV/local hotel has at least one accepted SAMO/Andromeda anchor and at least one accepted direct ANEX native identity',
                'live'=>'TV hotel observed in tour_operator_identity_observations',
            ],
            'edge_counts'=>[
                'accepted_samo_rows_scanned'=>$samoEdges,
                'accepted_samo_unique_tv'=>count($samoByLocal),
                'accepted_anex_native'=>(int)($anex['native_count']??0),
                'accepted_anex_unique_tv'=>count($anexByLocal),
            ],
            'active_tv'=>$activeBucket,
            'live_observation_table_present'=>$obsTable,
            'live_all'=>$liveAllBucket,
            'live_90d'=>$live90Bucket,
            'live_30d'=>$live30Bucket,
            'provider_http_calls'=>0,
            'tourvisor_calls'=>0,
            'samo_calls'=>0,
            'anex_calls'=>0,
            'database_writes'=>0,
            'mapping_writes'=>0,
        ];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        $facts=[1=>['name'=>'A','country_name'=>'Turkey','region_name'=>'R','subregion_name'=>'S','category'=>'5'],2=>['name'=>'B','country_name'=>'Turkey','region_name'=>'R','subregion_name'=>'S','category'=>'4'],3=>['name'=>'C','country_name'=>'Egypt','region_name'=>'H','subregion_name'=>'','category'=>'5'],4=>['name'=>'D','country_name'=>'UAE','region_name'=>'D','subregion_name'=>'','category'=>'5']];
        $x=hmtsac_bucket([1,2,3,4],[1=>['s1'=>true],2=>['s2'=>true]],[1=>['a1'=>true],3=>['a3'=>true]],$facts);
        hmtsac_need($x['counts']===['tv_total'=>4,'has_samo'=>2,'has_anex'=>2,'full_triple'=>1,'samo_only'=>1,'anex_only'=>1,'neither'=>1],'bucket_fixture');
        echo "MATCH_TV_SAMO_ANEX_COVERAGE_V1_SELFTEST_OK\n";exit;
    }
    hmtsac_need($mode==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hmtsac_need(is_dir($root)&&is_dir($dir)&&basename($dir)===HMTSAC_OP&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    hmtsac_need(($reservation['operation']??'')===HMTSAC_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{
        $result=hmtsac_execute(v2_data_db());$result['source_sha']=$sha;
        $h=hmtsac_save($dir.'/result.json',$result);
        hmtsac_save($dir.'/receipt.json',['operation'=>HMTSAC_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
        echo hmtsac_json(['state'=>$result['state'],'active'=>$result['active_tv']['counts'],'live30'=>$result['live_30d']['counts']])."\n";
    }catch(Throwable $e){
        $f=['operation'=>HMTSAC_OP,'state'=>'failed_read_only_coverage','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,120,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
        $h=hmtsac_save($dir.'/result.json',$f);hmtsac_save($dir.'/receipt.json',['operation'=>HMTSAC_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
        fwrite(STDERR,$f['reason']."\n");exit(2);
    }
}
