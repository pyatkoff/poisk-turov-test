<?php
declare(strict_types=1);

/**
 * Review-only gap matrix for the exact 3x3 hotel pilot readiness status.
 * This report never infers evidence, scores pages or changes publication state.
 */
function fail_gap(string $message, int $code=2): never
{
    fwrite(STDERR, "SEO_HOTEL_PILOT_GAP_MATRIX_FAIL:$message\n");
    exit($code);
}

$options=getopt('', ['status:','require-complete']);
$path=trim((string)($options['status']??''));
if($path==='') fail_gap('missing_status');
$raw=@file_get_contents($path);
if($raw===false||trim($raw)==='') fail_gap('empty_status');
try{$status=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(JsonException $e){fail_gap('invalid_json');}
if(!is_array($status)) fail_gap('status_must_be_object');
if(($status['state']??'')!=='review_only_hotel_pilot_status_complete') fail_gap('status_not_complete',3);
$rows=is_array($status['rows']??null)?$status['rows']:[];
if(count($rows)!==9) fail_gap('row_count_must_be_9');
if(($status['publication_candidates']??null)!==[]) fail_gap('publication_candidates_not_empty');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed','automatic_execution_allowed'] as $key){
    if(($status[$key]??null)!==false) fail_gap('unsafe_'.$key);
}
if(($status['explicit_user_indexation_approval_required']??null)!==true) fail_gap('approval_boundary_missing');

$dimensionOrder=['technical','identity','review_boundary','intent','demand','uniqueness','content','commercial_inventory','scoring_policy'];
$counts=[];
foreach($dimensionOrder as $dimension)$counts[$dimension]=[];
$missingByPath=[];$blockedByPath=[];$readyPaths=[];
foreach($rows as $row){
    if(!is_array($row)) fail_gap('invalid_row');
    $pagePath=trim((string)($row['path']??''));
    if($pagePath===''||$pagePath[0]!=='/') fail_gap('invalid_path');
    $dimensions=is_array($row['dimensions']??null)?$row['dimensions']:[];
    $missing=[];$blocked=[];
    foreach($dimensionOrder as $dimension){
        $item=is_array($dimensions[$dimension]??null)?$dimensions[$dimension]:[];
        $rawStatus=(string)($item['status']??'unknown');
        $normalized=in_array($rawStatus,['confirmed','blocked','unknown','pending','valid','invalid'],true)?$rawStatus:'unknown';
        $counts[$dimension][$normalized]=($counts[$dimension][$normalized]??0)+1;
        if(in_array($normalized,['unknown','pending'],true))$missing[]=$dimension;
        elseif(in_array($normalized,['blocked','invalid'],true))$blocked[]=$dimension;
    }
    if($missing!==[])$missingByPath[$pagePath]=$missing;
    if($blocked!==[])$blockedByPath[$pagePath]=$blocked;
    if($missing===[]&&$blocked===[])$readyPaths[]=$pagePath;
}
ksort($missingByPath,SORT_STRING);ksort($blockedByPath,SORT_STRING);sort($readyPaths,SORT_STRING);
foreach($counts as &$states)ksort($states,SORT_STRING);unset($states);
$priority=['intent','demand','uniqueness','content','scoring_policy'];
$priorityGaps=[];
foreach($priority as $dimension){
    $stateCounts=$counts[$dimension]??[];
    $confirmed=(int)($stateCounts['confirmed']??0)+(int)($stateCounts['valid']??0);
    $priorityGaps[$dimension]=[
        'confirmed_or_valid'=>$confirmed,
        'gap_count'=>9-$confirmed,
        'states'=>$stateCounts,
    ];
}

$out=[
    'state'=>'review_only_hotel_pilot_gap_matrix_ready',
    'hotel_count'=>9,
    'evidence_complete_count'=>(int)($status['evidence_complete_count']??0),
    'review_ready_count'=>(int)($status['review_ready_count']??0),
    'dimension_counts'=>$counts,
    'priority_gaps'=>$priorityGaps,
    'missing_by_path'=>$missingByPath,
    'blocked_by_path'=>$blockedByPath,
    'fully_clear_paths'=>$readyPaths,
    'source_status_sha256'=>(string)($status['status_sha256']??''),
    'publication_candidates'=>[],
    'publication_allowed'=>false,
    'indexation_allowed'=>false,
    'sitemap_allowed'=>false,
    'canonical_launch_allowed'=>false,
    'route_launch_allowed'=>false,
    'automatic_execution_allowed'=>false,
    'explicit_user_indexation_approval_required'=>true,
];
$out['gap_matrix_sha256']=hash('sha256',json_encode([$out['dimension_counts'],$out['priority_gaps'],$out['missing_by_path'],$out['blocked_by_path']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
if(array_key_exists('require-complete',$options)&&($out['review_ready_count']??0)!==9)exit(3);
