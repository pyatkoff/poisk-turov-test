<?php
declare(strict_types=1);
require_once __DIR__.'/seo-seasonal-intent-v1.php';
require_once __DIR__.'/seo-opportunity-readiness-v2.php';

/**
 * Gate seasonal review pages on explicit SEO opportunity evidence.
 * A valid identity/intent contract can confirm only entity + intent; demand,
 * uniqueness, content, technical and commercial inventory remain independent
 * evidence requirements. This never grants launch permissions.
 */
function v2_seo_seasonal_opportunity_gate(array $page, array $signals, ?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $intent=v2_seo_seasonal_intent_contract($page);
    if(($intent['review_ready']??false)!==true) {
        return [
            'state'=>'seasonal_intent_blocked',
            'intent'=>$intent,
            'opportunity'=>null,
            'publication_candidates'=>[],
            'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'canonical_launch_allowed'=>false,'route_launch_allowed'=>false,
        ];
    }
    $role=(string)($intent['page_role']??'');
    $opportunityRole=$role==='informational_guide'?'informational_guide':'seasonal_tours';
    $searchIntent=(string)($intent['search_intent']??'');
    foreach(['entity','intent'] as $key){
        if(!isset($signals[$key])){
            $signals[$key]=['status'=>'confirmed','score'=>100,'observed_at_epoch'=>$nowEpoch,'source'=>'seasonal_intent_contract:'.$key];
        }
    }
    $opportunity=v2_seo_opportunity_readiness([
        'path'=>(string)$intent['path'],
        'page_role'=>$opportunityRole,
        'intent'=>$searchIntent,
    ],$signals,$nowEpoch);
    return [
        'state'=>($opportunity['review_candidate']??false)?'seasonal_opportunity_review_ready':'seasonal_opportunity_evidence_blocked',
        'intent'=>$intent,
        'opportunity'=>$opportunity,
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
        'explicit_user_launch_approval_required'=>true,
    ];
}
