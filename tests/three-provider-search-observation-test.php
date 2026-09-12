<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-search-observation.php';

$checks = 0;
function so_check(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException('so_check_'.$checks);
}

function so_base(string $provider): array
{
    $money = [
        'search_price' => [
            'amount' => $provider === 'tourvisor' ? '150824' : '119114',
            'currency' => 'RUB',
            'source' => $provider.'_search',
        ],
        'fuel_charge_reported' => null,
        'additional_prices_reported' => [],
    ];
    if ($provider === 'tourvisor') {
        $money['fuel_charge_reported'] = [
            'amount' => '31710', 'currency' => 'RUB', 'source' => 'tourvisor_fuel',
        ];
    }
    if ($provider === 'anex') {
        $money['additional_prices_reported'] = [[
            'kind' => 'minimum_air_surcharge',
            'amount' => '1200',
            'currency' => 'RUB',
            'source' => 'anex_additional',
        ]];
    }

    if ($provider === 'anex') {
        $coverage = ['price_page' => 1, 'received_rows' => 300];
    } elseif ($provider === 'andromeda') {
        $coverage = [
            'page' => 2, 'pages_count' => 2, 'loaded_pages' => [1, 2],
            'received_offers' => 94, 'same_search_context' => true,
        ];
    } else {
        $coverage = [
            'search_status' => 'complete', 'results_fetch_limit' => 10000,
            'continuation_rounds' => 2, 'no_growth_after_continue' => true,
            'unique_groups' => 157,
        ];
    }

    return [
        'provider' => $provider,
        'operator_raw' => $provider === 'anex' ? 'ANEX' : 'Anex Tour',
        'external_hotel_id' => $provider === 'tourvisor' ? '6319' : ($provider === 'anex' ? '8121' : '3414'),
        'local_hotel_id' => null,
        'local_identity_state' => 'unmapped',
        'hotel' => [
            'name' => 'APERION BEACH HOTEL',
            'country' => [
                'supplier_id' => $provider === 'anex' ? '4' : 'TR',
                'supplier_name' => 'Turkey',
            ],
            'geography' => [
                'region' => 'Antalya', 'resort' => 'Manavgat', 'subregion' => null,
            ],
            'stars_raw' => '4★',
            'coordinates' => ['lat' => 36.713018, 'lon' => 31.563078],
        ],
        'criteria' => [
            'window' => [
                'date_from' => '2026-10-05', 'date_to' => '2026-10-05',
                'nights_from' => 7, 'nights_to' => 7, 'adults' => 2, 'child_ages' => [],
            ],
            'departure_local_id' => 1,
            'country_local_id' => 4,
            'meal_label' => 'AI',
        ],
        'offer' => [
            'meal_label' => 'AI without alcohol',
            'room_label' => 'Garden Room',
            'placement_label' => 'DBL',
            'availability' => [
                'hotel' => null,
                'flight_outbound_economy' => 'Y',
                'flight_return_economy' => 'Y',
            ],
        ],
        'money' => $money,
        'coverage_evidence' => $coverage,
        'lineage' => [
            'operation_id' => 'int-p1-observation-v1',
            'scenario_revision' => 1,
            'criteria_digest' => str_repeat('a', 64),
            'source_sha' => str_repeat('b', 40),
            'checkpoint_path' => 'reports/checkpoints/int-p1-observation-v1.json',
        ],
        'observed_at' => '2026-09-12T06:30:00Z',
    ];
}

foreach (['tourvisor', 'anex', 'andromeda'] as $provider) {
    $row = AnyTourThreeProviderSearchObservation::build(so_base($provider));
    so_check($row['schema_version'] === 1);
    so_check($row['provider'] === $provider);
    so_check($row['operator']['cross_provider_equivalence_verified'] === false);
    so_check(is_string($row['hotel']['external_id']));
    so_check($row['hotel']['external_id_scope'] === $provider);
    so_check($row['hotel']['external_id_universal'] === false);
    so_check($row['hotel']['local_hotel_id'] === null);
    so_check($row['hotel']['local_identity_state'] === 'unmapped');
    so_check($row['hotel']['name'] === 'APERION BEACH HOTEL');
    so_check($row['hotel']['country']['supplier_id_universal'] === false);
    so_check($row['hotel']['geography']['cross_provider_equivalence_verified'] === false);
    so_check($row['hotel']['stars_raw'] === '4★');
    so_check($row['hotel']['coordinates'] === ['lat' => 36.713018, 'lon' => 31.563078]);
    so_check($row['hotel']['supplier_labels_identity_proof'] === false);
    so_check($row['hotel']['coordinates_identity_proof'] === false);
    so_check($row['criteria']['window']['dates']['from'] === '2026-10-05');
    so_check($row['criteria']['window']['nights'] === ['from' => 7, 'to' => 7]);
    so_check($row['criteria']['window']['party']['adults'] === 2);
    so_check($row['criteria']['meal']['canonical_key'] === 'ai');
    so_check($row['criteria']['provider_filter_equivalence_verified'] === false);
    so_check($row['offer']['meal']['canonical_key'] === 'ai:without_alcohol');
    so_check($row['offer']['room_placement']['room']['raw'] === 'Garden Room');
    so_check($row['offer']['room_placement']['placement']['raw'] === 'DBL');
    so_check($row['offer']['availability']['offer_availability_verified'] === false);
    so_check($row['money']['provider'] === $provider);
    so_check($row['money']['search_price_fuel_relation'] === 'unknown');
    so_check($row['money']['final_price_verified'] === false);
    so_check($row['money']['arithmetic_applied'] === false);
    so_check($row['coverage']['provider'] === $provider);
    so_check($row['coverage']['observation_usable'] === true);
    so_check($row['coverage']['cross_provider_count_comparability_verified'] === false);
    so_check($row['lineage']['operation_id'] === 'int-p1-observation-v1');
    so_check($row['lineage']['replay_authority'] === false);
    so_check($row['observed_at'] === '2026-09-12T06:30:00Z');
    so_check($row['matching_handoff_state'] === 'evidence_only');
    so_check($row['mapping_write_allowed'] === false);
    so_check($row['hotel_identity_decision_allowed'] === false);
    so_check($row['package_identity_verified'] === false);
    so_check($row['cross_provider_price_equivalence_verified'] === false);
}

$anex = AnyTourThreeProviderSearchObservation::build(so_base('anex'));
so_check($anex['coverage']['coverage_state'] === 'bounded');
so_check($anex['coverage']['counts_exhaustive'] === false);
so_check($anex['coverage']['observed_count'] === 300);
so_check($anex['money']['fuel_charge_reported'] === null);
so_check(count($anex['money']['additional_prices_reported']) === 1);
so_check($anex['money']['additional_prices_reported'][0]['kind'] === 'minimum_air_surcharge');

$tv = AnyTourThreeProviderSearchObservation::build(so_base('tourvisor'));
so_check($tv['coverage']['coverage_state'] === 'complete');
so_check($tv['coverage']['counts_exhaustive'] === true);
so_check($tv['money']['fuel_charge_reported']['amount'] === '31710');
so_check($tv['money']['additional_prices_reported'] === []);

$andromeda = AnyTourThreeProviderSearchObservation::build(so_base('andromeda'));
so_check($andromeda['coverage']['coverage_state'] === 'complete');
so_check($andromeda['coverage']['loaded_pages'] === [1, 2]);
so_check($andromeda['money']['fuel_charge_reported'] === null);
so_check($andromeda['money']['additional_prices_reported'] === []);

$mappedInput = so_base('anex');
$mappedInput['local_hotel_id'] = 6319;
$mappedInput['local_identity_state'] = 'current_accepted';
$mappedInput['external_hotel_id'] = '0008121';
$mapped = AnyTourThreeProviderSearchObservation::build($mappedInput);
so_check($mapped['hotel']['external_id'] === '0008121');
so_check($mapped['hotel']['local_hotel_id'] === 6319);
so_check($mapped['hotel']['local_identity_state'] === 'current_accepted');
so_check($mapped['matching_handoff_state'] === 'not_required');

$minimalEvidence = so_base('andromeda');
$minimalEvidence['operator_raw'] = null;
$minimalEvidence['hotel']['name'] = null;
$minimalEvidence['hotel']['country'] = ['supplier_id' => null, 'supplier_name' => null];
$minimalEvidence['hotel']['geography'] = ['region' => null, 'resort' => null, 'subregion' => null];
$minimalEvidence['hotel']['stars_raw'] = null;
$minimalEvidence['hotel']['coordinates'] = null;
$minimalEvidence['offer']['placement_label'] = null;
$minimal = AnyTourThreeProviderSearchObservation::build($minimalEvidence);
so_check($minimal['operator']['raw'] === null);
so_check($minimal['hotel']['name'] === null);
so_check($minimal['hotel']['coordinates'] === null);
so_check($minimal['offer']['room_placement']['placement'] === null);

$bad = [];
$bad[] = function () { $x = so_base('anex'); $x['provider'] = 'samo'; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['external_hotel_id'] = 8121; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['external_hotel_id'] = "id\n8121"; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['local_hotel_id'] = 6319; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['local_identity_state'] = 'current_accepted'; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['local_identity_state'] = 'candidate'; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['hotel']['name'] = "bad\x01name"; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['hotel']['country']['supplier_id'] = 4; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['hotel']['coordinates'] = ['lat' => 91.0, 'lon' => 31.0]; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['hotel']['coordinates'] = ['lat' => 36.0, 'lon' => -181.0]; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['criteria']['window']['date_from'] = '2026-02-30'; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['criteria']['departure_local_id'] = 0; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['criteria']['meal_label'] = ''; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['offer']['room_label'] = '77'; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['offer']['availability']['hotel'] = 1; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['money']['fuel_charge_reported'] = ['amount'=>'1','currency'=>'RUB','source'=>'anex_fuel']; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('andromeda'); $x['money']['additional_prices_reported'] = [['kind'=>'x','amount'=>'1','currency'=>'RUB','source'=>'andromeda_additional']]; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['coverage_evidence'] = null; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['coverage_evidence']['price_page'] = 2; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('andromeda'); $x['coverage_evidence']['same_search_context'] = false; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('tourvisor'); $x['coverage_evidence']['no_growth_after_continue'] = true; $x['coverage_evidence']['continuation_rounds'] = 0; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['lineage']['operation_id'] = '../bad'; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['lineage']['scenario_revision'] = 0; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['lineage']['criteria_digest'] = str_repeat('A', 64); AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['lineage']['source_sha'] = str_repeat('b', 39); AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['lineage']['checkpoint_path'] = '../checkpoint.json'; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['lineage']['checkpoint_path'] = '/tmp/checkpoint.json'; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['observed_at'] = '2026-02-30T06:30:00Z'; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['observed_at'] = '2026-09-12T24:00:00Z'; AnyTourThreeProviderSearchObservation::build($x); };
$bad[] = function () { $x = so_base('anex'); $x['unexpected'] = true; AnyTourThreeProviderSearchObservation::build($x); };

foreach ($bad as $case) {
    try {
        $case();
        so_check(false);
    } catch (InvalidArgumentException $e) {
        so_check(true);
    }
}

echo 'Three-provider search observation: '.$checks." checks passed; supplier/network/DB/mapping=0.\n";
