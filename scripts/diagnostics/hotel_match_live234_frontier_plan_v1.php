<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

function hm234_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hm234_excluded(string $country):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($country))===1;}
function hm234_context(PDO $db,array $ids,bool $future):array{
    $out=[];
    foreach(array_chunk(array_values($ids),250) as $chunk){
        $ph=implode(',',array_fill(0,count($chunk),'?'));
        $futureSql=$future?' AND departure_date>=CURDATE()':'';
        $sql="SELECT hotel_id,departure_id,country_id,departure_date,nights,adults,children_count,child_ages_signature,observed_at,source
                FROM tour_price_observations
               WHERE hotel_id IN ($ph)$futureSql
               ORDER BY hotel_id,(source='user_search') DESC,observed_at DESC,departure_date ASC";
        $st=$db->prepare($sql);$st->execute($chunk);
        foreach($st->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
            $id=(int)$r['hotel_id'];if(!isset($out[$id]))$out[$id]=$r;
        }
    }
    return $out;
}
function hm234_plan(PDO $db):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try{
        $facts=[];$active=[];
        foreach($db->query("SELECT id,name,country_id,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE is_active=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
            if(hm234_excluded((string)$r['country_name']))continue;
            $id=(int)$r['id'];if($id<1)continue;$facts[$id]=$r;$active[$id]=true;
        }
        $samo=[];
        foreach($db->query("SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN)?:[] as $id){
            $id=(int)$id;if(isset($active[$id]))$samo[$id]=true;
        }
        $anex=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anexBy=[];
        foreach(($anex['by_local']??[]) as $id=>$set)if(isset($active[(int)$id])&&$set)$anexBy[(int)$id]=array_map('intval',array_keys($set));
        $live=[];$cut=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-30 days');
        foreach($db->query("SELECT hotel_id,MAX(last_seen_at) last_seen_at FROM tour_operator_identity_observations GROUP BY hotel_id")->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
            $id=(int)$r['hotel_id'];if(!isset($active[$id]))continue;
            try{$dt=new DateTimeImmutable((string)$r['last_seen_at'],new DateTimeZone('UTC'));}catch(Throwable){continue;}
            if($dt>=$cut)$live[$id]=true;
        }
        $front=[];foreach($live as $id=>$_)if(isset($anexBy[$id])&&!isset($samo[$id]))$front[$id]=true;
        hm234_need(count($front)===234,'frontier_changed_'.count($front));
        $ids=array_keys($front);sort($ids,SORT_NUMERIC);

        $ctx=hm234_context($db,$ids,true);
        $fallback=hm234_context($db,array_values(array_diff($ids,array_keys($ctx))),false);
        $deps=[];foreach($db->query("SELECT country_id,departure_id FROM catalog_departure_countries WHERE is_active=1 ORDER BY country_id,(departure_id=1) DESC,departure_id")->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
            $cid=(int)$r['country_id'];if(!isset($deps[$cid]))$deps[$cid]=(int)$r['departure_id'];
        }

        $rows=[];$fallbackCount=0;$pastShift=0;
        foreach($ids as $id){
            $f=$facts[$id];$cid=(int)$f['country_id'];$c=$ctx[$id]??$fallback[$id]??null;$source='future_observation';
            if($c===null){
                $fallbackCount++;$source='catalog_fallback';
                $c=['departure_id'=>$deps[$cid]??1,'country_id'=>$cid,'departure_date'=>(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+14 days')->format('Y-m-d'),'nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>''];
            }elseif(!isset($ctx[$id])){
                $pastShift++;$source='latest_observation_shifted';
                $c['departure_date']=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+14 days')->format('Y-m-d');
            }
            hm234_need((int)($c['departure_id']??0)>0,'departure_missing_'.$id);
            hm234_need((int)($c['country_id']??0)===$cid,'country_context_mismatch_'.$id);
            $rows[]=[
                'tv_hotel_id'=>$id,'hotel_name'=>(string)$f['name'],'country_id'=>$cid,'country'=>(string)$f['country_name'],
                'region'=>(string)$f['region_name'],'subregion'=>(string)$f['subregion_name'],'category'=>(string)$f['category'],
                'anex_native_ids'=>array_values($anexBy[$id]),
                'context_source'=>$source,'departure_id'=>(int)$c['departure_id'],'departure_date'=>(string)$c['departure_date'],
                'nights'=>max(1,min(28,(int)($c['nights']??7))),'adults'=>max(1,min(6,(int)($c['adults']??2))),
                'children_count'=>max(0,min(3,(int)($c['children_count']??0))),'child_ages_signature'=>(string)($c['child_ages_signature']??''),
            ];
        }
        $db->rollBack();
        return [
            'state'=>'live234_ready','generated_at_utc'=>gmdate('c'),'frontier_count'=>count($rows),
            'live30_count'=>count($live),'has_anex_count'=>count($anexBy),'has_samo_count'=>count($samo),
            'context_fallback_count'=>$fallbackCount,'past_context_shifted'=>$pastShift,
            'operator_ids'=>[18,25,43],'provider_namespaces'=>['18'=>'bgoperator','25'=>'operator_315','43'=>'operator_342'],
            'rows'=>$rows,'database_writes'=>0,'mapping_writes'=>0,
        ];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')==='--self-test'){hm234_need(hm234_excluded('Россия')&&!hm234_excluded('Turkey'),'exclude');echo "MATCH_LIVE234_PLAN_V1_SELFTEST_OK\n";exit;}
    hm234_need(($argv[1]??'')==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');hm234_need(is_dir($root),'root');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    echo json_encode(hm234_plan(v2_data_db()),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}
