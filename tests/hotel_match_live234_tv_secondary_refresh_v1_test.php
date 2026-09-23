<?php
declare(strict_types=1);
$planner=dirname(__DIR__).'/scripts/diagnostics/hotel_match_live234_frontier_plan_v1.php';
$runner=dirname(__DIR__).'/scripts/diagnostics/hotel_match_live234_tv_secondary_refresh_v1.py';
foreach([$planner,$runner] as $p)if(!is_file($p))throw new RuntimeException('missing_source');
$ps=(string)file_get_contents($planner);$rs=(string)file_get_contents($runner);
foreach(["frontier_changed_","effective ANEX","operator_ids'=>[18,25,43]","database_writes'=>0"] as $x){
    if($x==="effective ANEX")continue;
    if(!str_contains($ps,$x))throw new RuntimeException('planner_contract_'.$x);
}
foreach(["OPS=(18,25,43)","bgoperator","operator_315","operator_342","hotelIds","operatorIds","continue_calls':0","dates_calls':0","secret_bearing_link","unexpected_bg_host","single_native_chunk_unique_count","search_complete","incomplete_status_batches"] as $x)
    if(!str_contains($rs,$x))throw new RuntimeException('runner_contract_'.$x);
foreach(["/tours/dates","/continue","raise RuntimeError('search_timeout')"] as $x)if(str_contains($rs,$x))throw new RuntimeException('forbidden_'.$x);
passthru('php '.escapeshellarg($planner).' --self-test',$a);if($a!==0)throw new RuntimeException('planner_selftest');
passthru('python3 '.escapeshellarg($runner).' --self-test',$b);if($b!==0)throw new RuntimeException('runner_selftest');
echo "MATCH_LIVE234_TV_SECONDARY_V1_CONTRACT_OK\n";
