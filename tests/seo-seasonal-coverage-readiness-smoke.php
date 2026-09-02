<?php
require_once __DIR__ . '/../v2/seo-seasonal-coverage-readiness-v1.php';
function coverage_fail(string $m): void { fwrite(STDERR,"SEO_SEASONAL_COVERAGE_FAIL:$m\n"); exit(1); }

$data = [
 'state'=>'review_only_data_readiness',
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
$egypt=v2_seo_seasonal_coverage_assess($data,$policy);
if (($egypt['review_ready']??false)!==true || ($egypt['state']??'')!=='review_ready') coverage_fail('egypt_policy');
if (($egypt['score']??0)!==100) coverage_fail('egypt_score');
if (($egypt['publication_allowed']??true)!==false || ($egypt['copy_allowed']??true)!==false) coverage_fail('publication_boundary');

$maldivesPolicy=$policy; $maldivesPolicy['country_id']=8;
$maldives=v2_seo_seasonal_coverage_assess($data,$maldivesPolicy);
if (($maldives['review_ready']??true)!==false) coverage_fail('maldives_should_block');
if (!in_array('month_identity_coverage_below_policy',$maldives['errors']??[],true)) coverage_fail('maldives_month_blocker');
if (!in_array('resort_month_identity_coverage_below_policy',$maldives['errors']??[],true)) coverage_fail('maldives_resort_blocker');

$noPolicy=v2_seo_seasonal_coverage_assess($data,[]);
if (($noPolicy['review_ready']??true)!==false) coverage_fail('missing_policy_should_block');
if (!in_array('invalid_policy_country',$noPolicy['errors']??[],true)) coverage_fail('missing_policy_country');

$stalePolicy=$policy; $stalePolicy['min_freshness_seconds']=20000;
$stale=v2_seo_seasonal_coverage_assess($data,$stalePolicy);
if (($stale['review_ready']??true)!==false || !in_array('month_freshness_below_policy',$stale['errors']??[],true)) coverage_fail('freshness_policy');

$deepPolicy=$policy; $deepPolicy['min_offers_per_snapshot']=2;
$depth=v2_seo_seasonal_coverage_assess($data,$deepPolicy);
if (($depth['review_ready']??true)!==false || !in_array('resort_month_offer_depth_below_policy',$depth['errors']??[],true)) coverage_fail('offer_depth_policy');

echo "SEO_SEASONAL_COVERAGE_OK explicitPolicy=1 egyptReady=1 maldivesBlocked=1 publication=0\n";
