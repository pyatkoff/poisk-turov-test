<?php
require_once __DIR__ . '/../v2/seo-seasonal-identity-v1.php';
function identity_fail(string $m):void{fwrite(STDERR,"SEO_SEASONAL_IDENTITY_SMOKE_FAIL:$m\n");exit(1);}
$rows=[
 ['page_key'=>'month:1:4:2026-10','page_type'=>'month','country_id'=>4,'region_id'=>null,'departure_id'=>1,'departure_year'=>2026,'departure_month'=>10,'offer_count'=>8,'observed_at'=>'2026-09-02 05:00:00','expires_at'=>'2026-09-02 13:00:00','freshness_seconds'=>7200],
 ['page_key'=>'resort_month:1:4:77:2026-10','page_type'=>'resort_month','country_id'=>4,'region_id'=>77,'departure_id'=>1,'departure_year'=>2026,'departure_month'=>10,'offer_count'=>5,'observed_at'=>'2026-09-02 05:00:00','expires_at'=>'2026-09-02 13:00:00','freshness_seconds'=>7200],
 ['page_key'=>'month:1:4:2026-11','page_type'=>'month','country_id'=>4,'region_id'=>null,'departure_id'=>1,'departure_year'=>2026,'departure_month'=>10,'offer_count'=>8,'freshness_seconds'=>7200],
 ['page_key'=>'resort_month:1:8:0:2026-10','page_type'=>'resort_month','country_id'=>8,'region_id'=>0,'departure_id'=>1,'departure_year'=>2026,'departure_month'=>10,'offer_count'=>2,'freshness_seconds'=>7200],
 ['page_key'=>'month:1:8:2026-10','page_type'=>'month','country_id'=>8,'region_id'=>null,'departure_id'=>1,'departure_year'=>2026,'departure_month'=>10,'offer_count'=>2,'freshness_seconds'=>0],
];
$out=v2_seo_seasonal_identity_inventory($rows);
if(($out['state']??'')!=='review_only_seasonal_identity_inventory')identity_fail('state');
if(($out['identity_count']??0)!==2||($out['blocked_count']??0)!==3)identity_fail('counts');
if(($out['publication_candidates']??null)!==[])identity_fail('publication_candidates');
if(($out['publication_allowed']??true)!==false||($out['copy_allowed']??true)!==false)identity_fail('boundary');
$json=json_encode($out);
if(str_contains($json,'price')||str_contains($json,'hotel_name')||str_contains($json,'offers_json'))identity_fail('volatile_detail_leak');
$errors=array_merge(...array_map(fn($x)=>$x['errors']??[],$out['blocked']));
foreach(['page_key_mismatch','invalid_region_identity','stale_evidence'] as $required)if(!in_array($required,$errors,true))identity_fail($required);
echo "SEO_SEASONAL_IDENTITY_OK fresh=2 blocked=3 publication=0\n";
