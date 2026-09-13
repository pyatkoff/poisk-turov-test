<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/anex_tourvisor_hotelcode_evidence_collect.php';

const GTV_OPERATION = 'hotel-match-global-tourvisor-anex-evidence-2333-20260913-v1';

function gtv_wait_search(int $searchId, int &$statusCalls): array {
    $last = [];
    for ($i=0; $i<10; $i++) {
        usleep(800000);
        try {
            $last = v2_data_tv_get('/tours/search/'.$searchId.'/status', ['operatorStatus'=>false]);
            $statusCalls++;
            if (ate_search_complete(is_array($last)?$last:[])) break;
        } catch (Throwable $e) { return ['status'=>'status_error','error'=>$e->getMessage()]; }
    }
    return ['status'=>'ok','progress'=>(int)($last['progress']??0)];
}
function gtv_result_rows(int $searchId, int &$resultCalls): array {
    foreach ([1000,500,100] as $limit) {
        try {
            $p = v2_data_tv_get('/tours/search/'.$searchId, ['limit'=>$limit]);
            $resultCalls++;
            return ate_tv_rows(is_array($p)?$p:[]);
        } catch (Throwable $e) { usleep(600000); }
    }
    return [];
}
function gtv_current_guards(PDO $db): array {
    $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
    $existing=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id,enabled FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_ASSOC) as $r){$existing[(int)$r['anex_hotel_id']]=$r;}
    $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
    $occupied=[];foreach($existing as $aid=>$r)if((int)($r['enabled']??0)===1)$occupied[(int)$r['catalog_hotel_id']][]=$aid;
    return [$manual,$existing,$excluded,$occupied];
}
function gtv_run(PDO $db,string $operation): array {
    if($operation!==GTV_OPERATION) throw new RuntimeException('operation_scope');
    $current=ate_current_seeds($db);$seeds=$current['seeds'];$coverageBefore=$current['coverage'];
    $seedById=[];$countryCounts=[];foreach($seeds as $s){$seedById[(int)$s['anex_hotel_id']]=$s;$countryCounts[(int)$s['country_id']]=($countryCounts[(int)$s['country_id']]??0)+1;}arsort($countryCounts);
    $supplier=['catalog_calls'=>0,'search_starts'=>0,'status_calls'=>0,'result_calls'=>0,'continue_calls'=>0,'tour_detail_calls'=>0,'anex_page_gets'=>0];$errors=[];$searches=[];$raw=[];
    $deps=v2_data_tv_get('/departures',['departureCountryId'=>1]);$supplier['catalog_calls']++;$departure=ate_departure_id(is_array($deps)?$deps:[]);if(!$departure)throw new RuntimeException('departure_missing');
    $today=new DateTimeImmutable('today');
    foreach(array_keys($countryCounts) as $country){
        try{$ops=v2_data_tv_get('/operators',['departureId'=>$departure,'countryId'=>$country]);$supplier['catalog_calls']++;$op=ate_operator_id(is_array($ops)?$ops:[]);}catch(Throwable $e){$errors[]=['country_id'=>$country,'stage'=>'operators','error'=>$e->getMessage()];continue;}
        if(!$op){$searches[]=['country_id'=>$country,'status'=>'anex_operator_missing'];continue;}
        try{$dates=v2_data_tv_get('/tours/dates',['departureId'=>$departure,'countryId'=>$country,'onlyCharter'=>false]);$supplier['catalog_calls']++;$rows=is_array($dates)?$dates:[];}catch(Throwable $e){$errors[]=['country_id'=>$country,'stage'=>'dates','error'=>$e->getMessage()];continue;}
        $target=$today->modify('+21 days');$date=null;$best=PHP_INT_MAX;foreach($rows as $v){if(!is_string($v))continue;$d=DateTimeImmutable::createFromFormat('!Y-m-d',trim($v));if(!$d||$d<$today)continue;$gap=abs((int)$d->diff($target)->format('%r%a'));if($gap<$best){$best=$gap;$date=$d->format('Y-m-d');}}
        if(!$date){$searches[]=['country_id'=>$country,'status'=>'date_missing'];continue;}
        $payload=['departureId'=>$departure,'countryId'=>$country,'dateFrom'=>$date,'dateTo'=>$date,'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'operatorIds'=>[$op],'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false];
        try{$start=v2_data_tv_get('/tours/search',$payload);$supplier['search_starts']++;$sid=ate_search_id(is_array($start)?$start:[]);}catch(Throwable $e){$errors[]=['country_id'=>$country,'stage'=>'search_start','error'=>$e->getMessage()];sleep(3);continue;}
        if(!$sid){$searches[]=['country_id'=>$country,'status'=>'search_id_missing'];sleep(2);continue;}
        $w=gtv_wait_search($sid,$supplier['status_calls']);$rows=gtv_result_rows($sid,$supplier['result_calls']);$counts=[count($rows)];
        for($round=0;$round<4;$round++){
            try{v2_data_tv_get('/tours/search/'.$sid.'/continue');$supplier['continue_calls']++;}catch(Throwable $e){$errors[]=['country_id'=>$country,'stage'=>'continue','round'=>$round+1,'error'=>$e->getMessage()];break;}
            $w=gtv_wait_search($sid,$supplier['status_calls']);$more=gtv_result_rows($sid,$supplier['result_calls']);$counts[]=count($more);if(count($more)<=count($rows)){break;}$rows=$more;
        }
        $matched=0;foreach($rows as $hotel){$m=ate_match_tv_hotel($hotel,$seeds);if(!$m)continue;$tour=ate_first_anex_tour_id($hotel,$op);if(!$tour)continue;$m['tour_id']=$tour;$m['operator_id']=$op;$m['search_date']=$date;$raw[]=$m;$matched++;}
        $searches[]=['country_id'=>$country,'seed_count'=>$countryCounts[$country],'date'=>$date,'operator_id'=>$op,'search_id'=>$sid,'status'=>$w['status']??'unknown','progress'=>$w['progress']??0,'result_counts'=>$counts,'matched_queue_rows'=>$matched];sleep(2);
    }
    $bySeed=[];foreach($raw as $m)$bySeed[(int)$m['anex_hotel_id']][]=$m;$evidence=[];$safe=[];$conflicts=[];$ambiguous=[];
    [$manual,$existing,$excluded,$occupied]=gtv_current_guards($db);
    foreach($bySeed as $aid=>$list){$tv=[];foreach($list as $m)$tv[(int)$m['tv_hotel_id']]=$m;if(count($tv)!==1){$ambiguous[]=['anex_hotel_id'=>$aid,'tv_hotel_ids'=>array_map('intval',array_keys($tv))];continue;}$m=array_values($tv)[0];$lid=(int)$m['tv_hotel_id'];$m['matched_slices']=count($list);
        try{usleep(700000);$detail=v2_data_tv_get('/tours/'.rawurlencode((string)$m['tour_id']),['currency'=>'RUB']);$supplier['tour_detail_calls']++;$link=trim((string)($detail['operatorLink']??''));}catch(Throwable $e){$m['evidence_class']='tour_detail_error';$m['error']=$e->getMessage();$evidence[]=$m;continue;}
        $hotellist=$link!==''?ate_operator_hotellist($link):null;$m['operator_hotellist']=$hotellist;$m['operator_hotellist_matches_seed']=$hotellist!==null&&$hotellist===$aid;$m['operator_link_host']=$link!==''?(string)(parse_url($link,PHP_URL_HOST)??''):'';$pageCode=null;$pageStatus='not_fetched';
        if($link!==''&&ate_allowed_anex_page($link)&&$supplier['anex_page_gets']<220){usleep(800000);$page=ate_fetch_anex_page($link);$supplier['anex_page_gets']++;$pageStatus=(string)($page['status']??'unknown');if($pageStatus==='ok'){$h=ate_page_hotelcode_evidence((string)$page['body']);if(($h['status']??'')==='confirmed')$pageCode=(int)$h['hotel_code'];}}
        $m['page_status']=$pageStatus;$m['page_hotel_code']=$pageCode;
        if($hotellist!==null&&$hotellist!==$aid){$m['evidence_class']='operator_hotellist_conflict';$conflicts[]=$m;$evidence[]=$m;continue;}
        if(isset($manual[$aid])){$m['evidence_class']='manual_protected';$evidence[]=$m;continue;}if(isset($existing[$aid])){$m['evidence_class']='mapping_appeared_during_run';$evidence[]=$m;continue;}if(isset($excluded[$aid][$lid])){$m['evidence_class']='pair_exclusion_protected';$evidence[]=$m;continue;}
        $other=array_values(array_filter($occupied[$lid]??[],static fn($x)=>$x!==$aid));if($other){$m['evidence_class']='target_occupied_same_provider';$m['occupants']=$other;$evidence[]=$m;continue;}
        if($m['operator_hotellist_matches_seed']){$m['evidence_class']=$pageCode===$aid?'full_operator_link_plus_page_hotelcode':'strong_operator_hotellist';$safe[]=$m;}else{$m['evidence_class']='tourvisor_identity_without_direct_anex_id';}
        $evidence[]=$m;
    }
    usort($safe,static fn($a,$b)=>(int)($b['search_count']??0)<=>(int)($a['search_count']??0) ?: (int)$a['anex_hotel_id']<=>(int)$b['anex_hotel_id']);
    $coverageAfter=fc_coverage($db);
    return ['status'=>'completed','operation_id'=>$operation,'mode'=>'current_global_tourvisor_anex_full_results_evidence','database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'supplier_calls_total'=>array_sum($supplier),'supplier'=>$supplier,'queue_count'=>count($seeds),'queue_by_country'=>$countryCounts,'coverage_before'=>$coverageBefore,'coverage_after'=>$coverageAfter,'searches'=>$searches,'errors'=>$errors,'matched_unique'=>count($bySeed),'ambiguous'=>count($ambiguous),'ambiguous_rows'=>$ambiguous,'evidence_rows'=>$evidence,'safe_candidates'=>$safe,'safe_count'=>count($safe),'conflicts'=>$conflicts,'guards'=>['one_day_exact_night'=>true,'anex_operator_only'=>true,'search_continue_max_rounds'=>4,'manual_existing_exclusion_protected'=>true,'same_provider_target_occupancy_block'=>true,'coordinate_block_in_tv_match_m'=>5000,'database_write'=>false,'historical_operations_replayed'=>false]];
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,"library_only: guarded workflow required\n");exit(64);}