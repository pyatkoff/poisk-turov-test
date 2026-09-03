<?php
declare(strict_types=1);

/**
 * Validate an explicitly supplied search-feedback review policy.
 *
 * There are intentionally no built-in thresholds, defaults or fallback action.
 * Every rule and threshold must come from a reviewed external policy source.
 */
function v2_seo_search_feedback_policy(array $policy, ?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $errors=[];
    $policyId=trim((string)($policy['policy_id']??''));
    $version=trim((string)($policy['version']??''));
    $sourceRef=trim((string)($policy['source_ref']??''));
    $approvedAt=(int)($policy['approved_at_epoch']??0);
    $rules=is_array($policy['rules']??null)?array_values($policy['rules']):[];
    $allowedRecommendations=['hold_review','improve_review','expand_review','merge_review','noindex_review','retire_review'];
    $allowedFields=['metrics.impressions','metrics.clicks','metrics.avg_position','metrics.ctr','metrics.query_count'];
    $allowedOperators=['eq','gte','lte','gt','lt'];

    if($policyId==='')$errors[]='missing_policy_id';
    if($version==='')$errors[]='missing_version';
    if($sourceRef==='')$errors[]='missing_source_ref';
    if($approvedAt<=0)$errors[]='missing_approved_at_epoch';
    elseif($approvedAt>$nowEpoch+300)$errors[]='approved_at_in_future';
    if($rules===[])$errors[]='missing_rules';

    $normalized=[];$ruleIds=[];
    foreach($rules as $i=>$rule){
        if(!is_array($rule)){$errors[]='invalid_rule_'.$i;continue;}
        $ruleId=trim((string)($rule['rule_id']??''));
        $recommendation=(string)($rule['recommendation']??'');
        $conditions=is_array($rule['conditions']??null)?array_values($rule['conditions']):[];
        if($ruleId==='')$errors[]='missing_rule_id_'.$i;
        elseif(isset($ruleIds[$ruleId]))$errors[]='duplicate_rule_id_'.$ruleId;
        else $ruleIds[$ruleId]=true;
        if(!in_array($recommendation,$allowedRecommendations,true))$errors[]='invalid_recommendation_'.$i;
        if($conditions===[])$errors[]='missing_conditions_'.$i;
        $normalizedConditions=[];
        foreach($conditions as $j=>$condition){
            if(!is_array($condition)){$errors[]='invalid_condition_'.$i.'_'.$j;continue;}
            $field=(string)($condition['field']??'');
            $operator=(string)($condition['operator']??'');
            $value=$condition['value']??null;
            if(!in_array($field,$allowedFields,true))$errors[]='invalid_condition_field_'.$i.'_'.$j;
            if(!in_array($operator,$allowedOperators,true))$errors[]='invalid_condition_operator_'.$i.'_'.$j;
            if(!is_int($value)&&!is_float($value))$errors[]='invalid_condition_value_'.$i.'_'.$j;
            $normalizedConditions[]=['field'=>$field,'operator'=>$operator,'value'=>$value];
        }
        $normalized[]=['rule_id'=>$ruleId,'recommendation'=>$recommendation,'conditions'=>$normalizedConditions];
    }

    $fingerprintPayload=[
        'policy_id'=>$policyId,
        'version'=>$version,
        'source_ref'=>$sourceRef,
        'approved_at_epoch'=>$approvedAt,
        'rules'=>$normalized,
    ];
    $sha=hash('sha256',json_encode($fingerprintPayload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    return [
        'state'=>$errors===[]?'search_feedback_policy_valid':'search_feedback_policy_invalid',
        'policy_id'=>$policyId,
        'version'=>$version,
        'source_ref'=>$sourceRef,
        'approved_at_epoch'=>$approvedAt,
        'rules'=>$normalized,
        'policy_sha256'=>$sha,
        'errors'=>array_values(array_unique($errors)),
        'automatic_execution_allowed'=>false,
        'automatic_deindex_allowed'=>false,
        'publication_allowed'=>false,
        'indexation_change_allowed'=>false,
        'sitemap_change_allowed'=>false,
        'canonical_change_allowed'=>false,
        'route_change_allowed'=>false,
        'hotel_tours_indexation_allowed'=>false,
    ];
}

function v2_seo_search_feedback_condition_matches(array $row, array $condition): bool
{
    $field=(string)($condition['field']??'');
    if(!str_starts_with($field,'metrics.'))return false;
    $metric=substr($field,8);
    $metrics=is_array($row['metrics']??null)?$row['metrics']:[];
    if(!array_key_exists($metric,$metrics))return false;
    $actual=$metrics[$metric];
    $expected=$condition['value']??null;
    if((!is_int($actual)&&!is_float($actual))||(!is_int($expected)&&!is_float($expected)))return false;
    return match((string)($condition['operator']??'')){
        'eq'=>(float)$actual===(float)$expected,
        'gte'=>(float)$actual>=(float)$expected,
        'lte'=>(float)$actual<=(float)$expected,
        'gt'=>(float)$actual>(float)$expected,
        'lt'=>(float)$actual<(float)$expected,
        default=>false,
    };
}

/**
 * Apply a valid explicit policy to already-validated feedback evidence.
 * Recommendations are review labels only; no result can mutate production.
 */
function v2_seo_search_feedback_review(array $intake, array $policy, ?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $validatedPolicy=v2_seo_search_feedback_policy($policy,$nowEpoch);
    $errors=[];
    if(($intake['state']??'')!=='search_feedback_intake_ready')$errors[]='feedback_intake_not_ready';
    if(($validatedPolicy['state']??'')!=='search_feedback_policy_valid')$errors[]='invalid_feedback_policy';

    $recommendations=[];
    if($errors===[]){
        foreach((array)($intake['rows']??[]) as $row){
            if(!is_array($row)||($row['state']??'')!=='search_feedback_evidence_valid'){
                $errors[]='invalid_feedback_row';
                continue;
            }
            $matchedRule=null;
            foreach($validatedPolicy['rules'] as $rule){
                $matches=true;
                foreach((array)$rule['conditions'] as $condition){
                    if(!v2_seo_search_feedback_condition_matches($row,$condition)){$matches=false;break;}
                }
                if($matches){$matchedRule=$rule;break;}
            }
            $path=(string)($row['path']??'');
            if($matchedRule===null){
                $errors[]='no_matching_rule:'.$path;
                $recommendations[]=[
                    'path'=>$path,
                    'status'=>'policy_no_match',
                    'recommendation'=>null,
                    'rule_id'=>null,
                    'feedback_sha256'=>(string)($row['feedback_sha256']??''),
                ];
                continue;
            }
            $recommendations[]=[
                'path'=>$path,
                'status'=>'review_recommendation_only',
                'recommendation'=>(string)$matchedRule['recommendation'],
                'rule_id'=>(string)$matchedRule['rule_id'],
                'feedback_sha256'=>(string)($row['feedback_sha256']??''),
            ];
        }
    }

    $missing=[];
    foreach((array)($intake['missing_paths']??[]) as $path){
        $missing[]=['path'=>(string)$path,'status'=>'unknown_no_evidence','recommendation'=>null];
    }
    $fingerprint=hash('sha256',json_encode([
        'feedback_intake_sha256'=>$intake['feedback_intake_sha256']??'',
        'policy_sha256'=>$validatedPolicy['policy_sha256']??'',
        'recommendations'=>$recommendations,
        'missing'=>$missing,
    ],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

    return [
        'state'=>$errors===[]?'search_feedback_review_ready':'search_feedback_review_blocked',
        'domain'=>'anytoour.ru',
        'launch_scope'=>(string)($intake['launch_scope']??'controlled_country_resort_seasonal_v3'),
        'observed_count'=>(int)($intake['observed_count']??0),
        'missing_count'=>count($missing),
        'recommendations'=>$recommendations,
        'missing'=>$missing,
        'policy'=>$validatedPolicy,
        'feedback_intake_sha256'=>(string)($intake['feedback_intake_sha256']??''),
        'review_sha256'=>$fingerprint,
        'errors'=>array_values(array_unique($errors)),
        'recommendation_semantics'=>'review_only_no_execution',
        'explicit_user_approval_required'=>true,
        'automatic_execution_allowed'=>false,
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
