<?php
declare(strict_types=1);
$path=dirname(__DIR__).'/scripts/diagnostics/hotel_match_live942_tv_writer_v1.php';
$src=(string)file_get_contents($path);
$need=[
"const HMTVW_RECONCILE_SHA='57245b9020beefb3760c3bf65696f3fc4a33485c8b4ec74f2058f40b363e1700'",
"const HMTVW_POLICY='owner_exact_and_strong_20260908'",
"const HMTVW_CLASS='strong_candidate'",
"SET TRANSACTION ISOLATION LEVEL SERIALIZABLE",
"FOR UPDATE",
"manual_decision_present",
"pair_excluded",
"source_occupied",
"target_occupied",
"INSERT INTO anex_hotel_search_mappings",
"postcommit_readback",
"'planned'=>88",
"'authority'=>'direct_hotellist'",
"5c98c84ab34a24e4f65c32e328bf90bfcc0c328749cffba93e9c1fde603c47d8",
];
foreach($need as $x)if(!str_contains($src,$x))throw new RuntimeException('missing:'.$x);
foreach(['UPDATE anex_hotel_search_mappings','DELETE FROM anex_hotel_search_mappings','REPLACE INTO anex_hotel_search_mappings'] as $x)if(str_contains($src,$x))throw new RuntimeException('forbidden:'.$x);
passthru('php '.escapeshellarg($path).' --self-test',$rc);
if($rc!==0)throw new RuntimeException('self_test');
echo "MATCH_LIVE942_TV_WRITER_V1_CONTRACT_OK\n";
