<?php
require_once __DIR__ . '/../v2/seo-seasonal-identity-v1.php';
function identity_fail(string $m):void{fwrite(STDERR,"SEO_SEASONAL_IDENTITY_SMOKE_FAIL:$m\n");exit(1);}
$now=1000;
$base=['evidence_checked_at_epoch'=>1000,'expires_at_epoch'=>8200,'freshness_seconds'=>7200];
$rows=[
 array_merge($base,['page_key'=>'month:1:4:2026-10','page_type'=>'month','country_id'=>4,'region_id'=>null,'departure_id'=>1,'departure_year'=>2026,'departure_month'=>10,'offer_count'=>8,'observed_at'=>'x','expires_at'=>'y']),
 array_merge($base,['page_key'=>'resort_month:1:4:77:2026-10','page_type'=>'resort_month','country_id'=>4,'region_id'=>77,'departure_id'=>1,'departure_year'=>2026,'departure_month'=>10,'offer_count'=>5,'observed_at'=>'x','expires_at'=>'y']),
 array_merge($base,['page_key'=>'month:1:4:2026-11','page_type'=>'month','country_id'=>4,'region_id'=>null,'departure_id'=>1,'departure_year'=>2026,'departure_month'=>10,'offer_count'=>8]),
 array_merge($base,['page_key'=>'resort_month:1:8:0:2026-10','page_type'=>'resort_month','country_id'=>8,'region_id'=>0,'departure_id'=>1,'departure_year'=>2026,'departure_month'=>10,'offer_count'=>2]),
 array_merge($base,['page_key'=>'month:1:8:2026-10','page_type'=>'month','country_id'=>8,'region_id'=>null,'departure_id'=>1,'departure_year'=>2026,'departure_month'=>10,'offer_count'=>2,'expires_at_epoch'=>1000,'freshness_seconds'=>0]),
];
$out=v2_seo_seasonal_identity_inventory($rows,$now);
if(($out['state']??'')!=='review_only_seasonal_identity_inventory')identity_fail('state');
if(($out['identity_count']??0)!==2||($out['blocked_count']??0)!==3)identity_fail('counts');
if(($out['evidence_checked_at_epoch']??0)!==1000||($out['evidence_valid_until_epoch']??0)!==8200||($out['evidence_clock_valid']??false)!==true)identity_fail('clock');
if(($out['publication_candidates']??null)!==[]||($out['publication_allowed']??true)!==false||($out['copy_allowed']??true)!==false)identity_fail('boundary');
$json=json_encode($out);if(str_contains($json,'price')||str_contains($json,'hotel_name')||str_contains($json,'offers_json'))identity_fail('volatile_detail_leak');
$errors=array_merge(...array_map(fn($x)=>$x['errors']??[],$out['blocked']));
foreach(['page_key_mismatch','invalid_region_identity','stale_evidence'] as $required)if(!in_array($required,$errors,true))identity_fail($required);
$replayed=v2_seo_seasonal_identity_inventory(array_slice($rows,0,2),8200);
if(($replayed['identity_count']??-1)!==0||($replayed['blocked_count']??0)!==2)identity_fail('expired_replay');
$replayErrors=array_merge(...array_map(fn($x)=>$x['errors']??[],$replayed['blocked']));
if(!in_array('evidence_expired',$replayErrors,true))identity_fail('expired_error');
echo "SEO_SEASONAL_IDENTITY_OK fresh=2 blocked=3 replayBlocked=1 publication=0\n";
