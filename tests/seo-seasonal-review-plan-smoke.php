<?php
require_once __DIR__ . '/../v2/seo-seasonal-review-plan-v1.php';
function seasonal_plan_fail(string $m):void{fwrite(STDERR,"SEO_SEASONAL_PLAN_FAIL:$m\n");exit(1);}
$now=1000;
$coverage=[
 'state'=>'review_ready','review_ready'=>true,'country_id'=>4,'publication_allowed'=>false,'feed_publish_allowed'=>false,'copy_allowed'=>false,
 'checks'=>['evidence_clock'=>['pass'=>true,'checked_at_epoch'=>900,'valid_until_epoch'=>8000,'evaluated_at_epoch'=>1000]],
];
$binding=[
 'state'=>'review_only_seasonal_family_binding','country_id'=>4,'evidence_valid_until_epoch'=>7000,
 'publication_allowed'=>false,'copy_allowed'=>false,'publication_candidates'=>[],
 'bound'=>[
  ['page_key'=>'month:1:4:2026-10','page_type'=>'month','country_id'=>4,'region_id'=>null,'departure_id'=>1,'year'=>2026,'month'=>10,'parent_path'=>'/country/turkey/','expires_at_epoch'=>7000,'publication_allowed'=>false,'copy_allowed'=>false],
  ['page_key'=>'resort_month:1:4:77:2026-10','page_type'=>'resort_month','country_id'=>4,'region_id'=>77,'departure_id'=>1,'year'=>2026,'month'=>10,'parent_path'=>'/country/turkey/kemer/','expires_at_epoch'=>6500,'publication_allowed'=>false,'copy_allowed'=>false],
 ],
];
$out=v2_seo_seasonal_review_plan($coverage,$binding,['resort_month:1:4:77:2026-10','month:1:4:2026-10'],$now,3);
if(($out['state']??'')!=='review_only_seasonal_plan'||($out['item_count']??0)!==2)seasonal_plan_fail('state_count');
if(($out['evidence_valid_until_epoch']??0)!==7000)seasonal_plan_fail('validity_window');
if(($out['publication_candidates']??null)!==[]||($out['publication_allowed']??true)!==false||($out['feed_publish_allowed']??true)!==false||($out['copy_allowed']??true)!==false)seasonal_plan_fail('boundary');
if(($out['items'][0]['page_key']??'')!=='month:1:4:2026-10')seasonal_plan_fail('deterministic_order');
try{v2_seo_seasonal_review_plan($coverage,$binding,['month:1:4:2026-11'],$now);seasonal_plan_fail('unknown_key');}catch(InvalidArgumentException $e){}
try{v2_seo_seasonal_review_plan($coverage,$binding,['month:1:4:2026-10','month:1:4:2026-10'],$now);seasonal_plan_fail('duplicate_key');}catch(InvalidArgumentException $e){}
$blockedCoverage=$coverage;$blockedCoverage['review_ready']=false;$blockedCoverage['state']='review_blocked';
try{v2_seo_seasonal_review_plan($blockedCoverage,$binding,['month:1:4:2026-10'],$now);seasonal_plan_fail('blocked_coverage');}catch(InvalidArgumentException $e){}
try{v2_seo_seasonal_review_plan($coverage,$binding,['month:1:4:2026-10'],7000);seasonal_plan_fail('expired_binding');}catch(InvalidArgumentException $e){}
$wrongCountry=$coverage;$wrongCountry['country_id']=8;
try{v2_seo_seasonal_review_plan($wrongCountry,$binding,['month:1:4:2026-10'],$now);seasonal_plan_fail('country_mismatch');}catch(InvalidArgumentException $e){}
echo "SEO_SEASONAL_PLAN_OK explicit=2 autoSelection=0 publication=0\n";
