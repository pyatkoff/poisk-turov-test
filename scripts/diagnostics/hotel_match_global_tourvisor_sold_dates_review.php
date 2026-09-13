<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_global_tourvisor_anex_review.php';

const GTSD_OPERATION = 'hotel-match-global-tourvisor-sold-dates-2333-20260913-v1';
const GTSD_SELL_OPERATION = 'hotel-match-anex-sellability-review-1971-20260911-v4';

function gtsd_sold_map(array $sell): array {
    if (($sell['status']??'')!=='completed' || ($sell['operation_id']??'')!==GTSD_SELL_OPERATION) throw new RuntimeException('sell_input_invalid');
    if ((int)($sell['database_writes']??-1)!==0 || (int)($sell['mapping_writes']??-1)!==0) throw new RuntimeException('sell_input_writes');
    $out=[];
    foreach (($sell['sold']??[]) as $r) {
        if(!is_array($r))continue;$id=(int)($r['anex_hotel_id']??0);if($id<1)continue;$dates=[];
        foreach(($r['probe_dates']??[]) as $d){$d=trim((string)$d);$x=DateTimeImmutable::createFromFormat('!Y-m-d',$d);if($x&&$x->format('Y-m-d')===$d)$dates[$d]=true;}
        if($dates){ksort($dates,SORT_STRING);$out[$id]=array_keys($dates);}
    }
    return $out;
}
function gtsd_run(PDO $db,array $sell,string $operation): array {
    if($operation!==GTSD_OPERATION)throw new RuntimeException('operation_scope');
    $sold=gtsd_sold_map($sell);$current=ate_current_seeds($db);$allSeeds=$current['seeds'];$eligible=[];$slices=[];
    foreach($allSeeds as $s){$id=(int)$s['anex_hotel_id'];if(!isset($sold[$id]))continue;$eligible[$id]=$s+['sold_dates'=>$sold[$id]];foreach($sold[$id] as $date){$key=(int)$s['country_id'].'|'.$date;$slices[$key]['country_id']=(int)$s['country_id'];$slices[$key]['date']=$date;$slices[$key]['ids'][$id]=true;}}
    uasort($slices,static fn($a,$b)=>strcmp((string)$a['date'],(string)$b['date']) ?: ((int)$a['country_id']<=>(int)$b['country_id']));
    $supplier=['catalog_calls'=>0,'search_starts'=>0,'status_calls'=>0,'result_calls'=>0,'continue_calls'=>0,'tour_detail_calls'=>0,'anex_page_gets'=>0];$errors=[];$searches=[];$raw=[];$operatorCache=[];
    $deps=v2_data_tv_get('/departures',['departureCountryId'=>1]);$supplier['catalog_calls']++;$departure=ate_departure_id(is_array($deps)?$deps:[]);if(!$departure)throw new RuntimeException('departure_missing');
    foreach($slices as $slice){$country=(int)$slice['country_id'];$date=(string)$slice['date'];$sliceSeeds=[];foreach(array_keys($slice['ids']) as $id)$sliceSeeds[]=$eligible[(int)$id];
        if(!array_key_exists($country,$operatorCache)){try{$ops=v2_data_tv_get('/operators',['departureId'=>$departure,'countryId'=>$country]);$supplier['catalog_calls']++;$operatorCache[$country]=ate_operator_id(is_array($ops)?$ops:[]);}catch(Throwable $e){$operatorCache[$country]=null;$errors[]=['country_id'=>$country,'stage'=>'operators','error'=>$e->getMessage()];}}
        $op=$operatorCache[$country];if(!$op){$searches[]=['country_id'=>$country,'date'=>$date,'status'=>'anex_operator_missing','seed_count'=>count($sliceSeeds)];continue;}
        $payload=['departureId'=>$departure,'countryId'=>$country,'dateFrom'=>$date,'dateTo'=>$date,'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'operatorIds'=>[$op],'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false];
        try{$start=v2_data_tv_get('/tours/search',$payload);$supplier['search_starts']++;$sid=ate_search_id(is_array($start)?$start:[]);}catch(Throwable $e){$errors[]=['country_id'=>$country,'date'=>$date,'stage'=>'search_start','error'=>$e->getMessage()];sleep(3);continue;}
        if(!$sid){$searches[]=['country_id'=>$country,'date'=>$date,'status'=>'search_id_missing','seed_count'=>count($sliceSeeds)];sleep(2);continue;}
        $w=gtv_wait_search($sid,$supplier['status_calls']);$rows=gtv_result_rows($sid,$supplier['result_calls']);$counts=[count($rows)];
        for($round=0;$round<2;$round++){try{v2_data_tv_get('/tours/search/'.$sid.'/continue');$supplier['continue_calls']++;}catch(Throwable $e){$errors[]=['country_id'=>$country,'date'=>$date,'stage'=>'continue','round'=>$round+1,'error'=>$e->getMessage()];break;}$w=gtv_wait_search($sid,$supplier['status_calls']);$more=gtv_result_rows($sid,$supplier['result_calls']);$counts[]=count($more);if(count($more)>count($rows))$rows=$more;}
        $matched=0;foreach($rows as $hotel){$m=ate_match_tv_hotel($hotel,$sliceSeeds);if(!$m)continue;$tour=ate_first_anex_tour_id($hotel,$op);if(!$tour)continue;$m['tour_id']=$tour;$m['operator_id']=$op;$m['sold_date']=$date;$raw[]=$m;$matched++;}
        $searches[]=['country_id'=>$country,'date'=>$date,'seed_count'=>count($sliceSeeds),'operator_id'=>$op,'search_id'=>$sid,'status'=>$w['status']??'unknown','progress'=>$w['progress']??0,'result_counts'=>$counts,'matched_queue_rows'=>$matched];sleep(2);
    }
    $bySeed=[];foreach($raw as $m)$bySeed[(int)$m['anex_hotel_id']][]=$m;[$manual,$existing,$excluded,$occupied]=gtv_current_guards($db);$evidence=[];$safe=[];$conflicts=[];$ambiguous=[];
    foreach($bySeed as $aid=>$list){$tv=[];foreach($list as $m)$tv[(int)$m['tv_hotel_id']]=$m;if(count($tv)!==1){$ambiguous[]=['anex_hotel_id'=>$aid,'tv_hotel_ids'=>array_map('intval',array_keys($tv)),'sold_dates'=>array_values(array_unique(array_column($list,'sold_date')))];continue;}$m=array_values($tv)[0];$lid=(int)$m['tv_hotel_id'];$m['matched_sold_dates']=array_values(array_unique(array_column($list,'sold_date')));sort($m['matched_sold_dates']);$m['matched_sold_date_count']=count($m['matched_sold_dates']);
        try{usleep(700000);$detail=v2_data_tv_get('/tours/'.rawurlencode((string)$m['tour_id']),['currency'=>'RUB']);$supplier['tour_detail_calls']++;$link=trim((string)($detail['operatorLink']??''));}catch(Throwable $e){$m['evidence_class']='tour_detail_error';$m['error']=$e->getMessage();$evidence[]=$m;continue;}
        $hotellist=$link!==''?ate_operator_hotellist($link):null;$m['operator_hotellist']=$hotellist;$m['operator_hotellist_matches_seed']=$hotellist!==null&&$hotellist===$aid;$m['operator_link_host']=$link!==''?(string)(parse_url($link,PHP_URL_HOST)??''):'';$pageCode=null;$pageStatus='not_fetched';
        if($link!==''&&ate_allowed_anex_page($link)&&$supplier['anex_page_gets']<220){usleep(800000);$page=ate_fetch_anex_page($link);$supplier['anex_page_gets']++;$pageStatus=(string)($page['status']??'unknown');if($pageStatus==='ok'){$h=ate_page_hotelcode_evidence((string)$page['body']);if(($h['status']??'')==='confirmed')$pageCode=(int)$h['hotel_code'];}}
        $m['page_status']=$pageStatus;$m['page_hotel_code']=$pageCode;
        if($hotellist!==null&&$hotellist!==$aid){$m['evidence_class']='operator_hotellist_conflict';$conflicts[]=$m;$evidence[]=$m;continue;}if(isset($manual[$aid])){$m['evidence_class']='manual_protected';$evidence[]=$m;continue;}if(isset($existing[$aid])){$m['evidence_class']='mapping_appeared_during_run';$evidence[]=$m;continue;}if(isset($excluded[$aid][$lid])){$m['evidence_class']='pair_exclusion_protected';$evidence[]=$m;continue;}$other=array_values(array_filter($occupied[$lid]??[],static fn($x)=>$x!==$aid));if($other){$m['evidence_class']='target_occupied_same_provider';$m['occupants']=$other;$evidence[]=$m;continue;}
        if($m['operator_hotellist_matches_seed']){$m['evidence_class']=$pageCode===$aid?'full_operator_link_plus_page_hotelcode':'strong_operator_hotellist';$safe[]=$m;}else{$m['evidence_class']='tourvisor_identity_without_direct_anex_id';}$evidence[]=$m;
    }
    usort($safe,static fn($a,$b)=>(int)($b['search_count']??0)<=>(int)($a['search_count']??0) ?: (int)$a['anex_hotel_id']<=>(int)$b['anex_hotel_id']);
    return ['status'=>'completed','operation_id'=>$operation,'mode'=>'current_global_tourvisor_anex_saved_sold_dates','database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'anex_supplier_calls'=>0,'tourvisor_calls_total'=>array_sum($supplier),'tourvisor'=>$supplier,'historical_sell_evidence'=>['operation_id'=>GTSD_SELL_OPERATION,'sold_count'=>count($sold)],'current_queue_count'=>count($allSeeds),'current_sold_intersection'=>count($eligible),'slice_count'=>count($slices),'coverage'=>$current['coverage'],'searches'=>$searches,'errors'=>$errors,'matched_unique'=>count($bySeed),'ambiguous'=>count($ambiguous),'ambiguous_rows'=>$ambiguous,'safe_count'=>count($safe),'safe_candidates'=>$safe,'conflicts'=>$conflicts,'evidence_rows'=>$evidence,'guards'=>['saved_sellability_is_evidence_not_replay'=>true,'one_day_7_nights'=>true,'anex_operator_only'=>true,'continue_rounds'=>2,'current_manual_existing_exclusion_occupancy_protected'=>true,'coordinate_block_in_tv_match_m'=>5000,'database_write'=>false,'historical_operations_replayed'=>false]];
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,"library_only: guarded workflow required\n");exit(64);}