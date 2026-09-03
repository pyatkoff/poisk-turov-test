<?php
declare(strict_types=1);
$runtime=(string)file_get_contents(__DIR__.'/../v2/seo-core-resort-runtime-v1.php');
$materializer=(string)file_get_contents(__DIR__.'/../v2/data/materialize-seo-core-resort-routes-v1.php');
foreach(['v2_seo_generated_resort_registry','v2_seo_generated_resort_record','v2_seo_generated_resort_month_record','resort_month:1:'] as $needle)if(!str_contains($runtime,$needle))exit(1);
if(substr_count($runtime,"=>'january'")!==1||!str_contains($runtime,"12=>'december'"))exit(2);
foreach(['SEO_CORE_RESORT_GENERATED_V1','--apply','seo-core-resort-registry-v1.php','seo-core-resort-routes-v1.json','/sitemap.xml','publication=1 indexation=1 route_launch=1'] as $needle)if(!str_contains($materializer,$needle))exit(3);
if(!str_contains($materializer,'#^/country/(egypt|maldives)/[a-z0-9-]+/$#'))exit(4);
if(!str_contains($materializer,"\$months=[1=>'january'"))exit(5);
echo "SEO_CORE_RESORT_MATERIALIZER_OK runtime=1 months=12 production_apply=1 sitemap_union=1\n";
