<?php
declare(strict_types=1);
require_once __DIR__.'/seo-phase5-expansion-review-gate-v1.php';

/**
 * Converts first-party inventory opportunities into phase-5 REVIEW candidates only.
 * Route authority must come from the controlled identity registry. Catalog slug hints
 * are never routes. This function never grants publication/indexation/sitemap/route launch.
 */
function v2_seo_phase5_inventory_opportunity_review(array $freshnessChain,array $opportunityReport): array
{
    $errors=[];
    if(($opportunityReport['state']??'')!=='review_only_inventory_opportunity_report')$errors[]='invalid_opportunity_report_state';
    if(($opportunityReport['publication_candidates']??null)!==[])$errors[]='opportunity_publication_candidates_present';
    foreach(['publication_allowed','automatic_execution_allowed','hotel_tours_indexation_allowed'] as $flag){
        if(($opportunityReport[$flag]??true)!==false)$errors[]='opportunity_boundary_'.$flag;
    }
    if(($opportunityReport['route_semantics']??'')!=='only_explicit_identity_registry_bindings_are_routes_catalog_slugs_are_hints_only')$errors[]='route_semantics_invalid';
    if(($opportunityReport['candidate_generation_semantics']??'')!=='observed_database_groups_only_no_cartesian_generation')$errors[]='candidate_generation_semantics_invalid';
    $sourceRef=trim((string)($opportunityReport['evidence_sha256']??''));
    if($sourceRef==='')$errors[]='opportunity_evidence_fingerprint_missing';

    $review=[];$blocked=[];
    foreach(($opportunityReport['candidates']??[]) as $candidate){
        if(!is_array($candidate)){ $blocked[]=['errors'=>['invalid_candidate']]; continue; }
        $type=(string)($candidate['candidate_type']??'');
        $kind=match($type){
            'resort'=>'resort',
            'country_month','resort_month'=>'seasonal',
            default=>'',
        };
        if($kind==='') continue; // country expansion is not a phase-5 resort/seasonal candidate.

        $rowErrors=[];
        if(($candidate['route_mapping_state']??'')!=='controlled_identity_registry_match')$rowErrors[]='controlled_route_binding_missing';
        if(($candidate['path_exists_in_controlled_registry']??false)!==true)$rowErrors[]='controlled_route_registry_miss';
        $path=$candidate['review_path']??null;
        if(!is_string($path)||!str_starts_with($path,'/country/')||!str_ends_with($path,'/'))$rowErrors[]='review_path_invalid';
        if(($candidate['publication_allowed']??true)!==false)$rowErrors[]='candidate_publication_boundary';
        if(($candidate['indexation_allowed']??true)!==false)$rowErrors[]='candidate_indexation_boundary';
        if(($candidate['sitemap_allowed']??true)!==false)$rowErrors[]='candidate_sitemap_boundary';
        if(($candidate['route_launch_allowed']??true)!==false)$rowErrors[]='candidate_route_boundary';
        $fresh=(bool)($candidate['inventory']['fresh_observation_within_3d']??false);
        if(!$fresh)$rowErrors[]='inventory_not_fresh_within_3d';

        $candidateReview=[
            'kind'=>$kind,
            'review_ready'=>$rowErrors===[],
            'fresh_evidence'=>$fresh,
            'source_ref'=>$sourceRef,
            'publication_allowed'=>false,
            'indexation_allowed'=>false,
            'sitemap_allowed'=>false,
            'route_launch_allowed'=>false,
        ];
        $gate=v2_seo_phase5_expansion_review_gate($freshnessChain,$candidateReview);
        if(($gate['state']??'')!=='phase5_expansion_review_ready')$rowErrors=array_merge($rowErrors,$gate['errors']??['phase5_gate_blocked']);

        $out=[
            'identity_key'=>$candidate['identity_key']??null,
            'candidate_type'=>$type,
            'kind'=>$kind,
            'review_path'=>$path,
            'catalog_path_hint'=>$candidate['catalog_path_hint']??null,
            'inventory_rank'=>$candidate['inventory_rank']??null,
            'inventory'=>$candidate['inventory']??[],
            'demand'=>$candidate['demand']??['status'=>'unknown'],
            'source_ref'=>$sourceRef,
            'review_allowed'=>$rowErrors===[],
            'errors'=>array_values(array_unique($rowErrors)),
            'publication_allowed'=>false,
            'indexation_allowed'=>false,
            'sitemap_allowed'=>false,
            'canonical_launch_allowed'=>false,
            'route_launch_allowed'=>false,
        ];
        if($rowErrors===[])$review[]=$out; else $blocked[]=$out;
    }

    usort($review,static fn(array $a,array $b):int=>strcmp((string)$a['identity_key'],(string)$b['identity_key']));
    usort($blocked,static fn(array $a,array $b):int=>strcmp((string)($a['identity_key']??''),(string)($b['identity_key']??'')));
    $ready=$errors===[];
    return [
        'state'=>$ready?'phase5_inventory_opportunity_review_ready':'phase5_inventory_opportunity_review_blocked',
        'errors'=>array_values(array_unique($errors)),
        'review_candidate_count'=>count($review),
        'blocked_candidate_count'=>count($blocked),
        'review_candidates'=>$review,
        'blocked_candidates'=>$blocked,
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
