<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-opportunity-scoring-policy-v1.php';
function scoring_fail(string $m):void{fwrite(STDERR,"SEO_OPPORTUNITY_SCORING_FAIL:$m\n");exit(1);}
$policy=[
    'policy_id'=>'reviewed-example',
    'version'=>'1',
    'source_ref'=>'test-fixture-only',
    'approved_at_epoch'=>1800000000,
    'dimensions'=>[
        'demand'=>['weight'=>60,'rules'=>[
            ['field'=>'metrics.impressions','operator'=>'gte','value'=>100,'score'=>80],
            ['field'=>'metrics.impressions','operator'=>'gte','value'=>1,'score'=>40],
        ]],
        'uniqueness'=>['weight'=>40,'rules'=>[
            ['field'=>'decision','operator'=>'eq','value'=>'distinct','score'=>100],
        ]],
    ],
];
$p=v2_seo_opportunity_scoring_policy($policy);
if(($p['state']??'')!=='opportunity_scoring_policy_valid')scoring_fail('policy_valid');

$packet=[
    'state'=>'opportunity_evidence_review_ready',
    'page_key'=>'hotel:4:123',
    'path'=>'/_preview/hotel/example/',
    'query_cluster'=>'туры в example hotel',
    'packet_sha256'=>str_repeat('a',64),
    'evidence_fresh'=>true,
    'uniqueness_distinct'=>true,
    'demand'=>['metrics'=>['impressions'=>120]],
    'uniqueness'=>['decision'=>'distinct','overlap_ratio'=>0.1],
];
$r=v2_seo_opportunity_score_evidence_packet($packet,$policy);
if(($r['state']??'')!=='opportunity_scored_review_only'||($r['score']??0)!==88)scoring_fail('score');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $flag){if(($r[$flag]??true)!==false)scoring_fail('boundary_'.$flag);}
if(($r['publication_candidates']??null)!==[]||($r['explicit_user_launch_approval_required']??false)!==true)scoring_fail('launch_boundary');

$missing=$policy; unset($missing['dimensions']['demand']['rules']);
$r=v2_seo_opportunity_score_evidence_packet($packet,$missing);
if(($r['state']??'')!=='opportunity_scoring_blocked'||!in_array('invalid_scoring_policy',$r['errors']??[],true))scoring_fail('missing_rules');

$badWeights=$policy; $badWeights['dimensions']['demand']['weight']=50;
$p=v2_seo_opportunity_scoring_policy($badWeights);
if(($p['state']??'')!=='opportunity_scoring_policy_invalid'||!in_array('weights_must_total_100',$p['errors']??[],true))scoring_fail('weights');

$noMatch=$packet; $noMatch['demand']['metrics']['impressions']=0;
$r=v2_seo_opportunity_score_evidence_packet($noMatch,$policy);
if(($r['state']??'')!=='opportunity_scoring_blocked'||($r['score']??1)!==null||!in_array('demand_no_matching_rule',$r['errors']??[],true))scoring_fail('no_match');

$stale=$packet; $stale['evidence_fresh']=false;
$r=v2_seo_opportunity_score_evidence_packet($stale,$policy);
if(($r['state']??'')!=='opportunity_scoring_blocked'||!in_array('evidence_not_fresh',$r['errors']??[],true))scoring_fail('stale');

echo "SEO_OPPORTUNITY_SCORING_OK explicitPolicy=1 defaults=0 failClosed=1 publication=0 indexation=0\n";
