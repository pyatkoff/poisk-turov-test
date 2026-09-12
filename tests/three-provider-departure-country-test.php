<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-departure-country.php';

$checks = 0;
function dc_check(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException('dc_check_'.$checks);
}

$local = [
    'departure' => ['id' => 1, 'name' => 'Москва'],
    'country' => ['id' => 4, 'name' => 'Турция'],
];
$mapping = [
    'departure_id' => '0017',
    'country_id' => '0005',
    'method' => 'exact_unique_dictionary_name',
    'local_names_authoritative' => true,
    'exact_unique_match' => true,
    'country_scoped_by_departure' => true,
];

$anex = AnyTourThreeProviderDepartureCountry::fromEvidence('anex', $local, $mapping);
dc_check($anex['schema_version'] === 1);
dc_check($anex['provider'] === 'anex');
dc_check($anex['canonical_local']['departure'] === [
    'status' => 'verified', 'id' => 1, 'name' => 'Москва', 'source' => 'current_local_catalog'
]);
dc_check($anex['canonical_local']['country'] === [
    'status' => 'verified', 'id' => 4, 'name' => 'Турция', 'source' => 'current_local_catalog'
]);
dc_check($anex['supplier_mapping']['departure_id'] === '0017');
dc_check($anex['supplier_mapping']['country_id'] === '0005');
dc_check(is_string($anex['supplier_mapping']['departure_id']));
dc_check(is_string($anex['supplier_mapping']['country_id']));
dc_check($anex['filter_semantics']['departure'] === ['status' => 'verified', 'allowed' => true, 'supplier_id' => '0017']);
dc_check($anex['filter_semantics']['country'] === ['status' => 'verified', 'allowed' => true, 'supplier_id' => '0005']);
dc_check($anex['provider_specific_mapping_required'] === true);
dc_check($anex['local_numeric_id_is_supplier_id'] === false);
dc_check($anex['raw_supplier_numeric_id_universal'] === false);
dc_check($anex['cross_provider_equivalence_verified'] === false);
dc_check($anex['hotel_identity_proof_from_departure_country'] === false);
dc_check($anex['current_adapter_audit']['departure_mapping'] === 'verified_exact_unique_dictionary_name');
dc_check($anex['current_adapter_audit']['country_mapping'] === 'verified_departure_scoped_exact_unique_dictionary_name');
dc_check($anex['current_adapter_audit']['local_form_id_forwarded_as_supplier_id'] === false);

$anexUnresolved = AnyTourThreeProviderDepartureCountry::fromEvidence('anex', $local, null);
dc_check($anexUnresolved['canonical_local']['departure']['status'] === 'verified');
dc_check($anexUnresolved['canonical_local']['country']['status'] === 'verified');
dc_check($anexUnresolved['supplier_mapping'] === null);
dc_check($anexUnresolved['filter_semantics']['departure'] === ['status' => 'unknown', 'allowed' => false, 'supplier_id' => null]);
dc_check($anexUnresolved['filter_semantics']['country'] === ['status' => 'unknown', 'allowed' => false, 'supplier_id' => null]);
dc_check($anexUnresolved['local_numeric_id_is_supplier_id'] === false);

foreach (['tourvisor', 'andromeda'] as $provider) {
    $fact = AnyTourThreeProviderDepartureCountry::fromEvidence($provider, $local, null);
    dc_check($fact['provider'] === $provider);
    dc_check($fact['canonical_local']['departure']['id'] === 1);
    dc_check($fact['canonical_local']['country']['id'] === 4);
    dc_check($fact['filter_semantics']['departure']['status'] === 'unknown');
    dc_check($fact['filter_semantics']['departure']['allowed'] === false);
    dc_check($fact['filter_semantics']['country']['status'] === 'unknown');
    dc_check($fact['filter_semantics']['country']['allowed'] === false);
    dc_check($fact['current_adapter_audit']['departure_mapping'] === 'not_verified_in_this_boundary');
    dc_check($fact['current_adapter_audit']['country_mapping'] === 'not_verified_in_this_boundary');
    dc_check($fact['current_adapter_audit']['local_form_id_forwarded_as_supplier_id'] === false);
    dc_check($fact['raw_supplier_numeric_id_universal'] === false);
    dc_check($fact['cross_provider_equivalence_verified'] === false);
}

$unknown = AnyTourThreeProviderDepartureCountry::fromEvidence('tourvisor', null, null);
dc_check($unknown['canonical_local']['departure'] === ['status' => 'unknown', 'id' => null, 'name' => null, 'source' => null]);
dc_check($unknown['canonical_local']['country'] === ['status' => 'unknown', 'id' => null, 'name' => null, 'source' => null]);
dc_check($unknown['filter_semantics']['departure']['allowed'] === false);
dc_check($unknown['filter_semantics']['country']['allowed'] === false);

$trimmed = AnyTourThreeProviderDepartureCountry::fromEvidence('anex', [
    'departure' => ['id' => 2, 'name' => ' Санкт-Петербург '],
    'country' => ['id' => 1, 'name' => ' Египет '],
], [
    'departure_id' => ' 77 ', 'country_id' => ' 1 ', 'method' => 'exact_unique_dictionary_name',
    'local_names_authoritative' => true, 'exact_unique_match' => true, 'country_scoped_by_departure' => true,
]);
dc_check($trimmed['canonical_local']['departure']['name'] === 'Санкт-Петербург');
dc_check($trimmed['canonical_local']['country']['name'] === 'Египет');
dc_check($trimmed['supplier_mapping']['departure_id'] === '77');
dc_check($trimmed['supplier_mapping']['country_id'] === '1');

$bad = [
    function () { AnyTourThreeProviderDepartureCountry::fromEvidence('samo', null, null); },
    function () use ($mapping) { AnyTourThreeProviderDepartureCountry::fromEvidence('anex', null, $mapping); },
    function () use ($local, $mapping) { AnyTourThreeProviderDepartureCountry::fromEvidence('tourvisor', $local, $mapping); },
    function () use ($local, $mapping) { AnyTourThreeProviderDepartureCountry::fromEvidence('andromeda', $local, $mapping); },
    function () { AnyTourThreeProviderDepartureCountry::fromEvidence('anex', ['country' => ['id' => 4, 'name' => 'Турция'], 'departure' => ['id' => 1, 'name' => 'Москва']], null); },
    function () { AnyTourThreeProviderDepartureCountry::fromEvidence('anex', ['departure' => ['id' => 0, 'name' => 'Москва'], 'country' => ['id' => 4, 'name' => 'Турция']], null); },
    function () { AnyTourThreeProviderDepartureCountry::fromEvidence('anex', ['departure' => ['id' => 1, 'name' => ''], 'country' => ['id' => 4, 'name' => 'Турция']], null); },
    function () { AnyTourThreeProviderDepartureCountry::fromEvidence('anex', ['departure' => ['id' => 1, 'name' => "Мос\nква"], 'country' => ['id' => 4, 'name' => 'Турция']], null); },
    function () use ($local) { AnyTourThreeProviderDepartureCountry::fromEvidence('anex', $local, ['departure_id' => '17', 'country_id' => '5']); },
    function () use ($local, $mapping) { $x=$mapping; $x['departure_id']=17; AnyTourThreeProviderDepartureCountry::fromEvidence('anex', $local, $x); },
    function () use ($local, $mapping) { $x=$mapping; $x['country_id']=''; AnyTourThreeProviderDepartureCountry::fromEvidence('anex', $local, $x); },
    function () use ($local, $mapping) { $x=$mapping; $x['method']='numeric_id'; AnyTourThreeProviderDepartureCountry::fromEvidence('anex', $local, $x); },
    function () use ($local, $mapping) { $x=$mapping; $x['local_names_authoritative']=false; AnyTourThreeProviderDepartureCountry::fromEvidence('anex', $local, $x); },
    function () use ($local, $mapping) { $x=$mapping; $x['exact_unique_match']=false; AnyTourThreeProviderDepartureCountry::fromEvidence('anex', $local, $x); },
    function () use ($local, $mapping) { $x=$mapping; $x['country_scoped_by_departure']=false; AnyTourThreeProviderDepartureCountry::fromEvidence('anex', $local, $x); },
    function () use ($local, $mapping) { $x=$mapping; $x['departure_id']=str_repeat('7',65); AnyTourThreeProviderDepartureCountry::fromEvidence('anex', $local, $x); },
    function () use ($local, $mapping) { $x=$mapping; $x['country_id']="5\t6"; AnyTourThreeProviderDepartureCountry::fromEvidence('anex', $local, $x); },
];
foreach ($bad as $case) {
    try {
        $case();
        dc_check(false);
    } catch (InvalidArgumentException $e) {
        dc_check(true);
    }
}

echo 'Three-provider departure/country: '.$checks." checks passed; supplier/network/DB/mapping=0.\n";
