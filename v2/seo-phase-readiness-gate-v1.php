<?php
declare(strict_types=1);

/**
 * Ordered SEO3 phase gate.
 *
 * This consumes already-validated diagnostics and only decides whether the next
 * review phase may begin. It never mutates routes, robots, sitemap, canonicals,
 * publication flags, Search/Tourvisor, pricing, lead flow, or analytics.
 */
function v2_seo_phase_readiness_gate(
    array $manifest,
    array $productionIdentity,
    array $ds2RenderEvidence,
    array $hotelPilotEvidence
): array {
    $familyQuality=[];
    foreach(['country','resort','hotel_tours'] as $type){
        $familyQuality[$type]=(int)($manifest['quality_by_type'][$type]['quality_score']??0);
    }

    $stage1=(($manifest['integrity_ok']??false)===true)
        && (($manifest['family_quality_floor']??0)===100)
        && min($familyQuality)===100;

    $stage2=$stage1
        && (($productionIdentity['integrity_ok']??false)===true)
        && (($productionIdentity['source']??'')==='live_http_collector')
        && (($productionIdentity['hotel_tours_indexation_allowed']??true)===false);

    $stage3=$stage2
        && (($ds2RenderEvidence['state']??'')==='review_only_ds2_render_evidence_ready')
        && (($ds2RenderEvidence['expected_capture_count']??0)===10)
        && (($ds2RenderEvidence['observed_capture_count']??0)===10)
        && (($ds2RenderEvidence['hotel_tours_indexation_allowed']??true)===false);

    $stage4=$stage3
        && (($hotelPilotEvidence['state']??'')==='review_only_hotel_pilot_evidence_ready')
        && (($hotelPilotEvidence['expected_hotel_count']??0)===9)
        && (($hotelPilotEvidence['observed_hotel_count']??0)===9)
        && (($hotelPilotEvidence['publication_candidates']??null)===[])
        && (($hotelPilotEvidence['indexation_allowed']??true)===false);

    $stages=[
        'launch_readiness_quality'=>['ready'=>$stage1,'order'=>1],
        'production_identity'=>['ready'=>$stage2,'order'=>2],
        'ds2_reference_render'=>['ready'=>$stage3,'order'=>3],
        'hotel_pilot_review_evidence'=>['ready'=>$stage4,'order'=>4],
    ];

    $blockedStage=null;
    foreach($stages as $name=>$stage){ if(!$stage['ready']){ $blockedStage=$name; break; } }

    return [
        'state'=>$stage4?'phase_4_review_ready_expansion_review_may_begin':'phase_gate_blocked',
        'stages'=>$stages,
        'blocked_stage'=>$blockedStage,
        'family_quality'=>$familyQuality,
        'expansion_review_allowed'=>$stage4,
        'resort_seasonal_data_feed_expansion_allowed'=>$stage4,
        'publication_allowed'=>false,
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
