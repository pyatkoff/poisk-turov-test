<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-search-feedback-readiness-v1.php';

function feedback_readiness_fail(string $message): never
{
    fwrite(STDERR,"SEO_SEARCH_FEEDBACK_READINESS_FAIL:$message\n");
    exit(1);
}

$now=1800000000;
$empty=v2_seo_search_feedback_readiness([],[],$now);
if(($empty['state']??'')!=='search_feedback_readiness_blocked'||($empty['launch_path_count']??0)!==8)feedback_readiness_fail('empty_state');
if(($empty['counts']['missing']??0)!==8||($empty['policy_status']??'')!=='missing'||($empty['review_ready']??true)!==false)feedback_readiness_fail('empty_counts');
foreach($empty['pages'] as $page){
    if(($page['evidence_status']??'')!=='missing'||!array_key_exists('feedback_sha256',$page)||$page['feedback_sha256']!==null)feedback_readiness_fail('missing_semantics');
}

$rows=[];$i=0;
foreach(v2_seo_controlled_launch_paths() as $path){
    $i++;
    $rows[]=[
        'path'=>$path,
        'source_class'=>$i%2===0?'yandex_webmaster_export':'google_search_console_export',
        'source_ref'=>'fixture://feedback/'.$i,
        'collected_at_epoch'=>$now-60,
        'period_start_epoch'=>$now-7*86400,
        'period_end_epoch'=>$now-3600,
        'metrics'=>['impressions'=>1000+$i,'clicks'=>50,'avg_position'=>7.5,'ctr'=>0.05,'query_count'=>20],
    ];
}
$policy=[
    'policy_id'=>'fixture-feedback-readiness',
    'version'=>'1',
    'source_ref'=>'fixture://approved-policy',
    'approved_at_epoch'=>$now-300,
    'rules'=>[[
        'rule_id'=>'hold-all-fixture',
        'recommendation'=>'hold_review',
        'conditions'=>[['field'=>'metrics.impressions','operator'=>'gte','value'=>0]],
    ]],
];
$ready=v2_seo_search_feedback_readiness($rows,$policy,$now);
if(($ready['state']??'')!=='search_feedback_readiness_ready'||($ready['review_ready']??false)!==true)feedback_readiness_fail('ready_state');
if(($ready['counts']['ready']??0)!==8||($ready['policy_status']??'')!=='ready'||($ready['evidence_complete']??false)!==true)feedback_readiness_fail('ready_counts');
if(($ready['publication_candidates']??null)!==[])feedback_readiness_fail('publication_candidates');
foreach(['automatic_execution_allowed','automatic_expand_allowed','automatic_deindex_allowed','publication_allowed','indexation_change_allowed','sitemap_change_allowed','canonical_change_allowed','route_change_allowed','hotel_tours_indexation_allowed','hotel_tours_sitemap_allowed','search_contract_changes','tourvisor_contract_changes','pricing_contract_changes','lead_contract_changes','metrika_contract_changes'] as $flag){
    if(($ready[$flag]??true)!==false)feedback_readiness_fail('boundary_'.$flag);
}

$stale=$rows;
$stale[0]['collected_at_epoch']=$now-8*86400;
$stale[0]['period_end_epoch']=$stale[0]['collected_at_epoch']-3600;
$stale[0]['period_start_epoch']=$stale[0]['period_end_epoch']-7*86400;
$blocked=v2_seo_search_feedback_readiness($stale,$policy,$now);
if(($blocked['counts']['stale']??0)!==1||($blocked['review_ready']??true)!==false)feedback_readiness_fail('stale');

$invalidPolicy=$policy;
$invalidPolicy['source_ref']='';
$blocked=v2_seo_search_feedback_readiness($rows,$invalidPolicy,$now);
if(($blocked['policy_status']??'')!=='invalid'||($blocked['review_ready']??true)!==false)feedback_readiness_fail('invalid_policy');

$hotel=$rows;
$hotel[]=$rows[0];
$hotel[count($hotel)-1]['path']='/country/maldives/hotel/the-westin-maldives-miriandhoo-resort-65108/';
$blocked=v2_seo_search_feedback_readiness($hotel,$policy,$now);
if(($blocked['review_ready']??true)!==false||!in_array('row_outside_launch_cohort:/country/maldives/hotel/the-westin-maldives-miriandhoo-resort-65108/',$blocked['global_errors']??[],true))feedback_readiness_fail('hotel_boundary');

$a=v2_seo_search_feedback_readiness($rows,$policy,$now);
$rev=array_reverse($rows);
$b=v2_seo_search_feedback_readiness($rev,$policy,$now);
if(($a['readiness_sha256']??'')!==($b['readiness_sha256']??''))feedback_readiness_fail('fingerprint_order');

echo "SEO_SEARCH_FEEDBACK_READINESS_OK cohort=8 ready=8 missingUnknown=8 staleBlocked=1 policyRequired=1 hotelTours=0 execution=0\n";
