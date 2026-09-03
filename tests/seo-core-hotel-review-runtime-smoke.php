<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-core-hotel-cohort-v1.php';
require_once __DIR__.'/../v2/seo-core-hotel-review-runtime-v1.php';

$rows=[['hotel_id'=>65108,'country_id'=>8,'hotel_name'=>'The Westin Maldives Miriandhoo Resort','hotel_slug'=>'the-westin-maldives-miriandhoo-resort-65108','country_slug'=>'maldives','country_name'=>'Мальдивы','observation_count'=>4,'last_observed_at'=>'2026-09-03 12:00:00']];
$cohort=v2_seo_core_hotel_cohort_records($rows,1);$record=$cohort['records'][0]??null;if(!is_array($record))exit(1);
$file=v2_seo_core_hotel_review_registry_file();$dir=dirname($file);$createdDir=false;
if(!is_dir($dir)){$createdDir=mkdir($dir,0755,true);if(!$createdDir)exit(2);}
$had=is_file($file);$backup=$had?(string)file_get_contents($file):null;
try{
    file_put_contents($file,"<?php return ".var_export([$record['path']=>$record],true).";\n");
    $loaded=v2_seo_core_hotel_review_record($record['path']);
    if(!is_array($loaded)||($loaded['status']??'')!=='review'||($loaded['type']??'')!=='hotel_tours')exit(3);
    if(($loaded['data']['search_state']??[])!==['country'=>8,'hotel'=>65108])exit(4);
    if(v2_seo_core_hotel_review_record('/country/maldives/hotel/not-in-registry-999/')!==null)exit(5);
    if(v2_seo_core_hotel_review_record('/country/maldives/september/')!==null)exit(6);
}finally{
    if($had)file_put_contents($file,(string)$backup);else @unlink($file);
    if($createdDir)@rmdir($dir);
}
$src=(string)file_get_contents(__DIR__.'/../v2/data/materialize-seo-core-hotel-review-routes-v1.php');
foreach(['SEO_CORE_HOTEL_GENERATED_V1','publication=0 indexation=0 sitemap=0','--dry-run'] as $needle)if(!str_contains($src,$needle))exit(7);
echo "SEO_CORE_HOTEL_REVIEW_RUNTIME_OK review=1 no_publication=1\n";
