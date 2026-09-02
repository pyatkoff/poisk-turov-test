<?php
declare(strict_types=1);
require_once __DIR__.'/seo-launch-slice-v1.php';

function v2_seo_yandex_webmaster_controlled_path(string $url): ?string
{
    $parts=parse_url(trim($url));
    if(!is_array($parts))return null;
    $host=strtolower((string)($parts['host']??''));
    if(!in_array($host,['anytoour.ru','www.anytoour.ru'],true))return null;
    $path=(string)($parts['path']??'/');
    if($path==='')$path='/';
    if(!str_starts_with($path,'/'))$path='/'.$path;
    $candidates=[$path];
    if($path!=='/'&&!str_ends_with($path,'/'))$candidates[]=$path.'/';
    $allowed=array_fill_keys(v2_seo_controlled_launch_paths(),true);
    foreach($candidates as $candidate)if(isset($allowed[$candidate]))return $candidate;
    return null;
}

/**
 * Convert read-only Yandex Webmaster query-analytics/list responses into the
 * normalized search-feedback rows already used by SEO3.
 *
 * The source API reports URL statistics by date. We select the latest seven
 * dates actually present for the controlled cohort, sum observed clicks and
 * impressions, derive CTR from those observed counters, and compute an
 * impression-weighted position from observed daily POSITION values. No missing
 * metric is converted to zero and no URL outside the exact controlled cohort is
 * admitted to the output.
 */
function v2_seo_yandex_webmaster_feedback(array $payload, ?int $collectedAtEpoch=null): array
{
    $collectedAtEpoch??=time();
    $hostId=trim((string)($payload['host_id']??''));
    $responses=$payload['responses']??null;
    if(!is_array($responses))$responses=[];
    if(!array_is_list($responses))$responses=[$responses];
    $allowed=v2_seo_controlled_launch_paths();
    $byPath=[];$dates=[];$ignored=0;$errors=[];

    foreach($responses as $responseIndex=>$response){
        if(!is_array($response)){$errors[]='invalid_response_'.$responseIndex;continue;}
        $items=$response['text_indicator_to_statistics']??[];
        if(!is_array($items)){$errors[]='statistics_list_invalid_'.$responseIndex;continue;}
        foreach($items as $itemIndex=>$item){
            if(!is_array($item)){$errors[]='item_invalid_'.$responseIndex.'_'.$itemIndex;continue;}
            $indicator=is_array($item['text_indicator']??null)?$item['text_indicator']:[];
            if(($indicator['type']??'')!=='URL'){$ignored++;continue;}
            $url=(string)($indicator['value']??'');
            $path=v2_seo_yandex_webmaster_controlled_path($url);
            if($path===null){$ignored++;continue;}
            $statistics=$item['statistics']??[];
            if(!is_array($statistics)){$errors[]='statistics_invalid:'.$path;continue;}
            if(!isset($byPath[$path]))$byPath[$path]=[];
            foreach($statistics as $statIndex=>$stat){
                if(!is_array($stat)){$errors[]='stat_invalid:'.$path.':'.$statIndex;continue;}
                $date=trim((string)($stat['date']??''));
                $field=(string)($stat['field']??'');
                $value=$stat['value']??null;
                if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){$errors[]='stat_date_invalid:'.$path;continue;}
                if(!in_array($field,['IMPRESSIONS','CLICKS','POSITION','CTR'],true))continue;
                if(!is_int($value)&&!is_float($value)){$errors[]='stat_value_invalid:'.$path.':'.$field;continue;}
                if((float)$value<0){$errors[]='stat_value_negative:'.$path.':'.$field;continue;}
                $byPath[$path][$date][$field]=(float)$value;
                $dates[$date]=true;
            }
        }
    }

    $dateList=array_keys($dates); sort($dateList,SORT_STRING);
    if(count($dateList)>7)$dateList=array_slice($dateList,-7);
    $dateSet=array_fill_keys($dateList,true);
    $periodStart=$dateList[0]??'';
    $periodEnd=$dateList!==[]?$dateList[count($dateList)-1]:'';
    $startEpoch=$periodStart!==''?(new DateTimeImmutable($periodStart.' 00:00:00',new DateTimeZone('UTC')))->getTimestamp():0;
    $endEpoch=$periodEnd!==''?(new DateTimeImmutable($periodEnd.' 23:59:59',new DateTimeZone('UTC')))->getTimestamp():0;
    if($dateList===[])$errors[]='no_controlled_cohort_dates';
    if($endEpoch>$collectedAtEpoch)$errors[]='period_ends_after_collection';

    $rows=[];$pageDiagnostics=[];
    foreach($allowed as $path){
        if(!isset($byPath[$path]))continue;
        $impressions=0.0;$clicks=0.0;$positionWeighted=0.0;$positionWeight=0.0;
        $pageErrors=[];
        foreach($byPath[$path] as $date=>$fields){
            if(!isset($dateSet[$date]))continue;
            $dayImpressions=$fields['IMPRESSIONS']??null;
            $dayClicks=$fields['CLICKS']??null;
            $dayPosition=$fields['POSITION']??null;
            if($dayImpressions!==null)$impressions+=(float)$dayImpressions;
            if($dayClicks!==null)$clicks+=(float)$dayClicks;
            if($dayPosition!==null&&$dayImpressions!==null&&(float)$dayImpressions>0){
                $positionWeighted+=(float)$dayPosition*(float)$dayImpressions;
                $positionWeight+=(float)$dayImpressions;
            }
        }
        $metrics=[];
        if(floor($impressions)===$impressions)$metrics['impressions']=(int)$impressions; else $pageErrors[]='non_integer_impressions';
        if(floor($clicks)===$clicks)$metrics['clicks']=(int)$clicks; else $pageErrors[]='non_integer_clicks';
        if($positionWeight>0)$metrics['avg_position']=$positionWeighted/$positionWeight; else $pageErrors[]='position_unavailable';
        if($impressions>0)$metrics['ctr']=$clicks/$impressions; else $pageErrors[]='ctr_unavailable';
        if($clicks>$impressions)$pageErrors[]='clicks_exceed_impressions';
        $rows[]=[
            'path'=>$path,
            'source_class'=>'yandex_webmaster_export',
            'source_ref'=>'yandex-webmaster-query-analytics:'.$hostId.':'.$periodStart.'..'.$periodEnd,
            'collected_at_epoch'=>$collectedAtEpoch,
            'period_start_epoch'=>$startEpoch,
            'period_end_epoch'=>$endEpoch,
            'metrics'=>$metrics,
        ];
        $pageDiagnostics[]=['path'=>$path,'errors'=>$pageErrors];
        foreach($pageErrors as $error)$errors[]=$path.':'.$error;
    }

    $fingerprint=hash('sha256',json_encode([
        'host_id'=>$hostId,
        'period_start'=>$periodStart,
        'period_end'=>$periodEnd,
        'rows'=>$rows,
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

    return [
        'state'=>$errors===[]?'yandex_webmaster_feedback_ready':'yandex_webmaster_feedback_partial',
        'domain'=>'anytoour.ru',
        'launch_scope'=>'controlled_country_resort_v2',
        'host_id'=>$hostId,
        'collected_at_epoch'=>$collectedAtEpoch,
        'period_start_date'=>$periodStart,
        'period_end_date'=>$periodEnd,
        'selected_date_count'=>count($dateList),
        'controlled_path_count'=>count($allowed),
        'observed_controlled_path_count'=>count($rows),
        'ignored_outside_cohort_count'=>$ignored,
        'rows'=>$rows,
        'page_diagnostics'=>$pageDiagnostics,
        'collector_sha256'=>$fingerprint,
        'errors'=>array_values(array_unique($errors)),
        'missing_feedback_semantics'=>'unknown_not_zero',
        'publication_candidates'=>[],
        'automatic_execution_allowed'=>false,
        'automatic_expand_allowed'=>false,
        'automatic_deindex_allowed'=>false,
        'publication_allowed'=>false,
        'indexation_change_allowed'=>false,
        'sitemap_change_allowed'=>false,
        'canonical_change_allowed'=>false,
        'route_change_allowed'=>false,
        'hotel_tours_indexation_allowed'=>false,
        'hotel_tours_sitemap_allowed'=>false,
    ];
}
