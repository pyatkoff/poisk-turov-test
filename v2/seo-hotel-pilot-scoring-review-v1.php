<?php
declare(strict_types=1);
require_once __DIR__.'/seo-hotel-pilot-opportunity-packets-v1.php';
require_once __DIR__.'/seo-opportunity-scoring-policy-v1.php';

/**
 * Score the already-controlled 3x3 hotel pilot for review only.
 *
 * Preconditions remain strict: exact balanced 3x3 technical review slice,
 * complete fresh evidence packets, and an explicitly supplied valid scoring
 * policy. This function never promotes a row into a publication candidate.
 */
function v2_seo_hotel_pilot_scoring_review(array $reviewItems, array $packetsByPath, array $policy): array
{
    $packetSet=v2_seo_hotel_pilot_opportunity_packets($reviewItems,$packetsByPath);
    $validatedPolicy=v2_seo_opportunity_scoring_policy($policy);
    $errors=[];
    if(($packetSet['state']??'')!=='review_only_opportunity_evidence_complete')$errors[]='pilot_evidence_incomplete';
    if(($packetSet['opportunity_evidence_ready_count']??0)!==9)$errors[]='pilot_ready_count';
    if(($validatedPolicy['state']??'')!=='opportunity_scoring_policy_valid')$errors[]='invalid_scoring_policy';

    $rows=[]; $scores=[];
    if($errors===[]){
        foreach($packetSet['rows'] as $row){
            $path=(string)$row['path'];
            $packet=is_array($packetsByPath[$path]??null)?$packetsByPath[$path]:[];
            $scored=v2_seo_opportunity_score_evidence_packet($packet,$policy);
            if(($scored['state']??'')!=='opportunity_scored_review_only'||!is_int($scored['score']??null)){
                $errors[]='score_blocked:'.$path;
                continue;
            }
            $scores[]=(int)$scored['score'];
            $rows[]=[
                'path'=>$path,
                'country_id'=>(int)$row['country_id'],
                'hotel_id'=>(int)$row['hotel_id'],
                'technical_score'=>100,
                'opportunity_score'=>(int)$scored['score'],
                'dimension_scores'=>$scored['dimension_scores']??[],
                'packet_sha256'=>(string)($scored['packet_sha256']??''),
                'score_sha256'=>(string)($scored['score_sha256']??''),
            ];
        }
    }
    usort($rows,static function(array $a,array $b):int{
        $cmp=$b['opportunity_score']<=>$a['opportunity_score'];
        return $cmp!==0?$cmp:strcmp($a['path'],$b['path']);
    });
    $scoreCount=count($scores);
    $summary=[
        'count'=>$scoreCount,
        'min'=>$scoreCount?min($scores):null,
        'avg'=>$scoreCount?(int)round(array_sum($scores)/$scoreCount):null,
        'max'=>$scoreCount?max($scores):null,
    ];
    $fingerprint=hash('sha256',json_encode([
        'packet_set_sha256'=>$packetSet['packet_set_sha256']??'',
        'policy_sha256'=>$validatedPolicy['policy_sha256']??'',
        'rows'=>$rows,
    ],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

    return [
        'state'=>$errors===[]&&$scoreCount===9?'review_only_pilot_scored':'review_only_pilot_scoring_blocked',
        'hotel_count'=>9,
        'scored_count'=>$scoreCount,
        'country_counts'=>$packetSet['country_counts']??[],
        'score_summary'=>$summary,
        'rows'=>$rows,
        'packet_set_sha256'=>(string)($packetSet['packet_set_sha256']??''),
        'policy_sha256'=>(string)($validatedPolicy['policy_sha256']??''),
        'scoring_review_sha256'=>$fingerprint,
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
