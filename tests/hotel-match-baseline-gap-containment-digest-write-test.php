<?php
declare(strict_types=1);
define('HMBGCW_LIBRARY_ONLY',true);define('HMBGC_LIBRARY_ONLY',true);define('HMBG_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_current_audit.php';
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_containment_audit.php';
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_containment_digest_write.php';
$m=hmbgcw_manifest(__DIR__.'/../reports/hotel-match-baseline-gap-containment-digest-write-1971.json');
if(($m['expected_count']??0)!==94||($m['expected_safe_set_sha256']??'')!==HMBGCW_SET_SHA)exit(2);
$fixture=[['external_hotel_id'=>2,'proposed_local_id'=>3,'safe_lane'=>'safe_geo_containment','source_digest'=>str_repeat('a',64),'target_digest'=>str_repeat('b',64),'current_bridge_external_ids'=>[]],['external_hotel_id'=>1,'proposed_local_id'=>4,'safe_lane'=>'safe_bridge_containment','source_digest'=>str_repeat('c',64),'target_digest'=>str_repeat('d',64),'current_bridge_external_ids'=>['9','8']]];
if(count(hmbgcw_set_lines($fixture))!==2)exit(3);
$sha1=hmbgcw_set_sha($fixture);$sha2=hmbgcw_set_sha(array_reverse($fixture));if($sha1!==$sha2||!preg_match('/\A[a-f0-9]{64}\z/',$sha1))exit(4);
$s=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_containment_digest_write.php');foreach(['SERIALIZABLE','hmbgc_run($pdo,$auditManifest)','FOR UPDATE','current_safe_set_changed','locked_safe_set_changed','post_commit_readback_failed','resolver_readback_failed'] as $n)if(strpos($s,$n)===false)exit(5);if(substr_count($s,'INSERT INTO anex_hotel_search_mappings')!==1)exit(6);if(stripos($s,'UPDATE andromeda_hotel_identities')!==false||stripos($s,'DELETE FROM')!==false)exit(7);echo "MATCH containment digest writer source guards PASS; expected=94 supplier=0\n";
