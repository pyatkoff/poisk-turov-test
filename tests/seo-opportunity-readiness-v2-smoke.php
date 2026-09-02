<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-opportunity-readiness-v2.php';
function opp_fail(string $m):void{fwrite(STDERR,"SEO_OPPORTUNITY_V2_FAIL:$m\n");exit(1);}
$now=1800000000;
$page=['path'=>'/_preview/seo2/seasonal/antalya-september/','page_role'=>'seasonal_tours','intent'=>'commercial_transactional'];
$signals=[];
foreach(['entity','intent','demand','uniqueness','content','technical','commercial_inventory'] as $key){
    $signals[$key]=['status'=>'confirmed','score'=>90,'observed_at_epoch'=>$now-60,'source'=>'test:'.$key];
}
$ready=v2_seo_opportunity_readiness($page,$signals,$now);
if(($ready['state']??'')!=='opportunity_review_ready'||($ready['review_candidate']??false)!==true||($ready['opportunity_score']??0)!==90)opp_fail('ready');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $flag){if(($ready[$flag]??true)!==false)opp_fail('boundary_'.$flag);}
if(($ready['publication_candidates']??null)!==[]||($ready['explicit_user_launch_approval_required']??false)!==true)opp_fail('launch_boundary');

$unknown=$signals; unset($unknown['demand']);
$r=v2_seo_opportunity_readiness($page,$unknown,$now);
if(($r['review_candidate']??true)!==false||!in_array('demand:unknown',$r['blocked_dimensions']??[],true))opp_fail('unknown_demand');

$stale=$signals; $stale['commercial_inventory']['observed_at_epoch']=$now-86401;
$r=v2_seo_opportunity_readiness($page,$stale,$now);
if(($r['review_candidate']??true)!==false||($r['dimensions']['commercial_inventory']['status']??'')!=='unknown')opp_fail('stale_inventory');

$info=['path'=>'/_preview/seo2/editorial/antalya-september/','page_role'=>'informational_guide','intent'=>'informational'];
$infoSignals=$signals; unset($infoSignals['commercial_inventory']);
$r=v2_seo_opportunity_readiness($info,$infoSignals,$now);
if(($r['review_candidate']??false)!==true||($r['dimensions']['commercial_inventory']['required']??true)!==false)opp_fail('info_inventory_optional');

$bad=$page; $bad['intent']='informational';
$r=v2_seo_opportunity_readiness($bad,$signals,$now);
if(($r['state']??'')!=='invalid'||!in_array('role_intent_mismatch',$r['errors']??[],true))opp_fail('role_intent');

echo "SEO_OPPORTUNITY_V2_OK demandGate=1 uniquenessGate=1 inventoryFreshness=1 publication=0 indexation=0\n";
