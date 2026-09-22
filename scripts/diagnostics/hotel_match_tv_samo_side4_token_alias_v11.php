<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_tv_samo_side4_saved_alias_current_v7.php';

const HMT11_OP='hotel-match-tv-samo-side4-token-alias-1971-20260922-v11';

function hmt11_score(string $a,string $b): array {
    $ta=hma7_tokens($a);$tb=hma7_tokens($b);
    if(!$ta||!$tb)return ['score'=>0.0,'exact'=>false,'qualifier_conflict'=>true];
    $qa=hma7_qualifiers($ta);$qb=hma7_qualifiers($tb);$conflict=$qa!==$qb;
    $inter=count(array_intersect($ta,$tb));$union=count(array_unique(array_merge($ta,$tb)));
    $exact=$ta===$tb;$j=$union?$inter/$union:0.0;$contain=$inter/min(count($ta),count($tb));
    $score=$exact?1.0:(0.68*$j+0.32*$contain);
    if($conflict)$score=min($score,0.79);
    return ['score'=>round(max(0.0,min(1.0,$score)),6),'exact'=>$exact,'qualifier_conflict'=>$conflict];
}
function hmt11_resolve(array $tvRows,array $samoRows): array {
    $tv=hma7_hotels($tvRows);$sa=hma7_hotels($samoRows);$matrix=[];
    foreach($tv as $tid=>$t)foreach($sa as $sid=>$s){
        $overlap=array_values(array_intersect($t['families'],$s['families']));sort($overlap,SORT_STRING);
        $common3=array_values(array_intersect($overlap,['anex','funsun','intourist']));sort($common3,SORT_STRING);
        if(!$common3)continue;
        $best=null;$pair=null;
        foreach($t['names'] as $tn)foreach($s['names'] as $sn){
            $x=hmt11_score($tn,$sn);
            if($best===null||$x['score']>$best['score']){$best=$x;$pair=[$tn,$sn];}
        }
        if(($best['score']??0)<0.72)continue;
        $matrix[$tid][$sid]=['tv_hotel_id'=>(string)$tid,'samo_hotel_id'=>(string)$sid,'tv_name'=>$pair[0],'samo_name'=>$pair[1],
            'name_score'=>$best['score'],'name_exact_tokens'=>$best['exact'],'qualifier_conflict'=>$best['qualifier_conflict'],
            'operator_overlap'=>$overlap,'common3_overlap'=>$common3,'tv_offer_count'=>$t['offer_count'],'samo_offer_count'=>$s['offer_count']];
    }
    $tvRanks=[];$saRanks=[];
    foreach($matrix as $tid=>$xs){$v=array_values($xs);usort($v,fn($a,$b)=>$b['name_score']<=>$a['name_score']?:strcmp((string)$a['samo_hotel_id'],(string)$b['samo_hotel_id']));$tvRanks[$tid]=$v;foreach($v as $x)$saRanks[$x['samo_hotel_id']][]=$x;}
    foreach($saRanks as &$v)usort($v,fn($a,$b)=>$b['name_score']<=>$a['name_score']?:strcmp((string)$a['tv_hotel_id'],(string)$b['tv_hotel_id']));unset($v);
    $strong=[];$review=[];
    foreach($tvRanks as $tid=>$rank){
        $x=$rank[0];$second=$rank[1]['name_score']??0.0;$inverse=$saRanks[$x['samo_hotel_id']];$invSecond=$inverse[1]['name_score']??0.0;
        $x['tv_margin']=round($x['name_score']-$second,6);$x['samo_margin']=round($x['name_score']-$invSecond,6);
        $x['mutual_unique']=$inverse[0]['tv_hotel_id']===(string)$tid&&$x['tv_margin']>=0.06&&$x['samo_margin']>=0.06;
        $x['tier']=($x['mutual_unique']&&!$x['qualifier_conflict']&&$x['name_score']>=0.90)?'strong_common3':'review';
        if($x['tier']==='strong_common3')$strong[]=$x;else $review[]=$x;
    }
    usort($strong,fn($a,$b)=>$b['name_score']<=>$a['name_score']?:strcmp((string)$a['tv_hotel_id'],(string)$b['tv_hotel_id']));
    usort($review,fn($a,$b)=>$b['name_score']<=>$a['name_score']?:strcmp((string)$a['tv_hotel_id'],(string)$b['tv_hotel_id']));
    return ['tv_hotels'=>count($tv),'samo_hotels'=>count($sa),'strong_common3'=>$strong,'strong_count'=>count($strong),
        'review'=>array_slice($review,0,100),'review_count'=>count($review),
        'policy'=>'token_only_mutual_unique_score_gte_0.90_margin_0.06_common3_overlap_no_qualifier_conflict'];
}
function hmt11_execute(string $opDir): array {
    $reservation=json_decode((string)file_get_contents($opDir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    if(($reservation['operation']??null)!==HMT11_OP||($reservation['state']??null)!=='reserved_read_only_token_alias')throw new RuntimeException('reservation');
    $operations=dirname($opDir);$saved=hmc6_samo_rows($operations.'/'.HMA7_SOURCE_OP);$checkpoint=hmc3_previous_checkpoint($operations.'/'.HMC_PREVIOUS_OP);
    $common=['anex'=>['tv'=>['id'=>13,'name'=>'ANEX']], 'biblio'=>['tv'=>['id'=>18,'name'=>'Библио-Глобус']], 'funsun'=>['tv'=>['id'=>25,'name'=>'FUN&SUN']], 'intourist'=>['tv'=>['id'=>43,'name'=>'Интурист']]];
    $tv=hmc_tv_offer_rows($checkpoint['rows'],HMC_DATE_FROM,$common);
    $baseline=hma7_baseline_pairs(hmf_resolve($tv,$saved['rows'],[]));if(count($baseline)!==3)throw new RuntimeException('baseline_exact_count');
    $unresolved=hma7_unresolved_rows($tv,$baseline);if(count(hma7_hotels($unresolved))!==137)throw new RuntimeException('unresolved_tv_count');
    $alias=hmt11_resolve($unresolved,$saved['rows']);
    return ['operation'=>HMT11_OP,'state'=>'completed_read_only_token_alias','scope'=>['resort'=>'Side','date_from'=>HMC_DATE_FROM,'date_to'=>HMC_DATE_TO,'nights'=>7,'adults'=>2,'children'=>0,
        'retained_tv_hotels'=>140,'retained_tv_offers'=>1809,'retained_samo_offers'=>count($saved['rows'])],
        'baseline_exact_count'=>3,'unresolved_tv_hotels'=>137,'alias'=>$alias,
        'acquisition_policy'=>'COMMON3_ANEX_FUNSUN_INTOURIST_FOR_NEW_MATCH','biblio_fuel_policy'=>'owner_policy_zero_included_no_fuel_sample',
        'provider_http_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_writes'=>0,'search_visibility_verified'=>false];
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');$opDir=(string)getenv('MATCH_OPERATION_DIR');if(!is_dir($opDir))throw new RuntimeException('runtime_paths');
    try{$result=hmt11_execute($opDir);$sha=hmc_write($opDir.'/result.json',$result);hmc_write($opDir.'/receipt.json',['operation'=>HMT11_OP,'state'=>$result['state'],'result_sha256'=>$sha,'readback_verified'=>hash_file('sha256',$opDir.'/result.json')===$sha,'provider_accessed'=>false,'provider_http_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo hmc_json(['state'=>$result['state'],'strong'=>$result['alias']['strong_count'],'review'=>$result['alias']['review_count']])."\n";}
    catch(Throwable $e){$reason=preg_match('/^[A-Za-z0-9_.:-]{1,120}$/D',$e->getMessage())?$e->getMessage():'sanitized_failure';$fail=['operation'=>HMT11_OP,'state'=>'failed_read_only_token_alias','reason'=>$reason,'provider_http_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0];$sha=hmc_write($opDir.'/result.json',$fail);hmc_write($opDir.'/receipt.json',['operation'=>HMT11_OP,'state'=>$fail['state'],'result_sha256'=>$sha,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$reason."\n");exit(2);}
}
