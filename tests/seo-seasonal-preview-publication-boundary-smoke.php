<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/v2/seo-seasonal-review-page-v1.php';

function seasonal_boundary_fail(string $code): void { fwrite(STDERR,"SEO_SEASONAL_PREVIEW_BOUNDARY_FAIL:$code\n"); exit(1); }

$catalog=v2_seo_seasonal_preview_catalog();
$records=v2_seo_seasonal_review_content_prototypes();
if(count($catalog)!==2) seasonal_boundary_fail('unexpected_preview_count');
$seen=[];
foreach($catalog as $key=>$preview){
    if(!is_array($preview)) seasonal_boundary_fail('invalid_preview');
    $path=(string)($preview['path']??'');
    if(!str_starts_with($path,'/_preview/seo2/seasonal/')||!str_ends_with($path,'/')) seasonal_boundary_fail('path_outside_preview_namespace');
    if(isset($seen[$path])) seasonal_boundary_fail('duplicate_path');
    $seen[$path]=true;
    $contentKey=(string)($preview['content_key']??'');
    if(!isset($records[$contentKey])) seasonal_boundary_fail('missing_content');
    $record=$records[$contentKey];
    foreach(['publication_allowed','indexation_allowed','sitemap_allowed','route_creation_allowed'] as $flag){
        if(($record[$flag]??true)!==false) seasonal_boundary_fail('launch_flag_'.$flag);
    }
    $relative=trim($path,'/');
    $route=dirname(__DIR__).'/v2/'.$relative.'/index.php';
    if(!is_file($route)) seasonal_boundary_fail('missing_physical_preview_route');
    $routeSource=file_get_contents($route);
    if($routeSource===false||!str_contains($routeSource,"v2_seo_render_seasonal_preview('".$key."')")) seasonal_boundary_fail('wrong_route_renderer');
}

$renderer=file_get_contents(dirname(__DIR__).'/v2/seo-seasonal-review-page-v1.php');
if($renderer===false) seasonal_boundary_fail('renderer_unreadable');
if(str_contains($renderer,'sp_head($context)')) seasonal_boundary_fail('generic_canonical_head_reintroduced');
if(str_contains($renderer,'rel="canonical"')||str_contains($renderer,'property="og:url"')) seasonal_boundary_fail('canonical_or_og_url_reintroduced');
if(!str_contains($renderer,'content="noindex,follow"')) seasonal_boundary_fail('noindex_missing');
if(!str_contains($renderer,"publication_candidates']??null)!==[]")) seasonal_boundary_fail('publication_candidate_guard_missing');

foreach(['sitemap-v2.php','seo-publication-manifest-v1.php','seo-launch-manifest-v1.php','seo-content-catalog-v1.php'] as $surface){
    $file=dirname(__DIR__).'/v2/'.$surface;
    if(!is_file($file)) continue;
    $source=file_get_contents($file);
    foreach(array_keys($seen) as $path) if($source!==false&&str_contains($source,$path)) seasonal_boundary_fail('preview_path_leaked_into_'.$surface);
}

echo "SEO_SEASONAL_PREVIEW_PUBLICATION_BOUNDARY_OK previews=".count($catalog)."\n";
