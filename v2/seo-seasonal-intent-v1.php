<?php
declare(strict_types=1);

/**
 * Review-only intent contract for seasonal SEO pages.
 * Intent classification is deliberately independent from publication eligibility.
 * It never creates routes, canonicals, sitemap entries or publication candidates.
 */
function v2_seo_seasonal_intent_contract(array $page): array
{
    $pageKey=trim((string)($page['page_key']??''));
    $role=trim((string)($page['page_role']??''));
    $intent=trim((string)($page['search_intent']??''));
    $path=trim((string)($page['path']??''));
    $handoff=is_array($page['search_state']??null)?$page['search_state']:[];
    $errors=[];

    $allowed=[
        'commercial_tour_landing'=>'commercial_transactional',
        'informational_guide'=>'informational',
    ];
    if($pageKey==='') $errors[]='missing_page_key';
    if(!isset($allowed[$role])) $errors[]='unsupported_page_role';
    elseif($allowed[$role]!==$intent) $errors[]='role_intent_mismatch';
    if(!preg_match('#^/_preview/seo2/seasonal/[a-z0-9-]+/$#',$path)) $errors[]='path_outside_review_namespace';

    $parts=explode(':',$pageKey);
    $family=(string)($parts[0]??'');
    $countryId=(int)($parts[2]??0);
    if(!in_array($family,['month','resort_month'],true)||$countryId<=0) $errors[]='unsupported_page_identity';
    if((int)($handoff['country']??0)!==$countryId) $errors[]='handoff_country_mismatch';
    if($family==='resort_month'){
        $regionId=(int)($parts[3]??0);
        if(count($parts)!==5||$regionId<=0||(int)($handoff['region']??0)!==$regionId) $errors[]='handoff_region_mismatch';
    } elseif($family==='month' && array_key_exists('region',$handoff)) {
        $errors[]='country_month_region_leak';
    }

    if($role==='commercial_tour_landing' && empty($handoff)) $errors[]='commercial_handoff_missing';
    foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_allowed','route_launch_allowed'] as $flag){
        if(($page[$flag]??false)!==false) $errors[]='review_boundary_'.$flag;
    }
    if(($page['publication_candidates']??[])!==[]) $errors[]='publication_candidate_leak';

    $errors=array_values(array_unique($errors));
    return [
        'state'=>$errors===[]?'review_intent_ready':'blocked',
        'review_ready'=>$errors===[],
        'page_key'=>$pageKey,
        'page_role'=>$role,
        'search_intent'=>$intent,
        'path'=>$path,
        'search_state'=>$handoff,
        'errors'=>$errors,
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_allowed'=>false,
        'route_launch_allowed'=>false,
    ];
}

/**
 * Detect cannibalization risk before any public URL exists.
 * Two different roles for the same seasonal entity require distinct review paths;
 * public-route decisions remain a separate explicit approval step.
 */
function v2_seo_seasonal_intent_registry(array $pages): array
{
    $rows=[]; $errors=[]; $identityRoles=[]; $paths=[];
    foreach($pages as $key=>$page){
        if(!is_array($page)){ $errors[]='invalid_page_record'; continue; }
        $row=v2_seo_seasonal_intent_contract($page);
        $rows[(string)$key]=$row;
        if(($row['review_ready']??false)!==true){ $errors[]='blocked_intent:'.(string)$key; continue; }
        $path=(string)$row['path'];
        if(isset($paths[$path])) $errors[]='duplicate_review_path';
        $paths[$path]=true;
        $identity=(string)$row['page_key']; $role=(string)$row['page_role'];
        if(isset($identityRoles[$identity][$role])) $errors[]='duplicate_identity_role';
        $identityRoles[$identity][$role]=true;
    }
    $errors=array_values(array_unique($errors));
    return [
        'state'=>$errors===[]&&$rows!==[]?'review_intent_registry_ready':'blocked',
        'review_ready'=>$errors===[]&&$rows!==[],
        'page_count'=>count($rows),
        'rows'=>$rows,
        'errors'=>$errors,
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_allowed'=>false,
        'route_launch_allowed'=>false,
    ];
}
