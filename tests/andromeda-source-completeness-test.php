<?php
declare(strict_types=1);

require_once __DIR__ . '/../v2/api-andromeda-search3-preview.php';

$classes = [
    'AnyTourAndromedaSearch',
    'AnyTourAndromedaOfferStore',
    'AnyTourAndromedaHotelResolver',
    'AnyTourAndromedaHotelObservations',
];
foreach ($classes as $class) {
    if (!class_exists($class, false)) {
        throw new RuntimeException('ANDROMEDA_SOURCE_CLASS_MISSING_' . $class);
    }
}
foreach ([
    'anytour_andromeda_search3_run',
    'anytour_andromeda_search3_run_pages',
    'anytour_andromeda_search3_project',
] as $function) {
    if (!function_exists($function)) {
        throw new RuntimeException('ANDROMEDA_SOURCE_FUNCTION_MISSING_' . $function);
    }
}

$resolver = AnyTourAndromedaHotelResolver::fromRows([[
    'supplier_namespace' => 'andromeda_catalog',
    'external_hotel_id' => '123',
    'catalog_hotel_id' => 77,
    'existing_catalog_hotel_id' => 77,
    'decision_status' => 'accepted',
]], hash('sha256', 'source-completeness'));
$page = $resolver->apply([
    'provider' => 'andromeda',
    'selection_enabled' => false,
    'offers' => [[
        'provider' => 'andromeda',
        'supplier_namespace' => 'andromeda_catalog',
        'external_hotel_id' => '123',
        'local_hotel_id' => null,
        'selection_enabled' => false,
    ]],
]);
if (($page['offers'][0]['local_hotel_id'] ?? null) !== 77 || ($page['mapped_offer_count'] ?? null) !== 1) {
    throw new RuntimeException('ANDROMEDA_SOURCE_RESOLVER_REGRESSION');
}

echo "ANDROMEDA_SOURCE_COMPLETENESS_OK classes=4 endpoint=1 resolver=1\n";
