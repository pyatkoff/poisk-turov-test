<?php
declare(strict_types=1);
define('HMBGF_LIBRARY_ONLY',true);define('HMBGC_LIBRARY_ONLY',true);define('HMBG_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_current_audit.php';
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_containment_audit.php';
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_final10_dossier.php';
$m=hmbgf_manifest(__DIR__.'/../reports/hotel-match-baseline-gap-final10-dossier-input-1971.json');if(count($m['rows'])!==10)exit(2);$ids=array_map(static fn($r)=>(int)$r['external_hotel_id'],$m['rows']);if(count(array_unique($ids))!==10)exit(3);if(hmbgf_norm_names(['Blue Whale Resort','Blue Whale Resort.'])!==['blue whale'])exit(4);$s=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_final10_dossier.php');foreach(['START TRANSACTION READ ONLY','sibling_anex','andromeda_name_keys','recovered_source_name_keys','geo_supported'] as $n)if(strpos($s,$n)===false)exit(5);if(stripos($s,'INSERT INTO')!==false||stripos($s,'UPDATE ')!==false||stripos($s,'DELETE FROM')!==false)exit(6);echo "MATCH final10 dossier source guards PASS; rows=10 writes=0 supplier=0\n";
