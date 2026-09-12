<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-geography.php';

$checks = 0;
function geography_check(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) {
        throw new RuntimeException('geography_check_'.$checks);
    }
}

$rawOnly = AnyTourThreeProviderGeography::fromEvidence(
    'anex',
    [
        'region' => ['id' => '007', 'label' => ' Antalya '],
        'resort' => ['id' => '42', 'label' => 'Kemer'],
        'subregion' => null,
    ],
    null,
    false
);
geography_check($rawOnly['schema_version'] === 1);
geography_check($rawOnly['provider'] === 'anex');
geography_check($rawOnly['raw_supplier_geography_provided'] === true);
geography_check($rawOnly['raw_supplier_geography']['region'] === ['id' => '007', 'label' => 'Antalya']);
geography_check($rawOnly['raw_supplier_geography']['resort'] === ['id' => '42', 'label' => 'Kemer']);
geography_check($rawOnly['raw_supplier_geography']['subregion'] === null);
geography_check($rawOnly['canonical_geography']['region'] === ['status' => 'unknown', 'value' => null, 'source' => null]);
geography_check($rawOnly['canonical_geography']['resort']['status'] === 'unknown');
geography_check($rawOnly['canonical_geography']['subregion']['status'] === 'unknown');
geography_check($rawOnly['filter_semantics']['status'] === 'unknown');
foreach (['region', 'resort', 'subregion'] as $level) {
    geography_check($rawOnly['filter_semantics']['upstream_search_filter'][$level] === [
        'status' => 'unknown',
        'allowed' => false,
        'requires_verified_provider_mapping' => true,
    ]);
}
geography_check($rawOnly['provider_specific_mapping_required'] === true);
geography_check($rawOnly['supplier_geography_equivalence_verified'] === false);
geography_check($rawOnly['raw_supplier_numeric_id_universal'] === false);
geography_check($rawOnly['cross_provider_equivalence_verified'] === false);
geography_check($rawOnly['identity_proof_from_geography'] === false);
geography_check($rawOnly['current_adapter_audit']['generic_region_resort_subregion_forwarding'] === 'not_implemented');

$accepted = AnyTourThreeProviderGeography::fromEvidence(
    'andromeda',
    [
        'region' => ['id' => '17', 'label' => 'Hurghada'],
        'resort' => ['id' => null, 'label' => 'Makadi Bay'],
        'subregion' => ['id' => '0009', 'label' => null],
    ],
    ['region' => 'Hurghada', 'resort' => 'Makadi Bay', 'subregion' => null],
    true
);
geography_check($accepted['raw_supplier_geography']['subregion']['id'] === '0009');
geography_check($accepted['canonical_geography']['region'] === [
    'status' => 'verified', 'value' => 'Hurghada', 'source' => 'current_local_identity'
]);
geography_check($accepted['canonical_geography']['resort'] === [
    'status' => 'verified', 'value' => 'Makadi Bay', 'source' => 'current_local_identity'
]);
geography_check($accepted['canonical_geography']['subregion'] === [
    'status' => 'unknown', 'value' => null, 'source' => null
]);
geography_check($accepted['current_local_identity'] === true);
geography_check($accepted['current_adapter_audit']['generic_region_resort_subregion_forwarding'] === 'not_verified');
geography_check($accepted['raw_supplier_numeric_id_universal'] === false);
geography_check($accepted['identity_proof_from_geography'] === false);

$localPartial = AnyTourThreeProviderGeography::fromEvidence(
    'tourvisor', null, ['region' => null, 'resort' => 'Side', 'subregion' => null], true
);
geography_check($localPartial['raw_supplier_geography_provided'] === false);
geography_check($localPartial['raw_supplier_geography'] === [
    'region' => null, 'resort' => null, 'subregion' => null
]);
geography_check($localPartial['canonical_geography']['region']['status'] === 'unknown');
geography_check($localPartial['canonical_geography']['resort']['status'] === 'verified');
geography_check($localPartial['canonical_geography']['resort']['value'] === 'Side');
geography_check($localPartial['canonical_geography']['subregion']['status'] === 'unknown');
geography_check($localPartial['filter_semantics']['upstream_search_filter']['resort']['allowed'] === false);

foreach (['tourvisor', 'anex', 'andromeda'] as $provider) {
    $fact = AnyTourThreeProviderGeography::fromEvidence(
        $provider,
        [
            'region' => ['id' => '77', 'label' => 'Region'],
            'resort' => null,
            'subregion' => null,
        ],
        null,
        false
    );
    geography_check($fact['provider'] === $provider);
    geography_check($fact['raw_supplier_geography']['region']['id'] === '77');
    geography_check(is_string($fact['raw_supplier_geography']['region']['id']));
    geography_check($fact['canonical_geography']['region']['status'] === 'unknown');
    geography_check($fact['filter_semantics']['upstream_search_filter']['region']['status'] === 'unknown');
    geography_check($fact['filter_semantics']['upstream_search_filter']['region']['allowed'] === false);
    geography_check($fact['provider_specific_mapping_required'] === true);
    geography_check($fact['cross_provider_equivalence_verified'] === false);
    geography_check($fact['identity_proof_from_geography'] === false);
}

$bad = [
    function () { AnyTourThreeProviderGeography::fromEvidence('samo', null, null, false); },
    function () { AnyTourThreeProviderGeography::fromEvidence('anex', null, ['region' => 'A', 'resort' => null, 'subregion' => null], false); },
    function () { AnyTourThreeProviderGeography::fromEvidence('anex', ['region' => null, 'resort' => null], null, false); },
    function () { AnyTourThreeProviderGeography::fromEvidence('anex', ['region' => ['label' => 'A', 'id' => '1'], 'resort' => null, 'subregion' => null], null, false); },
    function () { AnyTourThreeProviderGeography::fromEvidence('anex', ['region' => ['id' => null, 'label' => null], 'resort' => null, 'subregion' => null], null, false); },
    function () { AnyTourThreeProviderGeography::fromEvidence('anex', ['region' => ['id' => 7, 'label' => 'A'], 'resort' => null, 'subregion' => null], null, false); },
    function () { AnyTourThreeProviderGeography::fromEvidence('anex', ['region' => ['id' => '7', 'label' => "A\nB"], 'resort' => null, 'subregion' => null], null, false); },
    function () { AnyTourThreeProviderGeography::fromEvidence('anex', ['region' => ['id' => str_repeat('7', 65), 'label' => 'A'], 'resort' => null, 'subregion' => null], null, false); },
    function () { AnyTourThreeProviderGeography::fromEvidence('anex', ['region' => ['id' => '7', 'label' => str_repeat('x', 161)], 'resort' => null, 'subregion' => null], null, false); },
    function () { AnyTourThreeProviderGeography::fromEvidence('tourvisor', null, ['region' => 7, 'resort' => null, 'subregion' => null], true); },
    function () { AnyTourThreeProviderGeography::fromEvidence('tourvisor', null, ['region' => '', 'resort' => null, 'subregion' => null], true); },
    function () { AnyTourThreeProviderGeography::fromEvidence('tourvisor', null, ['region' => "A\tB", 'resort' => null, 'subregion' => null], true); },
    function () { AnyTourThreeProviderGeography::fromEvidence('tourvisor', null, ['region' => 'A', 'resort' => null, 'subregion' => null, 'extra' => null], true); },
];
foreach ($bad as $case) {
    try {
        $case();
        geography_check(false);
    } catch (InvalidArgumentException $e) {
        geography_check(true);
    }
}

echo 'Three-provider geography: '.$checks." checks passed; supplier/network/DB/mapping=0.\n";
