<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/top500-daily-plan-v1.php';

function top500_daily_fail(string $message): void { fwrite(STDERR,"TOP500_DAILY_PLAN_FAIL:$message\n"); exit(1); }

$ids=range(1,65);
$hotels=[];
foreach($ids as $id){
    $country=$id<=45?4:8;
    $hotels[]=['id'=>$id,'country_id'=>$country,'country_name'=>$country===4?'Turkey':'Maldives','is_active'=>1];
}
$departures=[
    ['departure_id'=>7,'country_id'=>4,'is_active'=>1],
    ['departure_id'=>1,'country_id'=>4,'is_active'=>1],
    ['departure_id'=>3,'country_id'=>8,'is_active'=>1],
];
$plan=v2_top500_daily_plan($ids,$hotels,$departures,'2026-09-03','2026-09-23',1);
if(($plan['state']??'')!=='top500_daily_exact_plan') top500_daily_fail('state');
if(($plan['source_hotel_count']??0)!==65) top500_daily_fail('source_hotel_count');
if(($plan['catalog_hotel_count']??0)!==65) top500_daily_fail('catalog_hotel_count');
if(($plan['hotel_count']??0)!==65) top500_daily_fail('hotel_count');
if(($plan['unavailable_count']??-1)!==0) top500_daily_fail('unavailable_count');
if(($plan['missing_catalog_count']??-1)!==0 || ($plan['missing_catalog_ids']??null)!==[]) top500_daily_fail('missing_catalog');
if(($plan['no_departure_count']??-1)!==0 || ($plan['no_departure_hotels']??null)!==[]) top500_daily_fail('no_departure');
if(($plan['batch_count']??0)!==3) top500_daily_fail('batch_count');
if(($plan['max_batch_size']??0)!==30) top500_daily_fail('batch_size');
$seen=[];$turkeyDeparture=null;$maldivesDeparture=null;
foreach(($plan['targets']??[]) as $target){
    if(count($target['hotel_ids']??[])>30) top500_daily_fail('oversized_batch');
    if((int)$target['country_id']===4) $turkeyDeparture=(int)$target['departure_id'];
    if((int)$target['country_id']===8) $maldivesDeparture=(int)$target['departure_id'];
    foreach(($target['hotel_ids']??[]) as $id){if(isset($seen[$id])) top500_daily_fail('duplicate');$seen[$id]=true;}
}
if(count($seen)!==65) top500_daily_fail('coverage');
if($turkeyDeparture!==1) top500_daily_fail('preferred_departure');
if($maldivesDeparture!==3) top500_daily_fail('fallback_departure');

$broken=$hotels;array_pop($broken);
$degraded=v2_top500_daily_plan($ids,$broken,$departures,'2026-09-03','2026-09-23',1);
if(($degraded['state']??'')!=='top500_daily_searchable_plan') top500_daily_fail('degraded_state');
if(($degraded['source_hotel_count']??0)!==65) top500_daily_fail('degraded_source_count');
if(($degraded['catalog_hotel_count']??0)!==64) top500_daily_fail('degraded_catalog_count');
if(($degraded['hotel_count']??0)!==64) top500_daily_fail('degraded_hotel_count');
if(($degraded['unavailable_count']??0)!==1) top500_daily_fail('degraded_unavailable_count');
if(($degraded['missing_catalog_count']??0)!==1 || ($degraded['missing_catalog_ids']??[])!==[65]) top500_daily_fail('degraded_missing_catalog');
if(($degraded['no_departure_count']??-1)!==0) top500_daily_fail('degraded_no_departure');

$routeBlocked=v2_top500_daily_plan($ids,$hotels,array_slice($departures,0,2),'2026-09-03','2026-09-23',1);
if(($routeBlocked['state']??'')!=='top500_daily_searchable_plan') top500_daily_fail('route_state');
if(($routeBlocked['catalog_hotel_count']??0)!==65) top500_daily_fail('route_catalog_count');
if(($routeBlocked['hotel_count']??0)!==45) top500_daily_fail('route_searchable_count');
if(($routeBlocked['unavailable_count']??0)!==20) top500_daily_fail('route_unavailable_count');
if(($routeBlocked['missing_catalog_count']??-1)!==0) top500_daily_fail('route_missing_catalog');
if(($routeBlocked['no_departure_count']??0)!==20) top500_daily_fail('route_no_departure_count');
foreach(($routeBlocked['no_departure_hotels']??[]) as $row){if((int)($row['country_id']??0)!==8) top500_daily_fail('route_country');}
$routeSeen=[];
foreach(($routeBlocked['targets']??[]) as $target){foreach(($target['hotel_ids']??[]) as $id){if(isset($routeSeen[$id])) top500_daily_fail('route_duplicate');$routeSeen[$id]=true;}}
if(count($routeSeen)!==45) top500_daily_fail('route_coverage');

try{v2_top500_daily_plan($ids,$hotels,$departures,'2026-09-03','2026-09-24',1);top500_daily_fail('wide_window_allowed');}catch(InvalidArgumentException $e){}

echo "TOP500_DAILY_PLAN_OK source=65 exact=65 missing_catalog=1 route_blocked=20 batches=3 maxBatch=30\n";
