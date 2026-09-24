<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const HMLUC_OP='hotel-match-live30-unified-census-1971-20260924-v1';

function hmluc_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmluc_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmluc_save(string $path,array $v):string{
    $raw=hmluc_json($v)."\n";$f=@fopen($path,'x+b');hmluc_need($f!==false,'exclusive_create');
    try{hmluc_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmluc_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$raw);
}
function hmluc_excluded(string $country):bool{
    return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($country))===1;
}
function hmluc_columns(PDO $db,string $table):array{
    hmluc_need(preg_match('/^[A-Za-z0-9_]{1,64}$/D',$table)===1,'table_name');
    $st=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
    $st->execute([$table]);$out=[];
    foreach($st->fetchAll(PDO::FETCH_COLUMN)?:[] as $c)$out[(string)$c]=true;
    return $out;
}
function hmluc_time_column(array $cols):?string{
    foreach(['observed_at_utc','last_seen_at','observed_at','last_seen_utc','updated_at','created_at'] as $c)if(isset($cols[$c]))return $c;
    return null;
}
function hmluc_tv_summary(array $tvLocals,array $samoByLocal,array $anexByLocal):array{
    $out=['source_live30_total'=>count($tvLocals),'mapped_source_ids'=>count($tvLocals),'mapped_local_hotels'=>count($tvLocals),
        'triple'=>0,'double_samo'=>0,'double_anex'=>0,'single'=>0,'unresolved_source_ids'=>0,'collision_source_ids'=>0];
    foreach($tvLocals as $local=>$_){
        $s=isset($samoByLocal[$local])&&count($samoByLocal[$local])>0;
        $a=isset($anexByLocal[$local])&&count($anexByLocal[$local])>0;
        if($s&&$a)$out['triple']++;
        elseif($s)$out['double_samo']++;
        elseif($a)$out['double_anex']++;
        else $out['single']++;
    }
    return $out;
}
function hmluc_samo_summary(array $sourceIds,array $acceptedBySource,array $active,array $tvLive30,array $anexByLocal):array{
    $mappedSources=0;$unresolved=0;$collisions=0;$locals=[];
    foreach(array_keys($sourceIds) as $ext){
        $targets=[];
        foreach(array_keys($acceptedBySource['andromeda_catalog|'.$ext]??[]) as $local){$local=(int)$local;if(isset($active[$local]))$targets[$local]=true;}
        if(count($targets)===1){$mappedSources++;$locals[(int)array_key_first($targets)]=true;}
        elseif(count($targets)>1)$collisions++;
        else $unresolved++;
    }
    $out=['available'=>true,'source_live30_total'=>count($sourceIds),'mapped_source_ids'=>$mappedSources,'mapped_local_hotels'=>count($locals),
        'triple'=>0,'double_tv'=>0,'double_anex'=>0,'single'=>0,'unresolved_source_ids'=>$unresolved,'collision_source_ids'=>$collisions];
    foreach($locals as $local=>$_){
        $tv=isset($tvLive30[$local]);$a=isset($anexByLocal[$local])&&count($anexByLocal[$local])>0;
        if($tv&&$a)$out['triple']++;
        elseif($tv)$out['double_tv']++;
        elseif($a)$out['double_anex']++;
        else $out['single']++;
    }
    $out['_locals']=$locals;
    return $out;
}
function hmluc_anex_summary(array $sourceIds,array $anexByNative,array $active,array $tvLive30,array $samoByLocal):array{
    $mappedSources=0;$unresolved=0;$locals=[];
    foreach(array_keys($sourceIds) as $native){
        $key=(int)$native;$local=(int)($anexByNative[$key]??0);
        if($local>0&&isset($active[$local])){$mappedSources++;$locals[$local]=true;}else $unresolved++;
    }
    $out=['available'=>true,'source_live30_total'=>count($sourceIds),'mapped_source_ids'=>$mappedSources,'mapped_local_hotels'=>count($locals),
        'triple'=>0,'double_tv'=>0,'double_samo'=>0,'single'=>0,'unresolved_source_ids'=>$unresolved,'collision_source_ids'=>0];
    foreach($locals as $local=>$_){
        $tv=isset($tvLive30[$local]);$s=isset($samoByLocal[$local])&&count($samoByLocal[$local])>0;
        if($tv&&$s)$out['triple']++;
        elseif($tv)$out['double_tv']++;
        elseif($s)$out['double_samo']++;
        else $out['single']++;
    }
    $out['_locals']=$locals;
    return $out;
}
function hmluc_overlap(array $tv,array $samo,array $anex):array{
    $union=$tv+$samo+$anex;$b=['tv_only'=>0,'samo_only'=>0,'anex_only'=>0,'tv_samo'=>0,'tv_anex'=>0,'samo_anex'=>0,'all_three_live30'=>0];
    foreach($union as $local=>$_){
        $t=isset($tv[$local]);$s=isset($samo[$local]);$a=isset($anex[$local]);
        if($t&&$s&&$a)$b['all_three_live30']++;
        elseif($t&&$s)$b['tv_samo']++;
        elseif($t&&$a)$b['tv_anex']++;
        elseif($s&&$a)$b['samo_anex']++;
        elseif($t)$b['tv_only']++;
        elseif($s)$b['samo_only']++;
        elseif($a)$b['anex_only']++;
    }
    return ['union_mapped_local_hotels'=>count($union),'buckets'=>$b];
}
function hmluc_execute(PDO $db):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $generated=gmdate('c');$cut=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-30 days')->format('Y-m-d H:i:s');
        $active=[];$q=$db->query("SELECT id,country_name FROM catalog_hotels WHERE is_active=1 ORDER BY id");
        foreach($q->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$id=(int)($r['id']??0);if($id>0&&!hmluc_excluded((string)($r['country_name']??'')))$active[$id]=true;}
        hmluc_need(count($active)>0,'no_active_hotels');

        $acceptedBySource=[];$samoByLocal=[];
        $q=$db->query("SELECT supplier_namespace,external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY supplier_namespace,external_hotel_id,local_hotel_id");
        foreach($q->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
            $ns=trim((string)($r['supplier_namespace']??''));$ext=trim((string)($r['external_hotel_id']??''));$local=(int)($r['local_hotel_id']??0);
            if($ns===''||$ext===''||$local<=0||!isset($active[$local]))continue;
            $acceptedBySource[$ns.'|'.$ext][$local]=true;
            if($ns==='andromeda_catalog')$samoByLocal[$local][$ext]=true;
        }

        $anex=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anexByLocal=[];$anexByNative=[];
        foreach(($anex['by_local']??[]) as $local=>$nativeSet){
            $local=(int)$local;if(!isset($active[$local]))continue;
            foreach($nativeSet as $native=>$yes)if($yes)$anexByLocal[$local][(string)$native]=true;
        }
        foreach(($anex['by_native']??[]) as $native=>$local){$local=(int)$local;if(isset($active[$local]))$anexByNative[(int)$native]=$local;}

        $tvCols=hmluc_columns($db,'tour_operator_identity_observations');
        hmluc_need(isset($tvCols['hotel_id']),'tv_observation_schema');$tvTime=hmluc_time_column($tvCols);hmluc_need($tvTime!==null,'tv_observation_time_column');
        $tvLive30=[];$sql="SELECT hotel_id,MAX($tvTime) mx FROM tour_operator_identity_observations WHERE $tvTime>=? GROUP BY hotel_id";
        $st=$db->prepare($sql);$st->execute([$cut]);
        foreach($st->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$id=(int)($r['hotel_id']??0);if(isset($active[$id]))$tvLive30[$id]=true;}

        $samoCols=hmluc_columns($db,'andromeda_search_hotel_observations');
        hmluc_need(isset($samoCols['supplier_namespace'],$samoCols['external_hotel_id']),'samo_observation_schema');
        $samoTime=hmluc_time_column($samoCols);hmluc_need($samoTime!==null,'samo_observation_time_column');
        $samoIds=[];$sql="SELECT external_hotel_id,MAX($samoTime) mx FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' AND $samoTime>=? GROUP BY external_hotel_id";
        $st=$db->prepare($sql);$st->execute([$cut]);
        foreach($st->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$ext=trim((string)($r['external_hotel_id']??''));if($ext!=='')$samoIds[$ext]=true;}
        $samo=hmluc_samo_summary($samoIds,$acceptedBySource,$active,$tvLive30,$anexByLocal);$samoLocals=$samo['_locals'];unset($samo['_locals']);
        $samo['observation_time_column']=$samoTime;

        $anexObs=['available'=>false,'reason'=>null,'observation_time_column'=>null,'source_live30_total'=>null,'mapped_source_ids'=>null,'mapped_local_hotels'=>null,
            'triple'=>null,'double_tv'=>null,'double_samo'=>null,'single'=>null,'unresolved_source_ids'=>null,'collision_source_ids'=>null];
        $anexLiveLocals=[];
        $anexCols=hmluc_columns($db,'anex_search_hotel_observations');
        if($anexCols===[])$anexObs['reason']='observation_table_missing';
        elseif(!isset($anexCols['anex_hotel_id']))$anexObs['reason']='anex_hotel_id_column_missing';
        else{
            $anexTime=hmluc_time_column($anexCols);
            if($anexTime===null)$anexObs['reason']='observation_time_column_missing';
            else{
                $ids=[];$sql="SELECT anex_hotel_id,MAX($anexTime) mx FROM anex_search_hotel_observations WHERE $anexTime>=? GROUP BY anex_hotel_id";
                $st=$db->prepare($sql);$st->execute([$cut]);
                foreach($st->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
                    $native=trim((string)($r['anex_hotel_id']??''));
                    if(preg_match('/^[1-9][0-9]{0,7}$/D',$native)===1)$ids[$native]=true;
                }
                $anexObs=hmluc_anex_summary($ids,$anexByNative,$active,$tvLive30,$samoByLocal);$anexLiveLocals=$anexObs['_locals'];unset($anexObs['_locals']);
                $anexObs['observation_time_column']=$anexTime;
            }
        }

        $tv=hmluc_tv_summary($tvLive30,$samoByLocal,$anexByLocal);
        $overlap=$anexObs['available']?hmluc_overlap($tvLive30,$samoLocals,$anexLiveLocals):null;
        $db->rollBack();

        return [
            'operation'=>HMLUC_OP,'state'=>'completed_read_only_live30_unified_census','generated_at_utc'=>$generated,'cutoff_utc'=>$cut,
            'definitions'=>[
                'mapped'=>'source live30 identity resolves through the current canonical/effective registry to an active non-excluded local hotel',
                'triple'=>'within each source-live30 cohort, its mapped local is also live30 in the TV cohort where applicable and has the third canonical source mapping as defined by that cohort',
                'unresolved'=>'observed source identity has no current exact/effective local resolution; no name/geo/price inference',
            ],
            'tv_live30'=>$tv,
            'samo_live30'=>$samo,
            'direct_anex_live30'=>$anexObs,
            'live30_local_overlap'=>$overlap,
            'current_registry_totals'=>[
                'accepted_samo_unique_local_hotels'=>count($samoByLocal),
                'accepted_direct_anex_unique_local_hotels'=>count($anexByLocal),
                'accepted_direct_anex_native_ids'=>count($anexByNative),
            ],
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,
            'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,
        ];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        $tv=hmluc_tv_summary([1=>true,2=>true,3=>true,4=>true],[1=>['s'=>true],2=>['s'=>true]],[1=>['a'=>true],3=>['a'=>true]]);
        hmluc_need($tv['triple']===1&&$tv['double_samo']===1&&$tv['double_anex']===1&&$tv['single']===1,'tv_fixture');
        $s=hmluc_samo_summary(['x'=>true,'y'=>true,'z'=>true],['andromeda_catalog|x'=>[1=>true],'andromeda_catalog|y'=>[2=>true]],[1=>true,2=>true],[1=>true],[1=>['a'=>true],2=>['b'=>true]]);
        hmluc_need($s['mapped_source_ids']===2&&$s['mapped_local_hotels']===2&&$s['unresolved_source_ids']===1&&$s['triple']===1&&$s['double_anex']===1,'samo_fixture');
        $a=hmluc_anex_summary(['10'=>true,'20'=>true,'30'=>true],[10=>1,20=>2],[1=>true,2=>true],[1=>true],[1=>['s'=>true],2=>['s'=>true]]);
        hmluc_need($a['mapped_source_ids']===2&&$a['unresolved_source_ids']===1&&$a['triple']===1&&$a['double_samo']===1,'anex_fixture');
        $o=hmluc_overlap([1=>true,2=>true],[2=>true,3=>true],[2=>true,4=>true]);
        hmluc_need($o['union_mapped_local_hotels']===4&&$o['buckets']['all_three_live30']===1&&$o['buckets']['tv_only']===1&&$o['buckets']['samo_only']===1&&$o['buckets']['anex_only']===1,'overlap_fixture');
        echo "MATCH_LIVE30_UNIFIED_CENSUS_V1_SELFTEST_OK\n";exit;
    }
    hmluc_need($mode==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hmluc_need(is_dir($root)&&is_dir($dir)&&basename($dir)===HMLUC_OP&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    hmluc_need(($reservation['operation']??'')===HMLUC_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{
        $r=hmluc_execute(v2_data_db());$r['source_sha']=$sha;$h=hmluc_save($dir.'/result.json',$r);
        hmluc_save($dir.'/receipt.json',['operation'=>HMLUC_OP,'state'=>$r['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
        echo hmluc_json($r)."\n";
    }catch(Throwable $e){
        $f=['operation'=>HMLUC_OP,'state'=>'failed_read_only_live30_unified_census','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,120,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
        $h=hmluc_save($dir.'/result.json',$f);hmluc_save($dir.'/receipt.json',['operation'=>HMLUC_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
        fwrite(STDERR,$f['reason']."\n");exit(2);
    }
}
