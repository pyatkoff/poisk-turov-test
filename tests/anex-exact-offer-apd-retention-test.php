<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/anex-additional-prices-batch.php';

$ref='anex_online:'.str_repeat('a',64);
$offer=['offer_key'=>$ref,'kind'=>'concrete','hotel'=>['external_id'=>'8101','local_id'=>101],
 'checkin'=>'2026-10-05','nights'=>7];
$state=['gateway'=>['saved_offers'=>['offers'=>[$ref=>['offer'=>$offer,'supplier_tour_program_id'=>'2637','supplier_currency_id'=>'1']]],
 'search'=>['offers'=>[['offer_key'=>$ref,'kind'=>'concrete','hotel_external_id'=>'8101']]]],'additional_prices'=>[]];
$plan=anytour_anex_additional_prices_batch_plan([['offer_ref'=>$ref,'local_hotel_id'=>101]],$state);
$reads=0;
$result=anytour_anex_additional_prices_batch_execute($plan,$state,
 static function() use (&$reads):array{++$reads;return ['source'=>'anex_b2b_additional_prices_daily','rows'=>[['price_converted_adult'=>'1000']]];},
 static function(array &$state,string $digest):void{});
if($reads!==1||$result['offers'][0]['status']!=='complete')throw new RuntimeException('first completion failed');
$entry=$state['gateway']['saved_offers']['offers'][$ref];
if(($entry['additional_prices_context_digest']??null)!==$plan['offers'][0]['context_digest']
 ||($entry['additional_prices_evidence']['source']??null)!=='anex_b2b_additional_prices_daily')throw new RuntimeException('retained evidence missing');
$again=anytour_anex_additional_prices_batch_execute($plan,$state,
 static function() use (&$reads):array{++$reads;throw new RuntimeException('replay');},
 static function():void{throw new RuntimeException('checkpoint replay');});
if($reads!==1||$again['offers'][0]['status']!=='complete')throw new RuntimeException('completed evidence replayed');
if(($state['gateway']['saved_offers']['offers'][$ref]['additional_prices_evidence']['source']??null)!=='anex_b2b_additional_prices_daily')throw new RuntimeException('cached evidence lost');
echo "ANEX retained APD exact-offer evidence PASS; supplier replay=0\n";
