<?php
declare(strict_types=1);
require_once __DIR__.'/seo-hotel-tour-page-v1.php';

function v2_seo_core_hotel_review_registry_file(): string
{
    return __DIR__.'/data/generated/seo-core-hotel-review-registry-v1.php';
}

function v2_seo_core_hotel_review_record(string $path): ?array
{
    $path=(string)(parse_url($path,PHP_URL_PATH)??'');
    if(!preg_match('#^/country/(egypt|turkey|maldives)/hotel/[a-z0-9-]+-[0-9]+/$#',$path))return null;
    $file=v2_seo_core_hotel_review_registry_file();
    if(!is_file($file))return null;
    $registry=require $file;
    if(!is_array($registry))return null;
    $record=$registry[$path]??null;
    if(!is_array($record)||($record['type']??'')!=='hotel_tours'||($record['status']??'')!=='review')return null;
    return $record;
}

function v2_seo_render_core_hotel_review_route(?string $path=null): void
{
    $path??=(string)($_SERVER['REQUEST_URI']??'');
    $record=v2_seo_core_hotel_review_record($path);
    if($record===null){http_response_code(404);echo 'Not found';return;}
    v2_seo_render_hotel_tour_review($record);
}
