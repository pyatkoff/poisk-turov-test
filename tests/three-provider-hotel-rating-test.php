<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-hotel-rating.php';

$checks = 0;
function rating_check(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) {
        throw new RuntimeException('rating_check_'.$checks);
    }
}

$rawOnly = AnyTourThreeProviderHotelRating::fromEvidence('anex', ' 4.7 ', null, false);
rating_check($rawOnly['schema_version'] === 1);
rating_check($rawOnly['provider'] === 'anex');
rating_check($rawOnly['raw_supplier_rating'] === '4.7');
rating_check($rawOnly['raw_supplier_rating_observed'] === true);
rating_check($rawOnly['canonical_rating'] === ['status' => 'unknown', 'value' => null, 'source' => null]);
rating_check($rawOnly['filter_semantics']['status'] === 'local_only');
rating_check($rawOnly['filter_semantics']['upstream_search_filter'] === ['status' => 'local_only', 'allowed' => false]);
rating_check($rawOnly['current_local_identity'] === false);
rating_check($rawOnly['supplier_rating_equivalence_verified'] === false);
rating_check($rawOnly['raw_supplier_numeric_value_universal'] === false);
rating_check($rawOnly['cross_provider_equivalence_verified'] === false);
rating_check($rawOnly['identity_proof_from_rating'] === false);

$foreignScale = AnyTourThreeProviderHotelRating::fromEvidence('andromeda', '9.2/10', null, false);
rating_check($foreignScale['raw_supplier_rating'] === '9.2/10');
rating_check($foreignScale['canonical_rating']['status'] === 'unknown');
rating_check($foreignScale['filter_semantics']['upstream_search_filter']['allowed'] === false);
rating_check($foreignScale['identity_proof_from_rating'] === false);

$accepted = AnyTourThreeProviderHotelRating::fromEvidence('andromeda', 'excellent', 4.6, true);
rating_check($accepted['raw_supplier_rating'] === 'excellent');
rating_check($accepted['canonical_rating'] === [
    'status' => 'verified',
    'value' => 4.6,
    'source' => 'current_local_identity',
]);
rating_check($accepted['current_local_identity'] === true);
rating_check($accepted['supplier_rating_equivalence_verified'] === false);
rating_check($accepted['cross_provider_equivalence_verified'] === false);
rating_check($accepted['identity_proof_from_rating'] === false);
rating_check($accepted['filter_semantics']['status'] === 'local_only');

$localOnly = AnyTourThreeProviderHotelRating::fromEvidence('tourvisor', null, 5.0, true);
rating_check($localOnly['raw_supplier_rating'] === null);
rating_check($localOnly['raw_supplier_rating_observed'] === false);
rating_check($localOnly['canonical_rating']['status'] === 'verified');
rating_check($localOnly['canonical_rating']['value'] === 5.0);
rating_check($localOnly['canonical_rating']['source'] === 'current_local_identity');
rating_check($localOnly['filter_semantics']['upstream_search_filter']['allowed'] === false);

$identityWithoutRating = AnyTourThreeProviderHotelRating::fromEvidence('tourvisor', null, null, true);
rating_check($identityWithoutRating['canonical_rating']['status'] === 'unknown');
rating_check($identityWithoutRating['canonical_rating']['value'] === null);
rating_check($identityWithoutRating['current_local_identity'] === true);

$blank = AnyTourThreeProviderHotelRating::fromEvidence('anex', '   ', null, false);
rating_check($blank['raw_supplier_rating'] === null);
rating_check($blank['raw_supplier_rating_observed'] === false);

foreach (['tourvisor', 'anex', 'andromeda'] as $provider) {
    $fact = AnyTourThreeProviderHotelRating::fromEvidence($provider, 'supplier-score-A', null, false);
    rating_check($fact['provider'] === $provider);
    rating_check($fact['raw_supplier_rating'] === 'supplier-score-A');
    rating_check($fact['canonical_rating']['status'] === 'unknown');
    rating_check($fact['filter_semantics']['status'] === 'local_only');
    rating_check($fact['filter_semantics']['upstream_search_filter']['allowed'] === false);
    rating_check($fact['supplier_rating_equivalence_verified'] === false);
    rating_check($fact['raw_supplier_numeric_value_universal'] === false);
    rating_check($fact['cross_provider_equivalence_verified'] === false);
    rating_check($fact['identity_proof_from_rating'] === false);
}

$bad = [
    function () { AnyTourThreeProviderHotelRating::fromEvidence('samo', '4.8', null, false); },
    function () { AnyTourThreeProviderHotelRating::fromEvidence('anex', '4.8', 4.8, false); },
    function () { AnyTourThreeProviderHotelRating::fromEvidence('anex', '4.8', 0.0, true); },
    function () { AnyTourThreeProviderHotelRating::fromEvidence('anex', '4.8', -1.0, true); },
    function () { AnyTourThreeProviderHotelRating::fromEvidence('anex', '4.8', 5.1, true); },
    function () { AnyTourThreeProviderHotelRating::fromEvidence('anex', '4.8', INF, true); },
    function () { AnyTourThreeProviderHotelRating::fromEvidence('andromeda', "4.8\nscore", null, false); },
    function () { AnyTourThreeProviderHotelRating::fromEvidence('tourvisor', str_repeat('x', 33), null, false); },
];
foreach ($bad as $case) {
    try {
        $case();
        rating_check(false);
    } catch (InvalidArgumentException $e) {
        rating_check(true);
    }
}

echo 'Three-provider hotel rating: '.$checks." checks passed; supplier/network/DB/mapping=0.\n";
