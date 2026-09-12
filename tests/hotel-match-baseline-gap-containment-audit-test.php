<?php
declare(strict_types=1);
define('HMBGC_LIBRARY_ONLY',true);define('HMBG_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_current_audit.php';
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_containment_audit.php';
if(!hmbgc_contains_key('KUDOS PARC PATTAYA (EX. CITRUS PARC HOTEL PATTAYA)','citrus parc'))exit(2);
if(!hmbgc_contains_key('SAN MARINO HOTEL (EX. SAN MARCO HOTEL)','marco san'))exit(3);
if(!hmbgc_contains_key('MEXICANA SHARM RESORT','mexicana'))exit(4);
$s=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_containment_audit.php');foreach(['safe_bridge_containment','safe_geo_containment','safe_geo_bridge_fallback','containment_not_unique','current_source_name_conflict'] as $n)if(strpos($s,$n)===false)exit(5);foreach(['INSERT INTO','UPDATE ','DELETE FROM','COMMIT'] as $bad)if(stripos($s,$bad)!==false)exit(6);echo "MATCH baseline-gap containment audit source guards PASS; writes=0 supplier=0\n";
