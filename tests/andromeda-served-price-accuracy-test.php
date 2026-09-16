<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/andromeda-served-price-accuracy.php';

$c = 0; function acc(bool $ok): void { global $c; ++$c; if (!$ok) throw new RuntimeException('acc_'.$c); }
function obs(int $bps, int $served = 185125, int $final = 185125): array {
    return [
        'schema_version'=>1,'provider'=>'andromeda','basis'=>'search_api_response','state'=>'comparable',
        'price_basis'=>'transport_surcharge_estimate','served_at'=>1789545600,'actualized_at'=>1789545660,
        'served_price'=>['amount'=>(string)$served,'currency'=>'RUB'],
        'final_price'=>['amount'=>(string)$final,'currency'=>'RUB'],
        'signed_delta_amount'=>$final >= $served ? (string)($final-$served).'.00' : '-'.(string)($served-$final).'.00',
        'absolute_delta_amount'=>(string)abs($final-$served).'.00','relative_delta_bps'=>$bps,'final_price_verified'=>true,
    ];
}

$rows = [obs(0), obs(50, 200000, 201000), obs(250, 200000, 205000), obs(700, 200000, 214000), obs(1500, 200000, 230000)];
$s = AnyTourAndromedaServedPriceAccuracy::summarize($rows);
acc($s['population'] === 'natural_customer_actualizations');
acc($s['counts']['total'] === 5 && $s['counts']['comparable'] === 5);
acc($s['counts']['exact'] === 1 && $s['exact_accuracy'] === 0.2);
acc($s['within_100_bps_accuracy'] === 0.4);
acc($s['within_300_bps_accuracy'] === 0.6);
acc($s['within_500_bps_accuracy'] === 0.6);
acc($s['within_1000_bps_accuracy'] === 0.8);
acc($s['mean_relative_delta_bps'] === 500);
acc($s['p50_relative_delta_bps'] === 250);
acc($s['p90_relative_delta_bps'] === 1500 && $s['max_relative_delta_bps'] === 1500);
acc($s['runtime_gate_applied'] === false);

$empty = AnyTourAndromedaServedPriceAccuracy::summarize([]);
acc($empty['exact_accuracy'] === null && $empty['p90_relative_delta_bps'] === null);

$currency = obs(0); $currency['state']='currency_mismatch'; $currency['final_price']['currency']='USD';
$currency['signed_delta_amount']=$currency['absolute_delta_amount']=$currency['relative_delta_bps']=null;
$mix = AnyTourAndromedaServedPriceAccuracy::summarize([$currency]);
acc($mix['counts']['currency_mismatch'] === 1 && $mix['counts']['comparable'] === 0);

try { $bad = obs(0); $bad['final_price_verified']=false; AnyTourAndromedaServedPriceAccuracy::summarize([$bad]); acc(false); }
catch (InvalidArgumentException $e) { acc($e->getMessage()==='ANDROMEDA_SERVED_ACCURACY_OBSERVATION'); }

echo 'Andromeda served-price accuracy: '.$c." checks passed; supplier/write/runtime-gate=0.\n";
