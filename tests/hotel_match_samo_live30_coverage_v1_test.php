<?php
declare(strict_types=1);
$src=dirname(__DIR__).'/scripts/diagnostics/hotel_match_tv_samo_anex_coverage_v1.php';
$wrap=dirname(__DIR__).'/scripts/diagnostics/hotel_match_current_coverage_wrapper_v1.php';
foreach([$src,$wrap] as $p)if(!is_file($p))throw new RuntimeException('missing_source');
$s=(string)file_get_contents($src);$w=(string)file_get_contents($wrap);
foreach([
  'andromeda_search_hotel_observations',
  'supplier_namespace',
  'external_hotel_id',
  "'samo_live_30d'=>",
  "'catalog_observed_hotel_ids'=>",
  "'mapped_local_hotels_any_namespace'=>",
  "'unresolved_identity_keys'=>",
  "'source_collision_identity_keys'=>",
  "'full_triple'=>0",
  "'tv_only'=>0",
  "'anex_only'=>0",
  "'only_samo'=>0",
] as $needle)if(!str_contains($s,$needle))throw new RuntimeException('missing_'.$needle);
if(str_contains($s,"FROM anytour_offers WHERE provider='andromeda'"))throw new RuntimeException('offer_store_substitution_forbidden');
if(!str_contains($w,"'samo_live30'=>$result['samo_live_30d']??null"))throw new RuntimeException('wrapper_summary_missing');
passthru('php '.escapeshellarg($src).' --self-test',$code);
if($code!==0)throw new RuntimeException('selftest_failed');
echo "MATCH_SAMO_LIVE30_COVERAGE_V1_TEST_OK\n";
