<?php
declare(strict_types=1);
$plan=dirname(__DIR__).'/scripts/diagnostics/hotel_match_live30_common4_plan_v1.php';
$run=dirname(__DIR__).'/scripts/diagnostics/hotel_match_live30_common4_acquire_v1.py';
foreach([$plan,$run] as $p)if(!is_file($p))throw new RuntimeException('missing_source');
$ps=(string)file_get_contents($plan);$rs=(string)file_get_contents($run);
foreach([
  'HMC4P_EXPECTED_FRONTIER=1799',
  "'operator_ids'=>[13,18,25,43]",
  "'missing_operator_ids'=>$missing",
  "'database_writes'=>0",
  "'mapping_writes'=>0",
] as $needle)if(!str_contains($ps,$needle))throw new RuntimeException('planner_'.$needle);
foreach([
  "OPS=(13,18,25,43)",
  "NAMESPACES={13:'anex',18:'bgoperator',25:'operator_315',43:'operator_342'}",
  "CALL_CAP=int(os.environ.get('MATCH_CALL_CAP','900'))",
  "limit>150",
  "unexpected_anex_host",
  "unexpected_bg_host",
  "captured_single_native",
  "incomplete_status_batches",
  "continue_calls':0",
  "dates_calls':0",
  "'database_writes':0",
  "'mapping_writes':0",
] as $needle)if(!str_contains($rs,$needle))throw new RuntimeException('runner_'.$needle);
foreach(['/tours/dates','/continue'] as $forbidden)if(str_contains($rs,$forbidden))throw new RuntimeException('forbidden_'.$forbidden);
passthru('php '.escapeshellarg($plan).' --self-test',$a);if($a!==0)throw new RuntimeException('planner_selftest');
passthru('python3 '.escapeshellarg($run).' --self-test',$b);if($b!==0)throw new RuntimeException('runner_selftest');
echo "MATCH_LIVE30_COMMON4_ACQUIRE_V1_TEST_OK\n";
