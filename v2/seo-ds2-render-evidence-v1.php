<?php
declare(strict_types=1);
require_once __DIR__.'/seo-ds2-reference-acceptance-v1.php';

/** Validate externally captured render evidence for the fixed DS2 reference pair. */
function v2_seo_ds2_render_evidence(array $rows, ?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $d=v2_seo_ds2_reference_acceptance_dossier();
    $expected=[];
    foreach(['destination','hotel_tours'] as $family){
        $path=(string)$d['reference_pages'][$family]['path'];
        foreach($d['viewport_matrix'] as $widths) foreach($widths as $width) $expected[$family.':'.$width]=[$family,$path,(int)$width];
    }
    $seen=[];$errors=[];$normalized=[];
    foreach($rows as $i=>$row){
        if(!is_array($row)){ $errors[]='invalid_row_'.$i; continue; }
        $family=(string)($row['family']??'');$path=(string)($row['path']??'');$width=(int)($row['viewport_width']??0);
        $key=$family.':'.$width;$captured=(int)($row['captured_at_epoch']??0);$source=trim((string)($row['source_ref']??''));
        if(!isset($expected[$key])||$expected[$key][1]!==$path)$errors[]='unexpected_reference:'.$key;
        if(isset($seen[$key]))$errors[]='duplicate_reference:'.$key;
        $seen[$key]=true;
        $fresh=$captured>0&&$captured<=$nowEpoch&&$nowEpoch-$captured<=86400;
        if(!$fresh)$errors[]='stale_render_evidence:'.$key;
        if($source==='')$errors[]='missing_source_ref:'.$key;
        foreach(['http_ok','no_horizontal_overflow','primary_action_height_ok','search_handoff_contract_ok','editorial_hierarchy_ok','fresh_claim_boundary_ok'] as $check){
            if(($row[$check]??false)!==true)$errors[]='failed_'.$check.':'.$key;
        }
        if($family==='hotel_tours'){
            foreach(['review_status_ok','noindex_ok','out_of_sitemap_ok','publication_candidate_absent'] as $check) if(($row[$check]??false)!==true)$errors[]='failed_'.$check.':'.$key;
        }
        $normalized[$key]=['family'=>$family,'path'=>$path,'viewport_width'=>$width,'captured_at_epoch'=>$captured,'source_ref'=>$source,'fresh'=>$fresh];
    }
    foreach(array_keys($expected) as $key) if(!isset($seen[$key]))$errors[]='missing_reference:'.$key;
    ksort($normalized,SORT_STRING);
    return [
        'state'=>$errors===[]?'review_only_ds2_render_evidence_ready':'review_only_ds2_render_evidence_blocked',
        'expected_capture_count'=>count($expected),'observed_capture_count'=>count($normalized),'rows'=>array_values($normalized),
        'errors'=>array_values(array_unique($errors)),
        'evidence_sha256'=>hash('sha256',json_encode($normalized,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),
        'publication_allowed'=>false,'indexation_change_allowed'=>false,'sitemap_change_allowed'=>false,'canonical_change_allowed'=>false,'route_change_allowed'=>false,
        'hotel_tours_publication_candidate_allowed'=>false,'hotel_tours_indexation_allowed'=>false,'separate_user_hotel_indexation_approval_required'=>true,
        'search_contract_changes'=>false,'tourvisor_contract_changes'=>false,'pricing_contract_changes'=>false,'lead_contract_changes'=>false,'metrika_contract_changes'=>false,
    ];
}
