<?php
require_once __DIR__ . '/../v2/seo-seasonal-selected-readiness-v1.php';
function selected_ready_fail(string $m):void{fwrite(STDERR,"SEO_SEASONAL_SELECTED_READY_FAIL:$m\n");exit(1);}
$now=1000;
$item=static fn(string $key,int $offers,int $fresh,int $expires):array=>[
 'state'=>'review_only_seasonal_plan_item','page_key'=>$key,'page_type'=>'month','country_id'=>4,'region_id'=>null,'departure_id'=>1,'year'=>2026,'month'=>9,
 'parent_path'=>'/country/turkey/','offer_count'=>$offers,'evidence_checked_at_epoch'=>1000,'freshness_seconds'=>$fresh,'expires_at_epoch'=>$expires,
 'publication_allowed'=>false,'copy_allowed'=>false,
];
$plan=['state'=>'review_only_seasonal_plan','item_count'=>2,'evidence_valid_until_epoch'=>9000,'publication_candidates'=>[],'publication_allowed'=>false,'feed_publish_allowed'=>false,'copy_allowed'=>false,'items'=>[$item('month:1:4:2026-09',10,8000,9000),$item('month:1:4:2026-10',5,7000,8000)]];
$policy=['expected_items'=>2,'min_offer_count_per_item'=>3,'min_freshness_seconds_per_item'=>3600];
$out=v2_seo_seasonal_selected_readiness($plan,$policy,$now);
if(($out['state']??'')!=='selected_review_ready'||($out['review_ready']??false)!==true||($out['score']??0)!==100)selected_ready_fail('ready');
if(($out['min_observed_offer_count']??0)!==5||($out['min_observed_freshness_seconds']??0)!==7000)selected_ready_fail('observed_minima');
if(($out['publication_candidates']??null)!==[]||($out['publication_allowed']??true)!==false||($out['feed_publish_allowed']??true)!==false||($out['copy_allowed']??true)!==false)selected_ready_fail('boundary');
$shallow=$plan;$shallow['items'][1]['offer_count']=2;$blocked=v2_seo_seasonal_selected_readiness($shallow,$policy,$now);if(($blocked['review_ready']??true)!==false||!in_array('item_offer_depth_below_policy',$blocked['errors']??[],true))selected_ready_fail('shallow');
$stale=$plan;$stale['items'][0]['freshness_seconds']=100;$blocked=v2_seo_seasonal_selected_readiness($stale,$policy,$now);if(($blocked['review_ready']??true)!==false||!in_array('item_freshness_below_policy',$blocked['errors']??[],true))selected_ready_fail('freshness');
$duplicate=$plan;$duplicate['items'][1]['page_key']=$duplicate['items'][0]['page_key'];$blocked=v2_seo_seasonal_selected_readiness($duplicate,$policy,$now);if(($blocked['review_ready']??true)!==false||!in_array('duplicate_or_missing_page_key',$blocked['errors']??[],true))selected_ready_fail('duplicate');
$expired=$plan;$expired['evidence_valid_until_epoch']=1000;$blocked=v2_seo_seasonal_selected_readiness($expired,$policy,$now);if(($blocked['review_ready']??true)!==false||!in_array('plan_evidence_expired',$blocked['errors']??[],true))selected_ready_fail('expired');
$unsafe=$plan;$unsafe['publication_allowed']=true;$blocked=v2_seo_seasonal_selected_readiness($unsafe,$policy,$now);if(($blocked['review_ready']??true)!==false||!in_array('publication_boundary_crossed',$blocked['errors']??[],true))selected_ready_fail('unsafe');
echo "SEO_SEASONAL_SELECTED_READY_OK score=100 autoSelection=0 publication=0\n";
