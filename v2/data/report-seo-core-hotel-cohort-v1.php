<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/db-v1.php';
require_once __DIR__.'/seo-core-hotel-cohort-source-v1.php';
require_once dirname(__DIR__).'/seo-core-hotel-cohort-v1.php';
$limit=500;
foreach($argv as $arg)if(str_starts_with($arg,'--limit=')){$v=filter_var(substr($arg,8),FILTER_VALIDATE_INT);if($v!==false)$limit=max(1,min(500,(int)$v));}
$rows=v2_seo_core_hotel_cohort_source_rows(v2_data_db(),$limit);
$result=v2_seo_core_hotel_cohort_records($rows,$limit);
$result['source']='first_party_catalog_plus_price_observations';
$result['selection_window_days']=30;
$result['country_scope']=[1,4,8];
$result['ranking_note']='Cohort order reflects observed inventory coverage and recency only; it is not a hotel quality, popularity or rating claim.';
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
