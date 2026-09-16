<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/andromeda-served-price-checkpoint-reader.php';

$checks = 0;
function checkpoint_check(bool $ok): void { global $checks; ++$checks; if (!$ok) throw new RuntimeException('checkpoint_check_' . $checks); }
function checkpoint_observation(int $bps, string $served = '100000', string $final = '100000'): array {
    $delta = ((int)$final - (int)$served) . '.00';
    $absolute = abs((int)$final - (int)$served) . '.00';
    return [
        'schema_version'=>1, 'provider'=>'andromeda', 'basis'=>'search_api_response',
        'state'=>'comparable', 'price_basis'=>'transport_surcharge_estimate',
        'served_at'=>1789500000, 'actualized_at'=>1789500010,
        'served_price'=>['amount'=>$served,'currency'=>'RUB'],
        'final_price'=>['amount'=>$final,'currency'=>'RUB'],
        'signed_delta_amount'=>$delta, 'absolute_delta_amount'=>$absolute,
        'relative_delta_bps'=>$bps, 'final_price_verified'=>true,
    ];
}
function checkpoint_write(string $path, array $state): void {
    file_put_contents($path, json_encode(['state'=>$state], JSON_THROW_ON_ERROR));
}

$dir = sys_get_temp_dir() . '/anytour-andromeda-accuracy-' . bin2hex(random_bytes(6));
mkdir($dir, 0700, true);
checkpoint_write($dir.'/a-quote-v1.json', ['status'=>'completed','result'=>[
    'served_price_observation'=>checkpoint_observation(0)
]]);
checkpoint_write($dir.'/b-quote-flight-v1.json', ['status'=>'completed','result'=>[
    'served_price_observation'=>checkpoint_observation(500, '100000', '105000')
]]);
checkpoint_write($dir.'/c-quote-v1.json', ['status'=>'completed','result'=>[]]);
checkpoint_write($dir.'/d-quote-v1.json', ['status'=>'reserved','result'=>null]);
file_put_contents($dir.'/e-quote-v1.json', '{broken');
file_put_contents($dir.'/ignored.json', '{}');

$result = AnyTourAndromedaServedPriceCheckpointReader::summarizeDirectory($dir);
checkpoint_check($result['provider'] === 'andromeda');
checkpoint_check($result['population'] === 'natural_customer_actualizations');
checkpoint_check($result['source'] === 'completed_quote_checkpoints');
checkpoint_check($result['scan']['matched_files'] === 5);
checkpoint_check($result['scan']['files_examined'] === 5);
checkpoint_check($result['scan']['completed_checkpoints'] === 3);
checkpoint_check($result['scan']['observations'] === 2);
checkpoint_check($result['scan']['no_observation'] === 1);
checkpoint_check($result['scan']['not_completed'] === 1);
checkpoint_check($result['scan']['invalid_files'] === 1);
checkpoint_check($result['scan']['truncated_by_limit'] === false);
checkpoint_check($result['accuracy']['counts']['comparable'] === 2);
checkpoint_check($result['accuracy']['counts']['exact'] === 1);
checkpoint_check($result['accuracy']['exact_accuracy'] === 0.5);
checkpoint_check($result['accuracy']['within_500_bps_accuracy'] === 1.0);
checkpoint_check($result['accuracy']['p90_relative_delta_bps'] === 500);
checkpoint_check($result['supplier_calls'] === 0 && $result['db_writes'] === 0 && $result['checkpoint_writes'] === 0);
checkpoint_check(strpos(json_encode($result, JSON_THROW_ON_ERROR), $dir) === false);

$limited = AnyTourAndromedaServedPriceCheckpointReader::summarizeDirectory($dir, 1);
checkpoint_check($limited['scan']['files_examined'] === 1);
checkpoint_check($limited['scan']['truncated_by_limit'] === true);
checkpoint_check($limited['accuracy']['counts']['total'] === 1);

$bad = false;
try { AnyTourAndromedaServedPriceCheckpointReader::summarizeDirectory($dir, 0); } catch (InvalidArgumentException $e) { $bad = true; }
checkpoint_check($bad);

foreach (glob($dir.'/*') ?: [] as $path) @unlink($path);
@rmdir($dir);
echo 'Andromeda completed checkpoint accuracy reader: ' . $checks . " checks passed; supplier/DB/checkpoint writes=0.\n";
