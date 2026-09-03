<?php
declare(strict_types=1);

require_once __DIR__.'/seo-seasonal-review-page-v1.php';
require_once __DIR__.'/seo-opportunity-evidence-packet-v1.php';

/**
 * Bind external demand/uniqueness evidence to the exact seasonal review cohort.
 * This is review-only evidence intake: no route, sitemap, canonical or indexing
 * behavior can be changed here.
 */
function v2_seo_seasonal_preview_opportunity_evidence(array $rows, ?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $previews=v2_seo_seasonal_preview_catalog();
    $content=v2_seo_seasonal_review_content_prototypes();
    $expected=[];
    foreach($previews as $previewKey=>$preview){
        $contentKey=(string)($preview['content_key']??'');
        $record=is_array($content[$contentKey]??null)?$content[$contentKey]:[];
        $pageKey=(string)($record['page_key']??'');
        $path=(string)($preview['path']??'');
        if($pageKey===''||$path==='')throw new RuntimeException('Seasonal preview evidence identity is incomplete');
        $expected[(string)$previewKey]=['page_key'=>$pageKey,'path'=>$path];
    }
    $expectedCount=count($expected);
    if($expectedCount<1)throw new RuntimeException('Seasonal preview evidence requires an explicit review cohort');

    $errors=[];$seen=[];$normalized=[];$packets=[];
    foreach($rows as $i=>$row){
        if(!is_array($row)){ $errors[]='invalid_row:'.$i; continue; }
        $previewKey=trim((string)($row['preview_key']??''));
        if($previewKey===''||!isset($expected[$previewKey])){ $errors[]='unexpected_preview_key:'.$i; continue; }
        if(isset($seen[$previewKey])){ $errors[]='duplicate_preview_key:'.$previewKey; continue; }
        $seen[$previewKey]=true;
        $path=trim((string)($row['path']??''));
        $pageKey=trim((string)($row['page_key']??''));
        $queryCluster=trim((string)($row['query_cluster']??''));
        if($path!==$expected[$previewKey]['path'])$errors[]='path_mismatch:'.$previewKey;
        if($pageKey!==$expected[$previewKey]['page_key'])$errors[]='page_key_mismatch:'.$previewKey;
        if($queryCluster==='')$errors[]='missing_query_cluster:'.$previewKey;
        $page=['page_key'=>$pageKey,'path'=>$path,'query_cluster'=>$queryCluster];
        $demand=is_array($row['demand']??null)?$row['demand']:[];
        $uniqueness=is_array($row['uniqueness']??null)?$row['uniqueness']:[];
        $packet=v2_seo_opportunity_evidence_packet($page,$demand,$uniqueness,$nowEpoch);
        if(($packet['state']??'')==='opportunity_evidence_packet_invalid'){
            foreach((array)($packet['errors']??[]) as $packetError)$errors[]='packet:'.$previewKey.':'.$packetError;
        }
        $packets[$previewKey]=$packet;
        $normalized[]=[
            'preview_key'=>$previewKey,
            'path'=>$path,
            'page_key'=>$pageKey,
            'query_cluster'=>$queryCluster,
            'packet_state'=>(string)($packet['state']??''),
            'evidence_fresh'=>(bool)($packet['evidence_fresh']??false),
            'evidence_confirmed'=>(bool)($packet['evidence_confirmed']??false),
            'uniqueness_distinct'=>(bool)($packet['uniqueness_distinct']??false),
            'scoring_policy_pending'=>(bool)($packet['scoring_policy_pending']??false),
            'packet_sha256'=>(string)($packet['packet_sha256']??''),
        ];
    }
    foreach(array_keys($expected) as $previewKey)if(!isset($seen[$previewKey]))$errors[]='missing_preview_key:'.$previewKey;
    if(count($rows)!==$expectedCount)$errors[]='row_count_must_match_preview_count';
    usort($normalized,static fn(array $a,array $b):int=>strcmp($a['preview_key'],$b['preview_key']));
    ksort($packets,SORT_STRING);
    $readyCount=count(array_filter($normalized,static fn(array $row):bool=>$row['packet_state']==='opportunity_evidence_review_ready'));
    $fingerprint=hash('sha256',json_encode($normalized,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

    return [
        'state'=>$errors===[]&&$readyCount===$expectedCount?'review_only_seasonal_serp_evidence_ready':'review_only_seasonal_serp_evidence_blocked',
        'preview_count'=>$expectedCount,
        'ready_count'=>$readyCount,
        'blocked_count'=>$expectedCount-$readyCount,
        'rows'=>$normalized,
        'packets_by_preview'=>$packets,
        'evidence_sha256'=>$fingerprint,
        'errors'=>array_values(array_unique($errors)),
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
        'automatic_execution_allowed'=>false,
        'explicit_launch_approval_required'=>true,
        'search_contract_changes'=>false,
        'tourvisor_contract_changes'=>false,
        'pricing_contract_changes'=>false,
        'lead_contract_changes'=>false,
        'metrika_contract_changes'=>false,
    ];
}
