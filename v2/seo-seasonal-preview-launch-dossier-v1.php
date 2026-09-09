<?php
declare(strict_types=1);

require_once __DIR__.'/seo-seasonal-preview-readiness-v1.php';
require_once __DIR__.'/seo-seasonal-preview-evidence-readiness-v1.php';
require_once __DIR__.'/seo-seasonal-preview-opportunity-evidence-v1.php';

/**
 * Compose the exact seasonal review cohort into one evidence-first launch dossier.
 * GO_REVIEW is only a review recommendation: it never creates a public route,
 * publication candidate, sitemap entry, canonical or indexation change.
 */
function v2_seo_seasonal_preview_launch_dossier(
    array $serpRows,
    array $identityInventory = [],
    ?int $nowEpoch = null
): array {
    $nowEpoch ??= time();

    $structural = v2_seo_seasonal_preview_readiness($nowEpoch);
    $serp = v2_seo_seasonal_preview_opportunity_evidence($serpRows, $nowEpoch);
    $identity = $identityInventory === []
        ? [
            'state'=>'missing',
            'all_review_ready'=>false,
            'pages'=>[],
            'blocked'=>[['code'=>'missing_identity_inventory']],
        ]
        : v2_seo_seasonal_preview_evidence_readiness($identityInventory, $nowEpoch);

    $identityByPageKey=[];
    foreach (($identityInventory['identities'] ?? []) as $record) {
        if (!is_array($record)) continue;
        $pageKey=trim((string)($record['page_key']??''));
        if ($pageKey!==''&&!isset($identityByPageKey[$pageKey])) $identityByPageKey[$pageKey]=$record;
    }

    $packets=is_array($serp['packets_by_preview']??null)?$serp['packets_by_preview']:[];
    $rows=[];$go=0;$holds=0;$errors=[];
    $structuralPages=is_array($structural['pages']??null)?$structural['pages']:[];
    $expectedCount=count(v2_seo_seasonal_preview_catalog());
    if ($expectedCount<1) $errors[]='seasonal_review_cohort_empty';
    if (count($structuralPages)!==$expectedCount) $errors[]='structural_preview_count_mismatch';

    foreach ($structuralPages as $previewKey=>$page) {
        if (!is_array($page)) { $errors[]='invalid_structural_page:'.(string)$previewKey; continue; }
        $previewKey=(string)$previewKey;
        $path=(string)($page['path']??'');
        $pageKey=(string)($page['page_key']??'');
        $packet=is_array($packets[$previewKey]??null)?$packets[$previewKey]:[];
        $demand=is_array($packet['demand']??null)?$packet['demand']:[];
        $uniqueness=is_array($packet['uniqueness']??null)?$packet['uniqueness']:[];
        $identityPage=is_array($identity['pages'][$previewKey]??null)?$identity['pages'][$previewKey]:[];
        $inventory=is_array($identityByPageKey[$pageKey]??null)?$identityByPageKey[$pageKey]:[];

        $structuralReady=(int)($page['score']??0)===100&&($page['review_ready']??false)===true;
        $contentReady=($page['dimensions']['sourced_content']??false)===true;
        $technicalReady=$structuralReady
            &&($page['dimensions']['search_handoff_integrity']??false)===true
            &&($page['dimensions']['route_and_head_integrity']??false)===true
            &&($page['dimensions']['publication_boundary']??false)===true;

        $demandReady=($demand['state']??'')==='demand_evidence_valid'
            &&($demand['status']??'')==='confirmed'
            &&($demand['fresh']??false)===true
            &&in_array((string)($demand['serp_intent']??''),['commercial','mixed'],true);
        $uniquenessReady=($uniqueness['state']??'')==='uniqueness_evidence_valid'
            &&($uniqueness['status']??'')==='confirmed'
            &&($uniqueness['fresh']??false)===true
            &&($uniqueness['decision']??'')==='distinct';
        $identityReady=($identityPage['fresh_identity_ready']??false)===true
            &&($identityPage['review_ready']??false)===true;
        $inventoryReady=$identityReady
            &&($inventory['state']??'')==='fresh_review_identity'
            &&(int)($inventory['offer_count']??0)>0
            &&(int)($inventory['expires_at_epoch']??0)>$nowEpoch;

        $blocked=[];
        if (!$technicalReady) $blocked[]='technical';
        if (!$contentReady) $blocked[]='content';
        if (!$demandReady) $blocked[]='demand';
        if (!$uniquenessReady) $blocked[]='uniqueness';
        if (!$identityReady) $blocked[]='identity';
        if (!$inventoryReady) $blocked[]='commercial_inventory';
        $blocked=array_values(array_unique($blocked));
        $decision=$blocked===[]?'GO_REVIEW':'HOLD';
        if ($decision==='GO_REVIEW') $go++; else $holds++;

        $rows[]=[
            'preview_key'=>$previewKey,
            'path'=>$path,
            'page_key'=>$pageKey,
            'decision'=>$decision,
            'blocked_dimensions'=>$blocked,
            'dimensions'=>[
                'technical'=>['status'=>$technicalReady?'confirmed':'blocked','score'=>$technicalReady?100:null,'source'=>'seasonal_preview_readiness'],
                'content'=>['status'=>$contentReady?'confirmed':'blocked','source'=>'verified_seasonal_content_evidence'],
                'demand'=>[
                    'status'=>$demandReady?'confirmed':'unknown','source_class'=>(string)($demand['source_class']??''),
                    'source_ref'=>(string)($demand['source_ref']??''),'observed_at_epoch'=>(int)($demand['observed_at_epoch']??0),
                    'serp_intent'=>(string)($demand['serp_intent']??''),
                ],
                'uniqueness'=>[
                    'status'=>$uniquenessReady?'confirmed':'unknown','decision'=>(string)($uniqueness['decision']??'unknown'),
                    'source_class'=>(string)($uniqueness['source_class']??''),'source_ref'=>(string)($uniqueness['source_ref']??''),
                    'observed_at_epoch'=>(int)($uniqueness['observed_at_epoch']??0),
                ],
                'identity'=>[
                    'status'=>$identityReady?'confirmed':'unknown','evidence_checked_at_epoch'=>(int)($identityPage['identity_evidence_checked_at_epoch']??0),
                    'evidence_valid_until_epoch'=>(int)($identityPage['identity_evidence_valid_until_epoch']??0),
                ],
                'commercial_inventory'=>[
                    'status'=>$inventoryReady?'confirmed':'unknown','offer_count'=>$inventoryReady?(int)$inventory['offer_count']:null,
                    'source'=>$inventoryReady?'seasonal_identity_inventory:first_party_offer_snapshot':'','expires_at_epoch'=>$inventoryReady?(int)$inventory['expires_at_epoch']:0,
                ],
            ],
            'opportunity_packet_state'=>(string)($packet['state']??'missing'),
            'packet_sha256'=>(string)($packet['packet_sha256']??''),
            'publication_candidate'=>false,
        ];
    }

    usort($rows,static fn(array $a,array $b):int=>strcmp($a['preview_key'],$b['preview_key']));
    $allGo=$errors===[]&&$expectedCount>0&&count($rows)===$expectedCount&&$go===$expectedCount&&$holds===0;
    $fingerprint=hash('sha256',json_encode([
        'rows'=>$rows,'structural_state'=>$structural['state']??'','serp_state'=>$serp['state']??'',
        'serp_sha256'=>$serp['evidence_sha256']??'','identity_state'=>$identity['state']??'',
        'identity_valid_until'=>$identity['evidence_valid_until_epoch']??0,
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

    return [
        'state'=>$allGo?'review_only_seasonal_launch_dossier_go_review':'review_only_seasonal_launch_dossier_hold',
        'preview_count'=>count($rows),'go_review_count'=>$go,'hold_count'=>$holds,'all_go_review'=>$allGo,'rows'=>$rows,
        'structural_state'=>(string)($structural['state']??''),'serp_evidence_state'=>(string)($serp['state']??''),
        'identity_evidence_state'=>(string)($identity['state']??''),'dossier_sha256'=>$fingerprint,
        'errors'=>array_values(array_unique($errors)),'publication_candidates'=>[],'publication_allowed'=>false,
        'indexation_allowed'=>false,'sitemap_allowed'=>false,'canonical_launch_allowed'=>false,'route_launch_allowed'=>false,
        'automatic_execution_allowed'=>false,'go_review_is_publication_approval'=>false,
        'search_contract_changes'=>false,'tourvisor_contract_changes'=>false,'pricing_contract_changes'=>false,
        'lead_contract_changes'=>false,'metrika_contract_changes'=>false,
    ];
}
