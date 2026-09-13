<?php
declare(strict_types=1);

/**
 * MATCH #1971 second-stage, read-only classification over the completed provider-
 * duplicate audit. It never queries or writes by itself: the caller supplies the
 * CURRENT v1 result with blocked_rows exposed from an immutable reconstructed copy.
 */
function hmpdx_sorted_tokens(array $tokens): array
{
    $out=array_values(array_unique(array_map('strval',$tokens)));
    sort($out,SORT_STRING);
    return $out;
}

function hmpdx_geo_tokens(array $row): array
{
    $values=[];
    foreach($row['source_places']??[] as $value)$values[]=(string)$value;
    $target=$row['target']??[];
    foreach(['region','subregion'] as $key)if(trim((string)($target[$key]??''))!=='')$values[]=(string)$target[$key];
    $tokens=[];
    foreach($values as $value)foreach(hmcf_tokens($value) as $token)$tokens[$token]=true;
    return array_keys($tokens);
}

function hmpdx_exact_support(array $row): ?array
{
    if(($row['reason']??'')!=='target_direct_geo_required')return null;
    if((bool)($row['guard']['coordinate_conflict']??false))return null;
    $targetMatch=$row['target_name_match']??null;
    if(!is_array($targetMatch)
        || !($targetMatch['qualifier_ok']??false)
        || (int)($targetMatch['shared']??0)<2
        || (float)($targetMatch['score']??0)<0.75
        || (float)($row['margin']??0)<0.20)return null;

    $geo=array_fill_keys(hmpdx_geo_tokens($row),true);
    $best=null;
    foreach($row['accepted_occupants']??[] as $occupant){
        $match=$occupant['name_match']??null;
        if(!is_array($match)||!($match['qualifier_ok']??false))continue;
        $left=hmpdx_sorted_tokens($match['source_tokens']??[]);
        $right=hmpdx_sorted_tokens($match['target_tokens']??[]);
        if(count($left)<2||$left!==$right)continue;
        $nonGeo=array_values(array_filter($left,static fn($token)=>!isset($geo[$token])));
        if(count($nonGeo)<2)continue;
        $candidate=[
            'accepted_external_id'=>(string)($occupant['external_id']??''),
            'accepted_catalog_sha256'=>$occupant['catalog_sha256']??null,
            'exact_tokens'=>$left,
            'non_geography_tokens'=>$nonGeo,
            'qualifiers'=>hmpdx_sorted_tokens($match['source_qualifiers']??[]),
            'source_name'=>(string)($match['source_name']??''),
            'accepted_name'=>(string)($match['accepted_name']??''),
        ];
        if($candidate['accepted_external_id']==='')continue;
        if($best===null||count($candidate['exact_tokens'])>count($best['exact_tokens'])
            ||(count($candidate['exact_tokens'])===count($best['exact_tokens'])&&strcmp($candidate['accepted_external_id'],$best['accepted_external_id'])<0))$best=$candidate;
    }
    return $best;
}

function hmpdx_review(array $v1,string $operation): array
{
    if(($v1['status']??'')!=='completed'||!isset($v1['blocked_rows'])||!is_array($v1['blocked_rows']))throw new RuntimeException('v1_blocked_rows_missing');
    $prepared=[];$reasons=[];$sourceDirectGeo=0;$byCountry=[];
    foreach($v1['blocked_rows'] as $row){
        if(($row['reason']??'')!=='target_direct_geo_required')continue;
        $sourceDirectGeo++;
        $support=hmpdx_exact_support($row);
        if($support===null){$reasons['no_exact_non_geography_accepted_identity_bridge']=($reasons['no_exact_non_geography_accepted_identity_bridge']??0)+1;continue;}
        $country=(int)($row['country_id']??0);
        $prepared[]=[
            'external_id'=>(string)($row['external_id']??''),
            'country_id'=>$country,
            'source_names'=>$row['source_names']??[],
            'source_places'=>$row['source_places']??[],
            'target'=>$row['target']??null,
            'target_name_match'=>$row['target_name_match']??null,
            'margin'=>$row['margin']??null,
            'guard'=>$row['guard']??null,
            'accepted_identity_bridge'=>$support,
            'reason'=>'same_provider_exact_identity_bridge_without_direct_geo',
            'not_write_authority'=>true,
        ];
        $byCountry[$country]=($byCountry[$country]??0)+1;
    }
    ksort($reasons);ksort($byCountry,SORT_NUMERIC);
    usort($prepared,static fn($a,$b)=>$a['country_id']<=>$b['country_id'] ?: strcmp($a['external_id'],$b['external_id']));
    return [
        'schema'=>'hotel-match-provider-duplicate-exact-evidence/1',
        'status'=>'completed',
        'operation_id'=>$operation,
        'mode'=>'current_db_read_only_from_v1',
        'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,
        'v1_operation_id'=>$v1['operation_id']??null,
        'v1_tail_examined'=>(int)($v1['tail_examined']??0),
        'v1_prepared_candidate_count'=>(int)($v1['prepared_candidate_count']??0),
        'source_direct_geo_blocked'=>$sourceDirectGeo,
        'additional_exact_bridge_count'=>count($prepared),
        'additional_by_country'=>$byCountry,
        'blocked_reasons'=>$reasons,
        'prepared_candidates'=>$prepared,
        'guards'=>[
            'only_v1_target_direct_geo_required'=>true,
            'target_name_score_min'=>0.75,
            'target_margin_min'=>0.20,
            'target_shared_tokens_min'=>2,
            'accepted_identity_significant_tokens_exact'=>true,
            'accepted_identity_non_geography_tokens_min'=>2,
            'meaningful_qualifiers_identical'=>true,
            'coordinate_conflict_gt_5km_blocks'=>true,
            'generic_removed'=>['HOTEL','RESORT','SPA','THE','AND','EX/former'],
            'no_write_authority'=>true,
        ],
    ];
}
