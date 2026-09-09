<?php
declare(strict_types=1);
require_once __DIR__.'/seo-launch-slice-v1.php';

/**
 * Immutable launch cohort for measured SEO feedback.
 *
 * This baseline was collected from live anytoour.ru after the controlled
 * country/resort launch plus the two evidence-backed September seasonal pages.
 * The paths are intentionally pinned instead of being read dynamically from the
 * current launch allowlist: a future launch expansion must explicitly refresh
 * this evidence baseline before feedback for the new cohort is accepted.
 */
function v2_seo_postlaunch_feedback_cohort(): array
{
    $paths=[
        '/country/egypt/',
        '/country/maldives/',
        '/country/maldives/september/',
        '/country/turkey/',
        '/country/turkey/alanya/',
        '/country/turkey/antalya/',
        '/country/turkey/antalya/september/',
        '/country/turkey/belek/',
        '/country/turkey/kemer/',
        '/country/turkey/side/',
    ];
    sort($paths,SORT_STRING);
    $current=v2_seo_controlled_launch_paths();
    sort($current,SORT_STRING);
    return [
        'cohort_id'=>'controlled_country_resort_seasonal_v3',
        'domain'=>'anytoour.ru',
        'launch_source_sha'=>'9a721eb387bbdeae28e9979dcebde8959dd31bbd',
        'launch_baseline_sha256'=>'515921b352d69c9b57b37d45605ec1c3751f5deb587744e8e757c1605939c043',
        'launch_identity_registry_sha256'=>'df2679a82e43043b46daadffa6d3a216bc8fc09b82a4e43152ea7203b882a024',
        'launch_identity_observed_at_epoch'=>1788394228,
        'paths'=>$paths,
        'path_count'=>count($paths),
        'current_launch_scope_matches_baseline'=>$current===$paths,
        'hotel_tours_in_cohort'=>false,
    ];
}

/**
 * Normalize post-launch search feedback without turning missing data into zero.
 *
 * Supported metric sources are exports from Google Search Console and Yandex
 * Webmaster. Manual SERP review may record indexation/query/cannibalization
 * observations, but is forbidden from supplying analytics counters.
 */
function v2_seo_postlaunch_feedback_validate(array $input, ?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $cohort=v2_seo_postlaunch_feedback_cohort();
    $errors=[];
    $domain=trim((string)($input['domain']??''));
    $cohortId=trim((string)($input['cohort_id']??''));
    $launchSha=trim((string)($input['launch_source_sha']??''));
    if(($cohort['current_launch_scope_matches_baseline']??false)!==true)$errors[]='launch_cohort_baseline_drift';
    if($domain!==$cohort['domain'])$errors[]='domain_mismatch';
    if($cohortId!==$cohort['cohort_id'])$errors[]='cohort_mismatch';
    if($launchSha!==$cohort['launch_source_sha'])$errors[]='launch_source_sha_mismatch';

    $allowedPaths=array_fill_keys($cohort['paths'],true);
    $supportedSources=['google_search_console','yandex_webmaster','manual_serp_review'];
    $allowedIndexation=['indexed','not_indexed','unknown'];
    $allowedCannibal=['none','suspected','confirmed','unknown'];
    $maxAge=86400*31;
    $seen=[];$rows=[];

    foreach((is_array($input['rows']??null)?$input['rows']:[]) as $i=>$raw){
        if(!is_array($raw)){ $errors[]='row_invalid_'.$i; continue; }
        $rowErrors=[];
        $path=trim((string)($raw['path']??''));
        $sourceClass=trim((string)($raw['source_class']??''));
        $sourceRef=trim((string)($raw['source_ref']??''));
        $observed=(int)($raw['observed_at_epoch']??0);
        if($path===''||isset($seen[$path]))$rowErrors[]='path_duplicate_or_missing';
        if($path!==''&&!isset($allowedPaths[$path]))$rowErrors[]='path_outside_launch_cohort';
        if(str_contains($path,'/hotel/'))$rowErrors[]='hotel_tours_forbidden';
        if(!in_array($sourceClass,$supportedSources,true))$rowErrors[]='unsupported_source_class';
        if($sourceRef==='')$rowErrors[]='missing_source_ref';
        $fresh=$observed>0&&$observed<=$nowEpoch&&($nowEpoch-$observed)<=$maxAge;
        if($observed<=0)$rowErrors[]='observed_at_missing';
        elseif($observed>$nowEpoch+300)$rowErrors[]='observed_in_future';
        elseif($nowEpoch-$observed>$maxAge)$rowErrors[]='evidence_stale';

        $indexation=(string)($raw['indexation_state']??'unknown');
        if(!in_array($indexation,$allowedIndexation,true))$rowErrors[]='invalid_indexation_state';
        $cannibal=(string)($raw['cannibalization_state']??'unknown');
        if(!in_array($cannibal,$allowedCannibal,true))$rowErrors[]='invalid_cannibalization_state';

        $metrics=['impressions'=>null,'clicks'=>null,'ctr'=>null,'avg_position'=>null];
        $rawMetrics=is_array($raw['metrics']??null)?$raw['metrics']:[];
        foreach($rawMetrics as $key=>$value){
            $key=(string)$key;
            if(!array_key_exists($key,$metrics)){ $rowErrors[]='unsupported_metric_'.$key; continue; }
            if($value===null)continue;
            if(!is_int($value)&&!is_float($value)){ $rowErrors[]='invalid_metric_'.$key; continue; }
            $number=(float)$value;
            if($number<0){ $rowErrors[]='negative_metric_'.$key; continue; }
            if($key==='ctr'&&$number>1){ $rowErrors[]='ctr_out_of_range'; continue; }
            if($key==='avg_position'&&$number<=0){ $rowErrors[]='avg_position_out_of_range'; continue; }
            if(in_array($key,['impressions','clicks'],true)&&floor($number)!==$number){ $rowErrors[]='non_integer_metric_'.$key; continue; }
            $metrics[$key]=in_array($key,['impressions','clicks'],true)?(int)$number:$number;
        }
        if($sourceClass==='manual_serp_review'&&array_filter($metrics,static fn($v):bool=>$v!==null)!==[])$rowErrors[]='manual_serp_cannot_supply_analytics_metrics';
        if($metrics['impressions']!==null&&$metrics['clicks']!==null&&$metrics['clicks']>$metrics['impressions'])$rowErrors[]='clicks_exceed_impressions';

        $queries=[];
        foreach((is_array($raw['observed_queries']??null)?$raw['observed_queries']:[]) as $q){
            if(!is_string($q)||trim($q)===''){ $rowErrors[]='invalid_observed_query'; continue; }
            $queries[]=trim($q);
        }
        $queries=array_values(array_unique($queries)); sort($queries,SORT_STRING);

        $competing=[];
        foreach((is_array($raw['competing_paths']??null)?$raw['competing_paths']:[]) as $p){
            if(!is_string($p)||!str_starts_with($p,'/')){ $rowErrors[]='invalid_competing_path'; continue; }
            $p=trim($p);
            if($p===$path)$rowErrors[]='self_cannibal_path';
            else $competing[]=$p;
        }
        $competing=array_values(array_unique($competing)); sort($competing,SORT_STRING);
        if($cannibal==='none'&&$competing!==[])$rowErrors[]='cannibal_none_with_competing_paths';
        if(in_array($cannibal,['suspected','confirmed'],true)&&$competing===[])$rowErrors[]='cannibal_state_without_competing_paths';

        $rowErrors=array_values(array_unique($rowErrors));
        if($path!=='')$seen[$path]=true;
        $hasAnalytics=array_filter($metrics,static fn($v):bool=>$v!==null)!==[];
        $hasObservation=$hasAnalytics||$indexation!=='unknown'||$queries!==[]||$cannibal!=='unknown';
        $nonFreshnessErrors=array_values(array_diff($rowErrors,['evidence_stale']));
        if(in_array('evidence_stale',$rowErrors,true)&&$nonFreshnessErrors===[]){
            $state='stale';
        } elseif($rowErrors!==[]){
            $state='invalid';
        } else {
            $state=$fresh?($hasObservation?'measured':'unknown'):'stale';
        }
        $decision=$cannibal==='confirmed'&&$rowErrors===[]?'REVIEW_CANNIBALIZATION':'OBSERVE';
        if($state==='invalid'||$state==='stale')$decision='HOLD';

        foreach($rowErrors as $e)$errors[]=$path.':'.$e;
        $rows[]=[
            'path'=>$path,
            'source_class'=>$sourceClass,
            'source_ref'=>$sourceRef,
            'observed_at_epoch'=>$observed,
            'fresh'=>$fresh,
            'state'=>$state,
            'indexation_state'=>in_array($indexation,$allowedIndexation,true)?$indexation:'unknown',
            'metrics'=>$metrics,
            'observed_queries'=>$queries,
            'cannibalization_state'=>in_array($cannibal,$allowedCannibal,true)?$cannibal:'unknown',
            'competing_paths'=>$competing,
            'decision'=>$decision,
            'errors'=>$rowErrors,
        ];
    }

    usort($rows,static fn(array $a,array $b):int=>strcmp((string)$a['path'],(string)$b['path']));
    $errors=array_values(array_unique($errors));
    $measured=count(array_filter($rows,static fn(array $r):bool=>$r['state']==='measured'));
    $unknown=count(array_filter($rows,static fn(array $r):bool=>$r['state']==='unknown'));
    $stale=count(array_filter($rows,static fn(array $r):bool=>$r['state']==='stale'));
    $invalid=count(array_filter($rows,static fn(array $r):bool=>$r['state']==='invalid'));
    $missingPaths=array_values(array_diff($cohort['paths'],array_keys($seen))); sort($missingPaths,SORT_STRING);

    $fingerprint=hash('sha256',json_encode([
        'cohort_id'=>$cohortId,
        'domain'=>$domain,
        'launch_source_sha'=>$launchSha,
        'rows'=>$rows,
        'missing_paths'=>$missingPaths,
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

    return [
        'state'=>$errors===[]?'postlaunch_feedback_valid':'postlaunch_feedback_invalid',
        'domain'=>$domain,
        'cohort'=>$cohort,
        'row_count'=>count($rows),
        'measured_count'=>$measured,
        'unknown_count'=>$unknown,
        'stale_count'=>$stale,
        'invalid_count'=>$invalid,
        'missing_paths'=>$missingPaths,
        'rows'=>$rows,
        'feedback_sha256'=>$fingerprint,
        'errors'=>$errors,
        'missing_metrics_are_unknown_not_zero'=>true,
        'automatic_expand_allowed'=>false,
        'automatic_noindex_allowed'=>false,
        'publication_candidates'=>[],
        'hotel_tours_indexation_allowed'=>false,
        'hotel_tours_sitemap_allowed'=>false,
        'search_contract_changes'=>false,
        'tourvisor_contract_changes'=>false,
        'metrika_contract_changes'=>false,
    ];
}
