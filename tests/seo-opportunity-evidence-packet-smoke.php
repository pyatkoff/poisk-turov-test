<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-opportunity-evidence-packet-v1.php';
function packet_fail(string $m):void{fwrite(STDERR,"SEO_OPPORTUNITY_EVIDENCE_PACKET_FAIL:$m\n");exit(1);}
$now=1800000000;
$page=['page_key'=>'resort_month:turkey:4:20:09','path'=>'/_preview/seo2/seasonal/antalya-september/','query_cluster'=>'туры в анталю в сентябре'];
$demand=[
    'page_key'=>$page['page_key'],'query_cluster'=>$page['query_cluster'],
    'source_class'=>'manual_serp_review','source_ref'=>'serp:demand:antalya-september:2026-09-02',
    'observed_at_epoch'=>$now-60,'status'=>'confirmed','serp_intent'=>'commercial'
];
$uniq=[
    'page_key'=>$page['page_key'],'page_path'=>$page['path'],'query_cluster'=>$page['query_cluster'],
    'source_class'=>'manual_serp_review','source_ref'=>'serp:uniqueness:antalya-september:2026-09-02',
    'observed_at_epoch'=>$now-60,'status'=>'confirmed','decision'=>'distinct','competing_paths'=>['/country/turkey/antalya/']
];
$r=v2_seo_opportunity_evidence_packet($page,$demand,$uniq,$now);
if(($r['state']??'')!=='opportunity_evidence_review_ready'||($r['evidence_fresh']??false)!==true||($r['evidence_confirmed']??false)!==true||($r['uniqueness_distinct']??false)!==true)packet_fail('ready');
if(($r['scoring_policy_pending']??false)!==true||!preg_match('/^[a-f0-9]{64}$/',(string)($r['packet_sha256']??'')))packet_fail('score_fingerprint');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $flag)if(($r[$flag]??true)!==false)packet_fail('boundary_'.$flag);
if(($r['publication_candidates']??null)!==[]||($r['explicit_user_launch_approval_required']??false)!==true)packet_fail('publication_boundary');

$badDemand=$demand; $badDemand['query_cluster']='туры в турцию';
$r=v2_seo_opportunity_evidence_packet($page,$badDemand,$uniq,$now);
if(($r['state']??'')!=='opportunity_evidence_packet_invalid'||!in_array('demand_query_cluster_mismatch',$r['errors']??[],true))packet_fail('cluster_mismatch');

$merge=$uniq; $merge['decision']='merge';
$r=v2_seo_opportunity_evidence_packet($page,$demand,$merge,$now);
if(($r['state']??'')!=='opportunity_evidence_incomplete'||($r['uniqueness_distinct']??true)!==false||($r['uniqueness_signal']['status']??'')!=='blocked')packet_fail('merge_blocks');

$stale=$demand; $stale['observed_at_epoch']=$now-(86400*31)-1;
$r=v2_seo_opportunity_evidence_packet($page,$stale,$uniq,$now);
if(($r['state']??'')!=='opportunity_evidence_incomplete'||($r['evidence_fresh']??true)!==false)packet_fail('stale');

echo "SEO_OPPORTUNITY_EVIDENCE_PACKET_OK identityBound=1 queryClusterBound=1 freshnessDays=31 scoringPolicyPending=1 publication=0 indexation=0\n";
