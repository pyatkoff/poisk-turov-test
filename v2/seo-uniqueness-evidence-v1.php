<?php
declare(strict_types=1);

/**
 * Normalize SEO uniqueness/cannibalization evidence without inventing overlap.
 *
 * Supported evidence is explicit review/export data only. The presence of a
 * route, hotel, resort or query cluster is never enough to claim uniqueness.
 * Missing/stale evidence stays UNKNOWN and must block opportunity readiness.
 */
function v2_seo_uniqueness_evidence(array $record, ?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $pageKey=trim((string)($record['page_key']??''));
    $queryCluster=trim((string)($record['query_cluster']??''));
    $sourceClass=(string)($record['source_class']??'');
    $sourceRef=trim((string)($record['source_ref']??''));
    $observed=(int)($record['observed_at_epoch']??0);
    $status=(string)($record['status']??'unknown');
    $decision=(string)($record['decision']??'');
    $supportedSources=['manual_serp_review','search_console_export','site_query_overlap_audit'];
    $supportedDecisions=['distinct','merge','noindex','skip','unknown'];
    $errors=[];

    if($pageKey==='')$errors[]='missing_page_key';
    if($queryCluster==='')$errors[]='missing_query_cluster';
    if(!in_array($sourceClass,$supportedSources,true))$errors[]='unsupported_source_class';
    if($sourceRef==='')$errors[]='missing_source_ref';
    if(!in_array($status,['confirmed','blocked','unknown'],true))$errors[]='invalid_status';
    if(!in_array($decision,$supportedDecisions,true))$errors[]='invalid_decision';

    $maxAge=86400*31;
    $fresh=$observed>0&&$observed<=$nowEpoch&&($nowEpoch-$observed)<=$maxAge;

    $competingPaths=[];
    foreach((array)($record['competing_paths']??[]) as $path){
        $path=trim((string)$path);
        if($path===''||$path[0]!=='/'){$errors[]='invalid_competing_path';continue;}
        if($path===trim((string)($record['page_path']??''))){$errors[]='self_competing_path';continue;}
        $competingPaths[$path]=true;
    }
    $competingPaths=array_keys($competingPaths);
    sort($competingPaths,SORT_STRING);

    $overlap=(array_key_exists('overlap_ratio',$record)&&is_numeric($record['overlap_ratio']))?(float)$record['overlap_ratio']:null;
    if($overlap!==null&&($overlap<0||$overlap>1)){$errors[]='invalid_overlap_ratio';$overlap=null;}

    // A confirmed uniqueness conclusion must contain an explicit decision plus
    // review evidence. No automatic threshold converts overlap into a decision.
    if($status==='confirmed'&&(!$fresh||$errors!==[]||$decision==='unknown'))$status='unknown';
    if($status==='confirmed'&&$decision==='distinct'&&$sourceClass==='site_query_overlap_audit'&&$overlap===null)$status='unknown';
    if($status==='confirmed'&&in_array($decision,['merge','noindex','skip'],true)&&$competingPaths===[])$status='unknown';

    return [
        'state'=>$errors===[]?'uniqueness_evidence_valid':'uniqueness_evidence_invalid',
        'status'=>$status,
        'page_key'=>$pageKey,
        'query_cluster'=>$queryCluster,
        'page_path'=>trim((string)($record['page_path']??'')),
        'source_class'=>$sourceClass,
        'source_ref'=>$sourceRef,
        'observed_at_epoch'=>$observed,
        'fresh'=>$fresh,
        'decision'=>$decision,
        'competing_paths'=>$competingPaths,
        'overlap_ratio'=>$overlap,
        'errors'=>array_values(array_unique($errors)),
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
    ];
}

function v2_seo_uniqueness_signal_for_opportunity(array $evidence): array
{
    if(($evidence['state']??'')!=='uniqueness_evidence_valid'||($evidence['status']??'')!=='confirmed'||($evidence['fresh']??false)!==true){
        return ['status'=>'unknown','source'=>'uniqueness_evidence:unconfirmed','observed_at_epoch'=>(int)($evidence['observed_at_epoch']??0)];
    }
    $decision=(string)($evidence['decision']??'unknown');
    if($decision!=='distinct'){
        return [
            'status'=>'blocked',
            'source'=>'uniqueness_evidence:'.(string)$evidence['source_class'].':'.(string)$evidence['source_ref'],
            'observed_at_epoch'=>(int)$evidence['observed_at_epoch'],
            'decision'=>$decision,
            'competing_paths'=>(array)$evidence['competing_paths'],
        ];
    }
    // Deliberately no automatic 0..100 uniqueness score. A reviewed scoring
    // policy must explicitly translate evidence into a score later.
    return [
        'status'=>'confirmed',
        'score'=>null,
        'source'=>'uniqueness_evidence:'.(string)$evidence['source_class'].':'.(string)$evidence['source_ref'],
        'observed_at_epoch'=>(int)$evidence['observed_at_epoch'],
        'query_cluster'=>(string)$evidence['query_cluster'],
        'decision'=>'distinct',
        'competing_paths'=>(array)$evidence['competing_paths'],
        'overlap_ratio'=>$evidence['overlap_ratio'],
        'requires_explicit_scoring_policy'=>true,
    ];
}
