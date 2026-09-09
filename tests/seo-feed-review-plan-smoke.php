<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/v2/seo-feed-review-plan-v1.php';

function feed_plan_fail(string $code): void { fwrite(STDERR,"SEO_FEED_REVIEW_PLAN_FAIL:$code\n"); exit(1); }
function must_throw(callable $fn,string $code): void { try{$fn();}catch(InvalidArgumentException $e){return;} feed_plan_fail($code); }

$now=1000;
$item=function(int $country,int $hotel,string $tour,int $expires,string $family='turkey'): array {
    return [
        'state'=>'review_only_family_bound_feed_evidence','family_key'=>$family,
        'hotel_path'=>'/country/'.($family==='turkey'?'turkey':'maldives').'/hotel/test-'.$hotel.'/',
        'country_id'=>$country,'hotel_id'=>$hotel,'tour_id'=>$tour,
        'source_page_key'=>'month:1:'.$country.':2026-09','expires_at_epoch'=>$expires,
        'feed_publish_allowed'=>false,'publication_allowed'=>false,
    ];
};
$binding=[
    'state'=>'review_only_feed_family_binding','feed_publish_allowed'=>false,'publication_allowed'=>false,'publication_candidates'=>[],
    'bound'=>[$item(4,10,'tour-a',2000),$item(4,11,'tour-b',1800),$item(8,20,'mv-tour',2200,'maldives')],
    // Unrelated blocked evidence must not force an explicitly selected verified item to disappear.
    'blocked_count'=>1,'blocked'=>[['errors'=>['hotel_identity_not_in_verified_family']]],
];
$plan=v2_seo_feed_review_plan($binding,[['country_id'=>4,'hotel_id'=>11,'tour_id'=>'tour-b'],['country_id'=>8,'hotel_id'=>20,'tour_id'=>'mv-tour']],$now,3);
if(($plan['state']??'')!=='review_only_feed_plan') feed_plan_fail('state');
if(($plan['selection_mode']??'')!=='explicit_exact_tour_identity') feed_plan_fail('selection_mode');
if(($plan['item_count']??0)!==2||($plan['evidence_valid_until_epoch']??0)!==1800) feed_plan_fail('counts_or_expiry');
if(($plan['publication_candidates']??null)!==[]) feed_plan_fail('publication_candidates');
foreach(['feed_publish_allowed','publication_allowed','copy_allowed','route_creation_allowed'] as $flag) if(($plan[$flag]??true)!==false) feed_plan_fail('boundary_'.$flag);
if(array_keys($plan['items'][0]??[])!==['state','family_key','hotel_path','country_id','hotel_id','tour_id','source_page_key','expires_at_epoch','feed_publish_allowed','publication_allowed']) feed_plan_fail('normalized_item_shape');
if(isset($plan['items'][0]['price'])||isset($plan['items'][0]['availability'])) feed_plan_fail('volatile_payload_leak');
if(($plan['items'][0]['hotel_id']??0)!==11||($plan['items'][1]['country_id']??0)!==8) feed_plan_fail('explicit_order');

must_throw(fn()=>v2_seo_feed_review_plan($binding,[],$now,3),'empty_selectors');
must_throw(fn()=>v2_seo_feed_review_plan($binding,[['country_id'=>4,'hotel_id'=>99,'tour_id'=>'missing']],$now,3),'unknown_selector');
must_throw(fn()=>v2_seo_feed_review_plan($binding,[['country_id'=>4,'hotel_id'=>10,'tour_id'=>'tour-a'],['country_id'=>4,'hotel_id'=>10,'tour_id'=>'tour-a']],$now,3),'duplicate_selector');
must_throw(fn()=>v2_seo_feed_review_plan($binding,[['country_id'=>4,'hotel_id'=>10,'tour_id'=>'tour-a'],['country_id'=>4,'hotel_id'=>11,'tour_id'=>'tour-b']],$now,1),'oversized');
$expired=$binding;$expired['bound'][0]['expires_at_epoch']=$now;
must_throw(fn()=>v2_seo_feed_review_plan($expired,[['country_id'=>4,'hotel_id'=>10,'tour_id'=>'tour-a']],$now,3),'expired_selected');
$leak=$binding;$leak['publication_candidates']=['x'];
must_throw(fn()=>v2_seo_feed_review_plan($leak,[['country_id'=>4,'hotel_id'=>10,'tour_id'=>'tour-a']],$now,3),'binding_publication_leak');
$itemLeak=$binding;$itemLeak['bound'][0]['feed_publish_allowed']=true;
must_throw(fn()=>v2_seo_feed_review_plan($itemLeak,[['country_id'=>4,'hotel_id'=>10,'tour_id'=>'tour-a']],$now,3),'item_publication_leak');

// No implicit cheapest/first selection API: every returned identity must be caller-named.
foreach($plan['items'] as $planned){
    $id=$planned['country_id'].'|'.$planned['hotel_id'].'|'.$planned['tour_id'];
    if(!in_array($id,['4|11|tour-b','8|20|mv-tour'],true)) feed_plan_fail('implicit_selection');
}

echo "SEO_FEED_REVIEW_PLAN_OK explicit=2 publication=off\n";
