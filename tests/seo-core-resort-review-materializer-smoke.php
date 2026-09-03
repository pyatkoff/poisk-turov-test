<?php
declare(strict_types=1);
$runtime=(string)file_get_contents(__DIR__.'/../v2/seo-core-resort-review-runtime-v1.php');
$mat=(string)file_get_contents(__DIR__.'/../v2/data/materialize-seo-core-resort-review-routes-v1.php');
foreach(['v2_seo_generated_resort_review_record','v2_seo_generated_resort_month_review_record',"'status'=>'review'","'indexation_allowed'=>false","'sitemap_allowed'=>false"] as $needle)if(!str_contains($runtime,$needle))exit(1);
foreach(['SEO_CORE_RESORT_REVIEW_GENERATED_V1','publication=0 indexation=0 sitemap=0','seo-core-resort-review-registry-v1.php','seo-core-resort-review-routes-v1.json'] as $needle)if(!str_contains($mat,$needle))exit(2);
if(str_contains($mat,'sitemap.xml'))exit(3);
if(!str_contains($mat,'#^/country/(egypt|maldives)/[a-z0-9-]+/$#'))exit(4);
echo "SEO_CORE_RESORT_REVIEW_MATERIALIZER_OK months=12 noindex=1 sitemap=0\n";
