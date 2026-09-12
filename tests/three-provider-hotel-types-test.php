<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-hotel-types.php';

$checks = 0;
function types_check(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) {
        throw new RuntimeException('types_check_'.$checks);
    }
}

$rawOnly = AnyTourThreeProviderHotelTypes::fromEvidence(
    'anex',
    ['  Family  ', 'Adults only', 'Family', '17'],
    null,
    false
);
types_check($rawOnly['schema_version'] === 1);
types_check($rawOnly['provider'] === 'anex');
types_check($rawOnly['raw_supplier_types'] === ['Family', 'Adults only', '17']);
types_check($rawOnly['raw_supplier_types_provided'] === true);
types_check($rawOnly['canonical_types'] === ['status' => 'unknown', 'values' => null, 'source' => null]);
types_check($rawOnly['filter_semantics']['status'] === 'local_only');
types_check($rawOnly['filter_semantics']['upstream_search_filter'] === ['status' => 'local_only', 'allowed' => false]);
types_check($rawOnly['current_local_identity'] === false);
types_check($rawOnly['supplier_type_equivalence_verified'] === false);
types_check($rawOnly['raw_supplier_numeric_id_universal'] === false);
types_check($rawOnly['cross_provider_equivalence_verified'] === false);
types_check($rawOnly['identity_proof_from_types'] === false);

$accepted = AnyTourThreeProviderHotelTypes::fromEvidence(
    'andromeda',
    ['Resort', '22'],
    ['Beach', 'Family', 'Beach'],
    true
);
types_check($accepted['raw_supplier_types'] === ['Resort', '22']);
types_check($accepted['canonical_types'] === [
    'status' => 'verified',
    'values' => ['Beach', 'Family'],
    'source' => 'current_local_identity',
]);
types_check($accepted['current_local_identity'] === true);
types_check($accepted['supplier_type_equivalence_verified'] === false);
types_check($accepted['raw_supplier_numeric_id_universal'] === false);
types_check($accepted['cross_provider_equivalence_verified'] === false);
types_check($accepted['identity_proof_from_types'] === false);
types_check($accepted['filter_semantics']['upstream_search_filter']['allowed'] === false);

$knownEmpty = AnyTourThreeProviderHotelTypes::fromEvidence('tourvisor', null, [], true);
types_check($knownEmpty['raw_supplier_types'] === []);
types_check($knownEmpty['raw_supplier_types_provided'] === false);
types_check($knownEmpty['canonical_types']['status'] === 'verified');
types_check($knownEmpty['canonical_types']['values'] === []);
types_check($knownEmpty['canonical_types']['source'] === 'current_local_identity');

$identityUnknown = AnyTourThreeProviderHotelTypes::fromEvidence('tourvisor', [], null, true);
types_check($identityUnknown['raw_supplier_types'] === []);
types_check($identityUnknown['raw_supplier_types_provided'] === true);
types_check($identityUnknown['canonical_types']['status'] === 'unknown');
types_check($identityUnknown['canonical_types']['values'] === null);
types_check($identityUnknown['current_local_identity'] === true);

foreach (['tourvisor', 'anex', 'andromeda'] as $provider) {
    $fact = AnyTourThreeProviderHotelTypes::fromEvidence($provider, ['101', 'Boutique'], null, false);
    types_check($fact['provider'] === $provider);
    types_check($fact['raw_supplier_types'] === ['101', 'Boutique']);
    types_check($fact['canonical_types']['status'] === 'unknown');
    types_check($fact['filter_semantics']['status'] === 'local_only');
    types_check($fact['filter_semantics']['upstream_search_filter']['allowed'] === false);
    types_check($fact['supplier_type_equivalence_verified'] === false);
    types_check($fact['raw_supplier_numeric_id_universal'] === false);
    types_check($fact['cross_provider_equivalence_verified'] === false);
    types_check($fact['identity_proof_from_types'] === false);
}

$bad = [
    function () { AnyTourThreeProviderHotelTypes::fromEvidence('samo', ['Beach'], null, false); },
    function () { AnyTourThreeProviderHotelTypes::fromEvidence('anex', null, ['Beach'], false); },
    function () { AnyTourThreeProviderHotelTypes::fromEvidence('anex', [17], null, false); },
    function () { AnyTourThreeProviderHotelTypes::fromEvidence('anex', [''], null, false); },
    function () { AnyTourThreeProviderHotelTypes::fromEvidence('anex', ["Beach\nFamily"], null, false); },
    function () { AnyTourThreeProviderHotelTypes::fromEvidence('anex', [str_repeat('x', 65)], null, false); },
    function () { AnyTourThreeProviderHotelTypes::fromEvidence('anex', array_fill(0, 51, 'Beach'), null, false); },
    function () { AnyTourThreeProviderHotelTypes::fromEvidence('tourvisor', null, [17], true); },
    function () { AnyTourThreeProviderHotelTypes::fromEvidence('tourvisor', null, [''], true); },
    function () { AnyTourThreeProviderHotelTypes::fromEvidence('tourvisor', null, ["Beach\tFamily"], true); },
];
foreach ($bad as $case) {
    try {
        $case();
        types_check(false);
    } catch (InvalidArgumentException $e) {
        types_check(true);
    }
}

echo 'Three-provider hotel types: '.$checks." checks passed; supplier/network/DB/mapping=0.\n";
