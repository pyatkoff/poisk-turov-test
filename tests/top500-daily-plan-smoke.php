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
if(($plan['hotel_count']??0)!==65) top500_daily_fail('hotel_count');
if(($plan['unavailable_count']??-1)!==0) top500_daily_fail('unavailable_count');
if(($plan['unavailable_priority_ids']??null)!==[]) top500_daily_fail('unavailable_ids');
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
if(($degraded['state']??'')!=='top500_daily_resolvable_plan') top500_daily_fail('degraded_state');
if(($degraded['source_hotel_count']??0)!==65) top500_daily_fail('degraded_source_count');
if(($degraded['hotel_count']??0)!==64) top500_daily_fail('degraded_hotel_count');
if(($degraded['unavailable_count']??0)!==1) top500_daily_fail('degraded_unavailable_count');
if(($degraded['unavailable_priority_ids']??[])!==[65]) top500_daily_fail('degraded_unavailable_ids');
$degradedSeen=[];
foreach(($degraded['targets']??[]) as $target){
    if(count($target['hotel_ids']??[])>30) top500_daily_fail('degraded_oversized_batch');
    foreach(($target['hotel_ids']??[]) as $id){if(isset($degradedSeen[$id])) top500_daily_fail('degraded_duplicate');$degradedSeen[$id]=true;}
}
if(count($degradedSeen)!==64 || isset($degradedSeen[65])) top500_daily_fail('degraded_coverage');

try{v2_top500_daily_plan($ids,$hotels,$departures,'2026-09-03','2026-09-24',1);top500_daily_fail('wide_window_allowed');}catch(InvalidArgumentException $e){}

echo "TOP500_DAILY_PLAN_OK source=65 resolvable=65 degraded=64 unavailable=1 batches=3 maxBatch=30 exactCoverage=1\n";
