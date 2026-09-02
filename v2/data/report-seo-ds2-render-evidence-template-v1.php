<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/seo-ds2-reference-acceptance-v1.php';
$d=v2_seo_ds2_reference_acceptance_dossier();
$rows=[];
foreach(['destination','hotel_tours'] as $family){
    $path=(string)$d['reference_pages'][$family]['path'];
    foreach($d['viewport_matrix'] as $widths){
        foreach($widths as $width){
            $row=[
                'family'=>$family,
                'path'=>$path,
                'viewport_width'=>(int)$width,
                'captured_at_epoch'=>null,
                'source_ref'=>null,
                'http_ok'=>null,
                'no_horizontal_overflow'=>null,
                'primary_action_height_ok'=>null,
                'search_handoff_contract_ok'=>null,
                'editorial_hierarchy_ok'=>null,
                'fresh_claim_boundary_ok'=>null,
            ];
            if($family==='hotel_tours'){
                $row['review_status_ok']=null;
                $row['noindex_ok']=null;
                $row['out_of_sitemap_ok']=null;
                $row['publication_candidate_absent']=null;
            }
            $rows[]=$row;
        }
    }
}
echo json_encode(['state'=>'blank_ds2_render_evidence_template','rows'=>$rows,'publication_allowed'=>false,'hotel_tours_indexation_allowed'=>false],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
