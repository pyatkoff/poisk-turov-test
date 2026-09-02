<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-demand-evidence-v1.php';
require_once __DIR__.'/../v2/seo-opportunity-readiness-v2.php';
function demand_fail(string $m):void{fwrite(STDERR,"SEO_DEMAND_EVIDENCE_FAIL:$m\n");exit(1);}
$now=1800000000;
$record=[
 'page_key'=>'resort_month:1:4:20:2026-09','query_cluster'=>'туры в анталю в сентябре','source_class'=>'keyword_research_export','source_ref'=>'review-import-2026-09','observed_at_epoch'=>$now-60,'status'=>'confirmed','metrics'=>['monthly_searches'=>120],'serp_intent'=>'commercial'
];
$e=v2_seo_demand_evidence($record,$now);
if(($e['state']??'')!=='demand_evidence_valid'||($e['status']??'')!=='confirmed'||($e['fresh']??false)!==true) demand_fail('valid');
$s=v2_seo_demand_signal_for_opportunity($e);
if(($s['status']??'')!=='confirmed'||array_key_exists('score',$s)===false||$s['score']!==null||($s['requires_explicit_scoring_policy']??false)!==true) demand_fail('no_auto_score');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','route_launch_allowed'] as $flag)if(($e[$flag]??true)!==false)demand_fail('boundary_'.$flag);

// The opportunity layer still blocks this evidence until a separately reviewed
// 0..100 demand scoring policy exists. Raw monthly searches are not auto-scored.
$page=['path'=>'/_preview/seo2/seasonal/antalya-september/','page_role'=>'seasonal_tours','intent'=>'commercial_transactional'];
$signals=[];
foreach(['entity','intent','uniqueness','content','technical','commercial_inventory'] as $key)$signals[$key]=['status'=>'confirmed','score'=>100,'observed_at_epoch'=>$now-60,'source'=>'test:'.$key];
$signals['demand']=$s;
$r=v2_seo_opportunity_readiness($page,$signals,$now);
if(($r['review_candidate']??true)!==false||!in_array('demand:unknown',$r['blocked_dimensions']??[],true))demand_fail('raw_metric_bypassed_scoring_policy');

$stale=$record;$stale['observed_at_epoch']=$now-(86400*31)-1;
$e=v2_seo_demand_evidence($stale,$now);
if(($e['status']??'')!=='unknown'||($e['fresh']??true)!==false)demand_fail('stale');
$empty=$record;unset($empty['metrics']);$empty['serp_intent']='';
$e=v2_seo_demand_evidence($empty,$now);
if(($e['status']??'')!=='unknown')demand_fail('empty_confirmed');
$bad=$record;$bad['source_class']='invented_source';
$e=v2_seo_demand_evidence($bad,$now);
if(($e['state']??'')!=='demand_evidence_invalid'||($e['status']??'')!=='unknown')demand_fail('unsupported_source');

echo "SEO_DEMAND_EVIDENCE_OK provenance=1 freshnessDays=31 autoScore=0 publication=0 indexation=0\n";
