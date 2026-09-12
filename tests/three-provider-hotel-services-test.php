<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-hotel-services.php';

$checks = 0;
function services_check(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) {
        throw new RuntimeException('services_check_'.$checks);
    }
}

$rawOnly = AnyTourThreeProviderHotelServices::fromEvidence(
    'anex',
    ['  Wi-Fi  ', 'Pool', 'Wi-Fi', 'serviceCode:17'],
    null,
    false
);
services_check($rawOnly['schema_version'] === 1);
services_check($rawOnly['provider'] === 'anex');
services_check($rawOnly['raw_supplier_services'] === ['Wi-Fi', 'Pool', 'serviceCode:17']);
services_check($rawOnly['raw_supplier_services_provided'] === true);
services_check($rawOnly['canonical_services'] === ['status' => 'unknown', 'values' => null, 'source' => null]);
services_check($rawOnly['filter_semantics']['status'] === 'local_only');
services_check($rawOnly['filter_semantics']['upstream_search_filter'] === ['status' => 'local_only', 'allowed' => false]);
services_check($rawOnly['current_local_identity'] === false);
services_check($rawOnly['supplier_service_equivalence_verified'] === false);
services_check($rawOnly['raw_supplier_numeric_id_universal'] === false);
services_check($rawOnly['cross_provider_equivalence_verified'] === false);
services_check($rawOnly['identity_proof_from_services'] === false);

$accepted = AnyTourThreeProviderHotelServices::fromEvidence(
    'andromeda',
    ['SPA', '77'],
    ['Beach', 'Wi-Fi', 'Beach'],
    true
);
services_check($accepted['raw_supplier_services'] === ['SPA', '77']);
services_check($accepted['canonical_services'] === [
    'status' => 'verified',
    'values' => ['Beach', 'Wi-Fi'],
    'source' => 'current_local_identity',
]);
services_check($accepted['current_local_identity'] === true);
services_check($accepted['supplier_service_equivalence_verified'] === false);
services_check($accepted['raw_supplier_numeric_id_universal'] === false);
services_check($accepted['cross_provider_equivalence_verified'] === false);
services_check($accepted['identity_proof_from_services'] === false);
services_check($accepted['filter_semantics']['upstream_search_filter']['allowed'] === false);

$knownEmpty = AnyTourThreeProviderHotelServices::fromEvidence('tourvisor', null, [], true);
services_check($knownEmpty['raw_supplier_services'] === []);
services_check($knownEmpty['raw_supplier_services_provided'] === false);
services_check($knownEmpty['canonical_services']['status'] === 'verified');
services_check($knownEmpty['canonical_services']['values'] === []);
services_check($knownEmpty['canonical_services']['source'] === 'current_local_identity');

$identityUnknown = AnyTourThreeProviderHotelServices::fromEvidence('tourvisor', [], null, true);
services_check($identityUnknown['raw_supplier_services'] === []);
services_check($identityUnknown['raw_supplier_services_provided'] === true);
services_check($identityUnknown['canonical_services']['status'] === 'unknown');
services_check($identityUnknown['canonical_services']['values'] === null);
services_check($identityUnknown['current_local_identity'] === true);

foreach (['tourvisor', 'anex', 'andromeda'] as $provider) {
    $fact = AnyTourThreeProviderHotelServices::fromEvidence($provider, ['101', 'Kids club'], null, false);
    services_check($fact['provider'] === $provider);
    services_check($fact['raw_supplier_services'] === ['101', 'Kids club']);
    services_check($fact['canonical_services']['status'] === 'unknown');
    services_check($fact['filter_semantics']['status'] === 'local_only');
    services_check($fact['filter_semantics']['upstream_search_filter']['allowed'] === false);
    services_check($fact['supplier_service_equivalence_verified'] === false);
    services_check($fact['raw_supplier_numeric_id_universal'] === false);
    services_check($fact['cross_provider_equivalence_verified'] === false);
    services_check($fact['identity_proof_from_services'] === false);
}

$bad = [
    function () { AnyTourThreeProviderHotelServices::fromEvidence('samo', ['Pool'], null, false); },
    function () { AnyTourThreeProviderHotelServices::fromEvidence('anex', null, ['Pool'], false); },
    function () { AnyTourThreeProviderHotelServices::fromEvidence('anex', [17], null, false); },
    function () { AnyTourThreeProviderHotelServices::fromEvidence('anex', [''], null, false); },
    function () { AnyTourThreeProviderHotelServices::fromEvidence('anex', ["Pool\nSpa"], null, false); },
    function () { AnyTourThreeProviderHotelServices::fromEvidence('anex', [str_repeat('x', 65)], null, false); },
    function () { AnyTourThreeProviderHotelServices::fromEvidence('anex', array_fill(0, 101, 'Pool'), null, false); },
    function () { AnyTourThreeProviderHotelServices::fromEvidence('tourvisor', null, [17], true); },
    function () { AnyTourThreeProviderHotelServices::fromEvidence('tourvisor', null, [''], true); },
    function () { AnyTourThreeProviderHotelServices::fromEvidence('tourvisor', null, ["Pool\tSpa"], true); },
];
foreach ($bad as $case) {
    try {
        $case();
        services_check(false);
    } catch (InvalidArgumentException $e) {
        services_check(true);
    }
}

echo 'Three-provider hotel services: '.$checks." checks passed; supplier/network/DB/mapping=0.\n";
