<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-search-feedback-evidence-v1.php';
require_once __DIR__.'/../v2/seo-search-feedback-policy-v1.php';
function search_feedback_policy_fail(string $message): void{fwrite(STDERR,"SEO_SEARCH_FEEDBACK_POLICY_SMOKE_FAIL:$message\n");exit(1);}
$now=1788369600;
$rows=[
 ['path'=>'/country/turkey/','source_class'=>'google_search_console_export','source_ref'=>'fixture://gsc/turkey','collected_at_epoch'=>$now-60,'period_start_epoch'=>$now-7*86400,'period_end_epoch'=>$now-3600,'metrics'=>['impressions'=>1200,'clicks'=>72,'avg_position'=>12.0,'ctr'=>0.06,'query_count'=>40]],
 ['path'=>'/country/turkey/kemer/','source_class'=>'yandex_webmaster_export','source_ref'=>'fixture://webmaster/kemer','collected_at_epoch'=>$now-120,'period_start_epoch'=>$now-7*86400,'period_end_epoch'=>$now-7200,'metrics'=>['impressions'=>800,'clicks'=>96,'avg_position'=>5.0,'ctr'=>0.12,'query_count'=>31]],
];
$intake=v2_seo_search_feedback_intake($rows,$now);
if(($intake['state']??'')!=='search_feedback_intake_ready'||($intake['launch_scope']??'')!=='controlled_country_resort_seasonal_v3'||($intake['launched_path_count']??0)!==104)search_feedback_policy_fail('intake');
$policy=[
 'policy_id'=>'fixture-feedback-review','version'=>'1','source_ref'=>'fixture://approved-policy','approved_at_epoch'=>$now-300,
 'rules'=>[
  ['rule_id'=>'visibility-needs-improvement','recommendation'=>'improve_review','conditions'=>[['field'=>'metrics.impressions','operator'=>'gte','value'=>1000],['field'=>'metrics.avg_position','operator'=>'gt','value'=>10]]],
  ['rule_id'=>'strong-position-review-expand','recommendation'=>'expand_review','conditions'=>[['field'=>'metrics.impressions','operator'=>'gte','value'=>500],['field'=>'metrics.avg_position','operator'=>'lte','value'=>10]]],
 ],
];
$review=v2_seo_search_feedback_review($intake,$policy,$now);
if(($review['state']??'')!=='search_feedback_review_ready'||($review['launch_scope']??'')!=='controlled_country_resort_seasonal_v3')search_feedback_policy_fail('review_state');
if(($review['observed_count']??0)!==2||($review['missing_count']??0)!==102)search_feedback_policy_fail('counts');
$recommendations=[];foreach($review['recommendations'] as $row)$recommendations[(string)$row['path']]=$row['recommendation']??null;
if(($recommendations['/country/turkey/']??null)!=='improve_review'||($recommendations['/country/turkey/kemer/']??null)!=='expand_review')search_feedback_policy_fail('recommendations');
foreach($review['missing'] as $row){
    if(($row['status']??'')!=='unknown_no_evidence'||!array_key_exists('recommendation',$row)||$row['recommendation']!==null)search_feedback_policy_fail('missing_not_unknown');
}
foreach(['automatic_execution_allowed','automatic_deindex_allowed','publication_allowed','indexation_change_allowed','sitemap_change_allowed','canonical_change_allowed','route_change_allowed','hotel_tours_indexation_allowed'] as $flag)if(($review[$flag]??true)!==false)search_feedback_policy_fail('boundary_'.$flag);
if((v2_seo_search_feedback_review($intake,[],$now)['state']??'')!=='search_feedback_review_blocked')search_feedback_policy_fail('empty_policy');
$noMatch=$policy;$noMatch['rules']=[['rule_id'=>'impossible','recommendation'=>'hold_review','conditions'=>[['field'=>'metrics.impressions','operator'=>'gt','value'=>999999]]]];
if((v2_seo_search_feedback_review($intake,$noMatch,$now)['state']??'')!=='search_feedback_review_blocked')search_feedback_policy_fail('no_match');
$badField=$policy;$badField['rules'][0]['conditions'][0]['field']='metrics.revenue';if((v2_seo_search_feedback_policy($badField,$now)['state']??'')!=='search_feedback_policy_invalid')search_feedback_policy_fail('arbitrary_metric');
echo "SEO_SEARCH_FEEDBACK_POLICY_SMOKE_OK launched=104 observed=2 missingUnknown=102 hotelTours=0\n";
