<?php
require_once __DIR__ . '/../v2/seo-seasonal-candidate-v1.php';

function candidate_fail(string $message): void { fwrite(STDERR, "SEO_SEASONAL_CANDIDATE_FAIL:$message\n"); exit(1); }
$now = strtotime('2026-09-02T09:30:00Z');
$fresh = [
    'source'=>'seo_offer_snapshot','scope'=>'country_month','page_key'=>'month:1:4:2026-10',
    'country_id'=>4,'region_id'=>null,'departure_id'=>1,'year'=>2026,'month'=>10,'offer_count'=>5,
    'observed_at'=>'2026-09-02T08:30:00Z','expires_at'=>'2026-09-02T15:30:00Z',
];
$fresher = $fresh; $fresher['expires_at']='2026-09-02T17:30:00Z'; $fresher['offer_count']=7;
$stale = [
    'source'=>'seo_offer_snapshot','scope'=>'resort_month','page_key'=>'resort_month:1:4:77:2026-10',
    'country_id'=>4,'region_id'=>77,'departure_id'=>1,'year'=>2026,'month'=>10,'offer_count'=>3,
    'observed_at'=>'2026-09-02T08:00:00Z','expires_at'=>'2026-09-02T09:29:59Z',
];
$inventory = v2_seo_seasonal_candidate_inventory([$fresh,$stale,$fresher], $now);
if (($inventory['state']??'') !== 'review_only_seasonal_candidate_inventory') candidate_fail('state');
if (($inventory['candidate_count']??0) !== 1) candidate_fail('candidate_count');
if (($inventory['blocked_count']??0) !== 1) candidate_fail('blocked_count');
if (($inventory['publication_candidates']??null) !== []) candidate_fail('publication_boundary');
$candidate = $inventory['candidates'][0] ?? [];
if (($candidate['page_key']??'') !== 'month:1:4:2026-10') candidate_fail('identity');
if (($candidate['offer_count']??0) !== 7) candidate_fail('freshest_evidence_not_selected');
if (($candidate['publication_allowed']??true) !== false || ($candidate['copy_allowed']??true) !== false) candidate_fail('review_boundary');
if (array_key_exists('min_price',$candidate) || array_key_exists('price',$candidate)) candidate_fail('price_leaked');
echo "SEO_SEASONAL_CANDIDATE_OK candidate=1 staleBlocked=1 publication=0 copy=0\n";
