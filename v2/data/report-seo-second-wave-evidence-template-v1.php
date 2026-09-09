<?php
declare(strict_types=1);

require_once __DIR__.'/../seo-second-wave-country-review-v1.php';

$demand=[];
$uniqueness=[];
foreach(v2_seo_second_wave_country_specs() as $spec){
    $pageKey=(string)$spec['page_key'];
    $path=(string)$spec['path'];
    $queryCluster=(string)$spec['query_cluster'];
    $demand[]=[
        'page_key'=>$pageKey,
        'query_cluster'=>$queryCluster,
        'source_class'=>'',
        'source_ref'=>'',
        'observed_at_epoch'=>null,
        'status'=>'unknown',
        'metrics'=>[
            'impressions'=>null,
            'clicks'=>null,
            'avg_position'=>null,
            'monthly_searches'=>null,
        ],
        'serp_intent'=>'',
    ];
    $uniqueness[]=[
        'page_key'=>$pageKey,
        'query_cluster'=>$queryCluster,
        'page_path'=>$path,
        'source_class'=>'',
        'source_ref'=>'',
        'observed_at_epoch'=>null,
        'status'=>'unknown',
        'decision'=>'unknown',
        'competing_paths'=>[],
        'overlap_ratio'=>null,
    ];
}

echo json_encode([
    'state'=>'second_wave_external_evidence_collection_template',
    'domain'=>'anytoour.ru',
    'scope'=>'egypt_maldives_country_review_v1',
    'instructions'=>[
        'Fill demand rows only from real Search Console, keyword-research export, or manual SERP evidence supported by seo-demand-evidence-v1.php.',
        'Fill uniqueness rows only from explicit manual SERP review, Search Console export, or site query-overlap audit supported by seo-uniqueness-evidence-v1.php.',
        'Keep unavailable metrics null; do not convert missing evidence to zero.',
        'Do not change page_key, page_path, or query_cluster identity fields.',
        'Supply scoring policy separately; this template intentionally contains no weights or thresholds.',
    ],
    'demand_file_payload'=>['rows'=>$demand],
    'uniqueness_file_payload'=>['rows'=>$uniqueness],
    'scoring_policy_required'=>true,
    'scoring_policy_template_provided'=>false,
    'missing_evidence_semantics'=>'unknown_not_zero',
    'automatic_scoring_allowed'=>false,
    'automatic_launch_allowed'=>false,
    'explicit_user_launch_approval_required'=>true,
    'publication_candidates'=>[],
    'publication_scope_expanded'=>false,
    'publication_allowed'=>false,
    'indexation_allowed'=>false,
    'sitemap_allowed'=>false,
    'canonical_launch_allowed'=>false,
    'route_launch_allowed'=>false,
    'hotel_tours_indexation_allowed'=>false,
    'hotel_tours_sitemap_allowed'=>false,
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
