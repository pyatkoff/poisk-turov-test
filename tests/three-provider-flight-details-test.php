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
    'andromeda' => ['unknown', 'andromeda_get_flights'],
];
foreach ($expected as $provider => [$status, $method]) {
    $value = AnyTourThreeProviderFlightDetails::forSearch($provider);
    flight_check($value['provider'] === $provider);
    flight_check($value['capability_status'] === $status && $value['source_method'] === $method);
    flight_check($value['details_state'] === 'not_loaded' && $value['segments'] === []);
    flight_check($value['baggage_state'] === 'unknown' && $value['price_effect'] === 'unknown');
    flight_check($value['automatic_fetch_allowed'] === false && $value['final_price_verified'] === false);
}
try {
    AnyTourThreeProviderFlightDetails::forSearch('other');
    flight_check(false);
} catch (InvalidArgumentException $e) {
    flight_check(true);
}

echo 'Three-provider flight capability: '.$checks." checks passed; supplier/detail/booking=0.\n";
