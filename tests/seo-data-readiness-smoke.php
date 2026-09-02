<?php
require_once __DIR__ . '/../v2/seo-data-readiness-v1.php';
function data_ready_fail(string $m): void { fwrite(STDERR,"SEO_DATA_READINESS_SMOKE_FAIL:$m\n"); exit(1); }
$rows = [
 ['country_id'=>1,'country_name'=>'Египет','page_type'=>'month','snapshot_count'=>12,'identity_count'=>12,'offer_count'=>90,'min_offer_count'=>3,'oldest_observed_at'=>'2026-09-02 05:00:00','newest_observed_at'=>'2026-09-02 09:00:00','earliest_expires_at'=>'2026-09-02 13:00:00','latest_expires_at'=>'2026-09-02 17:00:00','min_freshness_seconds'=>7200,'max_freshness_seconds'=>21600,'usable_snapshot_count'=>12],
 ['country_id'=>1,'country_name'=>'Египет','page_type'=>'resort_month','snapshot_count'=>5,'identity_count'=>5,'offer_count'=>24,'min_offer_count'=>2,'oldest_observed_at'=>'2026-09-02 05:10:00','newest_observed_at'=>'2026-09-02 09:10:00','earliest_expires_at'=>'2026-09-02 13:10:00','latest_expires_at'=>'2026-09-02 17:10:00','min_freshness_seconds'=>7800,'max_freshness_seconds'=>22200,'usable_snapshot_count'=>4],
 ['country_id'=>4,'country_name'=>'Турция','page_type'=>'month','snapshot_count'=>8,'identity_count'=>8,'offer_count'=>61,'min_offer_count'=>4,'oldest_observed_at'=>'2026-09-02 06:00:00','newest_observed_at'=>'2026-09-02 09:00:00','earliest_expires_at'=>'2026-09-02 14:00:00','latest_expires_at'=>'2026-09-02 17:00:00','min_freshness_seconds'=>10800,'max_freshness_seconds'=>21600,'usable_snapshot_count'=>8],
 ['country_id'=>8,'country_name'=>'Мальдивы','page_type'=>'hotel','snapshot_count'=>99,'usable_snapshot_count'=>99],
];
$out = v2_seo_data_readiness_summary($rows,[8,4,1]);
if (($out['state']??'')!=='review_only_data_readiness') data_ready_fail('state');
if (($out['requested_country_ids']??[])!==[1,4,8]) data_ready_fail('country_order');
if (($out['country_count']??0)!==2 || ($out['missing_country_ids']??[])!==[8]) data_ready_fail('missing_country');
if (($out['snapshot_count']??0)!==25 || ($out['usable_snapshot_count']??0)!==24 || ($out['blocked_snapshot_count']??0)!==1) data_ready_fail('counts');
if (($out['all_unexpired_snapshots_usable']??true)!==false) data_ready_fail('partial_usability');
if (($out['publication_allowed']??true)!==false || ($out['feed_publish_allowed']??true)!==false || ($out['copy_allowed']??true)!==false) data_ready_fail('publication_boundary');
if (array_key_exists('min_price',$out) || str_contains(json_encode($out),'hotel_name')) data_ready_fail('volatile_detail_leak');
echo "SEO_DATA_READINESS_SMOKE_OK countries=2 missing=1 usable=24 blocked=1 publication=0\n";
