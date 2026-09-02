<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-search-feedback-evidence-v1.php';
require_once __DIR__.'/../v2/seo-search-feedback-policy-v1.php';

function search_feedback_policy_fail(string $message): void
{
    fwrite(STDERR,"SEO_SEARCH_FEEDBACK_POLICY_SMOKE_FAIL:$message\n");
    exit(1);
}

$now=1788369600;
$rows=[
    [
        'path'=>'/country/turkey/',
        'source_class'=>'google_search_console_export',
        'source_ref'=>'fixture://gsc/turkey',
        'collected_at_epoch'=>$now-60,
        'period_start_epoch'=>$now-7*86400,
        'period_end_epoch'=>$now-3600,
        'metrics'=>['impressions'=>1200,'clicks'=>72,'avg_position'=>12.0,'ctr'=>0.06,'query_count'=>40],
    ],
    [
        'path'=>'/country/turkey/kemer/',
        'source_class'=>'yandex_webmaster_export',
        'source_ref'=>'fixture://webmaster/kemer',
        'collected_at_epoch'=>$now-120,
        'period_start_epoch'=>$now-7*86400,
        'period_end_epoch'=>$now-7200,
        'metrics'=>['impressions'=>800,'clicks'=>96,'avg_position'=>5.0,'ctr'=>0.12,'query_count'=>31],
    ],
];
$intake=v2_seo_search_feedback_intake($rows,$now);
if(($intake['state']??'')!=='search_feedback_intake_ready'||($intake['launched_path_count']??0)!==8) search_feedback_policy_fail('intake');

$policy=[
    'policy_id'=>'fixture-feedback-review',
    'version'=>'1',
    'source_ref'=>'fixture://approved-policy',
    'approved_at_epoch'=>$now-300,
    'rules'=>[
        [
            'rule_id'=>'visibility-needs-improvement',
            'recommendation'=>'improve_review',
            'conditions'=>[
                ['field'=>'metrics.impressions','operator'=>'gte','value'=>1000],
                ['field'=>'metrics.avg_position','operator'=>'gt','value'=>10],
            ],
        ],
        [
            'rule_id'=>'strong-position-review-expand',
            'recommendation'=>'expand_review',
            'conditions'=>[
                ['field'=>'metrics.impressions','operator'=>'gte','value'=>500],
                ['field'=>'metrics.avg_position','operator'=>'lte','value'=>10],
            ],
        ],
    ],
];
$review=v2_seo_search_feedback_review($intake,$policy,$now);
if(($review['state']??'')!=='search_feedback_review_ready') search_feedback_policy_fail('review_state');
if(($review['observed_count']??0)!==2||($review['missing_count']??0)!==6) search_feedback_policy_fail('counts');
$recommendations=[];
foreach($review['recommendations'] as $row)$recommendations[(string)$row['path']]=$row['recommendation']??null;
if(($recommendations['/country/turkey/']??null)!=='improve_review') search_feedback_policy_fail('turkey_recommendation');
if(($recommendations['/country/turkey/kemer/']??null)!=='expand_review') search_feedback_policy_fail('kemer_recommendation');
foreach($review['missing'] as $row){
    if(($row['status']??'')!=='unknown_no_evidence'||!array_key_exists('recommendation',$row)||$row['recommendation']!==null) search_feedback_policy_fail('missing_not_unknown');
}
if(($review['recommendation_semantics']??'')!=='review_only_no_execution'||($review['explicit_user_approval_required']??false)!==true) search_feedback_policy_fail('review_boundary');
foreach(['automatic_execution_allowed','automatic_deindex_allowed','publication_allowed','indexation_change_allowed','sitemap_change_allowed','canonical_change_allowed','route_change_allowed','hotel_tours_indexation_allowed'] as $flag){
    if(($review[$flag]??true)!==false) search_feedback_policy_fail('boundary_'.$flag);
}
if(($review['publication_candidates']??null)!==[]||($review['publication_scope_expanded']??true)!==false) search_feedback_policy_fail('publication_scope');
foreach(['search_contract_changes','tourvisor_contract_changes','pricing_contract_changes','lead_contract_changes','metrika_contract_changes'] as $flag){
    if(($review[$flag]??true)!==false) search_feedback_policy_fail('contract_'.$flag);
}

$emptyPolicy=v2_seo_search_feedback_review($intake,[],$now);
if(($emptyPolicy['state']??'')!=='search_feedback_review_blocked') search_feedback_policy_fail('empty_policy_not_blocked');

$noMatch=$policy;
$noMatch['rules']=[[
    'rule_id'=>'impossible',
    'recommendation'=>'hold_review',
    'conditions'=>[['field'=>'metrics.impressions','operator'=>'gt','value'=>999999]],
]];
$blocked=v2_seo_search_feedback_review($intake,$noMatch,$now);
if(($blocked['state']??'')!=='search_feedback_review_blocked') search_feedback_policy_fail('no_match_not_blocked');

$noindexPolicy=$policy;
$noindexPolicy['rules']=[[
    'rule_id'=>'explicit-noindex-review-only',
    'recommendation'=>'noindex_review',
    'conditions'=>[['field'=>'metrics.impressions','operator'=>'gte','value'=>0]],
]];
$noindexReview=v2_seo_search_feedback_review($intake,$noindexPolicy,$now);
if(($noindexReview['state']??'')!=='search_feedback_review_ready') search_feedback_policy_fail('noindex_review_state');
foreach($noindexReview['recommendations'] as $row){
    if(($row['recommendation']??'')!=='noindex_review') search_feedback_policy_fail('noindex_label');
}
if(($noindexReview['automatic_deindex_allowed']??true)!==false||($noindexReview['indexation_change_allowed']??true)!==false) search_feedback_policy_fail('noindex_execution_leak');

$badField=$policy;
$badField['rules'][0]['conditions'][0]['field']='metrics.revenue';
$invalid=v2_seo_search_feedback_policy($badField,$now);
if(($invalid['state']??'')!=='search_feedback_policy_invalid') search_feedback_policy_fail('arbitrary_metric_allowed');

echo "SEO_SEARCH_FEEDBACK_POLICY_SMOKE_OK explicitPolicy=1 defaults=0 missingUnknown=6 recommendationsReviewOnly=1 autoDeindex=0 hotelTours=0\n";
