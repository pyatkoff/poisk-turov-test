<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-yandex-webmaster-feedback-v1.php';
require_once __DIR__.'/../v2/seo-search-feedback-evidence-v1.php';

function yw_feedback_fail(string $message): never
{
    fwrite(STDERR,"SEO_YANDEX_WEBMASTER_FEEDBACK_FAIL:$message\n");
    exit(1);
}

$collected=(new DateTimeImmutable('2026-09-03 12:00:00',new DateTimeZone('UTC')))->getTimestamp();
$dates=['2026-08-27','2026-08-28','2026-08-29','2026-08-30','2026-08-31','2026-09-01','2026-09-02'];
$stats=static function(array $dates, float $impressions, float $clicks, float $position): array {
    $out=[];
    foreach($dates as $date){
        $out[]=['date'=>$date,'field'=>'IMPRESSIONS','value'=>$impressions];
        $out[]=['date'=>$date,'field'=>'CLICKS','value'=>$clicks];
        $out[]=['date'=>$date,'field'=>'POSITION','value'=>$position];
        $out[]=['date'=>$date,'field'=>'CTR','value'=>$impressions>0?$clicks/$impressions:0.0];
    }
    return $out;
};
$payload=[
    'host_id'=>'https:anytoour.ru:443',
    'responses'=>[
        [
            'count'=>4,
            'text_indicator_to_statistics'=>[
                ['text_indicator'=>['type'=>'URL','value'=>'https://anytoour.ru/country/turkey/'],'statistics'=>$stats($dates,100.0,10.0,8.0)],
                ['text_indicator'=>['type'=>'URL','value'=>'https://anytoour.ru/country/maldives/hotel/the-westin-maldives-miriandhoo-resort-65108/'],'statistics'=>$stats($dates,200.0,20.0,4.0)],
            ],
        ],
        [
            'count'=>4,
            'text_indicator_to_statistics'=>[
                ['text_indicator'=>['type'=>'URL','value'=>'https://anytoour.ru/country/egypt/'],'statistics'=>$stats($dates,50.0,5.0,12.0)],
                ['text_indicator'=>['type'=>'URL','value'=>'https://anytoour.ru/random-page/'],'statistics'=>$stats($dates,500.0,50.0,3.0)],
            ],
        ],
    ],
];
$result=v2_seo_yandex_webmaster_feedback($payload,$collected);
if(($result['state']??'')!=='yandex_webmaster_feedback_ready')yw_feedback_fail('state_'.implode(',',(array)($result['errors']??[])));
if(($result['launch_scope']??'')!=='controlled_country_resort_seasonal_v3'||($result['controlled_path_count']??0)!==10)yw_feedback_fail('scope');
if(($result['selected_date_count']??0)!==7||($result['period_start_date']??'')!=='2026-08-27'||($result['period_end_date']??'')!=='2026-09-02')yw_feedback_fail('period');
if(($result['observed_controlled_path_count']??0)!==2||($result['ignored_outside_cohort_count']??0)!==2)yw_feedback_fail('counts');
$rows=[];foreach($result['rows'] as $row)$rows[$row['path']]=$row;
if(isset($rows['/country/maldives/hotel/the-westin-maldives-miriandhoo-resort-65108/']))yw_feedback_fail('hotel_leak');
$turkey=$rows['/country/turkey/']??null;
$egypt=$rows['/country/egypt/']??null;
if(!is_array($turkey)||!is_array($egypt))yw_feedback_fail('rows_missing');
if(($turkey['metrics']['impressions']??null)!==700||($turkey['metrics']['clicks']??null)!==70)yw_feedback_fail('turkey_counts');
if(abs((float)($turkey['metrics']['avg_position']??0)-8.0)>0.000001||abs((float)($turkey['metrics']['ctr']??0)-0.1)>0.000001)yw_feedback_fail('turkey_derived');
if(($egypt['metrics']['impressions']??null)!==350||($egypt['metrics']['clicks']??null)!==35||abs((float)($egypt['metrics']['avg_position']??0)-12.0)>0.000001)yw_feedback_fail('egypt_metrics');
$intake=v2_seo_search_feedback_intake(array_values($result['rows']),$collected);
if(($intake['state']??'')!=='search_feedback_intake_ready'||($intake['observed_count']??0)!==2||count($intake['missing_paths']??[])!==8)yw_feedback_fail('intake');
$missing=array_fill_keys((array)($intake['missing_paths']??[]),true);
foreach(['/country/turkey/antalya/september/','/country/maldives/september/'] as $seasonalPath){
    if(!isset($missing[$seasonalPath]))yw_feedback_fail('seasonal_missing_not_unknown_'.$seasonalPath);
}
if(($intake['missing_feedback_semantics']??'')!=='unknown_not_zero')yw_feedback_fail('missing_semantics');
foreach(['publication_allowed','indexation_change_allowed','sitemap_change_allowed','canonical_change_allowed','route_change_allowed','hotel_tours_indexation_allowed'] as $flag){
    if(($result[$flag]??true)!==false)yw_feedback_fail('boundary_'.$flag);
}
if(($result['publication_candidates']??null)!==[])yw_feedback_fail('publication_candidates');

$positionOnly=[];
foreach($dates as $date)$positionOnly[]=['date'=>$date,'field'=>'POSITION','value'=>9.0];
$partial=v2_seo_yandex_webmaster_feedback([
    'host_id'=>'https:anytoour.ru:443',
    'responses'=>[['text_indicator_to_statistics'=>[[
        'text_indicator'=>['type'=>'URL','value'=>'https://anytoour.ru/country/turkey/'],
        'statistics'=>$positionOnly,
    ]]]],
],$collected);
if(($partial['state']??'')!=='yandex_webmaster_feedback_partial')yw_feedback_fail('missing_counter_state');
$partialMetrics=$partial['rows'][0]['metrics']??null;
if(!is_array($partialMetrics))yw_feedback_fail('missing_counter_metrics');
if(array_key_exists('impressions',$partialMetrics)||array_key_exists('clicks',$partialMetrics)||array_key_exists('ctr',$partialMetrics)||array_key_exists('avg_position',$partialMetrics))yw_feedback_fail('missing_counter_fabricated_zero');
$partialErrors=implode('|',(array)($partial['errors']??[]));
if(!str_contains($partialErrors,'impressions_unavailable')||!str_contains($partialErrors,'clicks_unavailable'))yw_feedback_fail('missing_counter_diagnostics');
$partialIntake=v2_seo_search_feedback_intake((array)($partial['rows']??[]),$collected);
if(($partialIntake['state']??'')!=='search_feedback_intake_blocked')yw_feedback_fail('missing_counter_intake_must_block');

$zeroStats=[];
foreach($dates as $date){
    $zeroStats[]=['date'=>$date,'field'=>'IMPRESSIONS','value'=>0.0];
    $zeroStats[]=['date'=>$date,'field'=>'CLICKS','value'=>0.0];
}
$zero=v2_seo_yandex_webmaster_feedback([
    'host_id'=>'https:anytoour.ru:443',
    'responses'=>[['text_indicator_to_statistics'=>[[
        'text_indicator'=>['type'=>'URL','value'=>'https://anytoour.ru/country/turkey/'],
        'statistics'=>$zeroStats,
    ]]]],
],$collected);
if(($zero['state']??'')!=='yandex_webmaster_feedback_ready')yw_feedback_fail('observed_zero_state_'.implode(',',(array)($zero['errors']??[])));
$zeroMetrics=$zero['rows'][0]['metrics']??[];
if(($zeroMetrics['impressions']??null)!==0||($zeroMetrics['clicks']??null)!==0)yw_feedback_fail('observed_zero_counts');
if(array_key_exists('avg_position',$zeroMetrics)||array_key_exists('ctr',$zeroMetrics))yw_feedback_fail('observed_zero_position_ctr_not_unknown');
$zeroEvidence=v2_seo_search_feedback_evidence($zero['rows'][0],$collected);
if(($zeroEvidence['state']??'')!=='search_feedback_evidence_valid')yw_feedback_fail('observed_zero_evidence');

echo "SEO_YANDEX_WEBMASTER_FEEDBACK_OK cohort=10 observed=2 ignoredOutside=2 dates=7 unknownMissing=8 seasonalUnknown=2 missingCountersUnknown=1 observedZeroValid=1 hotelTours=0 execution=0\n";
