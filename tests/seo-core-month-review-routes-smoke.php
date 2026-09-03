<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-core-month-route-resolver-v1.php';

$records=v2_seo_core_month_content_records();
if(count($records)!==96) exit(1);
foreach($records as $record){
    $path=(string)$record['path'];
    $resolved=v2_seo_core_month_record_for_path($path);
    if(($resolved['id']??'')!==($record['id']??'')) exit(2);
    if(($resolved['status']??'')!=='review') exit(3);
}
foreach([
 '/country/maldives/september/',
 '/country/turkey/antalya/september/',
] as $legacy){
    if(!is_file(__DIR__.'/../v2'.rtrim($legacy,'/').'/index.php')) exit(4);
}
$existingLegacy=2;$generated=0;
foreach($records as $record){
    $path=(string)$record['path'];
    if(in_array($path,['/country/maldives/september/','/country/turkey/antalya/september/'],true)) continue;
    $route=__DIR__.'/../v2'.rtrim($path,'/').'/index.php';
    if(!is_file($route)) exit(5);
    $source=(string)file_get_contents($route);
    if(!str_contains($source,'seo-core-month-route-resolver-v1.php')) exit(6);
    if(!str_contains($source,"v2_seo_core_month_record_for_path('".$path."')")) exit(7);
    if(!str_contains($source,'v2_seo_render_seasonal(')) exit(8);
    $generated++;
}
if($generated!==94||$generated+$existingLegacy!==96) exit(9);
echo "SEO_CORE_MONTH_ROUTES_OK total=96 generated=94 preserved=2 review_noindex=1\n";
