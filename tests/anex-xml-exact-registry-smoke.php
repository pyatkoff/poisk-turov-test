<?php
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/../app/integrations/anex-xml-exact-registry.php';
require_once __DIR__ . '/../app/integrations/anex-normalizer.php';
$includeOutput = ob_get_clean();
$checks = 0;
function exact_registry_check(bool $condition, string $name): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAILED: ' . $name);
    $checks++;
}

exact_registry_check($includeOutput === '', 'include has no output');
$registry = AnyTourAnexXmlExactRegistry::fromFile();
exact_registry_check($registry->count() === 8734, 'audited mapping count');
exact_registry_check($registry->resolve('anex_xml', '39527', 'preview') === 116676, 'known exact mapping');
exact_registry_check($registry->resolve('anex_xml', 39527, 'preview') === 116676, 'integer external ID');
exact_registry_check($registry->resolve('anex_xml', '39527') === null, 'production is disabled');
exact_registry_check($registry->resolve('anex_online', '39527', 'preview') === 116676, 'online uses unified ANEX hotel ID');
exact_registry_check($registry->resolve('anex_online', '39527') === null, 'online production is disabled');
exact_registry_check($registry->resolve('tourvisor_api', '39527', 'preview') === null, 'other providers stay separate');
exact_registry_check($registry->resolve('andromeda', '39527', 'preview') === null, 'Andromeda stays separate');
$previewResolver = $registry->previewResolver();
exact_registry_check($previewResolver('anex_online', '39527') === 116676, 'explicit preview resolver');
exact_registry_check($previewResolver('tourvisor_api', '39527') === null, 'preview resolver remains provider scoped');
foreach (['039527', '+39527', '39527 ', '3.9527e4', '', '-1', 39527.0, true, null] as $bad) {
    exact_registry_check($registry->resolve('anex_xml', $bad, 'preview') === null, 'malformed ID fails closed');
}

$normalized = anytour_anex_normalize_prices(['prices' => [[
    'id' => 'fixture-online-price-39527', 'hotelKey' => 39527, 'hotel' => 'Fixture Hotel',
    'checkIn' => '20260914', 'checkOut' => '20260921', 'nights' => 7,
    'adult' => 2, 'child' => 0, 'packetType' => 0, 'price' => '1000.00',
    'currency' => 'USD', 'grouped' => false, 'bron' => false,
]]], [
    'checkin_begin' => '20260914', 'checkin_end' => '20260916',
    'nights_from' => 7, 'nights_till' => 10, 'adults' => 2, 'children' => 0,
], $previewResolver);
exact_registry_check(
    $normalized['offers'][0]['hotel']['external_id'] === '39527'
        && $normalized['offers'][0]['hotel']['local_id'] === 116676
        && $normalized['offers'][0]['hotel']['mapping_status'] === 'resolved',
    'PRICES.hotelKey resolves to AnyTour catalog ID'
);

$directory = sys_get_temp_dir() . '/anytour-anex-exact-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) throw new RuntimeException('fixture directory unavailable');
try {
    $sourceManifest = json_decode(file_get_contents(__DIR__ . '/../app/integrations/data/anex-xml-exact-registry.json'), true, 16, JSON_THROW_ON_ERROR);
    $csv = "anex_xml_id,catalog_hotel_id\n77,99\n";
    file_put_contents($directory . '/anex-xml-exact-identities.csv', $csv);
    $sourceManifest['mapping_sha256'] = hash('sha256', $csv);
    $sourceManifest['mapping_count'] = 1;
    $sourceManifest['strict_source_count'] = 2;
    $sourceManifest['deferred_short_name_count'] = 1;
    $manifestPath = $directory . '/registry.json';
    file_put_contents($manifestPath, json_encode($sourceManifest, JSON_THROW_ON_ERROR));
    $fixture = AnyTourAnexXmlExactRegistry::fromFile($manifestPath);
    exact_registry_check($fixture->resolve('anex_xml', '77', 'preview') === 99, 'valid compact fixture');
    exact_registry_check($fixture->resolve('anex_online', '77', 'preview') === 99, 'fixture shares ANEX hotel ID');

    file_put_contents($directory . '/anex-xml-exact-identities.csv', $csv . "78,100\n");
    try {
        AnyTourAnexXmlExactRegistry::fromFile($manifestPath);
        throw new RuntimeException('FAILED: digest mismatch accepted');
    } catch (UnexpectedValueException $e) {
        exact_registry_check($e->getMessage() === 'anex_xml_exact_registry_invalid', 'digest mismatch rejected');
    }
} finally {
    @unlink($directory . '/registry.json');
    @unlink($directory . '/anex-xml-exact-identities.csv');
    @rmdir($directory);
}

echo 'ANEX XML exact registry: ' . $checks . " checks passed\n";
