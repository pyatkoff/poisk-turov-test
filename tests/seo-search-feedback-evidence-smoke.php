<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-search-feedback-evidence-v1.php';
function feedback_fail(string $message): void{fwrite(STDERR,"SEO_SEARCH_FEEDBACK_EVIDENCE_FAIL:$message\n");exit(1);}

$now=1788369600;
$rows=[
    ['path'=>'/country/turkey/','source_class'=>'google_search_console_export','source_ref'=>'fixture://gsc/export-1','collected_at_epoch'=>$now-60,'period_start_epoch'=>$now-7*86400,'period_end_epoch'=>$now-3600,'metrics'=>['impressions'=>1000,'clicks'=>80,'avg_position'=>6.4,'ctr'=>0.08,'query_count'=>42]],
    ['path'=>'/country/egypt/','source_class'=>'yandex_webmaster_export','source_ref'=>'fixture://webmaster/export-egypt','collected_at_epoch'=>$now-120,'period_start_epoch'=>$now-7*86400,'period_end_epoch'=>$now-7200,'metrics'=>['impressions'=>500,'clicks'=>35,'avg_position'=>8.2,'ctr'=>0.07]],
];
$intake=v2_seo_search_feedback_intake($rows,$now);
if(($intake['state']??'')!=='search_feedback_intake_ready')feedback_fail('state');
if(($intake['domain']??'')!=='anytoour.ru'||($intake['launch_scope']??'')!=='controlled_country_resort_seasonal_v3'||($intake['launched_path_count']??0)!==104||($intake['observed_count']??0)!==2)feedback_fail('scope');
if(count($intake['missing_paths']??[])!==102||($intake['missing_feedback_semantics']??'')!=='unknown_not_zero')feedback_fail('missing_semantics');
if(($intake['zero_impression_metric_semantics']??'')!=='position_ctr_unknown_not_zero')feedback_fail('zero_impression_semantics');
if(($intake['ctr_consistency_semantics']??'')!=='ctr_must_match_clicks_over_impressions')feedback_fail('ctr_consistency_semantics');
if(($intake['recommendation_state']??'')!=='requires_explicit_feedback_policy')feedback_fail('policy_boundary');
foreach($intake['rows'] as $row){
    if(($row['state']??'')!=='search_feedback_evidence_valid'||($row['fresh']??false)!==true)feedback_fail('row_validity');
    if(($row['automatic_recommendation_allowed']??true)!==false||($row['automatic_deindex_allowed']??true)!==false)feedback_fail('row_automation_boundary');
}
foreach(['automatic_recommendation_allowed','automatic_deindex_allowed','publication_allowed','indexation_change_allowed','sitemap_change_allowed','canonical_change_allowed','route_change_allowed','hotel_tours_indexation_allowed'] as $flag)if(($intake[$flag]??true)!==false)feedback_fail('boundary_'.$flag);
if(($intake['publication_candidates']??null)!==[]||($intake['publication_scope_expanded']??true)!==false)feedback_fail('publication_scope');

$zero=$rows[0];
$zero['source_ref']='fixture://gsc/zero-impressions';
$zero['metrics']=['impressions'=>0,'clicks'=>0,'query_count'=>0];
$zeroIntake=v2_seo_search_feedback_intake([$zero],$now);
if(($zeroIntake['state']??'')!=='search_feedback_intake_ready')feedback_fail('zero_impressions_not_valid_unknown');
$zeroRow=$zeroIntake['rows'][0]??[];
if(array_key_exists('avg_position',$zeroRow['metrics']??[])||array_key_exists('ctr',$zeroRow['metrics']??[]))feedback_fail('zero_impressions_fabricated_metrics');

$fakeZero=$zero;$fakeZero['metrics']['avg_position']=0;$fakeZero['metrics']['ctr']=0.0;
$blocked=v2_seo_search_feedback_intake([$fakeZero],$now);
if(($blocked['state']??'')!=='search_feedback_intake_blocked')feedback_fail('fabricated_zero_metrics_not_blocked');

$zeroClicks=$rows[0];$zeroClicks['source_ref']='fixture://gsc/zero-clicks';$zeroClicks['metrics']=['impressions'=>1000,'clicks'=>0,'avg_position'=>9.1,'ctr'=>0.0];
if((v2_seo_search_feedback_intake([$zeroClicks],$now)['state']??'')!=='search_feedback_intake_ready')feedback_fail('zero_clicks_valid_ctr_blocked');

$badCtrConsistency=$rows[0];$badCtrConsistency['source_ref']='fixture://gsc/inconsistent-ctr';$badCtrConsistency['metrics']['ctr']=0.01;
$blocked=v2_seo_search_feedback_intake([$badCtrConsistency],$now);
if(($blocked['state']??'')!=='search_feedback_intake_blocked'||!in_array('ctr_inconsistent_with_clicks_impressions',$blocked['rows'][0]['errors']??[],true))feedback_fail('inconsistent_ctr');

$hotel=$rows[0];$hotel['path']='/country/turkey/hotel/aegean-park-1601/';
if((v2_seo_search_feedback_intake([$hotel],$now)['state']??'')!=='search_feedback_intake_blocked')feedback_fail('hotel_scope_not_blocked');
$stale=$rows[0];$stale['collected_at_epoch']=$now-8*86400;$stale['period_end_epoch']=$stale['collected_at_epoch']-3600;$stale['period_start_epoch']=$stale['period_end_epoch']-7*86400;
if((v2_seo_search_feedback_intake([$stale],$now)['state']??'')!=='search_feedback_intake_blocked')feedback_fail('stale_not_blocked');
$empty=v2_seo_search_feedback_intake([],$now);if(($empty['state']??'')!=='search_feedback_intake_blocked'||count($empty['missing_paths']??[])!==104)feedback_fail('empty_not_unknown');
echo "SEO_SEARCH_FEEDBACK_EVIDENCE_OK launched=104 observedFixture=2 missingUnknown=102 hotelTours=0\n";
