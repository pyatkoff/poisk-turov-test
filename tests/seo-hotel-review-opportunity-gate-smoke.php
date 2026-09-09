<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-hotel-review-opportunity-gate-v1.php';
function opp_hotel_fail(string $m):void{fwrite(STDERR,"SEO_HOTEL_OPPORTUNITY_GATE_FAIL:$m\n");exit(1);}
$now=1800000000;
$items=[];
foreach([1,4,8] as $country){
    for($i=1;$i<=3;$i++){
        $hotel=$country*1000+$i;
        $items[]=['path'=>"/country/test-$country/hotel/test-$hotel/",'country_id'=>$country,'hotel_id'=>$hotel,'score'=>100,'evidence_expires_epoch'=>$now+3600];
    }
}
// With no real opportunity signals, all technically ready/fresh hotels must stay blocked.
$blocked=v2_seo_hotel_review_opportunity_gate($items,[],$now);
if(($blocked['state']??'')!=='opportunity_evidence_blocked'||($blocked['opportunity_ready_count']??-1)!==0||($blocked['opportunity_blocked_count']??0)!==9)opp_hotel_fail('missing_signals_not_blocked');
foreach(($blocked['rows']??[]) as $row){
    $dims=$row['opportunity']['blocked_dimensions']??[];
    if(!in_array('demand:unknown',$dims,true)||!in_array('uniqueness:unknown',$dims,true)||!in_array('commercial_inventory:unknown',$dims,true))opp_hotel_fail('required_unknown_dimensions');
}
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $flag){if(($blocked[$flag]??true)!==false)opp_hotel_fail('boundary_'.$flag);}
if(($blocked['publication_candidates']??null)!==[])opp_hotel_fail('publication_candidates');

$signals=[];
foreach($items as $item){
    $path=$item['path'];
    foreach(['intent','demand','uniqueness','content','commercial_inventory'] as $key){
        $signals[$path][$key]=['status'=>'confirmed','score'=>90,'observed_at_epoch'=>$now-60,'source'=>'test:'.$key];
    }
}
$ready=v2_seo_hotel_review_opportunity_gate($items,$signals,$now);
if(($ready['state']??'')!=='opportunity_review_ready'||($ready['opportunity_ready_count']??0)!==9||($ready['opportunity_blocked_count']??-1)!==0)opp_hotel_fail('confirmed_signals');
if(($ready['publication_allowed']??true)!==false||($ready['indexation_allowed']??true)!==false)opp_hotel_fail('ready_crossed_launch_boundary');

$stale=$items;$stale[0]['evidence_expires_epoch']=$now;
try{v2_seo_hotel_review_opportunity_gate($stale,$signals,$now);opp_hotel_fail('stale_identity_allowed');}catch(InvalidArgumentException $e){}
$weak=$items;$weak[0]['score']=99;
try{v2_seo_hotel_review_opportunity_gate($weak,$signals,$now);opp_hotel_fail('weak_technical_allowed');}catch(InvalidArgumentException $e){}

echo "SEO_HOTEL_OPPORTUNITY_GATE_OK hotels=9 missingDemandBlocked=1 missingUniquenessBlocked=1 publication=0 indexation=0\n";
