<?php
declare(strict_types=1);
$path=dirname(__DIR__).'/scripts/diagnostics/hotel_match_current_coverage_wrapper_v1.php';
$src=(string)file_get_contents($path);
foreach([
  "hotel_match_tv_samo_anex_coverage_v1.php",
  "completed_read_only_coverage",
  "provider_http_calls",
  "database_writes",
  "mapping_writes",
  "no_replay",
  "MATCH_CHILD_OPERATION",
] as $x) if(!str_contains($src,$x)) throw new RuntimeException('missing_'.$x);
foreach(['INSERT INTO','UPDATE ','DELETE FROM','REPLACE INTO','ALTER TABLE','DROP TABLE','TRUNCATE TABLE'] as $x) if(str_contains($src,$x)) throw new RuntimeException('write_'.$x);
passthru('php '.escapeshellarg($path).' --self-test',$rc);
if($rc!==0) throw new RuntimeException('selftest');
echo "MATCH_CURRENT_COVERAGE_WRAPPER_V1_CONTRACT_OK\n";
