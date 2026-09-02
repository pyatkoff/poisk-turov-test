<?php
require_once __DIR__ . '/../v2/seo-feed-evidence-v1.php';

function feed_fail(string $message): void { fwrite(STDERR, "SEO_FEED_EVIDENCE_FAIL:$message\n"); exit(1); }
$now = strtotime('2026-09-02T09:30:00Z');
$base = [
    'page_type'=>'resort_month',
    'page_key'=>'resort_month:1:4:77:2026-10',
    'dimensions'=>['departureId'=>1,'countryId'=>4,'regionId'=>77,'year'=>2026,'month'=>10],
    'offer_count'=>2,
    'observed_at'=>'2026-09-02T09:00:00Z',
    'expires_at'=>'2026-09-02T15:30:00Z',
    'offers'=>[
        ['tourId'=>'t2','hotelId'=>20,'hotelName'=>'Hotel B','regionId'=>77,'departureDate'=>'2026-10-15','nights'=>8,'price'=>120000,'currency'=>'RUB','observedAt'=>'2026-09-02T08:55:00Z'],
        ['tourId'=>'t1','hotelId'=>10,'hotelName'=>'Hotel A','regionId'=>77,'departureDate'=>'2026-10-12','nights'=>7,'price'=>100000,'currency'=>'RUB','observedAt'=>'2026-09-02T08:50:00Z'],
    ],
];
$report = v2_seo_feed_evidence_from_snapshot($base, $now);
if (($report['state']??'') !== 'review_only_feed_evidence') feed_fail('state');
if (($report['item_count']??0) !== 2 || ($report['blocked_count']??-1) !== 0) feed_fail('counts');
if (($report['feed_publish_allowed']??true) !== false) feed_fail('publish_boundary');
if (($report['items'][0]['hotel_id']??0) !== 10) feed_fail('deterministic_sort');
if (($report['items'][0]['price']??0) !== 100000.0) feed_fail('exact_price');
if (($report['items'][0]['expires_at_epoch']??0) !== strtotime('2026-09-02T15:30:00Z')) feed_fail('expiry_inheritance');
if (!in_array('discount',$report['forbidden_claims']??[],true)) feed_fail('discount_claim_not_forbidden');

$badRegion = $base; $badRegion['offers'][0]['regionId']=99;
$report = v2_seo_feed_evidence_from_snapshot($badRegion, $now);
if (($report['item_count']??0) !== 1 || !in_array('region_identity_mismatch',$report['blocked'][0]['errors']??[],true)) feed_fail('region_mismatch');

$staleOffer = $base; $staleOffer['offers'][0]['observedAt']='2026-08-30T08:00:00Z';
$report = v2_seo_feed_evidence_from_snapshot($staleOffer, $now, 172800);
if (($report['item_count']??0) !== 1 || !in_array('stale_offer_observation',$report['blocked'][0]['errors']??[],true)) feed_fail('stale_offer');

$badPrice = $base; $badPrice['offers'][0]['price']=0;
$report = v2_seo_feed_evidence_from_snapshot($badPrice, $now);
if (($report['item_count']??0) !== 1 || !in_array('invalid_price',$report['blocked'][0]['errors']??[],true)) feed_fail('bad_price');

$staleSnapshot = $base; $staleSnapshot['expires_at']='2026-09-02T09:29:59Z';
$report = v2_seo_feed_evidence_from_snapshot($staleSnapshot, $now);
if (($report['state']??'') !== 'blocked' || ($report['items']??null) !== []) feed_fail('stale_snapshot');

echo "SEO_FEED_EVIDENCE_OK items=2 staleSnapshotBlocked=1 staleOfferBlocked=1 publish=0\n";
