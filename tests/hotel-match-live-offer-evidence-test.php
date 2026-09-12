<?php
declare(strict_types=1);
$script=dirname(__DIR__).'/scripts/diagnostics/hotel_match_live_offer_evidence.php';
if(!is_file($script))throw new RuntimeException('script_missing');
$raw=file_get_contents($script);
$checks=[
    strpos($raw,"const HMOE_OP='hotel-match-live-offer-evidence-1971-20260912-v1'")!==false,
    strpos($raw,"'price_arithmetic_modified'=>false")!==false,
    strpos($raw,"'database_writes'=>0")!==false,
    strpos($raw,"'mapping_writes'=>0")!==false,
    strpos($raw,"array_chunk($ids,30)")!==false,
    strpos($raw,"'operatorStatus'=>false")!==false,
    strpos($raw,"price_relative_delta")!==false,
    strpos($raw,"unique_alias")!==false,
    strpos($raw,"ROULETTE|FORTUNA|ТУР|ЭКСКУРС|EXCURSION")!==false,
    strpos($raw,"/tours/search")!==false,
    strpos($raw,'INSERT ')===false,
    strpos($raw,'UPDATE ')===false,
    strpos($raw,'DELETE ')===false,
];
foreach($checks as $i=>$ok)if(!$ok)throw new RuntimeException('guard_failed_'.$i);
$cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' --self-test';
exec($cmd,$out,$rc);if($rc!==0||!str_contains(implode("\n",$out),'self-test PASS'))throw new RuntimeException('self_test_failed');
echo 'MATCH live offer evidence guards PASS checks='.count($checks)."\n";
