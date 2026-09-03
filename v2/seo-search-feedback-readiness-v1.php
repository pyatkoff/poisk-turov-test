<?php
declare(strict_types=1);
require_once __DIR__.'/seo-search-feedback-evidence-v1.php';
require_once __DIR__.'/seo-search-feedback-policy-v1.php';

/**
 * Build a review-only readiness view for the exact controlled SEO launch cohort.
 * Missing evidence is explicit and never coerced to zero. This report can say
 * whether the feedback packet is complete enough for policy review; it cannot
 * expand, deindex, publish or otherwise mutate production.
 */
function v2_seo_search_feedback_readiness(array $rows, array $policy=[], ?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $paths=v2_seo_controlled_launch_paths();
    $allowed=array_fill_keys($paths,true);
    $byPath=[];$globalErrors=[];

    foreach($rows as $i=>$raw){
        if(!is_array($raw)){
            $globalErrors[]='invalid_row_'.$i;
            continue;
        }
        $path=trim((string)($raw['path']??''));
        if($path===''||!isset($allowed[$path])){
            $globalErrors[]='row_outside_launch_cohort:'.($path!==''?$path:(string)$i);
            continue;
        }
        if(isset($byPath[$path])){
            $globalErrors[]='duplicate_feedback_path:'.$path;
            continue;
        }
        $byPath[$path]=v2_seo_search_feedback_evidence($raw,$nowEpoch);
    }

    $pages=[];$counts=['ready'=>0,'missing'=>0,'stale'=>0,'invalid'=>0];
    foreach($paths as $path){
        if(!isset($byPath[$path])){
            $status='missing';
            $pages[]=[
                'path'=>$path,
                'evidence_status'=>$status,
                'feedback_sha256'=>null,
                'errors'=>[],
            ];
            $counts[$status]++;
            continue;
        }
        $evidence=$byPath[$path];
        $errors=array_values(array_map('strval',(array)($evidence['errors']??[])));
        if(($evidence['state']??'')==='search_feedback_evidence_valid'){
            $status='ready';
        } elseif(in_array('feedback_evidence_stale',$errors,true) && count(array_diff($errors,['feedback_evidence_stale']))===0){
            $status='stale';
        } else {
            $status='invalid';
        }
        $pages[]=[
            'path'=>$path,
            'evidence_status'=>$status,
            'source_class'=>(string)($evidence['source_class']??''),
            'source_ref'=>(string)($evidence['source_ref']??''),
            'collected_at_epoch'=>(int)($evidence['collected_at_epoch']??0),
            'period_start_epoch'=>(int)($evidence['period_start_epoch']??0),
            'period_end_epoch'=>(int)($evidence['period_end_epoch']??0),
            'metrics'=>$evidence['metrics']??[],
            'feedback_sha256'=>(string)($evidence['feedback_sha256']??''),
            'errors'=>$errors,
        ];
        $counts[$status]++;
    }

    $policyStatus='missing';
    $validatedPolicy=null;
    if($policy!==[]){
        $validatedPolicy=v2_seo_search_feedback_policy($policy,$nowEpoch);
        $policyStatus=(($validatedPolicy['state']??'')==='search_feedback_policy_valid')?'ready':'invalid';
    }

    $evidenceComplete=$counts['ready']===count($paths) && $counts['missing']===0 && $counts['stale']===0 && $counts['invalid']===0 && $globalErrors===[];
    $reviewReady=$evidenceComplete && $policyStatus==='ready';
    $stable=[
        'domain'=>'anytoour.ru',
        'launch_scope'=>'controlled_country_resort_seasonal_v3',
        'pages'=>$pages,
        'counts'=>$counts,
        'global_errors'=>$globalErrors,
        'policy_status'=>$policyStatus,
        'policy_sha256'=>(string)($validatedPolicy['policy_sha256']??''),
    ];
    $sha=hash('sha256',json_encode($stable,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

    return [
        'state'=>$reviewReady?'search_feedback_readiness_ready':'search_feedback_readiness_blocked',
        'domain'=>'anytoour.ru',
        'launch_scope'=>'controlled_country_resort_seasonal_v3',
        'launch_path_count'=>count($paths),
        'evidence_complete'=>$evidenceComplete,
        'review_ready'=>$reviewReady,
        'counts'=>$counts,
        'pages'=>$pages,
        'global_errors'=>$globalErrors,
        'policy_status'=>$policyStatus,
        'policy'=>$validatedPolicy,
        'readiness_sha256'=>$sha,
        'missing_metrics_semantics'=>'unknown_not_zero',
        'recommendation_semantics'=>'review_only_no_execution',
        'explicit_user_approval_required'=>true,
        'automatic_execution_allowed'=>false,
        'automatic_expand_allowed'=>false,
        'automatic_deindex_allowed'=>false,
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_change_allowed'=>false,
        'sitemap_change_allowed'=>false,
        'canonical_change_allowed'=>false,
        'route_change_allowed'=>false,
        'hotel_tours_indexation_allowed'=>false,
        'hotel_tours_sitemap_allowed'=>false,
        'search_contract_changes'=>false,
        'tourvisor_contract_changes'=>false,
        'pricing_contract_changes'=>false,
        'lead_contract_changes'=>false,
        'metrika_contract_changes'=>false,
    ];
}
