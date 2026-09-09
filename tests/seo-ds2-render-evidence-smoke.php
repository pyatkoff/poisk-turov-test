<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-ds2-render-evidence-v1.php';
function fail_render(string $x): never { fwrite(STDERR,"SEO_DS2_RENDER_EVIDENCE_FAIL:$x\n"); exit(1); }
$now=1788370200;$d=v2_seo_ds2_reference_acceptance_dossier();$rows=[];
foreach(['destination','hotel_tours'] as $family){
    foreach([375,430,768,1024,1440] as $width){
        $row=[
            'family'=>$family,
            'path'=>$d['reference_pages'][$family]['path'],
            'viewport_width'=>$width,
            'captured_at_epoch'=>$now-60,
            'source_ref'=>'fixture://render/'.$family.'/'.$width,
            'http_ok'=>true,
            'no_horizontal_overflow'=>true,
            'primary_action_height_ok'=>true,
            'search_handoff_contract_ok'=>true,
            'secondary_search_action_height_ok'=>true,
            'secondary_search_handoff_contract_ok'=>true,
            'editorial_hierarchy_ok'=>true,
            'related_navigation_ok'=>true,
            'content_order_ok'=>true,
            'fresh_claim_boundary_ok'=>true,
        ];
        if($family==='hotel_tours')$row+=['review_status_ok'=>true,'noindex_ok'=>true,'out_of_sitemap_ok'=>true,'publication_candidate_absent'=>true];
        $rows[]=$row;
    }
}
$r=v2_seo_ds2_render_evidence($rows,$now);
if(($r['state']??'')!=='review_only_ds2_render_evidence_ready'||($r['expected_capture_count']??0)!==10)fail_render('ready');
foreach(['publication_allowed','indexation_change_allowed','sitemap_change_allowed','canonical_change_allowed','route_change_allowed','hotel_tours_publication_candidate_allowed','hotel_tours_indexation_allowed'] as $flag)if(($r[$flag]??true)!==false)fail_render($flag);
$bad=$rows;$bad[5]['noindex_ok']=false;
if((v2_seo_ds2_render_evidence($bad,$now)['state']??'')!=='review_only_ds2_render_evidence_blocked')fail_render('hotel_boundary');
$bad=$rows;$bad[0]['secondary_search_action_height_ok']=false;
if((v2_seo_ds2_render_evidence($bad,$now)['state']??'')!=='review_only_ds2_render_evidence_blocked')fail_render('secondary_cta_boundary');
$bad=$rows;$bad[1]['related_navigation_ok']=false;
if((v2_seo_ds2_render_evidence($bad,$now)['state']??'')!=='review_only_ds2_render_evidence_blocked')fail_render('related_navigation_boundary');
$bad=$rows;$bad[2]['content_order_ok']=false;
if((v2_seo_ds2_render_evidence($bad,$now)['state']??'')!=='review_only_ds2_render_evidence_blocked')fail_render('content_order_boundary');
$missing=$rows;array_pop($missing);
if((v2_seo_ds2_render_evidence($missing,$now)['state']??'')!=='review_only_ds2_render_evidence_blocked')fail_render('missing_viewport');
echo "SEO_DS2_RENDER_EVIDENCE_OK captures=10 checks=10 hotel_tours=noindex_review_only\n";
