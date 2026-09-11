<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-flight-details.php';

$checks = 0;
function flight_check(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException('flight_check_'.$checks);
}

$expected = [
    'tourvisor' => ['verified', 'tourvisor_tours_flights'],
    'anex' => ['verified', 'freight_monitor_freights_by_packet'],
    'andromeda' => ['package_conditional', 'andromeda_get_flights'],
];
foreach ($expected as $provider => [$status, $method]) {
    $value = AnyTourThreeProviderFlightDetails::forSearch($provider);
    flight_check($value['provider'] === $provider);
    flight_check($value['capability_status'] === $status && $value['source_method'] === $method);
    flight_check($value['details_state'] === 'not_loaded' && $value['segments'] === []);
    flight_check($value['external_lookup_required'] === null);
    flight_check($value['baggage_state'] === 'unknown' && $value['price_effect'] === 'unknown');
    flight_check($value['automatic_fetch_allowed'] === false && $value['final_price_verified'] === false);
}
try {
    AnyTourThreeProviderFlightDetails::forSearch('other');
    flight_check(false);
} catch (InvalidArgumentException $e) {
    flight_check(true);
}

$bound = [
    'status'=>'package_bound_unquoted',
    'identity_verified'=>true,
    'package_binding_verified'=>true,
    'requires_external_flights'=>true,
];
$external = AnyTourThreeProviderFlightDetails::forAndromedaPackage($bound);
flight_check($external['details_state'] === 'external_lookup_required');
flight_check($external['external_lookup_required'] === true);
flight_check($external['automatic_fetch_allowed'] === false && $external['final_price_verified'] === false);
flight_check($external['segments'] === [] && $external['price_effect'] === 'unknown');

$embedded = AnyTourThreeProviderFlightDetails::forAndromedaPackage(
    array_replace($bound, ['requires_external_flights'=>false]));
flight_check($embedded['details_state'] === 'external_lookup_not_required');
flight_check($embedded['external_lookup_required'] === false);
flight_check($embedded['automatic_fetch_allowed'] === false && $embedded['final_price_verified'] === false);

$unknown = AnyTourThreeProviderFlightDetails::forAndromedaPackage(
    array_replace($bound, ['requires_external_flights'=>null]));
flight_check($unknown['details_state'] === 'requirement_unknown');
flight_check($unknown['external_lookup_required'] === null);

foreach ([
    [],
    array_replace($bound, ['status'=>'package_captured_unquoted']),
    array_replace($bound, ['identity_verified'=>false]),
    array_replace($bound, ['package_binding_verified'=>false]),
] as $invalid) {
    $value = AnyTourThreeProviderFlightDetails::forAndromedaPackage($invalid);
    flight_check($value['details_state'] === 'package_not_bound');
    flight_check($value['external_lookup_required'] === null);
    flight_check($value['automatic_fetch_allowed'] === false && $value['final_price_verified'] === false);
}

echo 'Three-provider flight capability: '.$checks." checks passed; get_flights/calc/supplier/booking=0.\n";
