<?php
declare(strict_types=1);
define('HMBG_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_current_audit.php';
$m=hmbg_manifest(__DIR__.'/../reports/hotel-match-baseline-gap-current-audit-1971.json');
if(count($m['pairs'])!==168) exit(2);
$seen=[];$bridge=0;$geo=0;
foreach($m['pairs'] as $p){$id=(int)$p[0];if(isset($seen[$id]))exit(3);$seen[$id]=1;if($p[5]===1)$bridge++;else$geo++;}
if($bridge!==93||$geo!==75) exit(4);
if(hmbg_key('The Grand Oasis Hotel & Resort')!=='grand oasis') exit(5);
if(!hmbg_geo_supported('Султанахмет-Фатих',['Стамбул','Султанахмет'])) exit(6);
$s=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_current_audit.php');
foreach(['START TRANSACTION READ ONLY','safe_bridge_current','safe_geo_current','current_source_name_conflict','current_bridge_missing','current_uniqueness_changed'] as $needle)if(strpos($s,$needle)===false)exit(7);
foreach(['INSERT INTO','UPDATE ','DELETE FROM','COMMIT'] as $bad)if(stripos($s,$bad)!==false)exit(8);
echo "MATCH baseline-gap CURRENT audit source guards PASS; pairs=168 bridge=93 geo=75 writes=0\n";
