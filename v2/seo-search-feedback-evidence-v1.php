<?php
declare(strict_types=1);
require_once __DIR__.'/seo-launch-slice-v1.php';

/**
 * Normalize one external search-performance observation for an already-launched
 * SEO page. Missing rows are UNKNOWN, never implicit zeroes. This layer records
 * evidence only and never makes automatic expand/deindex decisions.
 */
function v2_seo_search_feedback_evidence(array $record, ?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $errors=[];
    $path=trim((string)($record['path']??''));
    $sourceClass=(string)($record['source_class']??'');
    $sourceRef=trim((string)($record['source_ref']??''));
    $collectedAt=(int)($record['collected_at_epoch']??0);
    $periodStart=(int)($record['period_start_epoch']??0);
    $periodEnd=(int)($record['period_end_epoch']??0);
    $allowedSources=['google_search_console_export','yandex_webmaster_export'];
    $launchPaths=v2_seo_controlled_launch_paths();

    if(!in_array($path,$launchPaths,true))$errors[]='path_not_in_launched_scope';
    if(!in_array($sourceClass,$allowedSources,true))$errors[]='unsupported_source_class';
    if($sourceRef==='')$errors[]='missing_source_ref';
    if($collectedAt<=0||$collectedAt>$nowEpoch+300)$errors[]='invalid_collected_at';
    if($periodStart<=0||$periodEnd<=0||$periodStart>$periodEnd)$errors[]='invalid_period';
    if($periodEnd>$collectedAt)$errors[]='period_ends_after_collection';
    if($periodStart>0&&$periodEnd>0&&$periodEnd-$periodStart>35*86400)$errors[]='period_too_long';
    $fresh=$collectedAt>0&&$collectedAt<=$nowEpoch&&($nowEpoch-$collectedAt)<=7*86400;
    if(!$fresh)$errors[]='feedback_evidence_stale';

    $metrics=is_array($record['metrics']??null)?$record['metrics']:[];
    $normalized=[];
    foreach(['impressions','clicks','avg_position','ctr','query_count'] as $key){
        if(!array_key_exists($key,$metrics))continue;
        $value=$metrics[$key];
        if(!is_int($value)&&!is_float($value)){$errors[]='invalid_metric_'.$key;continue;}
        if($value<0){$errors[]='negative_metric_'.$key;continue;}
        if(in_array($key,['impressions','clicks','query_count'],true)&&!is_int($value)){$errors[]='metric_must_be_integer_'.$key;continue;}
        if($key==='ctr'&&$value>1){$errors[]='ctr_out_of_range';continue;}
        if($key==='avg_position'&&$value>0&&$value<1){$errors[]='avg_position_out_of_range';continue;}
        $normalized[$key]=$value;
    }
    foreach(['impressions','clicks','avg_position','ctr'] as $required){
        if(!array_key_exists($required,$normalized))$errors[]='missing_metric_'.$required;
    }
    if(isset($normalized['impressions'],$normalized['clicks'])&&$normalized['clicks']>$normalized['impressions'])$errors[]='clicks_exceed_impressions';

    $fingerprint=hash('sha256',json_encode([
        'path'=>$path,
        'source_class'=>$sourceClass,
        'source_ref'=>$sourceRef,
        'collected_at_epoch'=>$collectedAt,
        'period_start_epoch'=>$periodStart,
        'period_end_epoch'=>$periodEnd,
        'metrics'=>$normalized,
    ],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

    return [
        'state'=>$errors===[]?'search_feedback_evidence_valid':'search_feedback_evidence_invalid',
        'path'=>$path,
        'source_class'=>$sourceClass,
        'source_ref'=>$sourceRef,
        'collected_at_epoch'=>$collectedAt,
        'period_start_epoch'=>$periodStart,
        'period_end_epoch'=>$periodEnd,
        'fresh'=>$fresh,
        'metrics'=>$normalized,
        'feedback_sha256'=>$fingerprint,
        'errors'=>array_values(array_unique($errors)),
        'requires_explicit_feedback_policy'=>true,
        'automatic_recommendation_allowed'=>false,
        'automatic_deindex_allowed'=>false,
        'publication_allowed'=>false,
        'indexation_change_allowed'=>false,
        'sitemap_change_allowed'=>false,
        'hotel_tours_indexation_allowed'=>false,
    ];
}

/**
 * Intake can be partial: a missing launched page means UNKNOWN feedback, not
 * zero impressions/clicks. Any supplied invalid row blocks the intake.
 */
function v2_seo_search_feedback_intake(array $rows, ?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $launchPaths=v2_seo_controlled_launch_paths();
    $byPath=[]; $errors=[];
    foreach($rows as $i=>$raw){
        if(!is_array($raw)){ $errors[]='invalid_row_'.$i; continue; }
        $normalized=v2_seo_search_feedback_evidence($raw,$nowEpoch);
        $path=(string)($normalized['path']??'');
        if($path!==''&&isset($byPath[$path]))$errors[]='duplicate_feedback_path:'.$path;
        if($path!=='')$byPath[$path]=$normalized;
        if(($normalized['state']??'')!=='search_feedback_evidence_valid')$errors[]='invalid_feedback_row:'.$path;
    }
    $observed=array_keys($byPath); sort($observed,SORT_STRING);
    $missing=array_values(array_diff($launchPaths,$observed)); sort($missing,SORT_STRING);
    ksort($byPath,SORT_STRING);
    if($rows===[])$errors[]='no_feedback_evidence_supplied';

    $fingerprint=hash('sha256',json_encode([
        'launch_scope'=>'controlled_country_resort_v2',
        'rows'=>array_map(static fn(array $row):string=>(string)($row['feedback_sha256']??''),$byPath),
        'missing_paths'=>$missing,
    ],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

    return [
        'state'=>$errors===[]?'search_feedback_intake_ready':'search_feedback_intake_blocked',
        'domain'=>'anytoour.ru',
        'launch_scope'=>'controlled_country_resort_v2',
        'launched_path_count'=>count($launchPaths),
        'observed_count'=>count($byPath),
        'observed_paths'=>$observed,
        'missing_paths'=>$missing,
        'missing_feedback_semantics'=>'unknown_not_zero',
        'rows'=>array_values($byPath),
        'feedback_intake_sha256'=>$fingerprint,
        'errors'=>array_values(array_unique($errors)),
        'recommendation_state'=>'requires_explicit_feedback_policy',
        'automatic_recommendation_allowed'=>false,
        'automatic_deindex_allowed'=>false,
        'publication_candidates'=>[],
        'publication_scope_expanded'=>false,
        'publication_allowed'=>false,
        'indexation_change_allowed'=>false,
        'sitemap_change_allowed'=>false,
        'canonical_change_allowed'=>false,
        'route_change_allowed'=>false,
        'hotel_tours_indexation_allowed'=>false,
        'search_contract_changes'=>false,
        'tourvisor_contract_changes'=>false,
        'pricing_contract_changes'=>false,
        'lead_contract_changes'=>false,
        'metrika_contract_changes'=>false,
    ];
}
