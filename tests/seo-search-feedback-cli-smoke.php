<?php
declare(strict_types=1);

function search_feedback_cli_fail(string $message): void
{
    fwrite(STDERR,"SEO_SEARCH_FEEDBACK_CLI_SMOKE_FAIL:$message\n");
    exit(1);
}

$now=1788369600;
$cli=__DIR__.'/../v2/data/report-seo-search-feedback-v1.php';
$tmp=tempnam(sys_get_temp_dir(),'seo-feedback-');
if($tmp===false) search_feedback_cli_fail('tempfile');

$valid=[
    'rows'=>[[
        'path'=>'/country/maldives/',
        'source_class'=>'google_search_console_export',
        'source_ref'=>'fixture://gsc/maldives',
        'collected_at_epoch'=>$now-60,
        'period_start_epoch'=>$now-7*86400,
        'period_end_epoch'=>$now-3600,
        'metrics'=>['impressions'=>1200,'clicks'=>96,'avg_position'=>5.8,'ctr'=>0.08,'query_count'=>51],
    ]],
];
file_put_contents($tmp,json_encode($valid,JSON_THROW_ON_ERROR));
$cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg($cli).' --input='.escapeshellarg($tmp).' --now-epoch='.$now.' --require-ready';
$out=[];$code=0;exec($cmd.' 2>&1',$out,$code);
if($code!==0) search_feedback_cli_fail('valid_exit_'.$code.'_'.implode('|',$out));
$decoded=json_decode(implode("\n",$out),true);
if(!is_array($decoded)||($decoded['state']??'')!=='search_feedback_intake_ready') search_feedback_cli_fail('valid_state');
if(($decoded['launch_scope']??'')!=='controlled_country_resort_seasonal_v3'||($decoded['launched_path_count']??0)!==10) search_feedback_cli_fail('scope');
if(($decoded['observed_count']??0)!==1||count($decoded['missing_paths']??[])!==9) search_feedback_cli_fail('partial_counts');
if(($decoded['missing_feedback_semantics']??'')!=='unknown_not_zero') search_feedback_cli_fail('missing_semantics');
foreach(['automatic_recommendation_allowed','automatic_deindex_allowed','publication_allowed','indexation_change_allowed','sitemap_change_allowed','canonical_change_allowed','route_change_allowed','hotel_tours_indexation_allowed'] as $flag){
    if(($decoded[$flag]??true)!==false) search_feedback_cli_fail('boundary_'.$flag);
}

$stale=$valid;
$stale['rows'][0]['collected_at_epoch']=$now-8*86400;
$stale['rows'][0]['period_end_epoch']=$stale['rows'][0]['collected_at_epoch']-3600;
$stale['rows'][0]['period_start_epoch']=$stale['rows'][0]['period_end_epoch']-7*86400;
file_put_contents($tmp,json_encode($stale,JSON_THROW_ON_ERROR));
$out=[];$code=0;exec($cmd.' 2>&1',$out,$code);
if($code!==3) search_feedback_cli_fail('stale_require_ready_exit_'.$code);
$decoded=json_decode(implode("\n",$out),true);
if(!is_array($decoded)||($decoded['state']??'')!=='search_feedback_intake_blocked') search_feedback_cli_fail('stale_state');

$hotel=$valid;
$hotel['rows'][0]['path']='/country/maldives/hotel/the-westin-maldives-miriandhoo-resort-65108/';
file_put_contents($tmp,json_encode($hotel,JSON_THROW_ON_ERROR));
$out=[];$code=0;exec($cmd.' 2>&1',$out,$code);
if($code!==3) search_feedback_cli_fail('hotel_require_ready_exit_'.$code);
$decoded=json_decode(implode("\n",$out),true);
if(!is_array($decoded)||($decoded['state']??'')!=='search_feedback_intake_blocked') search_feedback_cli_fail('hotel_state');

@unlink($tmp);
echo "SEO_SEARCH_FEEDBACK_CLI_SMOKE_OK observed=1 missingUnknown=9 seasonal=2 staleBlocked=1 hotelBlocked=1\n";
