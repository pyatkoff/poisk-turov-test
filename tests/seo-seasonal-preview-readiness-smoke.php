<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/v2/seo-seasonal-preview-readiness-v1.php';

function seasonal_readiness_fail(string $code): void { fwrite(STDERR,"SEO_SEASONAL_PREVIEW_READINESS_FAIL:$code\n"); exit(1); }

$report=v2_seo_seasonal_preview_readiness(strtotime('2026-09-02T11:30:00Z'));
if(($report['state']??'')!=='review_ready') seasonal_readiness_fail('state');
if(($report['preview_count']??0)!==2) seasonal_readiness_fail('count');
if(($report['average_score']??0)!==100||($report['all_review_ready']??false)!==true) seasonal_readiness_fail('score');
if(($report['publication_candidates']??null)!==[]) seasonal_readiness_fail('candidates');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed'] as $flag) if(($report[$flag]??true)!==false) seasonal_readiness_fail('boundary_'.$flag);
if(($report['explicit_launch_approval_required']??false)!==true) seasonal_readiness_fail('approval');

$expected=[
    'antalya-september'=>['page_key'=>'resort_month:1:4:20:2026-09','path'=>'/_preview/seo2/seasonal/antalya-september/'],
    'maldives-september'=>['page_key'=>'month:1:8:2026-09','path'=>'/_preview/seo2/seasonal/maldives-september/'],
];
foreach($expected as $key=>$identity){
    $page=$report['pages'][$key]??null;
    if(!is_array($page)||($page['score']??0)!==100||($page['review_ready']??false)!==true) seasonal_readiness_fail($key.'_score');
    if(($page['page_key']??'')!==$identity['page_key']||($page['path']??'')!==$identity['path']) seasonal_readiness_fail($key.'_identity');
    if(($page['errors']??null)!==[]) seasonal_readiness_fail($key.'_errors');
    foreach(['identity_integrity','sourced_content','search_handoff_integrity','publication_boundary','route_and_head_integrity'] as $dimension){
        if(($page['dimensions'][$dimension]??false)!==true) seasonal_readiness_fail($key.'_'.$dimension);
    }
}

echo "SEO_SEASONAL_PREVIEW_READINESS_OK previews=2 score=100\n";
