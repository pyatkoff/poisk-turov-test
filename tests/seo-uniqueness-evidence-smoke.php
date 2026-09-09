<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-uniqueness-evidence-v1.php';
require_once __DIR__.'/../v2/seo-opportunity-readiness-v2.php';
function uniq_fail(string $m):void{fwrite(STDERR,"SEO_UNIQUENESS_EVIDENCE_FAIL:$m\n");exit(1);}
$now=1800000000;
$base=[
    'page_key'=>'resort_month:turkey:4:20:09',
    'page_path'=>'/_preview/seo2/seasonal/antalya-september/',
    'query_cluster'=>'туры в анталю в сентябре',
    'source_class'=>'manual_serp_review',
    'source_ref'=>'serp-review:antalya-september:2026-09-02',
    'observed_at_epoch'=>$now-60,
    'status'=>'confirmed',
    'decision'=>'distinct',
    'competing_paths'=>['/country/turkey/antalya/'],
];
$e=v2_seo_uniqueness_evidence($base,$now);
if(($e['state']??'')!=='uniqueness_evidence_valid'||($e['status']??'')!=='confirmed'||($e['fresh']??false)!==true)uniq_fail('valid_distinct');
$s=v2_seo_uniqueness_signal_for_opportunity($e);
if(($s['status']??'')!=='confirmed'||array_key_exists('score',$s)&&$s['score']!==null)uniq_fail('distinct_signal_score');

$page=['path'=>'/_preview/seo2/seasonal/antalya-september/','page_role'=>'seasonal_tours','intent'=>'commercial_transactional'];
$signals=[];
foreach(['entity','intent','demand','content','technical','commercial_inventory'] as $key)$signals[$key]=['status'=>'confirmed','score'=>100,'observed_at_epoch'=>$now-60,'source'=>'test:'.$key];
$signals['uniqueness']=$s;
$r=v2_seo_opportunity_readiness($page,$signals,$now);
if(($r['review_candidate']??true)!==false||!in_array('uniqueness:unknown',$r['blocked_dimensions']??[],true))uniq_fail('score_policy_gate');

$merge=$base; $merge['decision']='merge'; $merge['competing_paths']=['/country/turkey/antalya/'];
$s=v2_seo_uniqueness_signal_for_opportunity(v2_seo_uniqueness_evidence($merge,$now));
if(($s['status']??'')!=='blocked'||($s['decision']??'')!=='merge')uniq_fail('merge_block');

$stale=$base; $stale['observed_at_epoch']=$now-(86400*31)-1;
$e=v2_seo_uniqueness_evidence($stale,$now);
if(($e['status']??'')!=='unknown'||($e['fresh']??true)!==false)uniq_fail('stale');

$audit=$base; $audit['source_class']='site_query_overlap_audit'; unset($audit['overlap_ratio']);
$e=v2_seo_uniqueness_evidence($audit,$now);
if(($e['status']??'')!=='unknown')uniq_fail('audit_without_overlap');
$audit['overlap_ratio']=0.21;
$e=v2_seo_uniqueness_evidence($audit,$now);
if(($e['status']??'')!=='confirmed'||($e['overlap_ratio']??null)!==0.21)uniq_fail('audit_with_overlap');

$bad=$base; $bad['competing_paths']=[$base['page_path']];
$e=v2_seo_uniqueness_evidence($bad,$now);
if(($e['state']??'')!=='uniqueness_evidence_invalid'||($e['status']??'')!=='unknown')uniq_fail('self_competing');

foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $flag)if(($e[$flag]??true)!==false)uniq_fail('boundary_'.$flag);
echo "SEO_UNIQUENESS_EVIDENCE_OK provenance=1 freshnessDays=31 mergeBlock=1 scoringPolicyRequired=1 publication=0 indexation=0\n";
