<?php
declare(strict_types=1);

require_once __DIR__.'/seo-second-wave-country-review-v1.php';

/**
 * Pre-publication launch dossier for the second controlled country wave.
 *
 * The policy is deliberately categorical rather than numeric because the
 * available manual SERP evidence proves commercial intent/page shape but does
 * not contain trustworthy search-volume metrics. We do not invent a demand
 * score merely to cross a numeric threshold.
 *
 * This dossier authorizes only a subsequent narrow production change for the
 * two existing country routes. It does not itself mutate robots, sitemap,
 * canonicals, routes or publication state.
 */
function v2_seo_second_wave_country_launch_dossier(?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $review=v2_seo_second_wave_country_review($nowEpoch);
    $expected=['/country/egypt/','/country/maldives/'];
    sort($expected,SORT_STRING);
    $errors=[];
    $rows=[];

    if(($review['state']??'')!=='second_wave_country_review_ready') $errors[]='review_not_ready';
    if(($review['domain']??'')!=='anytoour.ru') $errors[]='domain_mismatch';
    if(($review['publication_candidates']??null)!==[]) $errors[]='review_publication_candidate_leak';

    foreach((array)($review['rows']??[]) as $row){
        $path=(string)($row['path']??'');
        $packet=is_array($row['opportunity_evidence']??null)?$row['opportunity_evidence']:[];
        $demand=is_array($packet['demand']??null)?$packet['demand']:[];
        $uniqueness=is_array($packet['uniqueness']??null)?$packet['uniqueness']:[];
        $rowErrors=[];
        if(!in_array($path,$expected,true)) $rowErrors[]='path_outside_second_wave';
        if(($row['page_type']??'')!=='country') $rowErrors[]='page_type_not_country';
        if((int)($row['technical_quality_score']??0)!==100||($row['technical_review_ready']??false)!==true) $rowErrors[]='technical_quality_not_100';
        if(($packet['state']??'')!=='opportunity_evidence_review_ready'||($packet['evidence_fresh']??false)!==true) $rowErrors[]='opportunity_evidence_not_fresh_ready';
        if(($demand['status']??'')!=='confirmed'||($demand['fresh']??false)!==true||($demand['serp_intent']??'')!=='commercial') $rowErrors[]='commercial_demand_evidence_missing';
        if(($uniqueness['status']??'')!=='confirmed'||($uniqueness['fresh']??false)!==true||($uniqueness['decision']??'')!=='distinct') $rowErrors[]='uniqueness_not_distinct';
        if((array)($uniqueness['competing_paths']??[])!==[]) $rowErrors[]='unresolved_cannibal_paths';
        if(($row['resort_layer']['route_creation_allowed']??true)!==false) $rowErrors[]='resort_scope_leak';

        foreach($rowErrors as $e) $errors[]=$path.':'.$e;
        $rows[]=[
            'path'=>$path,
            'page_type'=>'country',
            'query_cluster'=>(string)($row['query_cluster']??''),
            'technical_quality_score'=>(int)($row['technical_quality_score']??0),
            'evidence_packet_sha256'=>(string)($packet['packet_sha256']??''),
            'decision'=>$rowErrors===[]?'GO':'HOLD',
            'decision_policy'=>'categorical_evidence_complete_v1',
            'numeric_demand_score'=>null,
            'numeric_score_intentionally_not_invented'=>true,
            'errors'=>$rowErrors,
        ];
    }

    $paths=array_column($rows,'path'); sort($paths,SORT_STRING);
    if($paths!==$expected) $errors[]='second_wave_path_set_mismatch';
    if(count($rows)!==2) $errors[]='second_wave_row_count';

    $errors=array_values(array_unique($errors));
    $sha=hash('sha256',json_encode([
        'domain'=>'anytoour.ru',
        'scope'=>'egypt_maldives_country_launch_v1',
        'paths'=>$expected,
        'rows'=>array_map(static fn(array $r):array=>[
            'path'=>$r['path'],
            'query_cluster'=>$r['query_cluster'],
            'quality'=>$r['technical_quality_score'],
            'packet_sha256'=>$r['evidence_packet_sha256'],
            'decision'=>$r['decision'],
        ],$rows),
        'decision_policy'=>'categorical_evidence_complete_v1',
        'owner_launch_approval_date'=>'2026-09-03',
        'hotel_tours_approved'=>false,
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

    return [
        'state'=>$errors===[]?'second_wave_country_prelaunch_authorized':'second_wave_country_prelaunch_blocked',
        'domain'=>'anytoour.ru',
        'scope'=>'egypt_maldives_country_launch_v1',
        'paths'=>$expected,
        'rows'=>$rows,
        'errors'=>$errors,
        'dossier_sha256'=>$sha,
        'decision_policy'=>'categorical_evidence_complete_v1',
        'policy_reason'=>'manual SERP evidence confirms commercial intent but supplies no trustworthy numeric demand metric, so no synthetic score is created',
        'owner_launch_approval_date'=>'2026-09-03',
        'owner_launch_approval_scope'=>'country_resort_seo_only',
        'requires_fresh_live_production_identity_before_merge'=>true,
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
        'hotel_tours_approved'=>false,
        'hotel_tours_indexation_allowed'=>false,
        'hotel_tours_sitemap_allowed'=>false,
        'search_contract_changes'=>false,
        'tourvisor_contract_changes'=>false,
        'pricing_contract_changes'=>false,
        'lead_contract_changes'=>false,
        'metrika_contract_changes'=>false,
    ];
}
