<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-air-search-filter.php';

$checks = 0;
function air_filter_check(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) {
        throw new RuntimeException('air_filter_check_'.$checks);
    }
}

$matrix = AnyTourThreeProviderAirSearchFilter::matrix();
air_filter_check($matrix['schema_version'] === 1);
air_filter_check($matrix['statuses'] === ['verified', 'local_only', 'unsupported', 'unknown']);
air_filter_check(count($matrix['rows']) === 9);

$allowedStatuses = array_flip($matrix['statuses']);
$seen = [];
foreach ($matrix['rows'] as $row) {
    $key = $row['provider'].':'.$row['family'];
    air_filter_check(!isset($seen[$key]));
    $seen[$key] = true;
    air_filter_check(isset($allowedStatuses[$row['catalog_discovery']['status']]));
    air_filter_check(isset($allowedStatuses[$row['upstream_search_filter']['status']]));
    air_filter_check($row['upstream_search_filter']['status'] === 'unknown');
    air_filter_check($row['upstream_search_filter']['allowed'] === false);
    air_filter_check($row['upstream_search_filter']['evidence'] === null);
    air_filter_check($row['raw_numeric_id_universal'] === false);
    air_filter_check($row['cross_provider_equivalence_verified'] === false);
    air_filter_check(AnyTourThreeProviderAirSearchFilter::allowsUpstreamSearchFilter($row['provider'], $row['family']) === false);
}

air_filter_check(count($seen) === 9);

$tourvisor = AnyTourThreeProviderAirSearchFilter::forProvider('tourvisor');
air_filter_check(array_keys($tourvisor) === ['arrival_airport', 'direct_flight', 'charter']);
air_filter_check($tourvisor['arrival_airport']['catalog_discovery'] === [
    'status' => 'verified',
    'evidence' => 'existing_tourvisor_arrivals_catalog',
    'scope' => 'catalog_only',
]);
air_filter_check($tourvisor['direct_flight']['catalog_discovery'] === [
    'status' => 'verified',
    'evidence' => 'existing_tourvisor_catalog_onlyDirect',
    'scope' => 'catalog_only',
]);
air_filter_check($tourvisor['charter']['catalog_discovery'] === [
    'status' => 'verified',
    'evidence' => 'existing_tourvisor_catalog_onlyCharter',
    'scope' => 'catalog_only',
]);

foreach (['anex', 'andromeda'] as $provider) {
    $facts = AnyTourThreeProviderAirSearchFilter::forProvider($provider);
    air_filter_check(array_keys($facts) === ['arrival_airport', 'direct_flight', 'charter']);
    foreach ($facts as $fact) {
        air_filter_check($fact['catalog_discovery'] === [
            'status' => 'unknown',
            'evidence' => null,
            'scope' => 'not_verified',
        ]);
        air_filter_check($fact['upstream_search_filter']['status'] === 'unknown');
        air_filter_check($fact['upstream_search_filter']['allowed'] === false);
    }
}

$bad = [
    function () { AnyTourThreeProviderAirSearchFilter::forProvider(''); },
    function () { AnyTourThreeProviderAirSearchFilter::forProvider('samo'); },
    function () { AnyTourThreeProviderAirSearchFilter::fact('tourvisor', 'airport'); },
    function () { AnyTourThreeProviderAirSearchFilter::fact('anex', 'onlyDirect'); },
    function () { AnyTourThreeProviderAirSearchFilter::allowsUpstreamSearchFilter('unknown', 'charter'); },
];
foreach ($bad as $case) {
    try {
        $case();
        air_filter_check(false);
    } catch (InvalidArgumentException $e) {
        air_filter_check(true);
    }
}

echo 'Three-provider air search filter: '.$checks." checks passed; supplier/network/DB/mapping=0.\n";
