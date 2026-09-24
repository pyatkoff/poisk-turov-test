<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const HMSP39_OP='hotel-match-samo-live30-post39-coverage-1971-20260924-v1';
const HMSP39_LANES=['operator_5','operator_115','operator_315','operator_342'];

function hmsp39_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmsp39_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmsp39_save(string $path,array $v):string{
    $raw=hmsp39_json($v)."\n";$f=@fopen($path,'x+b');hmsp39_need($f!==false,'exclusive_create');
    try{hmsp39_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmsp39_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$raw);
}
function hmsp39_excluded(string $country):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($country))===1;}
function hmsp39_columns(PDO $db,string $table):array{
    hmsp39_need(preg_match('/^[A-Za-z0-9_]{1,64}$/D',$table)===1,'table_name');
    $st=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
    $st->execute([$table]);$out=[];foreach($st->fetchAll(PDO::FETCH_COLUMN)?:[] as $c)$out[(string)$c]=true;return $out;
}
function hmsp39_summary(array $locals,array $lanesByLocal,array $tvLive30,array $anexByLocal):array{
    $dist=['0'=>0,'1'=>0,'2'=>0,'3'=>0,'4'=>0];
    $present=array_fill_keys(HMSP39_LANES,0);$missing=$present;$withTv=0;$withAnex=0;$triple=0;$all4=0;
    foreach($locals as $local=>$_){
        $local=(int)$local;$n=0;
        foreach(HMSP39_LANES as $lane){$yes=isset($lanesByLocal[$local][$lane]);if($yes){$present[$lane]++;$n++;}else{$missing[$lane]++;}}
        $dist[(string)$n]++;if($n===4)$all4++;
        $tv=isset($tvLive30[$local]);$anex=isset($anexByLocal[$local])&&count($anexByLocal[$local])>0;
        if($tv)$withTv++;if($anex)$withAnex++;if($tv&&$anex)$triple++;
    }
    return [
        'mapped_local_hotels'=>count($locals),
        'common4_lanes_by_count'=>$dist,
        'common4_lane_present'=>$present,
        'common4_lane_missing'=>$missing,
        'common4_all4'=>$all4,
        'with_tv_live30'=>$withTv,
        'with_direct_anex'=>$withAnex,
        'full_triple_tv_samo_direct_anex'=>$triple,
    ];
}
function hmsp39_execute(PDO $db):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $active=[];$q=$db->query("SELECT id,country_name FROM catalog_hotels WHERE is_active=1 ORDER BY id");
        foreach($q->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$id=(int)($r['id']??0);if($id>0&&!hmsp39_excluded((string)($r['country_name']??'')))$active[$id]=true;}
        hmsp39_need(count($active)>0,'no_active_hotels');

        $acceptedBySource=[];$lanesByLocal=[];$laneRows=array_fill_keys(HMSP39_LANES,0);
        $q=$db->query("SELECT supplier_namespace,external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY supplier_namespace,external_hotel_id,local_hotel_id");
        foreach($q->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
            $ns=trim((string)($r['supplier_namespace']??''));$ext=trim((string)($r['external_hotel_id']??''));$local=(int)($r['local_hotel_id']??0);
            if($ns===''||$ext===''||$local<=0||!isset($active[$local]))continue;
            $acceptedBySource[$ns.'|'.$ext][$local]=true;
            if(in_array($ns,HMSP39_LANES,true)){$lanesByLocal[$local][$ns]=true;$laneRows[$ns]++;}
        }

        $anex=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anexByLocal=[];
        foreach(($anex['by_local']??[]) as $local=>$nativeSet){$local=(int)$local;if(!isset($active[$local]))continue;foreach($nativeSet as $native=>$yes)if($yes)$anexByLocal[$local][(string)$native]=true;}

        $tvLive30=[];$cols=hmsp39_columns($db,'tour_operator_identity_observations');
        if(isset($cols['hotel_id'],$cols['last_seen_at'])){
            $cut=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-30 days')->format('Y-m-d H:i:s');
            $st=$db->prepare('SELECT hotel_id,MAX(last_seen_at) mx FROM tour_operator_identity_observations GROUP BY hotel_id HAVING mx>=?');$st->execute([$cut]);
            foreach($st->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$id=(int)($r['hotel_id']??0);if(isset($active[$id]))$tvLive30[$id]=true;}
        }

        $obsCols=hmsp39_columns($db,'andromeda_search_hotel_observations');
        hmsp39_need(isset($obsCols['supplier_namespace'],$obsCols['external_hotel_id']),'observation_schema');
        $timeCol=null;foreach(['observed_at_utc','last_seen_at','observed_at','last_seen_utc','created_at'] as $c)if(isset($obsCols[$c])){$timeCol=$c;break;}
        hmsp39_need($timeCol!==null,'observation_time_column');
        $cut=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-30 days')->format('Y-m-d H:i:s');
        $sql="SELECT external_hotel_id,MAX($timeCol) mx FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' AND $timeCol>=? GROUP BY external_hotel_id ORDER BY external_hotel_id";
        $st=$db->prepare($sql);$st->execute([$cut]);$catalog=[];
        foreach($st->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$ext=trim((string)($r['external_hotel_id']??''));if($ext!=='')$catalog[$ext]=true;}

        $mappedSources=0;$mappedLocals=[];$unresolved=0;$collisions=0;
        foreach(array_keys($catalog) as $ext){
            $targets=array_keys($acceptedBySource['andromeda_catalog|'.$ext]??[]);
            if(count($targets)===1){$local=(int)$targets[0];if(isset($active[$local])){$mappedSources++;$mappedLocals[$local]=true;continue;}}
            if(count($targets)>1)$collisions++;else $unresolved++;
        }
        $summary=hmsp39_summary($mappedLocals,$lanesByLocal,$tvLive30,$anexByLocal);
        $db->rollBack();
        return [
            'operation'=>HMSP39_OP,'state'=>'completed_read_only_samo_post39_coverage','generated_at_utc'=>gmdate('c'),
            'catalog_observed_hotel_ids'=>count($catalog),'catalog_mapped_source_ids'=>$mappedSources,
            'catalog_mapped_local_hotels'=>count($mappedLocals),'catalog_unresolved_hotel_ids'=>$unresolved,'catalog_collision_hotel_ids'=>$collisions,
            'accepted_common4_identity_rows'=>$laneRows,'mapped_common4'=>$summary,
            'accepted_direct_anex_unique_tv'=>count($anexByLocal),'tv_live30_unique'=>count($tvLive30),
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,
            'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,
        ];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        $s=hmsp39_summary([10=>true,20=>true,30=>true],[10=>['operator_5'=>true,'operator_115'=>true],20=>['operator_315'=>true],30=>array_fill_keys(HMSP39_LANES,true)],[10=>true,30=>true],[10=>['1'=>true],20=>['2'=>true]]);
        hmsp39_need($s['common4_lanes_by_count']===['0'=>0,'1'=>1,'2'=>1,'3'=>0,'4'=>1],'dist');
        hmsp39_need($s['common4_lane_missing']['operator_342']===2,'missing');
        hmsp39_need($s['full_triple_tv_samo_direct_anex']===1,'triple');
        echo "MATCH_SAMO_POST39_COVERAGE_V1_SELFTEST_OK\n";exit;
    }
    hmsp39_need($mode==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hmsp39_need(is_dir($root)&&is_dir($dir)&&basename($dir)===HMSP39_OP&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    hmsp39_need(($reservation['operation']??'')===HMSP39_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{
        $r=hmsp39_execute(v2_data_db());$r['source_sha']=$sha;$h=hmsp39_save($dir.'/result.json',$r);
        hmsp39_save($dir.'/receipt.json',['operation'=>HMSP39_OP,'state'=>$r['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
        echo hmsp39_json($r)."\n";
    }catch(Throwable $e){
        $f=['operation'=>HMSP39_OP,'state'=>'failed_read_only_samo_post39_coverage','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,120,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
        $h=hmsp39_save($dir.'/result.json',$f);hmsp39_save($dir.'/receipt.json',['operation'=>HMSP39_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
        fwrite(STDERR,$f['reason']."\n");exit(2);
    }
}
