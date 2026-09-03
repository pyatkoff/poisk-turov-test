<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-launch-slice-v1.php';

function search_feedback_template_fail(string $message): void
{
    fwrite(STDERR,"SEO_SEARCH_FEEDBACK_TEMPLATE_SMOKE_FAIL:$message\n");
    exit(1);
}

$template=__DIR__.'/../v2/data/report-seo-search-feedback-template-v1.php';
$out=[];$code=0;
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($template).' 2>&1',$out,$code);
if($code!==0) search_feedback_template_fail('exit_'.$code.'_'.implode('|',$out));
$decoded=json_decode(implode("\n",$out),true);
if(!is_array($decoded)||($decoded['state']??'')!=='search_feedback_collection_template') search_feedback_template_fail('state');
if(($decoded['domain']??'')!=='anytoour.ru'||($decoded['launch_scope']??'')!=='controlled_country_resort_seasonal_v3') search_feedback_template_fail('scope');
$rows=$decoded['rows']??null;
if(!is_array($rows)||count($rows)!==104) search_feedback_template_fail('row_count');
$expected=v2_seo_controlled_launch_paths();
$paths=array_map(static fn(array $row):string=>(string)($row['path']??''),$rows);
if($paths!==$expected) search_feedback_template_fail('paths');
foreach(['/country/egypt/','/country/maldives/','/country/turkey/january/','/country/turkey/kemer/june/','/country/maldives/september/'] as $path){if(!in_array($path,$paths,true))search_feedback_template_fail('missing_'.$path);}
foreach($rows as $row){
    if(str_contains((string)($row['path']??''),'/hotel/')) search_feedback_template_fail('hotel_leak');
    if(($row['source_class']??null)!==''||($row['source_ref']??null)!=='') search_feedback_template_fail('source_not_blank');
    foreach(['collected_at_epoch','period_start_epoch','period_end_epoch'] as $field){
        if(!array_key_exists($field,$row)||$row[$field]!==null) search_feedback_template_fail('timestamp_not_null_'.$field);
    }
    $metrics=$row['metrics']??null;
    if(!is_array($metrics)) search_feedback_template_fail('metrics_missing');
    foreach(['impressions','clicks','avg_position','ctr','query_count'] as $metric){
        if(!array_key_exists($metric,$metrics)||$metrics[$metric]!==null) search_feedback_template_fail('metric_not_null_'.$metric);
    }
}
if(($decoded['missing_feedback_semantics']??'')!=='unknown_not_zero') search_feedback_template_fail('missing_semantics');
foreach(['automatic_recommendation_allowed','automatic_deindex_allowed','publication_allowed','indexation_change_allowed','sitemap_change_allowed','canonical_change_allowed','route_change_allowed','hotel_tours_indexation_allowed'] as $flag){
    if(($decoded[$flag]??true)!==false) search_feedback_template_fail('boundary_'.$flag);
}
echo "SEO_SEARCH_FEEDBACK_TEMPLATE_SMOKE_OK rows=104 coreMonths=96 hotelTours=0\n";
