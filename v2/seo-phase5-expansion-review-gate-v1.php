<?php
declare(strict_types=1);

/**
 * Ordered boundary for phase 5. Expansion work may be reviewed only after the
 * upstream fresh-evidence chain is green. This gate never grants publication.
 */
function v2_seo_phase5_expansion_review_gate(array $freshnessChain, array $candidateReview): array
{
    $errors=[];
    if(($freshnessChain['state']??'')!=='fresh_evidence_chain_ready_for_expansion_review')$errors[]='upstream_evidence_chain_not_ready';
    if(($freshnessChain['expansion_review_allowed']??false)!==true)$errors[]='upstream_expansion_review_not_allowed';
    foreach(['publication_allowed','hotel_tours_publication_allowed','hotel_tours_indexation_allowed','hotel_tours_sitemap_allowed','hotel_tours_canonical_launch_allowed','hotel_tours_route_launch_allowed'] as $flag){
        if(($freshnessChain[$flag]??true)!==false)$errors[]='upstream_boundary_'.$flag;
    }
    if(($freshnessChain['hotel_tours_publication_candidates']??null)!==[])$errors[]='upstream_hotel_publication_candidates_present';

    $kind=(string)($candidateReview['kind']??'');
    if(!in_array($kind,['resort','seasonal','data','feed'],true))$errors[]='unsupported_expansion_kind';
    if(($candidateReview['review_ready']??false)!==true)$errors[]='candidate_review_not_ready';
    if(($candidateReview['fresh_evidence']??false)!==true)$errors[]='candidate_evidence_not_fresh';
    if(trim((string)($candidateReview['source_ref']??''))==='')$errors[]='candidate_source_ref_missing';
    if(($candidateReview['publication_allowed']??true)!==false)$errors[]='candidate_publication_boundary';
    if(($candidateReview['indexation_allowed']??true)!==false)$errors[]='candidate_indexation_boundary';
    if(($candidateReview['sitemap_allowed']??true)!==false)$errors[]='candidate_sitemap_boundary';
    if(($candidateReview['route_launch_allowed']??true)!==false)$errors[]='candidate_route_boundary';

    $ready=$errors===[];
    return [
        'state'=>$ready?'phase5_expansion_review_ready':'phase5_expansion_review_blocked',
        'kind'=>$kind,
        'errors'=>array_values(array_unique($errors)),
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
