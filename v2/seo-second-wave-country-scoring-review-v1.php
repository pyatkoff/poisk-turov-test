<?php
declare(strict_types=1);

require_once __DIR__.'/seo-second-wave-country-review-v1.php';
require_once __DIR__.'/seo-production-identity-evidence-v1.php';
require_once __DIR__.'/seo-opportunity-scoring-policy-v1.php';

/**
 * Review-only scoring layer for the Egypt/Maldives country second wave.
 *
 * Inputs are intentionally external and explicit:
 *  - a fresh live production identity artifact;
 *  - opportunity packets carrying real demand/uniqueness evidence;
 *  - an explicitly approved scoring policy.
 *
 * Even a successful score never changes publication/indexation state. A later
 * launch dossier must make the GO/HOLD decision separately.
 */
function v2_seo_second_wave_country_scoring_review(
    array $productionIdentityEvidence,
    array $packetsByPath,
    array $policy,
    ?int $nowEpoch=null
): array {
    $nowEpoch??=time();
    $base=v2_seo_second_wave_country_review($nowEpoch);
    $errors=[];
    if(($base['state']??'')!=='second_wave_country_review_ready')$errors[]='base_country_review_not_ready';

    $expectedRows=[]; $expectedPaths=[];
    foreach((array)($base['rows']??[]) as $row){
        $path=(string)($row['path']??'');
        if($path==='')continue;
        $expectedPaths[]=$path;
        $packet=is_array($row['opportunity_evidence']??null)?$row['opportunity_evidence']:[];
        $expectedRows[$path]=[
            'page_key'=>(string)($packet['page_key']??''),
            'query_cluster'=>(string)($packet['query_cluster']??''),
            'technical_quality_score'=>(int)($row['technical_quality_score']??0),
        ];
    }
    sort($expectedPaths,SORT_STRING);
    if(count($expectedPaths)!==2)$errors[]='second_wave_expected_path_count';

    $identity=v2_seo_production_identity_evidence_validate(
        $productionIdentityEvidence,
        $expectedPaths,
        'egypt_maldives_country_review_v1',
        $nowEpoch
    );
    if(($identity['state']??'')!=='production_identity_evidence_valid')$errors[]='production_identity_not_valid';

    $validatedPolicy=v2_seo_opportunity_scoring_policy($policy);
    if(($validatedPolicy['state']??'')!=='opportunity_scoring_policy_valid')$errors[]='scoring_policy_not_valid';

    $packetPaths=array_keys($packetsByPath); sort($packetPaths,SORT_STRING);
    if($packetPaths!==$expectedPaths)$errors[]='opportunity_packet_path_set_mismatch';

    $rows=[]; $scores=[];
    foreach($expectedPaths as $path){
        $expected=$expectedRows[$path]??[];
        $packet=is_array($packetsByPath[$path]??null)?$packetsByPath[$path]:[];
        $rowErrors=[];
        if(($packet['state']??'')!=='opportunity_evidence_review_ready')$rowErrors[]='packet_not_ready';
        if((string)($packet['path']??'')!==$path)$rowErrors[]='packet_path_mismatch';
        if((string)($packet['page_key']??'')!==(string)($expected['page_key']??''))$rowErrors[]='packet_page_key_mismatch';
        if((string)($packet['query_cluster']??'')!==(string)($expected['query_cluster']??''))$rowErrors[]='packet_query_cluster_mismatch';
        if((int)($expected['technical_quality_score']??0)!==100)$rowErrors[]='technical_quality_not_100';

        $scored=v2_seo_opportunity_score_evidence_packet($packet,$policy);
        if(($scored['state']??'')!=='opportunity_scored_review_only'||!is_int($scored['score']??null)){
            $rowErrors[]='opportunity_scoring_blocked';
        }
        if($rowErrors!==[]){
            foreach($rowErrors as $err)$errors[]=$err.':'.$path;
            $rows[]=[
                'path'=>$path,
                'technical_quality_score'=>(int)($expected['technical_quality_score']??0),
                'opportunity_score'=>null,
                'scoring_state'=>'HOLD',
                'errors'=>$rowErrors,
            ];
            continue;
        }
        $score=(int)$scored['score'];
        $scores[]=$score;
        $rows[]=[
            'path'=>$path,
            'technical_quality_score'=>100,
            'opportunity_score'=>$score,
            'dimension_scores'=>$scored['dimension_scores']??[],
            'packet_sha256'=>(string)($scored['packet_sha256']??''),
            'score_sha256'=>(string)($scored['score_sha256']??''),
            'scoring_state'=>'SCORED_REVIEW_ONLY',
            'errors'=>[],
        ];
    }

    usort($rows,static function(array $a,array $b):int{
        $as=$a['opportunity_score']; $bs=$b['opportunity_score'];
        if(is_int($as)&&is_int($bs)){
            $cmp=$bs<=>$as;
            return $cmp!==0?$cmp:strcmp($a['path'],$b['path']);
        }
        if(is_int($as))return -1;
        if(is_int($bs))return 1;
        return strcmp($a['path'],$b['path']);
    });

    $scoreCount=count($scores);
    $summary=[
        'count'=>$scoreCount,
        'min'=>$scoreCount?min($scores):null,
        'avg'=>$scoreCount?(int)round(array_sum($scores)/$scoreCount):null,
        'max'=>$scoreCount?max($scores):null,
    ];
    $fingerprint=hash('sha256',json_encode([
        'base_review_sha256'=>$base['review_sha256']??'',
        'identity_sha256'=>$identity['identity_sha256']??'',
        'policy_sha256'=>$validatedPolicy['policy_sha256']??'',
        'rows'=>$rows,
    ],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

    return [
        'state'=>$errors===[]&&$scoreCount===2?'second_wave_country_scored_review_only':'second_wave_country_scoring_blocked',
        'domain'=>'anytoour.ru',
        'country_count'=>2,
        'scored_count'=>$scoreCount,
        'score_summary'=>$summary,
        'rows'=>$rows,
        'base_review_sha256'=>(string)($base['review_sha256']??''),
        'identity_sha256'=>(string)($identity['identity_sha256']??''),
        'identity_remaining_seconds'=>(int)($identity['remaining_seconds']??0),
        'policy_sha256'=>(string)($validatedPolicy['policy_sha256']??''),
        'scoring_review_sha256'=>$fingerprint,
        'errors'=>array_values(array_unique($errors)),
        'launch_decision_state'=>'requires_separate_launch_dossier',
        'publication_candidates'=>[],
        'publication_scope_expanded'=>false,
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
        'hotel_tours_indexation_allowed'=>false,
        'hotel_tours_sitemap_allowed'=>false,
        'search_contract_changes'=>false,
        'tourvisor_contract_changes'=>false,
        'pricing_contract_changes'=>false,
        'lead_contract_changes'=>false,
        'metrika_contract_changes'=>false,
    ];
}
