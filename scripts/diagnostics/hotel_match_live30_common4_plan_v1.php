<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_live30_common4_gap_matrix_v1.php';

const HMC4P_EXPECTED_FRONTIER=1799;

function hmc4p_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmc4p_context(PDO $db,array $ids,bool $future):array{
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
function hmc4p_plan(PDO $db):array{
    $matrix=hmc4_execute($db);
    hmc4p_need(($matrix['state']??'')==='completed_read_only_common4_gap_matrix','matrix_state');
    hmc4p_need((int)($matrix['live30_non_triple_total']??-1)===HMC4P_EXPECTED_FRONTIER,'frontier_changed_'.(int)($matrix['live30_non_triple_total']??-1));
    $matrixRows=$matrix['rows']??null;hmc4p_need(is_array($matrixRows)&&count($matrixRows)===HMC4P_EXPECTED_FRONTIER,'matrix_rows');

    $byId=[];$ids=[];
    foreach($matrixRows as $r){
        hmc4p_need(is_array($r),'matrix_row');
        $id=(int)($r['tv_hotel_id']??0);hmc4p_need($id>0&&!isset($byId[$id]),'matrix_id');
        $byId[$id]=$r;$ids[]=$id;
    }
    sort($ids,SORT_NUMERIC);

    $facts=[];
    foreach(array_chunk($ids,250) as $chunk){
        $ph=implode(',',array_fill(0,count($chunk),'?'));
        $st=$db->prepare("SELECT id,country_id,country_name,region_name,subregion_name,category,name FROM catalog_hotels WHERE id IN ($ph) AND is_active=1");
        $st->execute($chunk);
        foreach($st->fetchAll(PDO::FETCH_ASSOC)?:[] as $r)$facts[(int)$r['id']]=$r;
    }
    hmc4p_need(count($facts)===count($ids),'facts_missing');

    $future=hmc4p_context($db,$ids,true);
    $remaining=array_values(array_diff($ids,array_keys($future)));
    $fallback=$remaining?hmc4p_context($db,$remaining,false):[];

    $deps=[];
    foreach($db->query("SELECT country_id,departure_id FROM catalog_departure_countries WHERE is_active=1 ORDER BY country_id,(departure_id=1) DESC,departure_id")->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
        $cid=(int)$r['country_id'];if(!isset($deps[$cid]))$deps[$cid]=(int)$r['departure_id'];
    }

    $opMap=['anex'=>13,'biblio'=>18,'funsun'=>25,'intourist'=>43];
    $rows=[];$contextFallback=0;$pastShift=0;$missingLaneCounts=['13'=>0,'18'=>0,'25'=>0,'43'=>0];
    $noMissing=0;
    foreach($ids as $id){
        $f=$facts[$id];$mr=$byId[$id];$cid=(int)$f['country_id'];
        $ctx=$future[$id]??$fallback[$id]??null;$source='future_observation';
        if($ctx===null){
            $contextFallback++;$source='catalog_fallback';
            $ctx=['departure_id'=>$deps[$cid]??1,'country_id'=>$cid,
                  'departure_date'=>(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+14 days')->format('Y-m-d'),
                  'nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>''];
        }elseif(!isset($future[$id])){
            $pastShift++;$source='latest_observation_shifted';
            $ctx['departure_date']=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+14 days')->format('Y-m-d');
        }
        hmc4p_need((int)($ctx['departure_id']??0)>0,'departure_missing_'.$id);
        hmc4p_need((int)($ctx['country_id']??0)===$cid,'country_context_mismatch_'.$id);

        $missing=[];
        foreach($opMap as $lane=>$op){
            $exact=(bool)($mr['lanes'][$lane]['exact_evidence']??false);
            if(!$exact){$missing[]=$op;$missingLaneCounts[(string)$op]++;}
        }
        if($missing===[])$noMissing++;
        $rows[]=[
            'tv_hotel_id'=>$id,'hotel_name'=>(string)$f['name'],'gap_bucket'=>(string)$mr['gap_bucket'],
            'country_id'=>$cid,'country'=>(string)$f['country_name'],'region'=>(string)$f['region_name'],
            'subregion'=>(string)$f['subregion_name'],'category'=>(string)$f['category'],
            'exact_lane_count'=>(int)($mr['exact_lane_count']??0),'missing_operator_ids'=>$missing,
            'context_source'=>$source,'departure_id'=>(int)$ctx['departure_id'],
            'departure_date'=>(string)$ctx['departure_date'],
            'nights'=>max(1,min(28,(int)($ctx['nights']??7))),
            'adults'=>max(1,min(6,(int)($ctx['adults']??2))),
            'children_count'=>max(0,min(3,(int)($ctx['children_count']??0))),
            'child_ages_signature'=>(string)($ctx['child_ages_signature']??''),
        ];
    }

    return [
        'state'=>'live30_common4_ready','generated_at_utc'=>gmdate('c'),
        'frontier_count'=>count($rows),'expected_frontier'=>HMC4P_EXPECTED_FRONTIER,
        'operator_ids'=>[13,18,25,43],'missing_lane_counts'=>$missingLaneCounts,
        'rows_with_no_missing_lanes'=>$noMissing,
        'context_fallback_count'=>$contextFallback,'past_context_shifted'=>$pastShift,
        'gap_bucket_counts'=>$matrix['gap_bucket_counts']??[],
        'exact_lane_count_distribution'=>$matrix['exact_lane_count_distribution']??[],
        'rows'=>$rows,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,
    ];
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')==='--self-test'){
        hmc4p_need(HMC4P_EXPECTED_FRONTIER===1799,'frontier');
        echo "MATCH_LIVE30_COMMON4_PLAN_V1_SELFTEST_OK\n";exit;
    }
    hmc4p_need(($argv[1]??'')==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');hmc4p_need(is_dir($root),'root');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    echo json_encode(hmc4p_plan(v2_data_db()),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}
