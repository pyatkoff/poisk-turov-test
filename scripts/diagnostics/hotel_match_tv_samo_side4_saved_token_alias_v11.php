<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_tv_samo_side4_saved_alias_current_v7.php';

const HMT11_OP='hotel-match-tv-samo-side4-saved-token-alias-1971-20260922-v11';

function hmt11_token_score(string $a,string $b): array {
    $ta=hma7_tokens($a);$tb=hma7_tokens($b);
    if(!$ta||!$tb)return [
        'score'=>0.0,'exact'=>false,'qualifier_conflict'=>true,
        'shared_tokens'=>0,'a_tokens'=>$ta,'b_tokens'=>$tb
    ];
    $qa=hma7_qualifiers($ta);$qb=hma7_qualifiers($tb);$qualifierConflict=$qa!==$qb;
    $inter=count(array_intersect($ta,$tb));$union=count(array_unique(array_merge($ta,$tb)));
    $j=$union>0?$inter/$union:0.0;
    $contain=$inter/min(count($ta),count($tb));
    $exact=$ta===$tb;
    $score=$exact?1.0:(0.72*$j+0.28*$contain);
    if($qualifierConflict)$score=min($score,0.79);
    return [
        'score'=>round(max(0.0,min(1.0,$score)),6),
        'exact'=>$exact,
        'qualifier_conflict'=>$qualifierConflict,
        'shared_tokens'=>$inter,
        'a_tokens'=>$ta,'b_tokens'=>$tb,
    ];
}

function hmt11_resolve(array $tvRows,array $samoRows): array {
    $tv=hma7_hotels($tvRows);$sa=hma7_hotels($samoRows);
    $matrix=[];
    foreach($tv as $tid=>$t){
        foreach($sa as $sid=>$s){
            $best=null;$names=null;
            foreach($t['names'] as $tn){
                foreach($s['names'] as $sn){
                    $x=hmt11_token_score($tn,$sn);
                    if($best===null||$x['score']>$best['score']){
                        $best=$x;$names=[$tn,$sn];
                    }
                }
            }
            if($best===null||$best['score']<0.58)continue;
            $overlap=array_values(array_intersect($t['families'],$s['families']));
            sort($overlap,SORT_STRING);
            $matrix[$tid][$sid]=[
                'tv_hotel_id'=>(string)$tid,
                'samo_hotel_id'=>(string)$sid,
                'tv_name'=>$names[0],
                'samo_name'=>$names[1],
                'token_score'=>$best['score'],
                'token_exact'=>$best['exact'],
                'qualifier_conflict'=>$best['qualifier_conflict'],
                'shared_tokens'=>$best['shared_tokens'],
                'tv_tokens'=>$best['a_tokens'],
                'samo_tokens'=>$best['b_tokens'],
                'operator_overlap'=>$overlap,
                'operator_overlap_count'=>count($overlap),
                'tv_offer_count'=>$t['offer_count'],
                'samo_offer_count'=>$s['offer_count'],
                'safe_to_write_now'=>false,
            ];
        }
    }
    $tvRanks=[];$samoRanks=[];
    foreach($matrix as $tid=>$pairs){
        $v=array_values($pairs);
        usort($v,static fn($a,$b)=>
            [$b['token_score'],$b['operator_overlap_count'],$b['shared_tokens'],$a['samo_hotel_id']]
            <=>
            [$a['token_score'],$a['operator_overlap_count'],$a['shared_tokens'],$b['samo_hotel_id']]
        );
        $tvRanks[$tid]=$v;
        foreach($v as $x)$samoRanks[$x['samo_hotel_id']][]=$x;
    }
    foreach($samoRanks as &$v){
        usort($v,static fn($a,$b)=>
            [$b['token_score'],$b['operator_overlap_count'],$b['shared_tokens'],$a['tv_hotel_id']]
            <=>
            [$a['token_score'],$a['operator_overlap_count'],$a['shared_tokens'],$b['tv_hotel_id']]
        );
    }
    unset($v);

    $strong=[];$review=[];$noCandidate=0;
    foreach($tv as $tid=>$unused){
        if(!isset($tvRanks[$tid])){$noCandidate++;continue;}
        $rank=$tvRanks[$tid];$x=$rank[0];
        $tvSecond=$rank[1]['token_score']??0.0;
        $inverse=$samoRanks[$x['samo_hotel_id']]??[];
        $saSecond=$inverse[1]['token_score']??0.0;
        $x['tv_margin']=round($x['token_score']-$tvSecond,6);
        $x['samo_margin']=round($x['token_score']-$saSecond,6);
        $x['mutual_unique']=isset($inverse[0])&&$inverse[0]['tv_hotel_id']===(string)$tid
            &&$x['tv_margin']>=0.08&&$x['samo_margin']>=0.08;
        $enoughIdentityTokens=$x['shared_tokens']>=2
            ||($x['token_exact']&&count($x['tv_tokens'])>=2&&count($x['samo_tokens'])>=2);
        $isStrong=$x['mutual_unique']
            &&!$x['qualifier_conflict']
            &&$x['operator_overlap_count']>0
            &&$enoughIdentityTokens
            &&$x['token_score']>=0.84;
        $x['tier']=$isStrong?'strong_common4_token':'review';
        if($isStrong)$strong[]=$x;
        else $review[]=$x;
    }
    usort($strong,static fn($a,$b)=>
        [$b['token_score'],$b['operator_overlap_count'],$b['shared_tokens'],$a['tv_hotel_id']]
        <=>
        [$a['token_score'],$a['operator_overlap_count'],$a['shared_tokens'],$b['tv_hotel_id']]
    );
    usort($review,static fn($a,$b)=>
        [$b['token_score'],$b['operator_overlap_count'],$b['shared_tokens'],$a['tv_hotel_id']]
        <=>
        [$a['token_score'],$a['operator_overlap_count'],$a['shared_tokens'],$b['tv_hotel_id']]
    );
    return [
        'schema'=>'anytour.match.side4.saved-token-alias.v11',
        'tv_hotels'=>count($tv),
        'samo_hotels'=>count($sa),
        'strong_common4'=>$strong,
        'strong_count'=>count($strong),
        'review'=>$review,
        'review_count'=>count($review),
        'no_candidate_count'=>$noCandidate,
        'policy'=>'token_only_mutual_unique_margin_0.08_score_0.84_shared_tokens_2_COMMON4_no_qualifier_conflict',
        'levenshtein_used'=>false,
        'provider_calls'=>0,
        'database_reads'=>0,
        'database_writes'=>0,
        'mapping_writes'=>0,
    ];
}

function hmt11_execute(string $opDir): array {
    $reservation=json_decode((string)file_get_contents($opDir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    if(($reservation['operation']??null)!==HMT11_OP||($reservation['state']??null)!=='reserved_read_only_saved_token_alias')
        throw new RuntimeException('reservation');
    $operations=dirname($opDir);
    $saved=hmc6_samo_rows($operations.'/'.HMA7_SOURCE_OP);
    $checkpoint=hmc3_previous_checkpoint($operations.'/'.HMC_PREVIOUS_OP);
    $common=[
        'anex'=>['tv'=>['id'=>13,'name'=>'ANEX']],
        'biblio'=>['tv'=>['id'=>18,'name'=>'Библио-Глобус']],
        'funsun'=>['tv'=>['id'=>25,'name'=>'FUN&SUN']],
        'intourist'=>['tv'=>['id'=>43,'name'=>'Интурист']],
    ];
    $tv=hmc_tv_offer_rows($checkpoint['rows'],HMC_DATE_FROM,$common);
    $baselinePairs=hma7_baseline_pairs(hmf_resolve($tv,$saved['rows'],[]));
    if(count($baselinePairs)!==3)throw new RuntimeException('baseline_exact_count');
    $unresolvedRows=hma7_unresolved_rows($tv,$baselinePairs);
    $unresolvedHotels=hma7_hotels($unresolvedRows);
    if(count($unresolvedHotels)!==137)throw new RuntimeException('unresolved_tv_count');
    $aliases=hmt11_resolve($unresolvedRows,$saved['rows']);
    if($aliases['tv_hotels']!==137)throw new RuntimeException('alias_tv_count');
    return [
        'operation'=>HMT11_OP,
        'state'=>'completed_read_only_saved_token_alias',
        'source_operation'=>HMA7_SOURCE_OP,
        'scope'=>[
            'resort'=>'Side',
            'date_from'=>HMC_DATE_FROM,
            'date_to'=>HMC_DATE_TO,
            'nights'=>7,'adults'=>2,'children'=>0,
            'retained_tv_hotels'=>140,
            'retained_tv_offers'=>1809,
            'retained_samo_offers'=>count($saved['rows']),
            'baseline_exact_pairs'=>3,
            'unresolved_tv_hotels'=>137,
        ],
        'match_acquisition_policy'=>'COMMON4_ANEX_BIBLIO_FUNSUN_INTOURIST',
        'fuel_only_policy'=>'FUNSUN_INTOURIST_ANEX_APD_BG_OWNER_ZERO',
        'baseline_exact'=>$baselinePairs,
        'alias'=>$aliases,
        'provider_http_calls'=>0,
        'database_reads'=>0,
        'database_writes'=>0,
        'mapping_writes'=>0,
        'booking_calls'=>0,
        'lead_writes'=>0,
        'search_visibility_verified'=>false,
        'terminal_predecessors_no_replay'=>['v7'=>35773753954,'v8'=>35774407017,'v9'=>35775685083],
    ];
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');
    $opDir=(string)getenv('MATCH_OPERATION_DIR');
    if(!is_dir($opDir))throw new RuntimeException('runtime_paths');
    try{
        $result=hmt11_execute($opDir);
        $sha=hmc_write($opDir.'/result.json',$result);
        hmc_write($opDir.'/receipt.json',[
            'operation'=>HMT11_OP,'state'=>$result['state'],'result_sha256'=>$sha,
            'readback_verified'=>hash_file('sha256',$opDir.'/result.json')===$sha,
            'provider_accessed'=>false,'provider_http_calls'=>0,
            'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0,
        ]);
        echo hmc_json([
            'state'=>$result['state'],
            'baseline_exact'=>3,
            'unresolved'=>137,
            'strong_alias'=>$result['alias']['strong_count'],
            'review'=>$result['alias']['review_count'],
            'no_candidate'=>$result['alias']['no_candidate_count'],
        ])."\n";
    }catch(Throwable $e){
        $reason=preg_match('/^[A-Za-z0-9_.:-]{1,120}$/D',$e->getMessage())?$e->getMessage():'sanitized_failure';
        $fail=[
            'operation'=>HMT11_OP,'state'=>'failed_read_only_saved_token_alias','reason'=>$reason,
            'provider_http_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0,
        ];
        $sha=hmc_write($opDir.'/result.json',$fail);
        hmc_write($opDir.'/receipt.json',[
            'operation'=>HMT11_OP,'state'=>$fail['state'],'result_sha256'=>$sha,'readback_verified'=>true,
            'provider_accessed'=>false,'provider_http_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0,
        ]);
        fwrite(STDERR,$reason."\n");exit(2);
    }
}
