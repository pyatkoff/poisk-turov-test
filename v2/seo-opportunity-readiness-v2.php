<?php
declare(strict_types=1);

/**
 * SEO Opportunity / Launch Readiness v2.
 *
 * This layer answers whether a review page is worth considering for a future
 * launch. It is intentionally separate from technical page readiness and never
 * grants publication/indexation/sitemap/canonical/route launch permissions.
 * Missing evidence is UNKNOWN and blocks candidacy instead of receiving an
 * invented score.
 */
function v2_seo_opportunity_readiness(array $page, array $signals, ?int $nowEpoch = null): array
{
    $nowEpoch ??= time();
    $path=(string)($page['path']??'');
    $pageRole=(string)($page['page_role']??'');
    $intent=(string)($page['intent']??'');
    $allowedRoles=['country_tours','resort_tours','hotel_tours','seasonal_tours','informational_guide'];
    $allowedIntents=['commercial_transactional','informational'];
    $errors=[];
    if($path===''||$path[0]!=='/') $errors[]='invalid_path';
    if(!in_array($pageRole,$allowedRoles,true)) $errors[]='unsupported_page_role';
    if(!in_array($intent,$allowedIntents,true)) $errors[]='unsupported_intent';
    if($pageRole==='informational_guide'&&$intent!=='informational') $errors[]='role_intent_mismatch';
    if($pageRole!=='informational_guide'&&$intent!=='commercial_transactional') $errors[]='role_intent_mismatch';

    $dimensions=[];
    $definitions=[
        'entity'=>['required'=>true,'max_age'=>86400*30],
        'intent'=>['required'=>true,'max_age'=>86400*30],
        'demand'=>['required'=>true,'max_age'=>86400*31],
        'uniqueness'=>['required'=>true,'max_age'=>86400*31],
        'content'=>['required'=>true,'max_age'=>86400*31],
        'technical'=>['required'=>true,'max_age'=>86400*7],
        'commercial_inventory'=>['required'=>$intent==='commercial_transactional','max_age'=>86400],
    ];
    $knownScores=[]; $blocked=[];
    foreach($definitions as $key=>$definition){
        $row=is_array($signals[$key]??null)?$signals[$key]:[];
        $status=(string)($row['status']??'unknown');
        $score=array_key_exists('score',$row)?(int)$row['score']:null;
        $observed=(int)($row['observed_at_epoch']??0);
        $source=(string)($row['source']??'');
        $required=(bool)$definition['required'];
        $fresh=$observed>0&&$observed<=$nowEpoch&&($nowEpoch-$observed)<=(int)$definition['max_age'];
        if(!in_array($status,['confirmed','blocked','unknown'],true)) $status='unknown';
        if($status==='confirmed'&&($score===null||$score<0||$score>100||$source===''||!$fresh)) $status='unknown';
        if($status==='blocked'&&$source==='') $status='unknown';
        if($status==='confirmed') $knownScores[]=$score;
        if($required&&$status!=='confirmed') $blocked[]=$key.':'.$status;
        $dimensions[$key]=[
            'required'=>$required,
            'status'=>$status,
            'score'=>$status==='confirmed'?$score:null,
            'source'=>$source,
            'observed_at_epoch'=>$observed,
            'fresh'=>$fresh,
        ];
    }

    // Demand and uniqueness are gates, not decorative averages. A technically
    // perfect page cannot become a candidate while either is unknown/blocked.
    $opportunityScore=$knownScores===[]?null:(int)round(array_sum($knownScores)/count($knownScores));
    $reviewCandidate=$errors===[]&&$blocked===[];
    return [
        'state'=>$errors!==[]?'invalid':($reviewCandidate?'opportunity_review_ready':'opportunity_evidence_blocked'),
        'path'=>$path,
        'page_role'=>$pageRole,
        'intent'=>$intent,
        'opportunity_score'=>$opportunityScore,
        'review_candidate'=>$reviewCandidate,
        'dimensions'=>$dimensions,
        'blocked_dimensions'=>$blocked,
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
