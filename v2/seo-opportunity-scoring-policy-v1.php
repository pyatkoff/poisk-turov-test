<?php
declare(strict_types=1);

/**
 * Validate and apply an explicitly supplied SEO opportunity scoring policy.
 *
 * There is deliberately no built-in/default policy and no implicit threshold.
 * A caller must provide reviewed rules, weights and provenance. If a required
 * rule is missing or no rule matches the observed evidence, scoring fails closed.
 */
function v2_seo_opportunity_scoring_policy(array $policy): array
{
    $errors=[];
    $policyId=trim((string)($policy['policy_id']??''));
    $version=trim((string)($policy['version']??''));
    $sourceRef=trim((string)($policy['source_ref']??''));
    $approvedAt=(int)($policy['approved_at_epoch']??0);
    $dimensions=is_array($policy['dimensions']??null)?$policy['dimensions']:[];
    if($policyId==='')$errors[]='missing_policy_id';
    if($version==='')$errors[]='missing_version';
    if($sourceRef==='')$errors[]='missing_source_ref';
    if($approvedAt<=0)$errors[]='missing_approved_at_epoch';

    $normalized=[]; $weightTotal=0;
    foreach(['demand','uniqueness'] as $dimension){
        $row=is_array($dimensions[$dimension]??null)?$dimensions[$dimension]:[];
        $weight=(int)($row['weight']??0);
        $rules=is_array($row['rules']??null)?array_values($row['rules']):[];
        if($weight<=0||$weight>100)$errors[]=$dimension.'_invalid_weight';
        if($rules===[])$errors[]=$dimension.'_missing_rules';
        $weightTotal+=$weight;
        $normalizedRules=[];
        foreach($rules as $i=>$rule){
            if(!is_array($rule)){ $errors[]=$dimension.'_invalid_rule_'.$i; continue; }
            $field=trim((string)($rule['field']??''));
            $operator=(string)($rule['operator']??'');
            $value=$rule['value']??null;
            $score=$rule['score']??null;
            if($field===''||!in_array($operator,['eq','gte','lte','gt','lt'],true)||!is_numeric($score)||(int)$score<0||(int)$score>100){
                $errors[]=$dimension.'_invalid_rule_'.$i; continue;
            }
            if($dimension==='demand'&&!str_starts_with($field,'metrics.'))$errors[]='demand_rule_field_not_metric_'.$i;
            if($dimension==='uniqueness'&&!in_array($field,['decision','overlap_ratio'],true))$errors[]='uniqueness_rule_field_'.$i;
            $normalizedRules[]=['field'=>$field,'operator'=>$operator,'value'=>$value,'score'=>(int)$score];
        }
        $normalized[$dimension]=['weight'=>$weight,'rules'=>$normalizedRules];
    }
    if($weightTotal!==100)$errors[]='weights_must_total_100';
    $fingerprintPayload=['policy_id'=>$policyId,'version'=>$version,'source_ref'=>$sourceRef,'approved_at_epoch'=>$approvedAt,'dimensions'=>$normalized];
    $sha=hash('sha256',json_encode($fingerprintPayload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    return [
        'state'=>$errors===[]?'opportunity_scoring_policy_valid':'opportunity_scoring_policy_invalid',
        'policy_id'=>$policyId,
        'version'=>$version,
        'source_ref'=>$sourceRef,
        'approved_at_epoch'=>$approvedAt,
        'dimensions'=>$normalized,
        'policy_sha256'=>$sha,
        'errors'=>array_values(array_unique($errors)),
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
    ];
}

function v2_seo_opportunity_score_evidence_packet(array $packet, array $policy): array
{
    $validated=v2_seo_opportunity_scoring_policy($policy);
    $errors=[];
    if(($validated['state']??'')!=='opportunity_scoring_policy_valid')$errors[]='invalid_scoring_policy';
    if(($packet['state']??'')!=='opportunity_evidence_review_ready')$errors[]='evidence_packet_not_ready';
    if(($packet['evidence_fresh']??false)!==true)$errors[]='evidence_not_fresh';
    if(($packet['uniqueness_distinct']??false)!==true)$errors[]='uniqueness_not_distinct';

    $dimensionScores=[];
    foreach(['demand','uniqueness'] as $dimension){
        $rules=$validated['dimensions'][$dimension]['rules']??[];
        $source=is_array($packet[$dimension]??null)?$packet[$dimension]:[];
        $matched=null;
        foreach($rules as $rule){
            $field=(string)$rule['field'];
            $actual=null; $exists=false;
            if(str_starts_with($field,'metrics.')){
                $metric=substr($field,8);
                if(is_array($source['metrics']??null)&&array_key_exists($metric,$source['metrics'])){$actual=$source['metrics'][$metric];$exists=true;}
            } elseif(array_key_exists($field,$source)){$actual=$source[$field];$exists=true;}
            if(!$exists)continue;
            $expected=$rule['value']; $ok=false;
            switch($rule['operator']){
                case 'eq': $ok=(string)$actual===(string)$expected; break;
                case 'gte': $ok=is_numeric($actual)&&is_numeric($expected)&&(float)$actual>=(float)$expected; break;
                case 'lte': $ok=is_numeric($actual)&&is_numeric($expected)&&(float)$actual<=(float)$expected; break;
                case 'gt': $ok=is_numeric($actual)&&is_numeric($expected)&&(float)$actual>(float)$expected; break;
                case 'lt': $ok=is_numeric($actual)&&is_numeric($expected)&&(float)$actual<(float)$expected; break;
            }
            if($ok){$matched=(int)$rule['score'];break;}
        }
        if($matched===null)$errors[]=$dimension.'_no_matching_rule';
        else $dimensionScores[$dimension]=$matched;
    }

    $score=null;
    if($errors===[]){
        $score=0.0;
        foreach($dimensionScores as $dimension=>$dimensionScore){
            $score += $dimensionScore*((int)$validated['dimensions'][$dimension]['weight']/100);
        }
        $score=(int)round($score);
    }
    $scoreFingerprint=hash('sha256',json_encode([
        'packet_sha256'=>$packet['packet_sha256']??'',
        'policy_sha256'=>$validated['policy_sha256']??'',
        'dimension_scores'=>$dimensionScores,
        'score'=>$score,
    ],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    return [
        'state'=>$errors===[]?'opportunity_scored_review_only':'opportunity_scoring_blocked',
        'page_key'=>(string)($packet['page_key']??''),
        'path'=>(string)($packet['path']??''),
        'query_cluster'=>(string)($packet['query_cluster']??''),
        'score'=>$score,
        'dimension_scores'=>$dimensionScores,
        'policy'=>$validated,
        'packet_sha256'=>(string)($packet['packet_sha256']??''),
        'score_sha256'=>$scoreFingerprint,
        'errors'=>array_values(array_unique($errors)),
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
        'explicit_user_launch_approval_required'=>true,
    ];
}
