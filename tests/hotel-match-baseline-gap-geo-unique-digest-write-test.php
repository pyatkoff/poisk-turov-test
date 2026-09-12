<?php
declare(strict_types=1);
define('HMBGGW_LIBRARY_ONLY',true);define('HMBGG_LIBRARY_ONLY',true);define('HMBGC_LIBRARY_ONLY',true);define('HMBG_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_current_audit.php';
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_containment_audit.php';
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_geo_unique_audit.php';
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_geo_unique_digest_write.php';
$m=hmbggw_manifest(__DIR__.'/../reports/hotel-match-baseline-gap-geo-unique-digest-write-1971.json');if(($m['expected_count']??0)!==13||($m['expected_safe_set_sha256']??'')!==HMBGGW_SET_SHA)exit(2);
$f=[['external_hotel_id'=>2,'proposed_local_id'=>3,'safe_lane'=>'safe_geo_scoped_unique','source_digest'=>str_repeat('a',64),'target_digest'=>str_repeat('b',64)],['external_hotel_id'=>1,'proposed_local_id'=>4,'safe_lane'=>'safe_geo_scoped_unique','source_digest'=>str_repeat('c',64),'target_digest'=>str_repeat('d',64)]];if(hmbggw_set_sha($f)!==hmbggw_set_sha(array_reverse($f)))exit(3);
$s=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_geo_unique_digest_write.php');foreach(['SERIALIZABLE','FOR UPDATE','current_safe_set_changed','locked_safe_set_changed','post_commit_readback_failed','resolver_readback_failed','safe_geo_scoped_unique'] as $n)if(strpos($s,$n)===false)exit(4);if(substr_count($s,'INSERT INTO anex_hotel_search_mappings')!==1||stripos($s,'UPDATE andromeda_hotel_identities')!==false||stripos($s,'DELETE FROM')!==false)exit(5);echo "MATCH geo-unique digest writer source guards PASS; expected=13 supplier=0\n";
