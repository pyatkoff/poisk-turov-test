<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_tv_samo_side4_saved_alias_v9.php';
$long=str_repeat('ALPHA ',50);
$x=hma7_name_score($long.'HOTEL',$long.'HOTEL');
if(!$x['exact']||$x['score']!==1.0)throw new RuntimeException('long_name_fallback');
$source=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_tv_samo_side4_saved_alias_v9.php');
if(str_contains($source,'hmc_tv_call(')||str_contains($source,'->price(')||str_contains($source,'v2_data_db('))throw new RuntimeException('offline_only');
echo "MATCH_TV_SAMO_SIDE4_SAVED_ALIAS_V9_TEST_OK\n";
