<?php
require_once __DIR__ . '/../v2/seo-seasonal-coverage-readiness-v1.php';
require_once __DIR__ . '/../v2/seo-seasonal-review-plan-v1.php';
require_once __DIR__ . '/../v2/seo-seasonal-review-dataset-v1.php';
function coverage_fail(string $m): void { fwrite(STDERR,"SEO_SEASONAL_COVERAGE_FAIL:$m\n"); exit(1); }

$data = [
 'state'=>'review_only_data_readiness','evidence_checked_at_epoch'=>1000,'evidence_valid_until_epoch'=>20000,'evidence_clock_valid'=>true,
 'countries'=>[
   ['country_id'=>1,'snapshot_count'=>172,'usable_snapshot_count'=>172,'types'=>[
      ['page_type'=>'month','snapshot_count'=>36,'usable_snapshot_count'=>36,'identity_count'=>36,'min_offer_count'=>3,'min_freshness_seconds'=>14948],
      ['page_type'=>'resort_month','snapshot_count'=>136,'usable_snapshot_count'=>136,'identity_count'=>136,'min_offer_count'=>1,'min_freshness_seconds'=>14948],
   ]],
   ['country_id'=>8,'snapshot_count'=>2,'usable_snapshot_count'=>2,'types'=>[
      ['page_type'=>'month','snapshot_count'=>1,'usable_snapshot_count'=>1,'identity_count'=>1,'min_offer_count'=>10,'min_freshness_seconds'=>14948],
      ['page_type'=>'resort_month','snapshot_count'=>1,'usable_snapshot_count'=>1,'identity_count'=>1,'min_offer_count'=>10,'min_freshness_seconds'=>14948],
   ]],
 ]
];
$policy=['country_id'=>1,'min_month_identities'=>3,'min_resort_month_identities'=>3,'min_freshness_seconds'=>3600,'min_offers_per_snapshot'=>1];
$egypt=v2_seo_seasonal_coverage_assess($data,$policy,5000);
if (($egypt['review_ready']??false)!==true || ($egypt['state']??'')!=='review_ready') coverage_fail('egypt_policy');
if (($egypt['score']??0)!==100) coverage_fail('egypt_score');
if (($egypt['checks']['evidence_clock']['pass']??false)!==true) coverage_fail('evidence_clock_pass');
if (($egypt['publication_allowed']??true)!==false || ($egypt['copy_allowed']??true)!==false) coverage_fail('publication_boundary');

$binding=[
 'state'=>'review_only_seasonal_family_binding','country_id'=>1,'evidence_valid_until_epoch'=>18000,
 'publication_allowed'=>false,'copy_allowed'=>false,'publication_candidates'=>[],
 'bound'=>[
  ['page_key'=>'month:1:1:2026-10','page_type'=>'month','region_id'=>null,'departure_id'=>1,'year'=>2026,'month'=>10,'parent_path'=>'/country/egypt/','offer_count'=>5,'evidence_checked_at_epoch'=>1000,'freshness_seconds'=>17000,'expires_at_epoch'=>18000,'publication_allowed'=>false,'copy_allowed'=>false],
  ['page_key'=>'resort_month:1:1:55:2026-10','page_type'=>'resort_month','region_id'=>55,'departure_id'=>1,'year'=>2026,'month'=>10,'parent_path'=>'/country/egypt/sharm-el-sheikh/','offer_count'=>2,'evidence_checked_at_epoch'=>1000,'freshness_seconds'=>16000,'expires_at_epoch'=>17000,'publication_allowed'=>false,'copy_allowed'=>false],
 ],
];
$plan=v2_seo_seasonal_review_plan($egypt,$binding,['resort_month:1:1:55:2026-10','month:1:1:2026-10'],5000,3);
if(($plan['state']??'')!=='review_only_seasonal_plan'||($plan['item_count']??0)!==2)coverage_fail('review_plan');
if(($plan['publication_candidates']??null)!==[]||($plan['publication_allowed']??true)!==false||($plan['feed_publish_allowed']??true)!==false||($plan['copy_allowed']??true)!==false)coverage_fail('review_plan_boundary');
if(($plan['items'][0]['page_key']??'')!=='month:1:1:2026-10')coverage_fail('review_plan_order');
if(($plan['items'][0]['offer_count']??0)!==5||($plan['items'][0]['freshness_seconds']??0)!==17000||($plan['items'][0]['evidence_checked_at_epoch']??0)!==1000)coverage_fail('review_plan_factual_metrics');
try{v2_seo_seasonal_review_plan($egypt,$binding,['month:1:1:2026-11'],5000);coverage_fail('review_plan_unknown_key');}catch(InvalidArgumentException $e){}
$zeroDepth=$binding;$zeroDepth['bound'][0]['offer_count']=0;
try{v2_seo_seasonal_review_plan($egypt,$zeroDepth,['month:1:1:2026-10'],5000);coverage_fail('review_plan_zero_depth');}catch(InvalidArgumentException $e){}

$egyptDatasetPlan=$plan;$egyptDatasetPlan['family']='egypt';$egyptDatasetPlan['items']=[$plan['items'][0]];$egyptDatasetPlan['item_count']=1;
$turkeyDatasetPlan=$egyptDatasetPlan;$turkeyDatasetPlan['family']='turkey';$turkeyDatasetPlan['country_id']=4;$turkeyDatasetPlan['items'][0]['country_id']=4;$turkeyDatasetPlan['items'][0]['page_key']='month:1:4:2026-10';$turkeyDatasetPlan['items'][0]['parent_path']='/country/turkey/';
$maldivesDatasetPlan=$egyptDatasetPlan;$maldivesDatasetPlan['family']='maldives';$maldivesDatasetPlan['country_id']=8;$maldivesDatasetPlan['items'][0]['country_id']=8;$maldivesDatasetPlan['items'][0]['page_key']='month:1:8:2026-10';$maldivesDatasetPlan['items'][0]['parent_path']='/country/maldives/';
$dataset=v2_seo_seasonal_review_dataset([$turkeyDatasetPlan,$egyptDatasetPlan,$maldivesDatasetPlan],['egypt','turkey','maldives'],5000,3);
if(($dataset['state']??'')!=='review_only_seasonal_dataset'||($dataset['family_count']??0)!==3||($dataset['item_count']??0)!==3)coverage_fail('review_dataset');
if(($dataset['publication_candidates']??null)!==[]||($dataset['publication_allowed']??true)!==false||($dataset['feed_publish_allowed']??true)!==false||($dataset['copy_allowed']??true)!==false)coverage_fail('review_dataset_boundary');
if(array_key_exists('offer_count',$dataset['items'][0]??[]))coverage_fail('review_dataset_volatile_offer_leak');
try{v2_seo_seasonal_review_dataset([$egyptDatasetPlan,$turkeyDatasetPlan],['egypt','turkey','maldives'],5000,3);coverage_fail('review_dataset_missing_family');}catch(InvalidArgumentException $e){}
$duplicateCountry=$turkeyDatasetPlan;$duplicateCountry['country_id']=1;$duplicateCountry['items'][0]['country_id']=1;
try{v2_seo_seasonal_review_dataset([$egyptDatasetPlan,$duplicateCountry,$maldivesDatasetPlan],['egypt','turkey','maldives'],5000,3);coverage_fail('review_dataset_duplicate_country');}catch(InvalidArgumentException $e){}
$staleDatasetPlan=$maldivesDatasetPlan;$staleDatasetPlan['evidence_valid_until_epoch']=5000;
try{v2_seo_seasonal_review_dataset([$egyptDatasetPlan,$turkeyDatasetPlan,$staleDatasetPlan],['egypt','turkey','maldives'],5000,3);coverage_fail('review_dataset_stale_plan');}catch(InvalidArgumentException $e){}

$maldivesPolicy=$policy; $maldivesPolicy['country_id']=8;
$maldives=v2_seo_seasonal_coverage_assess($data,$maldivesPolicy,5000);
if (($maldives['review_ready']??true)!==false) coverage_fail('maldives_should_block');
if (!in_array('month_identity_coverage_below_policy',$maldives['errors']??[],true)) coverage_fail('maldives_month_blocker');
if (!in_array('resort_month_identity_coverage_below_policy',$maldives['errors']??[],true)) coverage_fail('maldives_resort_blocker');
try{v2_seo_seasonal_review_plan($maldives,$binding,['month:1:1:2026-10'],5000);coverage_fail('blocked_coverage_plan');}catch(InvalidArgumentException $e){}

$noPolicy=v2_seo_seasonal_coverage_assess($data,[],5000);
if (($noPolicy['review_ready']??true)!==false || !in_array('invalid_policy_country',$noPolicy['errors']??[],true)) coverage_fail('missing_policy');
$stalePolicy=$policy; $stalePolicy['min_freshness_seconds']=20000;
$stale=v2_seo_seasonal_coverage_assess($data,$stalePolicy,5000);
if (($stale['review_ready']??true)!==false || !in_array('month_freshness_below_policy',$stale['errors']??[],true)) coverage_fail('freshness_policy');
$deepPolicy=$policy; $deepPolicy['min_offers_per_snapshot']=2;
$depth=v2_seo_seasonal_coverage_assess($data,$deepPolicy,5000);
if (($depth['review_ready']??true)!==false || !in_array('resort_month_offer_depth_below_policy',$depth['errors']??[],true)) coverage_fail('offer_depth_policy');
$expired=v2_seo_seasonal_coverage_assess($data,$policy,20000);
if (($expired['review_ready']??true)!==false || !in_array('evidence_expired',$expired['errors']??[],true)) coverage_fail('expired_replay_should_block');
$missingClock=$data; unset($missingClock['evidence_checked_at_epoch'],$missingClock['evidence_valid_until_epoch'],$missingClock['evidence_clock_valid']);
$clockless=v2_seo_seasonal_coverage_assess($missingClock,$policy,5000);
if (($clockless['review_ready']??true)!==false || !in_array('evidence_clock_missing_or_invalid',$clockless['errors']??[],true)) coverage_fail('missing_clock_should_block');
echo "SEO_SEASONAL_COVERAGE_OK explicitPolicy=1 explicitPlan=1 reviewDataset=1 selectedDepth=1 autoSelection=0 publication=0\n";
