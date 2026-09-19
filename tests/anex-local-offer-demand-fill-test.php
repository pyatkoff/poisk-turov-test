<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/ops/anex_local_offer_demand_fill.php';
function ck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
function reject(callable $fn,string $label):void{try{$fn();}catch(InvalidArgumentException){return;}throw new RuntimeException($label);}
$scope=['departureId'=>1,'countryId'=>4,'regionId'=>20,'dateFrom'=>'2026-09-22','dateTo'=>'2026-09-22','nights'=>7,'adults'=>2,'childAges'=>[],
    'searches'=>107,'observations'=>562,'lastSeen'=>'2026-09-19 20:16:12'];
$cmd=AnyTourAnexDemandFillV1::collectorCommand($scope,'/tmp/collector.php',123,600,600);
ck($cmd[0]===PHP_BINARY&&$cmd[1]==='/tmp/collector.php','binary');
ck(in_array('--departure=1',$cmd,true)&&in_array('--country=4',$cmd,true)&&in_array('--region=20',$cmd,true),'identity');
ck(in_array('--date-from=2026-09-22',$cmd,true)&&in_array('--nights=7',$cmd,true)&&in_array('--generation=123',$cmd,true),'context');
ck(!array_filter($cmd,static fn($x)=>str_starts_with($x,'--child-ages=')),'no-children');
$family=array_replace($scope,['regionId'=>null,'childAges'=>[7,3],'searches'=>2]);
$familyCmd=AnyTourAnexDemandFillV1::collectorCommand($family,'/tmp/collector.php',124);
ck(!in_array('--region=20',$familyCmd,true)&&in_array('--child-ages=3,7',$familyCmd,true),'children-sorted');
reject(fn()=>AnyTourAnexDemandFillV1::collectorCommand(array_replace($scope,['dateTo'=>'2026-09-23']),'/tmp/c.php',1),'date-mismatch');
$summary=AnyTourAnexDemandFillV1::summarize($scope,['grouped_candidates'=>4,'expand_calls'=>4,'charter_concrete_candidates'=>24,
 'apd_batch_items'=>24,'final_price_ready_offers'=>24,'retryable_offers'=>0,'discovered_set_drained'=>true,
 'search_client_instances'=>5,'apd_client_instances'=>0,'snapshot_finalize'=>['published'=>true]]);
ck($summary['finalPriceReadyOffers']===24&&$summary['discoveredSetDrained']===true&&$summary['scope']['regionId']===20,'summary');
echo "ANEX_LOCAL_OFFER_DEMAND_FILL_OK command=1 children=1 summary=1\n";
