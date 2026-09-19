<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$script=$root.'/scripts/diagnostics/anex_current_hotel15851_acceptance_v1.php';
exec('php '.escapeshellarg($script).' --self-test 2>&1',$out,$code);
if($code!==0||implode("\n",$out)!=='ANEX_CURRENT_HOTEL15851_SELFTEST_OK checks=4')throw new RuntimeException('selftest failed');
$src=file_get_contents($script);if(!is_string($src))throw new RuntimeException('source missing');
foreach(["'hotelIds'=>[(string)HOTEL]","'regionIds'=>[(string)REGION]","'action'=>'additional_prices_batch'","diagnostic_db_writes'=>0"] as $token)
 if(!str_contains($src,$token))throw new RuntimeException('missing '.$token);
foreach(["'action'=>'offer'","'action'=>'expand'","action=get_flights","action=changeservice","action=calc","action=book"] as $token)
 if(str_contains($src,$token))throw new RuntimeException('forbidden '.$token);
echo "ANEX_CURRENT_HOTEL15851_TEST_OK checks=11\n";
