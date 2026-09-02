<?php
require_once __DIR__ . '/../v2/seo-seasonal-family-binding-v1.php';
function seasonal_binding_fail(string $m): void { fwrite(STDERR,"SEO_SEASONAL_BINDING_FAIL:$m\n"); exit(1); }
$now=1000;
$country=['type'=>'country','path'=>'/country/turkey/','data'=>['search_state'=>['country'=>4]]];
$resorts=[
 ['type'=>'resort','path'=>'/country/turkey/kemer/','data'=>['search_state'=>['country'=>4,'region'=>77]]],
 ['type'=>'resort','path'=>'/country/turkey/side/','data'=>['search_state'=>['country'=>4,'region'=>80]]],
];
$base=static fn(array $x):array=>array_merge([
 'state'=>'fresh_review_identity','page_type'=>'month','country_id'=>4,'region_id'=>null,'departure_id'=>1,'year'=>2026,'month'=>10,
 'evidence_checked_at_epoch'=>1000,'expires_at_epoch'=>8200,'freshness_seconds'=>7200,'publication_allowed'=>false,'copy_allowed'=>false,
],$x);
$inventory=[
 'state'=>'review_only_seasonal_identity_inventory','evidence_checked_at_epoch'=>1000,'evidence_valid_until_epoch'=>8200,'evidence_clock_valid'=>true,
 'publication_allowed'=>false,'copy_allowed'=>false,'publication_candidates'=>[],
 'identities'=>[
  $base(['page_key'=>'month:1:4:2026-10']),
  $base(['page_key'=>'resort_month:1:4:77:2026-10','page_type'=>'resort_month','region_id'=>77]),
  $base(['page_key'=>'resort_month:1:4:999:2026-10','page_type'=>'resort_month','region_id'=>999]),
  $base(['page_key'=>'month:1:8:2026-10','country_id'=>8]),
  $base(['page_key'=>'month:1:4:2026-10']),
 ],
];
$out=v2_seo_seasonal_family_binding($country,$resorts,$inventory,$now);
if(($out['state']??'')!=='review_only_seasonal_family_binding')seasonal_binding_fail('state');
if(($out['bound_count']??0)!==2||($out['blocked_count']??0)!==3)seasonal_binding_fail('counts');
if(($out['registered_region_count']??0)!==2)seasonal_binding_fail('region_count');
if(($out['evidence_valid_until_epoch']??0)!==8200)seasonal_binding_fail('clock_passthrough');
if(($out['publication_candidates']??null)!==[]||($out['publication_allowed']??true)!==false||($out['copy_allowed']??true)!==false)seasonal_binding_fail('boundary');
$parents=[];foreach($out['bound'] as $row)$parents[$row['page_key']]=$row['parent_path'];
if(($parents['month:1:4:2026-10']??'')!=='/country/turkey/')seasonal_binding_fail('country_parent');
if(($parents['resort_month:1:4:77:2026-10']??'')!=='/country/turkey/kemer/')seasonal_binding_fail('resort_parent');
$errors=array_merge(...array_map(static fn(array $x):array=>$x['errors']??[],$out['blocked']));
foreach(['unregistered_region_identity','country_identity_mismatch','duplicate_page_key'] as $required)if(!in_array($required,$errors,true))seasonal_binding_fail($required);
$badResorts=$resorts;$badResorts[]=['type'=>'resort','path'=>'/country/turkey/fake/','data'=>['search_state'=>['country'=>4,'region'=>77]]];
try{v2_seo_seasonal_family_binding($country,$badResorts,$inventory,$now);seasonal_binding_fail('duplicate_region_not_rejected');}catch(InvalidArgumentException $e){}
$unsafe=$inventory;$unsafe['publication_allowed']=true;
try{v2_seo_seasonal_family_binding($country,$resorts,$unsafe,$now);seasonal_binding_fail('unsafe_inventory_not_rejected');}catch(InvalidArgumentException $e){}
try{v2_seo_seasonal_family_binding($country,$resorts,$inventory,8200);seasonal_binding_fail('expired_inventory_not_rejected');}catch(InvalidArgumentException $e){}
echo "SEO_SEASONAL_BINDING_OK bound=2 blocked=3 replayBlocked=1 publication=0\n";
