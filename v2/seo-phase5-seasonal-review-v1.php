<?php
declare(strict_types=1);
require_once __DIR__.'/seo-phase5-expansion-review-gate-v1.php';
require_once __DIR__.'/seo-seasonal-opportunity-gate-v1.php';

/**
 * Phase-5 wrapper for seasonal review only. It composes the existing seasonal
 * opportunity evidence with the completed upstream freshness chain. No launch
 * permission is granted here.
 */
function v2_seo_phase5_seasonal_review(array $freshnessChain,array $page,array $signals,?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $seasonal=v2_seo_seasonal_opportunity_gate($page,$signals,$nowEpoch);
    $sources=[];
    foreach($signals as $signal){
        if(is_array($signal)&&trim((string)($signal['source']??''))!=='')$sources[]=(string)$signal['source'];
    }
    $sources=array_values(array_unique($sources));sort($sources,SORT_STRING);
    $candidate=[
        'kind'=>'seasonal',
        'review_ready'=>(($seasonal['state']??'')==='seasonal_opportunity_review_ready'),
        'fresh_evidence'=>(($seasonal['opportunity']['review_candidate']??false)===true),
        'source_ref'=>implode('|',$sources),
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'route_launch_allowed'=>false,
    ];
    $phase5=v2_seo_phase5_expansion_review_gate($freshnessChain,$candidate);
    $ready=(($seasonal['state']??'')==='seasonal_opportunity_review_ready')&&(($phase5['state']??'')==='phase5_expansion_review_ready');
    return [
        'state'=>$ready?'phase5_seasonal_review_ready':'phase5_seasonal_review_blocked',
        'seasonal'=>$seasonal,
        'phase5'=>$phase5,
        'review_allowed'=>$ready,
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
        'hotel_tours_publication_candidates'=>[],
        'hotel_tours_publication_allowed'=>false,
        'hotel_tours_indexation_allowed'=>false,
        'hotel_tours_sitemap_allowed'=>false,
        'hotel_tours_canonical_launch_allowed'=>false,
        'hotel_tours_route_launch_allowed'=>false,
        'separate_user_hotel_indexation_approval_required'=>true,
        'search_contract_changes'=>false,
        'tourvisor_contract_changes'=>false,
        'pricing_contract_changes'=>false,
        'lead_contract_changes'=>false,
        'metrika_contract_changes'=>false,
    ];
}
