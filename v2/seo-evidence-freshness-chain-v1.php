<?php
declare(strict_types=1);
require_once __DIR__.'/seo-phase-readiness-gate-v1.php';

function v2_seo_evidence_freshness_chain(
    array $manifest,
    array $productionIdentity,
    array $ds2RenderEvidence,
    array $hotelPilotEvidence,
    ?int $nowEpoch=null,
    int $maxAgeSeconds=86400
): array {
    $nowEpoch??=time();$errors=[];
    $shaOk=static fn(mixed $v):bool=>is_string($v)&&preg_match('/^[a-f0-9]{64}$/',$v)===1;
    $freshAt=static fn(int $ts) use($nowEpoch,$maxAgeSeconds):bool=>$ts>0&&$ts<=$nowEpoch&&($nowEpoch-$ts)<=$maxAgeSeconds;

    if(($productionIdentity['integrity_ok']??false)!==true)$errors[]='production_identity_not_valid';
    if(($productionIdentity['source']??'')!=='live_http_collector')$errors[]='production_identity_not_live';
    if(!$freshAt((int)($productionIdentity['observed_at_epoch']??0)))$errors[]='production_identity_stale';
    if(!$shaOk($productionIdentity['identity_registry_sha256']??null))$errors[]='production_identity_fingerprint_missing';

    $renderRows=is_array($ds2RenderEvidence['rows']??null)?$ds2RenderEvidence['rows']:[];
    if(($ds2RenderEvidence['state']??'')!=='review_only_ds2_render_evidence_ready'||count($renderRows)!==10)$errors[]='ds2_render_not_ready';
    if(!$shaOk($ds2RenderEvidence['evidence_sha256']??null))$errors[]='ds2_render_fingerprint_missing';
    foreach($renderRows as $i=>$row) if(!is_array($row)||($row['fresh']??false)!==true||!$freshAt((int)($row['captured_at_epoch']??0)))$errors[]='ds2_render_row_stale_'.$i;

    $pilotRows=is_array($hotelPilotEvidence['rows']??null)?$hotelPilotEvidence['rows']:[];
    if(($hotelPilotEvidence['state']??'')!=='review_only_hotel_pilot_evidence_ready'||count($pilotRows)!==9)$errors[]='hotel_pilot_not_ready';
    if(!$shaOk($hotelPilotEvidence['dossier_sha256']??null))$errors[]='hotel_pilot_fingerprint_missing';
    foreach($pilotRows as $i=>$row) if(!is_array($row)||($row['fresh']??false)!==true||!$freshAt((int)($row['captured_at_epoch']??0)))$errors[]='hotel_pilot_row_stale_'.$i;

    if(($hotelPilotEvidence['publication_candidates']??null)!==[])$errors[]='hotel_pilot_publication_candidate_boundary';
    if(($hotelPilotEvidence['indexation_allowed']??true)!==false)$errors[]='hotel_pilot_indexation_boundary';
    if(($ds2RenderEvidence['hotel_tours_indexation_allowed']??true)!==false)$errors[]='ds2_hotel_indexation_boundary';
    if(($productionIdentity['hotel_tours_indexation_allowed']??true)!==false)$errors[]='production_hotel_indexation_boundary';

    $phase=v2_seo_phase_readiness_gate($manifest,$productionIdentity,$ds2RenderEvidence,$hotelPilotEvidence);
    $ready=$errors===[]&&(($phase['expansion_review_allowed']??false)===true);
    return [
        'state'=>$ready?'fresh_evidence_chain_ready_for_expansion_review':'fresh_evidence_chain_blocked',
        'checked_at_epoch'=>$nowEpoch,'max_age_seconds'=>$maxAgeSeconds,'errors'=>array_values(array_unique($errors)),'phase_gate'=>$phase,
        'expansion_review_allowed'=>$ready,'publication_allowed'=>false,'hotel_tours_publication_candidates'=>[],
        'hotel_tours_publication_allowed'=>false,'hotel_tours_indexation_allowed'=>false,'hotel_tours_sitemap_allowed'=>false,
        'hotel_tours_canonical_launch_allowed'=>false,'hotel_tours_route_launch_allowed'=>false,'separate_user_hotel_indexation_approval_required'=>true,
        'search_contract_changes'=>false,'tourvisor_contract_changes'=>false,'pricing_contract_changes'=>false,'lead_contract_changes'=>false,'metrika_contract_changes'=>false,
    ];
}
