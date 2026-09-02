<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/seo-hotel-launch-pilot-v1.php';
$rows=[];
foreach(v2_seo_hotel_launch_pilot_spec()['countries'] as $bucket){
    foreach($bucket['paths'] as $path){
        $rows[]=[
            'path'=>$path,
            'country_id'=>(int)$bucket['country_id'],
            'captured_at_epoch'=>null,
            'source_ref'=>null,
            'quality_score'=>null,
            'identity_verified'=>null,
            'catalog_integrity_ok'=>null,
            'fresh_offer_evidence'=>null,
            'review_status_ok'=>null,
            'noindex_ok'=>null,
            'out_of_sitemap_ok'=>null,
            'publication_candidate_absent'=>null,
        ];
    }
}
echo json_encode(['state'=>'blank_hotel_pilot_review_evidence_template','rows'=>$rows,'publication_candidates'=>[],'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
