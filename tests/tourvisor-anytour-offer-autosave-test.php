<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/tourvisor-anytour-offer-autosave.php';

$checks = 0;
function tv_autosave_check(bool $ok, string $label): void
{
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException('tourvisor_autosave_check_' . $checks . ':' . $label);
}

$scope = [
    'departureId' => 1,
    'countryId' => 4,
    'dateFrom' => '2026-10-05',
    'dateTo' => '2026-10-08',
    'nightsFrom' => 7,
    'nightsTo' => 9,
    'adults' => 2,
    'childs' => [],
    'meal' => '',
    'hotelCategory' => '',
    'hotelRating' => '',
    'hotelTypes' => [],
    'hotelIds' => [],
    'hotelServices' => [],
    'arrivalId' => null,
    'regionIds' => [],
    'subregionIds' => [],
    'operatorIds' => [],
    'priceFrom' => null,
    'priceTo' => null,
    'currency' => 'RUB',
    'onlyCharter' => false,
    'onlyDirect' => false,
];

$now = new DateTimeImmutable('2026-09-17T09:30:00Z');
$searchId = 987654321;

$pathMethod = new ReflectionMethod(AnyTourTourvisorOfferAutosaveV1::class, 'statePath');
$statePath = $pathMethod->invoke(null, $searchId);
@unlink($statePath);

$start = AnyTourTourvisorOfferAutosaveV1::captureSearchStart($scope, ['searchId' => $searchId], $now);
tv_autosave_check(($start['ok'] ?? null) === true, 'start_saved');
tv_autosave_check(($start['searchId'] ?? null) === $searchId, 'start_id');
tv_autosave_check(is_file($statePath), 'state_file');

$readMethod = new ReflectionMethod(AnyTourTourvisorOfferAutosaveV1::class, 'readState');
$state = $readMethod->invoke(null, $searchId, $now);
tv_autosave_check(is_array($state) && ($state['scope'] ?? null) === $scope, 'scope_roundtrip');
tv_autosave_check(array_key_exists('terminal_at', $state) && $state['terminal_at'] === null, 'not_terminal_initially');

$partial = AnyTourTourvisorOfferAutosaveV1::captureSearchStatus($searchId, ['progress' => 99], $now->modify('+10 seconds'));
tv_autosave_check(($partial['ok'] ?? null) === false && ($partial['reason'] ?? null) === 'not_terminal', 'partial_rejected');

$terminal = AnyTourTourvisorOfferAutosaveV1::captureSearchStatus(
    $searchId,
    ['data' => ['status' => 'complete']],
    $now->modify('+20 seconds')
);
tv_autosave_check(($terminal['ok'] ?? null) === true, 'terminal_saved');
$state = $readMethod->invoke(null, $searchId, $now->modify('+21 seconds'));
tv_autosave_check(is_int($state['terminal_at'] ?? null), 'terminal_marker');

$wrongLimit = AnyTourTourvisorOfferAutosaveV1::autosaveSearchResults(
    $searchId,
    25,
    [['id' => 1, 'tours' => []]],
    $now->modify('+30 seconds')
);
tv_autosave_check(($wrongLimit['reason'] ?? null) === 'not_final_bounded_fetch', 'limit_gate');

$empty = AnyTourTourvisorOfferAutosaveV1::autosaveSearchResults(
    $searchId,
    100,
    [],
    $now->modify('+31 seconds')
);
tv_autosave_check(($empty['reason'] ?? null) === 'empty_not_authoritative', 'empty_never_authoritative');

$nestedId = 987654322;
$nestedPath = $pathMethod->invoke(null, $nestedId);
@unlink($nestedPath);
$nested = AnyTourTourvisorOfferAutosaveV1::captureSearchStart(
    $scope,
    ['data' => ['searchId' => $nestedId]],
    $now
);
tv_autosave_check(($nested['ok'] ?? null) === true && ($nested['searchId'] ?? null) === $nestedId, 'nested_search_id');

// Exercise the exact Tourvisor row -> protected finalPriceReady path without DB or supplier I/O.
$entryMethod = new ReflectionMethod(AnyTourTourvisorOfferAutosaveV1::class, 'entryFromTour');
$baseTour = [
    'id' => 'TV-OFFER-1',
    'date' => '2026-10-05',
    'nights' => 7,
    'price' => 150824,
    'currency' => 'RUB',
    'meal' => ['id' => 7, 'name' => 'Все включено'],
    'roomType' => 'Standard Room',
    'operator' => ['id' => 5, 'name' => 'PEGAS Touristik'],
    'fuelCharge' => 31710,
];
$entry = $entryMethod->invoke(
    null,
    $searchId,
    3417,
    77,
    $baseTour,
    2,
    0,
    [],
    '2026-09-17T09:30:00Z',
    $now
);
tv_autosave_check(is_array($entry), 'tour_row_compiled');
$dto = AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer(
    $entry['offer'], $entry['retained'], $entry['current'], $now->getTimestamp(), $entry['priced_money']
);
tv_autosave_check(($dto['finalPriceReady'] ?? null) === true, 'reported_fuel_ready');
tv_autosave_check(($dto['price'] ?? null) === '150824' && ($dto['finalPrice'] ?? null) === '150824', 'search_price_is_final_listing_price');
tv_autosave_check(($dto['money']['fuel_charge_reported']['amount'] ?? null) === '31710', 'fuel_fact_retained');

$zeroTour = $baseTour;
$zeroTour['id'] = 'TV-OFFER-2';
$zeroTour['fuelCharge'] = 0;
$zeroEntry = $entryMethod->invoke(null, $searchId, 3417, 77, $zeroTour, 2, 0, [], '2026-09-17T09:30:00Z', $now);
$zeroDto = AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer(
    $zeroEntry['offer'], $zeroEntry['retained'], $zeroEntry['current'], $now->getTimestamp(), $zeroEntry['priced_money']
);
tv_autosave_check(($zeroDto['finalPriceReady'] ?? null) === true && ($zeroDto['price'] ?? null) === '150824', 'explicit_zero_fuel_ready');

$unknownTour = $baseTour;
$unknownTour['id'] = 'TV-OFFER-3';
unset($unknownTour['fuelCharge']);
$unknownEntry = $entryMethod->invoke(null, $searchId, 3417, 77, $unknownTour, 2, 0, [], '2026-09-17T09:30:00Z', $now);
$unknownDto = AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer(
    $unknownEntry['offer'], $unknownEntry['retained'], $unknownEntry['current'], $now->getTimestamp(), $unknownEntry['priced_money']
);
tv_autosave_check(($unknownDto['finalPriceReady'] ?? null) === false, 'missing_fuel_not_ready');
tv_autosave_check(
    array_key_exists('price', $unknownDto) && $unknownDto['price'] === null
        && array_key_exists('finalPrice', $unknownDto) && $unknownDto['finalPrice'] === null,
    'missing_fuel_no_base_fallback'
);

// Operator specialization is deferred: retain all valid returned labels without
// relabeling Tourvisor observations as direct ANEX or Andromeda observations.
$labels = ['PEGAS Touristik', 'Пегас Туристик', 'Coral Travel', 'Корал Тревел',
    'Sunmar', 'Санмар', 'ANEX', 'FUN&SUN', 'Библио-Глобус', 'Интурист',
    'Pegasus Holidays', 'Новый оператор'];
foreach ($labels as $index => $label) {
    $tour = $baseTour;
    $tour['id'] = 'TV-ALL-OPERATORS-' . $index;
    $tour['operator'] = ['id' => $index + 1, 'name' => $label];
    $before = $tour;
    $allEntry = $entryMethod->invoke(null, $searchId, 3417, 77, $tour, 2, 0, [], '2026-09-17T09:30:00Z', $now);
    tv_autosave_check(is_array($allEntry), 'every_returned_operator_admitted_' . $index);
    tv_autosave_check($tour === $before, 'input_tour_unchanged_' . $index);
    tv_autosave_check($allEntry['offer']['provider'] === 'tourvisor'
        && $allEntry['offer']['operator']['raw'] === $label, 'provider_operator_separate_' . $index);
    tv_autosave_check($allEntry['offer']['identity'] === [
        'search_ref_digest' => hash('sha256', 'tourvisor:search:' . $searchId),
        'offer_ref_digest' => hash('sha256', 'tourvisor:tour:' . $tour['id']),
        'provider_hotel_ref_digest' => hash('sha256', 'tourvisor:hotel:3417'),
    ], 'source_identity_retained_' . $index);
    $allDto = AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer(
        $allEntry['offer'], $allEntry['retained'], $allEntry['current'], $now->getTimestamp(), $allEntry['priced_money']
    );
    tv_autosave_check($allDto['finalPriceReady'] === true && $allDto['price'] === '150824', 'same_price_contract_' . $index);
    tv_autosave_check($allEntry['offer']['final_price_verified'] === false
        && $allEntry['offer']['selection_state'] === 'disabled', 'no_quote_authority_' . $index);
}

// A 99-offer cohort must not collapse to the 26 offers from the former allowlist.
// Exercise the real producer with an in-memory ingest callback, not a live DB.
$mixed = [];
for ($i = 0; $i < 99; ++$i) {
    $tour = $baseTour;
    $tour['id'] = 'TV-MIXED-' . $i;
    $tour['operator'] = $i < 26 ? 'PEGAS Touristik' : $labels[6 + (($i - 26) % 6)];
    $mixed[] = $entryMethod->invoke(null, $searchId, 4000 + $i, 8000 + $i,
        $tour, 2, 0, [], '2026-09-17T09:30:00Z', $now);
}
$mixedBefore = $mixed;
$storedRows = null;
$ingestCalls = 0;
$ingest = static function (string $provider, array $params, array $rows, DateTimeImmutable $at)
    use (&$storedRows, &$ingestCalls, $scope, $now): array {
    ++$ingestCalls;
    tv_autosave_check($provider === 'tourvisor' && $params === $scope, 'original_provider_and_scope_to_ingest');
    tv_autosave_check($at == $now, 'original_observation_time_to_ingest');
    $storedRows = $rows;
    return ['test_only' => true];
};
$produced = AnyTourIntOfferSnapshotProducerV1::produce('tourvisor', $scope,
    ['complete' => true, 'authoritative_empty' => false, 'offers' => $mixed], $now, $ingest);
tv_autosave_check($ingestCalls === 1 && count($storedRows) === 99, 'all_99_reach_ingest_once');
tv_autosave_check($produced['inputOfferCount'] === 99 && $produced['readyOfferCount'] === 99,
    '99_input_99_ready_not_26');
tv_autosave_check(count(array_unique(array_column($storedRows, 'anytour_hotel_id'))) === 99,
    '99_distinct_hotels_retained');
tv_autosave_check($produced['selectionAuthority'] === false && $mixed === $mixedBefore,
    'no_authority_or_input_mutation');
foreach ($storedRows as $index => $row) {
    tv_autosave_check($row['anytour_hotel_id'] === 8000 + $index
        && $row['dto']['provider'] === 'tourvisor'
        && $row['dto']['price'] === '150824'
        && $row['expires_at'] === '2026-09-17T09:45:00Z', 'mixed_row_identity_price_ttl_' . $index);
}

// New operators still obey the unchanged price and hotel-identity contracts.
$notReady = $mixed[26];
$notReady['offer']['money'] = $unknownEntry['offer']['money'];
$notReady['priced_money'] = $unknownEntry['priced_money'];
$unresolved = $mixed[27];
$unresolved['anytour_hotel_id'] = null;
$guarded = AnyTourIntOfferSnapshotProducerV1::produce('tourvisor', $scope,
    ['complete' => true, 'authoritative_empty' => false, 'offers' => [$mixed[28], $notReady, $unresolved]],
    $now, $ingest);
tv_autosave_check(count($storedRows) === 1 && $guarded['notReadyCount'] === 1
    && $guarded['unresolvedHotelCount'] === 1, 'no_price_or_identity_guard_bypass');

$missingOperatorTour = $baseTour;
$missingOperatorTour['id'] = 'TV-OFFER-NO-OP';
unset($missingOperatorTour['operator']);
tv_autosave_check(
    $entryMethod->invoke(null, $searchId, 3417, 77, $missingOperatorTour, 2, 0, [], '2026-09-17T09:30:00Z', $now) === null,
    'missing_operator_never_persisted'
);

$compileMethod = new ReflectionMethod(AnyTourTourvisorOfferAutosaveV1::class, 'compileEntries');
$missingMealTour = $baseTour;
$missingMealTour['id'] = 'TV-MISSING-MEAL';
unset($missingMealTour['meal']);
$validSiblingTour = $baseTour;
$validSiblingTour['id'] = 'TV-VALID-SIBLING';
[$compiledSiblings, $skippedSiblings] = $compileMethod->invoke(
    null,
    [['id' => 3417, 'tours' => [$missingMealTour, $validSiblingTour]]],
    [3417 => 77],
    $searchId,
    2,
    0,
    [],
    '2026-09-17T09:30:00Z',
    $now
);
tv_autosave_check($skippedSiblings === 1 && count($compiledSiblings) === 1,
    'incomplete_sibling_does_not_abort_cohort');
tv_autosave_check(
    ($compiledSiblings[0]['offer']['identity']['offer_ref_digest'] ?? null)
        === hash('sha256', 'tourvisor:tour:TV-VALID-SIBLING'),
    'valid_sibling_identity_retained'
);
[$compiledInvalidOnly, $skippedInvalidOnly] = $compileMethod->invoke(
    null,
    [['id' => 3417, 'tours' => [$missingMealTour]]],
    [3417 => 77],
    $searchId,
    2,
    0,
    [],
    '2026-09-17T09:30:00Z',
    $now
);
tv_autosave_check($compiledInvalidOnly === [] && $skippedInvalidOnly === 1,
    'all_incomplete_rows_stay_empty');

$helperSource = (string)file_get_contents(__DIR__ . '/../app/integrations/tourvisor-anytour-offer-autosave.php');
$apiSource = (string)file_get_contents(__DIR__ . '/../v2/api-v2.php');

tv_autosave_check(strpos($helperSource, 'fuelCharge') !== false, 'uses_supplier_fuel_fact');
tv_autosave_check(strpos($helperSource, 'AnyTourIntOfferSnapshotProducerV1::produce') !== false, 'uses_producer');
tv_autosave_check(strpos($helperSource, "'authoritative_empty' => false") !== false, 'never_authoritative_empty');
tv_autosave_check(strpos($helperSource, 'withSearchSurchargeEstimate') === false, 'no_new_surcharge_arithmetic');
tv_autosave_check(strpos($helperSource, 'search_plus_additional') === false, 'no_shadow_price_path');
tv_autosave_check(strpos($helperSource, 'v2_data_tv_get(') === false && strpos($helperSource, 'curl_') === false, 'no_supplier_io');
tv_autosave_check(strpos($helperSource, 'ownedOperatorFamily') === false, 'no_operator_allowlist_in_either_stage');
tv_autosave_check(strpos($helperSource, "'no_routed_offers'") !== false, 'empty_cohort_receipt_compatible');
tv_autosave_check(strpos($helperSource, 'skippedIncompleteOfferCount') !== false,
    'incomplete_row_count_exposed');
tv_autosave_check(strpos($helperSource, "'tour_contract_incomplete'") === false,
    'single_incomplete_row_no_longer_aborts_snapshot');

tv_autosave_check(strpos($apiSource, "tourvisor_autosave_start(\$searchParams, \$data);") !== false, 'start_hook');
tv_autosave_check(strpos($apiSource, 'tourvisor_autosave_status($id, $data);') !== false, 'status_hook');
tv_autosave_check(strpos($apiSource, 'tourvisor_autosave_results($id, $limit, $data);') !== false, 'results_hook');
tv_autosave_check(substr_count($apiSource, "tv_get('/tours/search', \$searchParams)") === 1, 'no_extra_search_start_call');
tv_autosave_check(strpos($apiSource, 'catch (Throwable $ignored)') !== false, 'fail_open_gateway');

@unlink($statePath);
@unlink($nestedPath);

echo 'Tourvisor AnyTour offer autosave: ' . $checks . " checks passed; supplier calls=0; DB writes=0.\n";
