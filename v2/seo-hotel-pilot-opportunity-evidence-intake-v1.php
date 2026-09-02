<?php
declare(strict_types=1);
require_once __DIR__.'/seo-hotel-launch-pilot-v1.php';
require_once __DIR__.'/seo-opportunity-evidence-packet-v1.php';

/**
 * Normalize externally collected demand + uniqueness evidence into the exact
 * controlled 3x3 hotel pilot. This prevents evidence from being attached to the
 * wrong path/query cluster and rejects missing/extra pilot rows fail-closed.
 *
 * The intake does not score, rank or publish anything.
 */
function v2_seo_hotel_pilot_opportunity_evidence_intake(array $rows, ?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $spec=v2_seo_hotel_launch_pilot_spec();
    $expected=[]; $countryByPath=[];
    foreach(($spec['countries']??[]) as $bucket){
        $countryId=(int)($bucket['country_id']??0);
        foreach((array)($bucket['paths']??[]) as $path){
            $path=(string)$path;
            $expected[$path]=true;
            $countryByPath[$path]=$countryId;
        }
    }
    if(count($expected)!==9) throw new RuntimeException('Controlled hotel pilot spec must contain exactly 9 paths');

    $errors=[]; $seen=[]; $packets=[]; $normalized=[];
    foreach($rows as $i=>$row){
        if(!is_array($row)){ $errors[]='invalid_row:'.$i; continue; }
        $path=trim((string)($row['path']??''));
        $pageKey=trim((string)($row['page_key']??''));
        $queryCluster=trim((string)($row['query_cluster']??''));
        if($path===''||!isset($expected[$path])){ $errors[]='unexpected_path:'.$i; continue; }
        if(isset($seen[$path])){ $errors[]='duplicate_path:'.$path; continue; }
        $seen[$path]=true;
        if($pageKey===''){ $errors[]='missing_page_key:'.$path; continue; }
        if($queryCluster===''){ $errors[]='missing_query_cluster:'.$path; continue; }
        $page=['page_key'=>$pageKey,'path'=>$path,'query_cluster'=>$queryCluster];
        $demand=is_array($row['demand']??null)?$row['demand']:[];
        $uniqueness=is_array($row['uniqueness']??null)?$row['uniqueness']:[];
        $packet=v2_seo_opportunity_evidence_packet($page,$demand,$uniqueness,$nowEpoch);
        if(($packet['state']??'')==='opportunity_evidence_packet_invalid'){
            foreach((array)($packet['errors']??[]) as $packetError)$errors[]='packet:'.$path.':'.$packetError;
        }
        $packets[$path]=$packet;
        $normalized[]=[
            'path'=>$path,
            'country_id'=>(int)$countryByPath[$path],
            'page_key'=>$pageKey,
            'query_cluster'=>$queryCluster,
            'packet_state'=>(string)($packet['state']??''),
            'packet_sha256'=>(string)($packet['packet_sha256']??''),
            'evidence_fresh'=>(bool)($packet['evidence_fresh']??false),
            'evidence_confirmed'=>(bool)($packet['evidence_confirmed']??false),
            'uniqueness_distinct'=>(bool)($packet['uniqueness_distinct']??false),
            'scoring_policy_pending'=>(bool)($packet['scoring_policy_pending']??false),
        ];
    }
    foreach(array_keys($expected) as $path)if(!isset($seen[$path]))$errors[]='missing_path:'.$path;
    if(count($rows)!==9)$errors[]='row_count_must_be_9';
    usort($normalized,static fn(array $a,array $b):int=>strcmp($a['path'],$b['path']));
    ksort($packets,SORT_STRING);
    $readyCount=count(array_filter($normalized,static fn(array $r):bool=>$r['packet_state']==='opportunity_evidence_review_ready'));
    $fingerprint=hash('sha256',json_encode($normalized,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    return [
        'state'=>$errors===[]&&$readyCount===9?'review_only_pilot_evidence_intake_ready':'review_only_pilot_evidence_intake_blocked',
        'row_count'=>count($rows),
        'expected_count'=>9,
        'ready_count'=>$readyCount,
        'blocked_count'=>9-$readyCount,
        'rows'=>$normalized,
        'packets_by_path'=>$packets,
        'intake_sha256'=>$fingerprint,
        'errors'=>array_values(array_unique($errors)),
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
        'explicit_user_indexation_approval_required'=>true,
    ];
}
