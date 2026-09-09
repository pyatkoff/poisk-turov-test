<?php
require_once __DIR__ . '/../v2/seo-feed-family-binding-v1.php';
function feed_binding_fail(string $m):void{fwrite(STDERR,"SEO_FEED_BINDING_FAIL:$m\n");exit(1);}
$catalog=[
 'publication_candidates'=>[],
 'registry'=>[
  '/country/turkey/hotel/hotel-a-10/'=>['type'=>'hotel_tours','page'=>['search_state'=>['country'=>4,'hotel'=>10]]],
  '/country/turkey/hotel/hotel-b-20/'=>['type'=>'hotel_tours','page'=>['search_state'=>['country'=>4,'hotel'=>20]]],
 ],
 'reports'=>[
  '/country/turkey/hotel/hotel-a-10/'=>['status'=>'review'],
  '/country/turkey/hotel/hotel-b-20/'=>['status'=>'review'],
 ],
];
$families=[['key'=>'turkey','country_id'=>4,'catalog'=>$catalog]];
$now=1000;
$report=[
 'state'=>'review_only_feed_evidence','feed_publish_allowed'=>false,
 'items'=>[
  ['state'=>'fresh_feed_evidence','country_id'=>4,'hotel_id'=>10,'tour_id'=>'t1','source_page_key'=>'resort_month:1:4:77:2026-10','expires_at_epoch'=>2000,'feed_publish_allowed'=>false],
  ['state'=>'fresh_feed_evidence','country_id'=>4,'hotel_id'=>999,'tour_id'=>'t2','source_page_key'=>'resort_month:1:4:77:2026-10','expires_at_epoch'=>2000,'feed_publish_allowed'=>false],
  ['state'=>'fresh_feed_evidence','country_id'=>4,'hotel_id'=>20,'tour_id'=>'t3','source_page_key'=>'month:1:4:2026-10','expires_at_epoch'=>999,'feed_publish_allowed'=>false],
  ['state'=>'fresh_feed_evidence','country_id'=>4,'hotel_id'=>10,'tour_id'=>'t1','source_page_key'=>'month:1:4:2026-10','expires_at_epoch'=>2000,'feed_publish_allowed'=>false],
 ],
];
$out=v2_seo_feed_family_binding($families,[$report],$now);
if(($out['state']??'')!=='review_only_feed_family_binding')feed_binding_fail('state');
if(($out['verified_hotel_identity_count']??0)!==2||($out['bound_count']??0)!==1||($out['blocked_count']??0)!==3)feed_binding_fail('counts');
if(($out['feed_publish_allowed']??true)!==false||($out['publication_allowed']??true)!==false||($out['publication_candidates']??null)!==[])feed_binding_fail('boundary');
if(($out['bound'][0]['hotel_path']??'')!=='/country/turkey/hotel/hotel-a-10/')feed_binding_fail('hotel_path');
$errors=array_merge(...array_map(static fn(array $x):array=>$x['errors']??[],$out['blocked']));
foreach(['hotel_identity_not_in_verified_family','feed_evidence_expired','duplicate_tour_identity'] as $required){if(!in_array($required,$errors,true))feed_binding_fail($required);}
$unsafe=$catalog;$unsafe['publication_candidates']=['/country/turkey/hotel/hotel-a-10/'];
try{v2_seo_feed_family_binding([['key'=>'turkey','country_id'=>4,'catalog'=>$unsafe]],[$report],$now);feed_binding_fail('candidate_leak_not_rejected');}catch(InvalidArgumentException $e){}
$badStatus=$catalog;$badStatus['reports']['/country/turkey/hotel/hotel-a-10/']['status']='approved';
try{v2_seo_feed_family_binding([['key'=>'turkey','country_id'=>4,'catalog'=>$badStatus]],[$report],$now);feed_binding_fail('approved_hotel_not_rejected');}catch(InvalidArgumentException $e){}
$unsafeReport=$report;$unsafeReport['feed_publish_allowed']=true;
$blocked=v2_seo_feed_family_binding($families,[$unsafeReport],$now);
if(($blocked['bound_count']??-1)!==0||($blocked['blocked_count']??0)!==1||!in_array('feed_report_not_review_only',$blocked['blocked'][0]['errors']??[],true))feed_binding_fail('unsafe_report');
echo "SEO_FEED_BINDING_OK verifiedHotels=2 bound=1 blocked=3 publish=0\n";
