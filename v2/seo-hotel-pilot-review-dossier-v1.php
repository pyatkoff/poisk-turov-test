<?php
declare(strict_types=1);
require_once __DIR__.'/seo-hotel-launch-pilot-v1.php';

/**
 * Join the fixed 3x3 hotel pilot to explicit fresh quality/identity evidence.
 * This dossier is review-only and can never itself authorize publication.
 */
function v2_seo_hotel_pilot_review_dossier(array $rows, ?int $nowEpoch=null): array
{
    $nowEpoch??=time();$spec=v2_seo_hotel_launch_pilot_spec();$expected=[];
    foreach($spec['countries'] as $bucket)foreach($bucket['paths'] as $path)$expected[$path]=(int)$bucket['country_id'];
    $seen=[];$errors=[];$out=[];
    foreach($rows as $i=>$row){
        if(!is_array($row)){ $errors[]='invalid_row_'.$i;continue; }
        $path=(string)($row['path']??'');$country=(int)($row['country_id']??0);$captured=(int)($row['captured_at_epoch']??0);$source=trim((string)($row['source_ref']??''));
        if(!isset($expected[$path])||$expected[$path]!==$country)$errors[]='pilot_identity_mismatch:'.$path;
        if(isset($seen[$path]))$errors[]='duplicate_path:'.$path;$seen[$path]=true;
        $fresh=$captured>0&&$captured<=$nowEpoch&&$nowEpoch-$captured<=86400;if(!$fresh)$errors[]='stale_evidence:'.$path;
        if($source==='')$errors[]='missing_source_ref:'.$path;
        if((int)($row['quality_score']??-1)!==100)$errors[]='quality_below_100:'.$path;
        foreach(['identity_verified','catalog_integrity_ok','fresh_offer_evidence','review_status_ok','noindex_ok','out_of_sitemap_ok','publication_candidate_absent'] as $check)if(($row[$check]??false)!==true)$errors[]='failed_'.$check.':'.$path;
        $out[$path]=['path'=>$path,'country_id'=>$country,'quality_score'=>(int)($row['quality_score']??-1),'captured_at_epoch'=>$captured,'source_ref'=>$source,'fresh'=>$fresh];
    }
    foreach(array_keys($expected) as $path)if(!isset($seen[$path]))$errors[]='missing_pilot_path:'.$path;
    ksort($out,SORT_STRING);
    return [
        'state'=>$errors===[]?'review_only_hotel_pilot_evidence_ready':'review_only_hotel_pilot_evidence_blocked',
        'selection_policy'=>$spec['selection_policy'],'expected_hotel_count'=>9,'observed_hotel_count'=>count($out),'rows'=>array_values($out),'errors'=>array_values(array_unique($errors)),
        'dossier_sha256'=>hash('sha256',json_encode($out,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),
        'publication_candidates'=>[],'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'canonical_launch_allowed'=>false,'route_launch_allowed'=>false,
        'explicit_user_indexation_approval_required'=>true,'search_contract_changes'=>false,'tourvisor_contract_changes'=>false,'pricing_contract_changes'=>false,'lead_contract_changes'=>false,'metrika_contract_changes'=>false,
    ];
}
