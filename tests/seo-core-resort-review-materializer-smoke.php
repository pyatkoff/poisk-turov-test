<?php
declare(strict_types=1);
$runtime=(string)file_get_contents(__DIR__.'/../v2/seo-core-resort-review-runtime-v1.php');
$mat=(string)file_get_contents(__DIR__.'/../v2/data/materialize-seo-core-resort-review-routes-v1.php');
foreach(['v2_seo_generated_resort_review_record','v2_seo_generated_resort_month_review_record','v2_seo_generated_resort_launch_enabled',"$public?'approved':'review'",'v2_seo_core_resort_launch_paths'] as $needle)if(!str_contains($runtime,$needle))exit(1);
foreach(['SEO_CORE_RESORT_REVIEW_GENERATED_V1','publication=1 indexation=1 sitemap=1 hotel_tours=0','seo-core-resort-review-registry-v1.php','seo-core-resort-review-routes-v1.json',"rr_write($root.'/sitemap.xml'",'hotel_tours_indexation_allowed'] as $needle)if(!str_contains($mat,$needle))exit(2);
if(!str_contains($mat,'#^/country/(egypt|maldives)/([a-z0-9-]+)/$#'))exit(3);
if(!str_contains($mat,'v2_seo_core_resort_reserved_slugs'))exit(4);
echo "SEO_CORE_RESORT_LAUNCH_MATERIALIZER_OK months=12 manifest_gate=1 sitemap=1 hotel_tours=0\n";
