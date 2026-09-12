<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-hotel-category.php';

$checks = 0;
function category_check(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) {
        throw new RuntimeException('category_check_'.$checks);
    }
}

$rawOnly = AnyTourThreeProviderHotelCategory::fromEvidence('anex', ' 5* ', null, false);
category_check($rawOnly['schema_version'] === 1);
category_check($rawOnly['provider'] === 'anex');
category_check($rawOnly['raw_supplier_label'] === '5*');
category_check($rawOnly['raw_supplier_label_observed'] === true);
category_check($rawOnly['canonical_category'] === ['status' => 'unknown', 'value' => null, 'source' => null]);
category_check($rawOnly['current_local_identity'] === false);
category_check($rawOnly['supplier_label_equivalence_verified'] === false);
category_check($rawOnly['raw_supplier_numeric_id_universal'] === false);
category_check($rawOnly['cross_provider_equivalence_verified'] === false);
category_check($rawOnly['identity_proof_from_category'] === false);

$numericLooking = AnyTourThreeProviderHotelCategory::fromEvidence('andromeda', '5', null, false);
category_check($numericLooking['raw_supplier_label'] === '5');
category_check($numericLooking['canonical_category']['status'] === 'unknown');
category_check($numericLooking['canonical_category']['value'] === null);
category_check($numericLooking['identity_proof_from_category'] === false);

$accepted = AnyTourThreeProviderHotelCategory::fromEvidence('andromeda', '4★', 4, true);
category_check($accepted['raw_supplier_label'] === '4★');
category_check($accepted['canonical_category'] === [
    'status' => 'verified',
    'value' => 4,
    'source' => 'current_local_identity',
]);
category_check($accepted['current_local_identity'] === true);
category_check($accepted['supplier_label_equivalence_verified'] === false);
category_check($accepted['cross_provider_equivalence_verified'] === false);
category_check($accepted['identity_proof_from_category'] === false);

$localOnly = AnyTourThreeProviderHotelCategory::fromEvidence('tourvisor', null, 5, true);
category_check($localOnly['raw_supplier_label'] === null);
category_check($localOnly['raw_supplier_label_observed'] === false);
category_check($localOnly['canonical_category']['status'] === 'verified');
category_check($localOnly['canonical_category']['value'] === 5);
category_check($localOnly['canonical_category']['source'] === 'current_local_identity');

$identityWithoutCategory = AnyTourThreeProviderHotelCategory::fromEvidence('tourvisor', null, null, true);
category_check($identityWithoutCategory['canonical_category']['status'] === 'unknown');
category_check($identityWithoutCategory['canonical_category']['value'] === null);
category_check($identityWithoutCategory['current_local_identity'] === true);

$blank = AnyTourThreeProviderHotelCategory::fromEvidence('anex', '   ', null, false);
category_check($blank['raw_supplier_label'] === null);
category_check($blank['raw_supplier_label_observed'] === false);

foreach (['tourvisor', 'anex', 'andromeda'] as $provider) {
    $fact = AnyTourThreeProviderHotelCategory::fromEvidence($provider, '5 deluxe', null, false);
    category_check($fact['provider'] === $provider);
    category_check($fact['raw_supplier_label'] === '5 deluxe');
    category_check($fact['canonical_category']['status'] === 'unknown');
    category_check($fact['supplier_label_equivalence_verified'] === false);
    category_check($fact['raw_supplier_numeric_id_universal'] === false);
    category_check($fact['cross_provider_equivalence_verified'] === false);
    category_check($fact['identity_proof_from_category'] === false);
}

$bad = [
    function () { AnyTourThreeProviderHotelCategory::fromEvidence('samo', '5', null, false); },
    function () { AnyTourThreeProviderHotelCategory::fromEvidence('anex', '5', 5, false); },
    function () { AnyTourThreeProviderHotelCategory::fromEvidence('anex', '5', 0, true); },
    function () { AnyTourThreeProviderHotelCategory::fromEvidence('anex', '5', 6, true); },
    function () { AnyTourThreeProviderHotelCategory::fromEvidence('andromeda', "5\nstar", null, false); },
    function () { AnyTourThreeProviderHotelCategory::fromEvidence('tourvisor', str_repeat('x', 33), null, false); },
];
foreach ($bad as $case) {
    try {
        $case();
        category_check(false);
    } catch (InvalidArgumentException $e) {
        category_check(true);
    }
}

echo 'Three-provider hotel category: '.$checks." checks passed; supplier/network/DB/mapping=0.\n";
