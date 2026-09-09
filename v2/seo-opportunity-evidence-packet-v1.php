<?php
declare(strict_types=1);
require_once __DIR__.'/seo-demand-evidence-v1.php';
require_once __DIR__.'/seo-uniqueness-evidence-v1.php';

/**
 * Bind demand + uniqueness evidence to one page/query-cluster identity.
 * This packet is review-only and deliberately does not score or publish.
 */
function v2_seo_opportunity_evidence_packet(array $page, array $demandRecord, array $uniquenessRecord, ?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $pageKey=trim((string)($page['page_key']??''));
    $path=trim((string)($page['path']??''));
    $queryCluster=trim((string)($page['query_cluster']??''));
    $errors=[];
    if($pageKey==='')$errors[]='missing_page_key';
    if($path===''||$path[0]!=='/')$errors[]='invalid_path';
    if($queryCluster==='')$errors[]='missing_query_cluster';

    $demand=v2_seo_demand_evidence($demandRecord,$nowEpoch);
    $uniq=v2_seo_uniqueness_evidence($uniquenessRecord,$nowEpoch);
    foreach([['demand',$demand],['uniqueness',$uniq]] as [$label,$e]){
        if(($e['state']??'')!==$label.'_evidence_valid')$errors[]=$label.'_invalid';
        if((string)($e['page_key']??'')!==$pageKey)$errors[]=$label.'_page_key_mismatch';
        if((string)($e['query_cluster']??'')!==$queryCluster)$errors[]=$label.'_query_cluster_mismatch';
    }
    if((string)($uniq['page_path']??'')!==''&&(string)$uniq['page_path']!==$path)$errors[]='uniqueness_page_path_mismatch';

    $demandSignal=v2_seo_demand_signal_for_opportunity($demand);
    $uniqSignal=v2_seo_uniqueness_signal_for_opportunity($uniq);
    $evidenceFresh=($demand['fresh']??false)===true&&($uniq['fresh']??false)===true;
    $evidenceConfirmed=($demand['status']??'')==='confirmed'&&($uniq['status']??'')==='confirmed';
    $distinct=($uniq['decision']??'')==='distinct';
    $scoringPending=($demandSignal['requires_explicit_scoring_policy']??false)===true||($uniqSignal['requires_explicit_scoring_policy']??false)===true;

    $fingerprintPayload=[
        'page_key'=>$pageKey,
        'path'=>$path,
        'query_cluster'=>$queryCluster,
        'demand'=>[
            'source_class'=>$demand['source_class']??'',
            'source_ref'=>$demand['source_ref']??'',
            'observed_at_epoch'=>$demand['observed_at_epoch']??0,
            'metrics'=>$demand['metrics']??[],
            'serp_intent'=>$demand['serp_intent']??'',
        ],
        'uniqueness'=>[
            'source_class'=>$uniq['source_class']??'',
            'source_ref'=>$uniq['source_ref']??'',
            'observed_at_epoch'=>$uniq['observed_at_epoch']??0,
            'decision'=>$uniq['decision']??'',
            'competing_paths'=>$uniq['competing_paths']??[],
            'overlap_ratio'=>$uniq['overlap_ratio']??null,
        ],
    ];
    $sha=hash('sha256',json_encode($fingerprintPayload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

    return [
        'state'=>$errors!==[]?'opportunity_evidence_packet_invalid':($evidenceFresh&&$evidenceConfirmed&&$distinct?'opportunity_evidence_review_ready':'opportunity_evidence_incomplete'),
        'page_key'=>$pageKey,
        'path'=>$path,
        'query_cluster'=>$queryCluster,
        'demand'=>$demand,
        'uniqueness'=>$uniq,
        'demand_signal'=>$demandSignal,
        'uniqueness_signal'=>$uniqSignal,
        'evidence_fresh'=>$evidenceFresh,
        'evidence_confirmed'=>$evidenceConfirmed,
        'uniqueness_distinct'=>$distinct,
        'scoring_policy_pending'=>$scoringPending,
        'packet_sha256'=>$sha,
        'errors'=>array_values(array_unique($errors)),
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
        'explicit_user_launch_approval_required'=>true,
    ];
}
