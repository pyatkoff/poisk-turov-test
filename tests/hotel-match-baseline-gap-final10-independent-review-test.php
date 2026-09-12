<?php
declare(strict_types=1);
define('HMBGFR_LIBRARY_ONLY',true);define('HMBGF_LIBRARY_ONLY',true);define('HMBGC_LIBRARY_ONLY',true);define('HMBG_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_current_audit.php';
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_containment_audit.php';
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_final10_dossier.php';
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_final10_independent_review.php';
$h=hmbgfr_exact_key_hits(['Hotel Sahinler.'],[17612=>['Hotel Sahinler'],17360=>['Buyuk Sahinler Hotel']]);if($h!==[17612])exit(2);
$b=hmbgfr_bridge_hits([['external_id'=>'2000082981']],[103067=>[['external_id'=>'2000082981']],146171=>[]]);if(array_keys($b)!==[103067])exit(3);
$b=hmbgfr_bridge_hits([['external_id'=>'528356']],[9405=>[['external_id'=>'528356']],108078=>[['external_id'=>'other']]]);if(array_keys($b)!==[9405])exit(4);
$s=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_final10_independent_review.php');foreach(['START TRANSACTION READ ONLY','safe_recovery_bridge_continuity','safe_exact_sibling_anex','independent_evidence_not_unique','current_source_country_conflict'] as $n)if(strpos($s,$n)===false)exit(5);if(stripos($s,'INSERT INTO')!==false||stripos($s,'UPDATE ')!==false||stripos($s,'DELETE FROM')!==false)exit(6);echo "MATCH final10 independent review source guards PASS; writes=0 supplier=0\n";
