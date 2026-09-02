<?php
declare(strict_types=1);
require_once __DIR__.'/../seo-launch-slice-v1.php';

/**
 * Emits a blank collection skeleton for the exact six Turkey URLs already live
 * in the controlled SEO launch. Null/blank fields are intentional: no search
 * performance fact is fabricated by this template.
 */
$rows=[];
foreach(v2_seo_turkey_launch_paths() as $path){
    $rows[]=[
        'path'=>(string)$path,
        'source_class'=>'',
        'source_ref'=>'',
        'collected_at_epoch'=>null,
        'period_start_epoch'=>null,
        'period_end_epoch'=>null,
        'metrics'=>[
            'impressions'=>null,
            'clicks'=>null,
            'avg_position'=>null,
            'ctr'=>null,
            'query_count'=>null,
        ],
    ];
}

echo json_encode([
    'state'=>'search_feedback_collection_template',
    'domain'=>'anytoour.ru',
    'launch_scope'=>'turkey_country_resort_v1',
    'instructions'=>'Fill only from a real Google Search Console or Yandex Webmaster export. Keep unavailable values null; do not convert missing evidence to zero.',
    'supported_source_classes'=>['google_search_console_export','yandex_webmaster_export'],
    'rows'=>$rows,
    'missing_feedback_semantics'=>'unknown_not_zero',
    'requires_explicit_feedback_policy'=>true,
    'automatic_recommendation_allowed'=>false,
    'automatic_deindex_allowed'=>false,
    'publication_candidates'=>[],
    'publication_allowed'=>false,
    'indexation_change_allowed'=>false,
    'sitemap_change_allowed'=>false,
    'canonical_change_allowed'=>false,
    'route_change_allowed'=>false,
    'hotel_tours_indexation_allowed'=>false,
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
