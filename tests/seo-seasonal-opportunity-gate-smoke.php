<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-seasonal-opportunity-gate-v1.php';
function season_opp_fail(string $m):void{fwrite(STDERR,"SEO_SEASONAL_OPPORTUNITY_GATE_FAIL:$m\n");exit(1);}
$now=1800000000;
$page=[
 'page_key'=>'resort_month:1:4:20:2026-09','page_role'=>'commercial_tour_landing','search_intent'=>'commercial_transactional',
 'path'=>'/_preview/seo2/seasonal/antalya-september/','search_state'=>['country'=>4,'region'=>20],
 'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'canonical_allowed'=>false,'route_launch_allowed'=>false,'publication_candidates'=>[],
];
$r=v2_seo_seasonal_opportunity_gate($page,[],$now);
if(($r['state']??'')!=='seasonal_opportunity_evidence_blocked'||($r['opportunity']['review_candidate']??true)!==false)season_opp_fail('missing_signals');
$dims=$r['opportunity']['blocked_dimensions']??[];
foreach(['demand:unknown','uniqueness:unknown','content:unknown','technical:unknown','commercial_inventory:unknown'] as $need)if(!in_array($need,$dims,true))season_opp_fail('missing_'.$need);
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $flag)if(($r[$flag]??true)!==false)season_opp_fail('boundary_'.$flag);

$signals=[];
foreach(['demand','uniqueness','content','technical','commercial_inventory'] as $key)$signals[$key]=['status'=>'confirmed','score'=>90,'observed_at_epoch'=>$now-60,'source'=>'test:'.$key];
$r=v2_seo_seasonal_opportunity_gate($page,$signals,$now);
if(($r['state']??'')!=='seasonal_opportunity_review_ready'||($r['opportunity']['review_candidate']??false)!==true)season_opp_fail('confirmed');
if(($r['publication_allowed']??true)!==false||($r['indexation_allowed']??true)!==false||($r['publication_candidates']??null)!==[])season_opp_fail('confirmed_launch_leak');

$stale=$signals;$stale['commercial_inventory']['observed_at_epoch']=$now-(86400*3)-1;
$r=v2_seo_seasonal_opportunity_gate($page,$stale,$now);
if(($r['state']??'')!=='seasonal_opportunity_evidence_blocked'||!in_array('commercial_inventory:unknown',$r['opportunity']['blocked_dimensions']??[],true))season_opp_fail('72h_inventory');

$bad=$page;$bad['search_state']=['country'=>4,'region'=>999];
$r=v2_seo_seasonal_opportunity_gate($bad,$signals,$now);
if(($r['state']??'')!=='seasonal_intent_blocked'||($r['opportunity']??'x')!==null)season_opp_fail('identity');
echo "SEO_SEASONAL_OPPORTUNITY_GATE_OK demandGate=1 uniquenessGate=1 inventoryHours=72 publication=0 indexation=0\n";
