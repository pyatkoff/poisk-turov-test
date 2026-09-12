<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-search-coverage.php';

$checks = 0;
function sc_check(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException('sc_check_'.$checks);
}

foreach (['tourvisor', 'anex', 'andromeda'] as $provider) {
    $unknown = AnyTourThreeProviderSearchCoverage::fromEvidence($provider, null);
    sc_check($unknown['schema_version'] === 1);
    sc_check($unknown['provider'] === $provider);
    sc_check($unknown['coverage_state'] === 'unknown');
    sc_check($unknown['counts_exhaustive'] === false);
    sc_check($unknown['observed_count'] === null);
    sc_check($unknown['observation_scope'] === 'unknown');
    sc_check($unknown['observation_usable'] === false);
    sc_check($unknown['cross_provider_count_comparability_verified'] === false);
    sc_check($unknown['hotel_identity_proof_from_coverage'] === false);
    sc_check($unknown['package_identity_proof_from_coverage'] === false);
    sc_check($unknown['price_equivalence_proof_from_coverage'] === false);
}

$anex = AnyTourThreeProviderSearchCoverage::fromEvidence('anex', [
    'price_page' => 1,
    'received_rows' => 300,
]);
sc_check($anex['coverage_state'] === 'bounded');
sc_check($anex['counts_exhaustive'] === false);
sc_check($anex['observed_count'] === 300);
sc_check($anex['page'] === 1);
sc_check($anex['pages_count'] === null);
sc_check($anex['loaded_pages'] === [1]);
sc_check($anex['completion_method'] === 'pricepage_1_without_total_pagination_proof');
sc_check($anex['observation_scope'] === 'bounded_subset');
sc_check($anex['observation_usable'] === true);
sc_check($anex['current_source_audit']['current_behavior'] === 'PRICEPAGE=1');
sc_check($anex['current_source_audit']['exhaustion_proof'] === 'not_implemented_in_current_search_source');

$anexEmpty = AnyTourThreeProviderSearchCoverage::fromEvidence('anex', [
    'price_page' => 1,
    'received_rows' => 0,
]);
sc_check($anexEmpty['coverage_state'] === 'bounded');
sc_check($anexEmpty['observed_count'] === 0);
sc_check($anexEmpty['counts_exhaustive'] === false);

$andromedaPartial = AnyTourThreeProviderSearchCoverage::fromEvidence('andromeda', [
    'page' => 1,
    'pages_count' => 3,
    'loaded_pages' => [1],
    'received_offers' => 50,
    'same_search_context' => true,
]);
sc_check($andromedaPartial['coverage_state'] === 'partial');
sc_check($andromedaPartial['counts_exhaustive'] === false);
sc_check($andromedaPartial['observed_count'] === 50);
sc_check($andromedaPartial['page'] === 1);
sc_check($andromedaPartial['pages_count'] === 3);
sc_check($andromedaPartial['loaded_pages'] === [1]);
sc_check($andromedaPartial['completion_method'] === 'advertised_pages_not_fully_retained');
sc_check($andromedaPartial['observation_scope'] === 'bounded_subset');
sc_check($andromedaPartial['current_source_audit']['current_behavior'] === 'page_and_pages_count_exposed');

$andromedaComplete = AnyTourThreeProviderSearchCoverage::fromEvidence('andromeda', [
    'page' => 3,
    'pages_count' => 3,
    'loaded_pages' => [3, 1, 2],
    'received_offers' => 109,
    'same_search_context' => true,
]);
sc_check($andromedaComplete['coverage_state'] === 'complete');
sc_check($andromedaComplete['counts_exhaustive'] === true);
sc_check($andromedaComplete['observed_count'] === 109);
sc_check($andromedaComplete['loaded_pages'] === [1, 2, 3]);
sc_check($andromedaComplete['completion_method'] === 'all_advertised_pages_retained');
sc_check($andromedaComplete['observation_scope'] === 'exhaustive');
sc_check($andromedaComplete['observation_usable'] === true);
sc_check($andromedaComplete['cross_provider_count_comparability_verified'] === false);

$andromedaOnePage = AnyTourThreeProviderSearchCoverage::fromEvidence('andromeda', [
    'page' => 1,
    'pages_count' => 1,
    'loaded_pages' => [1],
    'received_offers' => 8,
    'same_search_context' => true,
]);
sc_check($andromedaOnePage['coverage_state'] === 'complete');
sc_check($andromedaOnePage['counts_exhaustive'] === true);

$tvBounded = AnyTourThreeProviderSearchCoverage::fromEvidence('tourvisor', [
    'search_status' => 'complete',
    'results_fetch_limit' => 100,
    'continuation_rounds' => 0,
    'no_growth_after_continue' => false,
    'unique_groups' => 50,
]);
sc_check($tvBounded['coverage_state'] === 'bounded');
sc_check($tvBounded['counts_exhaustive'] === false);
sc_check($tvBounded['observed_count'] === 50);
sc_check($tvBounded['completion_method'] === 'search_complete_without_continuation_exhaustion');
sc_check($tvBounded['observation_scope'] === 'bounded_subset');
sc_check($tvBounded['current_source_audit']['current_behavior'] === 'status_complete_does_not_prove_result_exhaustion');

$tvPartial = AnyTourThreeProviderSearchCoverage::fromEvidence('tourvisor', [
    'search_status' => 'incomplete',
    'results_fetch_limit' => 100,
    'continuation_rounds' => 0,
    'no_growth_after_continue' => false,
    'unique_groups' => 19,
]);
sc_check($tvPartial['coverage_state'] === 'partial');
sc_check($tvPartial['counts_exhaustive'] === false);
sc_check($tvPartial['completion_method'] === 'supplier_search_not_complete');

$tvComplete = AnyTourThreeProviderSearchCoverage::fromEvidence('tourvisor', [
    'search_status' => 'complete',
    'results_fetch_limit' => 10000,
    'continuation_rounds' => 5,
    'no_growth_after_continue' => true,
    'unique_groups' => 157,
]);
sc_check($tvComplete['coverage_state'] === 'complete');
sc_check($tvComplete['counts_exhaustive'] === true);
sc_check($tvComplete['observed_count'] === 157);
sc_check($tvComplete['completion_method'] === 'search_complete_and_continue_no_growth');
sc_check($tvComplete['observation_scope'] === 'exhaustive');
sc_check($tvComplete['cross_provider_count_comparability_verified'] === false);
sc_check($tvComplete['hotel_identity_proof_from_coverage'] === false);
sc_check($tvComplete['package_identity_proof_from_coverage'] === false);
sc_check($tvComplete['price_equivalence_proof_from_coverage'] === false);

$bad = [
    function () { AnyTourThreeProviderSearchCoverage::fromEvidence('samo', null); },
    function () { AnyTourThreeProviderSearchCoverage::fromEvidence('anex', ['price_page' => 2, 'received_rows' => 1]); },
    function () { AnyTourThreeProviderSearchCoverage::fromEvidence('anex', ['price_page' => 1, 'received_rows' => -1]); },
    function () { AnyTourThreeProviderSearchCoverage::fromEvidence('anex', ['price_page' => 1, 'received_rows' => '300']); },
    function () { AnyTourThreeProviderSearchCoverage::fromEvidence('andromeda', ['page'=>1,'pages_count'=>3,'loaded_pages'=>[1],'received_offers'=>50,'same_search_context'=>false]); },
    function () { AnyTourThreeProviderSearchCoverage::fromEvidence('andromeda', ['page'=>4,'pages_count'=>3,'loaded_pages'=>[1,2,3],'received_offers'=>50,'same_search_context'=>true]); },
    function () { AnyTourThreeProviderSearchCoverage::fromEvidence('andromeda', ['page'=>2,'pages_count'=>3,'loaded_pages'=>[1],'received_offers'=>50,'same_search_context'=>true]); },
    function () { AnyTourThreeProviderSearchCoverage::fromEvidence('andromeda', ['page'=>1,'pages_count'=>3,'loaded_pages'=>[1,1],'received_offers'=>50,'same_search_context'=>true]); },
    function () { AnyTourThreeProviderSearchCoverage::fromEvidence('andromeda', ['page'=>1,'pages_count'=>3,'loaded_pages'=>[1,4],'received_offers'=>50,'same_search_context'=>true]); },
    function () { AnyTourThreeProviderSearchCoverage::fromEvidence('andromeda', ['page'=>1,'pages_count'=>3,'loaded_pages'=>'1','received_offers'=>50,'same_search_context'=>true]); },
    function () { AnyTourThreeProviderSearchCoverage::fromEvidence('andromeda', ['page'=>1,'pages_count'=>0,'loaded_pages'=>[1],'received_offers'=>50,'same_search_context'=>true]); },
    function () { AnyTourThreeProviderSearchCoverage::fromEvidence('tourvisor', ['search_status'=>'done','results_fetch_limit'=>100,'continuation_rounds'=>0,'no_growth_after_continue'=>false,'unique_groups'=>1]); },
    function () { AnyTourThreeProviderSearchCoverage::fromEvidence('tourvisor', ['search_status'=>'complete','results_fetch_limit'=>0,'continuation_rounds'=>0,'no_growth_after_continue'=>false,'unique_groups'=>1]); },
    function () { AnyTourThreeProviderSearchCoverage::fromEvidence('tourvisor', ['search_status'=>'complete','results_fetch_limit'=>10001,'continuation_rounds'=>0,'no_growth_after_continue'=>false,'unique_groups'=>1]); },
    function () { AnyTourThreeProviderSearchCoverage::fromEvidence('tourvisor', ['search_status'=>'complete','results_fetch_limit'=>100,'continuation_rounds'=>-1,'no_growth_after_continue'=>false,'unique_groups'=>1]); },
    function () { AnyTourThreeProviderSearchCoverage::fromEvidence('tourvisor', ['search_status'=>'complete','results_fetch_limit'=>100,'continuation_rounds'=>0,'no_growth_after_continue'=>true,'unique_groups'=>1]); },
    function () { AnyTourThreeProviderSearchCoverage::fromEvidence('tourvisor', ['search_status'=>'complete','results_fetch_limit'=>100,'continuation_rounds'=>1,'no_growth_after_continue'=>1,'unique_groups'=>1]); },
];
foreach ($bad as $case) {
    try {
        $case();
        sc_check(false);
    } catch (InvalidArgumentException $e) {
        sc_check(true);
    }
}

echo 'Three-provider search coverage: '.$checks." checks passed; supplier/network/DB/mapping=0.\n";
