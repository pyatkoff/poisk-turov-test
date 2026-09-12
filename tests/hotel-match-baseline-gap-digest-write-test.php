<?php
declare(strict_types=1);
define('HMBGW_LIBRARY_ONLY',true);define('HMBG_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_current_audit.php';
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_digest_write.php';
$m=hmbgw_manifest(__DIR__.'/../reports/hotel-match-baseline-gap-digest-write-1971.json');
if(count($m['pairs'])!==51)exit(2);$seen=[];$lanes=[];foreach($m['pairs'] as $p){if(isset($seen[(int)$p[0]]))exit(3);$seen[(int)$p[0]]=1;$lanes[$p[2]]=($lanes[$p[2]]??0)+1;}if(($lanes['safe_bridge_current']??0)!==42||($lanes['safe_geo_current']??0)!==9)exit(4);
$s=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_digest_write.php');foreach(['SERIALIZABLE','hmbg_run($pdo,$auditManifest)','FOR UPDATE','post_commit_readback_failed','resolver_readback_failed'] as $n)if(strpos($s,$n)===false)exit(5);if(substr_count($s,'INSERT INTO anex_hotel_search_mappings')!==1)exit(6);if(stripos($s,'UPDATE andromeda_hotel_identities')!==false||stripos($s,'DELETE FROM')!==false)exit(7);echo "MATCH baseline-gap digest writer source guards PASS; pairs=51 bridge=42 geo=9 supplier=0\n";
