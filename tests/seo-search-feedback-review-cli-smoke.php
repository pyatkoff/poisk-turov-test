<?php
declare(strict_types=1);

function search_feedback_review_cli_fail(string $message): void
{
    fwrite(STDERR,"SEO_SEARCH_FEEDBACK_REVIEW_CLI_SMOKE_FAIL:$message\n");
    exit(1);
}

$now=1788369600;
$cli=__DIR__.'/../v2/data/report-seo-search-feedback-review-v1.php';
$feedbackFile=tempnam(sys_get_temp_dir(),'seo-feedback-review-');
$policyFile=tempnam(sys_get_temp_dir(),'seo-feedback-policy-');
if($feedbackFile===false||$policyFile===false)search_feedback_review_cli_fail('tempfile');

$feedback=['rows'=>[[
    'path'=>'/country/turkey/',
    'source_class'=>'google_search_console_export',
    'source_ref'=>'fixture://gsc/turkey',
    'collected_at_epoch'=>$now-60,
    'period_start_epoch'=>$now-7*86400,
    'period_end_epoch'=>$now-3600,
    'metrics'=>['impressions'=>1200,'clicks'=>72,'avg_position'=>12.0,'ctr'=>0.06,'query_count'=>40],
]]];
$policy=[
    'policy_id'=>'fixture-feedback-review-cli',
    'version'=>'1',
    'source_ref'=>'fixture://approved-policy',
    'approved_at_epoch'=>$now-300,
    'rules'=>[[
        'rule_id'=>'visibility-needs-improvement',
        'recommendation'=>'improve_review',
        'conditions'=>[
            ['field'=>'metrics.impressions','operator'=>'gte','value'=>1000],
            ['field'=>'metrics.avg_position','operator'=>'gt','value'=>10],
        ],
    ]],
];
file_put_contents($feedbackFile,json_encode($feedback,JSON_THROW_ON_ERROR));
file_put_contents($policyFile,json_encode($policy,JSON_THROW_ON_ERROR));
$cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg($cli)
    .' --feedback='.escapeshellarg($feedbackFile)
    .' --policy='.escapeshellarg($policyFile)
    .' --now-epoch='.$now
    .' --require-review-ready';
$out=[];$code=0;exec($cmd.' 2>&1',$out,$code);
if($code!==0)search_feedback_review_cli_fail('valid_exit_'.$code.'_'.implode('|',$out));
$decoded=json_decode(implode("\n",$out),true);
if(!is_array($decoded)||($decoded['state']??'')!=='search_feedback_review_ready')search_feedback_review_cli_fail('valid_state');
if(($decoded['observed_count']??0)!==1||($decoded['missing_count']??0)!==5)search_feedback_review_cli_fail('counts');
if((($decoded['recommendations'][0]['recommendation']??null)!=='improve_review'))search_feedback_review_cli_fail('recommendation');
if(($decoded['recommendation_semantics']??'')!=='review_only_no_execution'||($decoded['explicit_user_approval_required']??false)!==true)search_feedback_review_cli_fail('semantics');
foreach(['automatic_execution_allowed','automatic_deindex_allowed','publication_allowed','indexation_change_allowed','sitemap_change_allowed','canonical_change_allowed','route_change_allowed','hotel_tours_indexation_allowed'] as $flag){
    if(($decoded[$flag]??true)!==false)search_feedback_review_cli_fail('boundary_'.$flag);
}
if(($decoded['publication_candidates']??null)!==[]||($decoded['publication_scope_expanded']??true)!==false)search_feedback_review_cli_fail('publication_scope');
foreach(['search_contract_changes','tourvisor_contract_changes','pricing_contract_changes','lead_contract_changes','metrika_contract_changes'] as $flag){
    if(($decoded[$flag]??true)!==false)search_feedback_review_cli_fail('contract_'.$flag);
}

$stale=$feedback;
$stale['rows'][0]['collected_at_epoch']=$now-8*86400;
$stale['rows'][0]['period_end_epoch']=$stale['rows'][0]['collected_at_epoch']-3600;
$stale['rows'][0]['period_start_epoch']=$stale['rows'][0]['period_end_epoch']-7*86400;
file_put_contents($feedbackFile,json_encode($stale,JSON_THROW_ON_ERROR));
$out=[];$code=0;exec($cmd.' 2>&1',$out,$code);
if($code!==3)search_feedback_review_cli_fail('stale_exit_'.$code);
$decoded=json_decode(implode("\n",$out),true);
if(!is_array($decoded)||($decoded['state']??'')!=='search_feedback_review_blocked')search_feedback_review_cli_fail('stale_state');

file_put_contents($feedbackFile,json_encode($feedback,JSON_THROW_ON_ERROR));
$noMatch=$policy;
$noMatch['rules'][0]['conditions']=[['field'=>'metrics.impressions','operator'=>'gt','value'=>999999]];
file_put_contents($policyFile,json_encode($noMatch,JSON_THROW_ON_ERROR));
$out=[];$code=0;exec($cmd.' 2>&1',$out,$code);
if($code!==3)search_feedback_review_cli_fail('no_match_exit_'.$code);

$noindex=$policy;
$noindex['rules'][0]=[
    'rule_id'=>'review-noindex-only',
    'recommendation'=>'noindex_review',
    'conditions'=>[['field'=>'metrics.impressions','operator'=>'gte','value'=>0]],
];
file_put_contents($policyFile,json_encode($noindex,JSON_THROW_ON_ERROR));
$out=[];$code=0;exec($cmd.' 2>&1',$out,$code);
if($code!==0)search_feedback_review_cli_fail('noindex_label_exit_'.$code);
$decoded=json_decode(implode("\n",$out),true);
if(($decoded['recommendations'][0]['recommendation']??'')!=='noindex_review')search_feedback_review_cli_fail('noindex_label');
if(($decoded['automatic_deindex_allowed']??true)!==false||($decoded['indexation_change_allowed']??true)!==false)search_feedback_review_cli_fail('noindex_execution_leak');

$hotel=$feedback;
$hotel['rows'][0]['path']='/country/turkey/hotel/aegean-park-1601/';
file_put_contents($feedbackFile,json_encode($hotel,JSON_THROW_ON_ERROR));
file_put_contents($policyFile,json_encode($policy,JSON_THROW_ON_ERROR));
$out=[];$code=0;exec($cmd.' 2>&1',$out,$code);
if($code!==3)search_feedback_review_cli_fail('hotel_exit_'.$code);

@unlink($feedbackFile);@unlink($policyFile);
echo "SEO_SEARCH_FEEDBACK_REVIEW_CLI_SMOKE_OK ready=1 missingUnknown=5 staleBlocked=1 noMatchBlocked=1 noindexReviewOnly=1 hotelBlocked=1\n";
