<?php
declare(strict_types=1);
require_once __DIR__.'/../seo-hotel-launch-pilot-v1.php';

/**
 * Emits the exact controlled 3x3 evidence collection skeleton.
 * Blank evidence fields are intentional: this file never fabricates demand,
 * SERP observations, uniqueness decisions, scores or volatile hotel facts.
 */
$rows=[];
foreach(v2_seo_hotel_launch_pilot_spec()['countries'] as $bucket){
    $countryId=(int)$bucket['country_id'];
    foreach((array)$bucket['paths'] as $path){
        $hotelId=(int)preg_replace('/^.*-(\\d+)\\/$/','$1',(string)$path);
        $rows[]=[
            'path'=>(string)$path,
            'page_key'=>"hotel:$countryId:$hotelId",
            'query_cluster'=>'',
            'demand'=>[
                'page_key'=>"hotel:$countryId:$hotelId",
                'query_cluster'=>'',
                'source_class'=>'',
                'source_ref'=>'',
                'observed_at_epoch'=>null,
                'status'=>'unknown',
                'serp_intent'=>'',
            ],
            'uniqueness'=>[
                'page_key'=>"hotel:$countryId:$hotelId",
                'query_cluster'=>'',
                'page_path'=>(string)$path,
                'source_class'=>'',
                'source_ref'=>'',
                'observed_at_epoch'=>null,
                'status'=>'unknown',
                'decision'=>'unknown',
                'competing_paths'=>[],
            ],
        ];
    }
}
echo json_encode([
    'state'=>'review_only_evidence_collection_template',
    'instructions'=>'Fill only from real Search Console/keyword research/manual SERP evidence; then pass rows to report-seo-hotel-pilot-opportunity-evidence-v1.php.',
    'rows'=>$rows,
    'publication_candidates'=>[],
    'publication_allowed'=>false,
    'indexation_allowed'=>false,
    'sitemap_allowed'=>false,
    'canonical_launch_allowed'=>false,
    'route_launch_allowed'=>false,
    'explicit_user_indexation_approval_required'=>true,
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
