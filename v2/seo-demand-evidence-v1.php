<?php
declare(strict_types=1);

/**
 * Normalize external SEO demand/SERP evidence without inventing demand values.
 *
 * This contract records provenance only. It does not fetch keyword data, infer
 * search volume or convert presence of a page/entity into demand. Missing or
 * stale evidence stays UNKNOWN and must block opportunity readiness upstream.
 */
function v2_seo_demand_evidence(array $record, ?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $pageKey=trim((string)($record['page_key']??''));
    $queryCluster=trim((string)($record['query_cluster']??''));
    $sourceClass=(string)($record['source_class']??'');
    $sourceRef=trim((string)($record['source_ref']??''));
    $observed=(int)($record['observed_at_epoch']??0);
    $status=(string)($record['status']??'unknown');
    $supportedSources=['search_console','keyword_research_export','manual_serp_review'];
    $errors=[];
    if($pageKey==='')$errors[]='missing_page_key';
    if($queryCluster==='')$errors[]='missing_query_cluster';
    if(!in_array($sourceClass,$supportedSources,true))$errors[]='unsupported_source_class';
    if($sourceRef==='')$errors[]='missing_source_ref';
    if(!in_array($status,['confirmed','blocked','unknown'],true))$errors[]='invalid_status';
    $maxAge=86400*31;
    $fresh=$observed>0&&$observed<=$nowEpoch&&($nowEpoch-$observed)<=$maxAge;
    if($status==='confirmed'&&!$fresh)$status='unknown';
    if($status==='confirmed'&&$errors!==[])$status='unknown';

    $metrics=is_array($record['metrics']??null)?$record['metrics']:[];
    $normalizedMetrics=[];
    foreach($metrics as $key=>$value){
        $key=(string)$key;
        if(!in_array($key,['impressions','clicks','avg_position','monthly_searches'],true))continue;
        if(!is_int($value)&&!is_float($value)){$errors[]='invalid_metric_'.$key;continue;}
        if($value<0){$errors[]='negative_metric_'.$key;continue;}
        $normalizedMetrics[$key]=$value;
    }
    // Confirmation requires at least one observed metric or an explicit SERP
    // classification. Presence alone is not treated as demand.
    $serpIntent=trim((string)($record['serp_intent']??''));
    if($serpIntent!==''&&!in_array($serpIntent,['commercial','informational','mixed'],true))$errors[]='invalid_serp_intent';
    if($status==='confirmed'&&$normalizedMetrics===[]&&$serpIntent==='')$status='unknown';
    if($errors!==[]&&$status==='confirmed')$status='unknown';

    return [
        'state'=>$errors===[]?'demand_evidence_valid':'demand_evidence_invalid',
        'status'=>$status,
        'page_key'=>$pageKey,
        'query_cluster'=>$queryCluster,
        'source_class'=>$sourceClass,
        'source_ref'=>$sourceRef,
        'observed_at_epoch'=>$observed,
        'fresh'=>$fresh,
        'metrics'=>$normalizedMetrics,
        'serp_intent'=>$serpIntent,
        'errors'=>array_values(array_unique($errors)),
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'route_launch_allowed'=>false,
    ];
}

function v2_seo_demand_signal_for_opportunity(array $evidence): array
{
    if(($evidence['state']??'')!=='demand_evidence_valid'||($evidence['status']??'')!=='confirmed'||($evidence['fresh']??false)!==true){
        return ['status'=>'unknown','source'=>'demand_evidence:unconfirmed','observed_at_epoch'=>(int)($evidence['observed_at_epoch']??0)];
    }
    // Deliberately no automatic demand score from raw metrics. A scoring policy
    // needs a separate reviewed contract; until then evidence can confirm demand
    // provenance but cannot fabricate a 0..100 score.
    return [
        'status'=>'confirmed',
        'score'=>null,
        'source'=>'demand_evidence:'.(string)$evidence['source_class'].':'.(string)$evidence['source_ref'],
        'observed_at_epoch'=>(int)$evidence['observed_at_epoch'],
        'query_cluster'=>(string)$evidence['query_cluster'],
        'metrics'=>(array)$evidence['metrics'],
        'serp_intent'=>(string)$evidence['serp_intent'],
        'requires_explicit_scoring_policy'=>true,
    ];
}
