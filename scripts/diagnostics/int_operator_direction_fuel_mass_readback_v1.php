<?php
declare(strict_types=1);

/**
 * Supplier-free DB/retained readback for reusable operator+direction fuel rules.
 *
 * This diagnostic never calls a supplier and never writes DB/runtime/store state.
 * It binds one terminal Andromeda collector operation to its exact retained cohort,
 * latest authoritative LOCAL refresh, and the canonical operator+departure+destination
 * direction rule already persisted by INT.
 */

const ODFR_MAX_FILES = 100000;
const ODFR_MAX_BYTES = 3000000;

function odfr_fail(string $reason): never { throw new RuntimeException($reason); }
function odfr_json(string $path, int $max = ODFR_MAX_BYTES): ?array {
    if (!is_file($path) || is_link($path)) return null;
    $size = filesize($path);
    if (!is_int($size) || $size < 2 || $size > $max) return null;
    try {
        $v = json_decode((string)file_get_contents($path), true, 96, JSON_THROW_ON_ERROR);
        return is_array($v) ? $v : null;
    } catch (Throwable $ignored) { return null; }
}
function odfr_digest(mixed $v): ?string {
    return is_string($v) && preg_match('/\A[a-f0-9]{64}\z/D', $v) === 1 ? $v : null;
}
function odfr_money(mixed $v): ?string {
    if (is_int($v)) $v = (string)$v;
    return is_string($v) && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $v) === 1 ? $v : null;
}
function odfr_units(mixed $v): ?int {
    $m = odfr_money($v);
    if ($m === null) return null;
    $parts = explode('.', $m, 2);
    return (int)$parts[0] * 100 + (int)str_pad($parts[1] ?? '', 2, '0');
}
function odfr_format(int $units): string {
    return intdiv($units, 100) . '.' . str_pad((string)($units % 100), 2, '0', STR_PAD_LEFT);
}
function odfr_rate(mixed $v): ?string {
    return is_string($v) && preg_match('/\A(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,8})?\z/D', $v) === 1 ? $v : null;
}
function odfr_convert(int $units, string $rate): ?int {
    if ($units < 0) return null;
    $parts = explode('.', $rate, 2);
    $scale = strlen($parts[1] ?? '');
    $factor = 10 ** $scale;
    $numerator = (int)$parts[0] * $factor + (int)($parts[1] ?? '0');
    if ($numerator < 1 || $units > intdiv(PHP_INT_MAX, $numerator)) return null;
    $product = $units * $numerator;
    $converted = intdiv($product, $factor);
    if ($scale > 0 && $product % $factor >= intdiv($factor, 2)) ++$converted;
    return $converted <= 99999999999999 ? $converted : null;
}
function odfr_positive_ref(mixed $v, string $name): string {
    if (is_int($v) && $v > 0) return (string)$v;
    if (is_string($v) && preg_match('/\A[1-9][0-9]{0,12}\z/D', $v) === 1) return $v;
    odfr_fail($name);
}
function odfr_operation(mixed $v): string {
    if (!is_string($v) || preg_match('/\Aint-andromeda-[a-z0-9][a-z0-9-]{8,160}\z/D', $v) !== 1) {
        odfr_fail('operation_invalid');
    }
    return $v;
}
function odfr_operator_family(mixed $raw): ?string {
    if (!is_string($raw)) return null;
    $raw = trim(str_replace(['Ё','ё'], 'е', $raw));
    if ($raw === '' || strlen($raw) > 160 || preg_match('/[\x00-\x1F\x7F]/u', $raw)) return null;
    if (preg_match('/интурист/iu', $raw) === 1 || preg_match('/intourist/i', $raw) === 1) return 'intourist';
    if (preg_match('/фан[^\p{L}\p{N}]*с[аa]н/iu', $raw) === 1 || preg_match('/fun[^a-z0-9]*&?[^a-z0-9]*sun/i', $raw) === 1) return 'fun_and_sun';
    if (preg_match('/библио[^\p{L}\p{N}]*глобус/iu', $raw) === 1 || preg_match('/biblio[^a-z0-9]*globus/i', $raw) === 1) return 'biblio_globus';
    return null;
}
function odfr_expected_family(mixed $v): string {
    if (!is_string($v) || !in_array($v, ['fun_and_sun','intourist','biblio_globus'], true)) {
        odfr_fail('operator_family_invalid');
    }
    return $v;
}
function odfr_owner_policy(mixed $value, string $family, array $direction): bool {
    if (!is_array($value) || array_is_list($value)) return false;
    $expected = [
        'schema_version'=>1,
        'source'=>'owner_policy',
        'policy_date'=>'2026-09-23',
        'operator_family'=>'fun_and_sun',
        'destination'=>'country:4',
        'amount'=>'70.00',
        'currency'=>'EUR',
        'unit'=>'per_person_one_way',
        'base_relation'=>'excluded',
    ];
    $actual = $value;
    ksort($actual, SORT_STRING);
    ksort($expected, SORT_STRING);
    return $actual === $expected
        && $family === 'fun_and_sun'
        && ($direction['operator_family'] ?? null) === 'fun_and_sun'
        && ($direction['destination'] ?? null) === 'country:4';
}
function odfr_direction_matches(mixed $value, array $expected): bool {
    if (!is_array($value) || array_is_list($value)) return false;
    $actual = $value;
    ksort($actual, SORT_STRING);
    $canonical = $expected;
    ksort($canonical, SORT_STRING);
    return $actual === $canonical;
}
function odfr_direction_summary(mixed $value): array {
    if (!is_array($value) || array_is_list($value)) return ['shape'=>'invalid'];
    $keys = array_keys($value);
    sort($keys, SORT_STRING);
    $family = $value['operator_family'] ?? null;
    $market = $value['market'] ?? null;
    $destination = $value['destination'] ?? null;
    return [
        'keys'=>$keys,
        'operator_family'=>is_string($family) && in_array($family, ['fun_and_sun','intourist','biblio_globus'], true)
            ? $family : null,
        'market'=>is_string($market) && preg_match('/\Adeparture:[1-9][0-9]{0,12}\z/D', $market) === 1
            ? $market : null,
        'destination'=>is_string($destination) && preg_match('/\Acountry:[1-9][0-9]{0,12}\z/D', $destination) === 1
            ? $destination : null,
    ];
}
function odfr_set_add(array &$set, mixed $v): void {
    $key = is_scalar($v) || $v === null
        ? gettype($v) . ':' . (string)$v
        : 'json:' . json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $set[$key] = $v;
}
function odfr_values(array $set): array { ksort($set, SORT_STRING); return array_values($set); }

/**
 * @param array<int,array<string,mixed>> $dbRows
 * @param array<string,string> $retainedFamilies offer_ref_digest => operator_family
 */
function odfr_evaluate_rows(array $dbRows, array $retainedFamilies, string $family, string $departure, string $country): array {
    $targetStored = 0; $targetReady = 0; $targetVerified = 0; $targetConfirmation = 0;
    $directionRows = 0; $directionValid = 0; $readyNonTarget = 0; $payloadHashInvalid = 0;
    $targetMissingRetained = 0; $targetReadyNoDirection = 0; $badReadyBoundary = 0;
    $validationFailures = []; $listingMismatch = 0;
    $listingEqualsBase = 0; $listingEqualsTotal = 0; $listingInvalid = 0; $listingOther = 0;
    $ruleAmounts=[]; $ruleCurrencies=[]; $ruleUnits=[]; $ruleRelations=[]; $ruleNativeTotals=[];
    $rulePassengers=[]; $ruleDirections=[]; $ruleAuthorities=[]; $fuelSources=[];
    $actualDirectionSummaries=[]; $actualDirectionDigests=[];
    $fxRates=[]; $fuelCharges=[]; $basePrices=[]; $derivedTotals=[]; $displayPrices=[];
    $expectedDirection = ['operator_family'=>$family, 'market'=>'departure:'.$departure, 'destination'=>'country:'.$country];

    foreach ($dbRows as $row) {
        $digest = odfr_digest($row['offer_ref_digest'] ?? null);
        if ($digest === null) odfr_fail('offer_digest_invalid');
        $retainedFamily = $retainedFamilies[$digest] ?? null;
        if ($retainedFamily === null) { ++$targetMissingRetained; continue; }
        $isTarget = $retainedFamily === $family;
        if ($isTarget) ++$targetStored;

        $raw = (string)($row['payload_json'] ?? '');
        $sha = odfr_digest($row['payload_sha256'] ?? null);
        if ($sha === null || !hash_equals($sha, hash('sha256', $raw))) { ++$payloadHashInvalid; continue; }
        try { $payload = json_decode($raw, true, 96, JSON_THROW_ON_ERROR); }
        catch (Throwable $ignored) { ++$payloadHashInvalid; continue; }
        if (!is_array($payload)) { ++$payloadHashInvalid; continue; }

        $isReady = (int)($row['final_price_ready'] ?? 0) === 1;
        $isVerified = (int)($row['final_price_verified'] ?? 0) === 1;
        $state = $payload['listingPriceState'] ?? 'missing';
        if (!$isTarget && $isReady) ++$readyNonTarget;
        if (!$isTarget) continue;
        if ($isReady) ++$targetReady;
        if ($isVerified) ++$targetVerified;
        if ($state === 'search_price_confirmation_required') ++$targetConfirmation;
        if (!$isReady) continue;

        $payloadReady = $payload['listingPriceReady'] ?? null;
        $payloadVerified = $payload['finalPriceVerified'] ?? null;
        if ($state !== 'final_ready_estimate' || $payloadReady !== true || $payloadVerified !== false
            || ($payload['quoteState'] ?? null) !== 'unknown' || ($payload['quoteEvidenceDigest'] ?? null) !== null
            || ($payload['selection_state'] ?? null) !== 'refresh_required' || ($payload['booking_enabled'] ?? null) !== false
            || $isVerified) {
            ++$badReadyBoundary;
        }

        $money = is_array($payload['money'] ?? null) ? $payload['money'] : [];
        $rule = is_array($money['operator_fuel_rule'] ?? null) ? $money['operator_fuel_rule'] : null;
        if ($rule === null) { ++$targetReadyNoDirection; continue; }
        $actualDirection = $rule['direction'] ?? null;
        $directionSummary = odfr_direction_summary($actualDirection);
        odfr_set_add($actualDirectionSummaries, $directionSummary);
        odfr_set_add($actualDirectionDigests, hash('sha256', json_encode(
            $directionSummary,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        )));
        $hasOwnerPolicy = array_key_exists('owner_policy', $rule);
        $ownerPolicyValid = $hasOwnerPolicy && odfr_owner_policy($rule['owner_policy'], $family, $expectedDirection);
        $authority = $hasOwnerPolicy ? 'owner_policy' : 'supplier_direction_evidence';
        $expectedFuelSource = $hasOwnerPolicy ? 'operator_fuel_owner_policy' : 'operator_fuel_direction_rule';
        ++$directionRows;
        $fuel = is_array($money['fuel_charge_reported'] ?? null) ? $money['fuel_charge_reported'] : null;
        $total = is_array($money['search_price_with_surcharge'] ?? null) ? $money['search_price_with_surcharge'] : null;
        $base = is_array($money['search_price'] ?? null) ? $money['search_price'] : null;
        if ($fuel === null || $total === null || $base === null) {
            $validationFailures['money_shape'] = ($validationFailures['money_shape'] ?? 0) + 1;
            continue;
        }

        $amount = odfr_units($rule['amount'] ?? null);
        $currency = is_string($rule['currency'] ?? null) ? $rule['currency'] : null;
        $unit = $rule['unit'] ?? null;
        $relation = $rule['base_relation'] ?? null;
        $party = is_array($rule['applicable_party'] ?? null) ? $rule['applicable_party'] : null;
        $pax = is_array($party) && is_int($party['adults'] ?? null) && is_int($party['children'] ?? null)
            ? $party['adults'] + $party['children'] : null;
        $nativeTotal = odfr_units($rule['applied_native_total'] ?? null);
        $directionCount = $rule['direction_count'] ?? null;
        $passengerCount = $rule['passenger_count'] ?? null;
        $exchange = is_array($rule['exchange'] ?? null) ? $rule['exchange'] : null;
        $rate = $currency === 'RUB' ? '1' : odfr_rate($exchange['rate'] ?? null);
        $computedNative = null;
        if ($amount !== null) {
            if ($unit === 'per_person_one_way' && is_int($pax) && $pax > 0) $computedNative = $amount * $pax * 2;
            elseif ($unit === 'party_roundtrip') $computedNative = $amount;
        }
        $converted = ($computedNative !== null && $rate !== null) ? odfr_convert($computedNative, $rate) : null;
        $fuelUnits = odfr_units($fuel['amount'] ?? null);
        $baseUnits = odfr_units($base['amount'] ?? null);
        $totalUnits = odfr_units($total['amount'] ?? null);
        $displayUnits = odfr_units((string)($row['display_price'] ?? ''));
        $listingUnits = odfr_units($payload['listingPrice'] ?? null);
        $expectedTotal = ($baseUnits !== null && $converted !== null)
            ? $baseUnits + ($relation === 'excluded' ? $converted : 0) : null;

        $checks = [
            'rule_schema'=>(($rule['schema_version'] ?? null) === 2),
            'rule_kind'=>(($rule['kind'] ?? null) === 'fuel'),
            'rule_direction'=>odfr_direction_matches($actualDirection, $expectedDirection),
            'rule_unit'=>(in_array($unit, ['per_person_one_way','party_roundtrip'], true)),
            'rule_relation'=>(in_array($relation, ['included','excluded'], true)),
            'rule_amount'=>($amount !== null && $amount > 0),
            'rule_currency'=>(is_string($currency) && preg_match('/\A[A-Z]{3}\z/D', $currency) === 1),
            'rule_authority'=>(!$hasOwnerPolicy || $ownerPolicyValid),
            'rule_independent_offers'=>(is_int($rule['independent_offer_count'] ?? null)
                && ($hasOwnerPolicy ? $rule['independent_offer_count'] === 0 : $rule['independent_offer_count'] >= 2)),
            'rule_evidence'=>(is_int($rule['evidence_count'] ?? null)
                && ($hasOwnerPolicy ? $rule['evidence_count'] === 0 : $rule['evidence_count'] >= 2)),
            'rule_evidence_digest'=>(odfr_digest($rule['evidence_sha256'] ?? null) !== null),
            'rule_digest'=>(odfr_digest($rule['rule_sha256'] ?? null) !== null),
            'party_no_infant'=>(is_array($party) && is_array($party['child_ages'] ?? null)
                && count(array_filter($party['child_ages'], static fn($age): bool => !is_int($age) || $age < 2)) === 0),
            'native_total'=>(is_int($computedNative) && $computedNative === $nativeTotal),
            'pax_count'=>($unit !== 'per_person_one_way' || ($passengerCount === $pax && $directionCount === 2)),
            'exchange'=>(($currency === 'RUB' && $exchange === null) || ($currency !== 'RUB' && is_array($exchange)
                && ($exchange['from'] ?? null) === $currency && ($exchange['to'] ?? null) === 'RUB'
                && $rate !== null && odfr_digest($exchange['evidence_sha256'] ?? null) !== null)),
            'fuel'=>(($fuel['currency'] ?? null) === 'RUB' && ($fuel['source'] ?? null) === $expectedFuelSource
                && $converted !== null && $fuelUnits === $converted),
            'base'=>(($base['currency'] ?? null) === 'RUB' && $baseUnits !== null && $baseUnits > 0),
            'relation'=>(($money['search_price_fuel_relation'] ?? null) === $relation),
            'total'=>(($total['currency'] ?? null) === 'RUB' && ($total['source'] ?? null) === 'derived_search_estimate'
                && $expectedTotal !== null && $totalUnits === $expectedTotal),
            'display_total'=>($displayUnits !== null && $expectedTotal !== null && $displayUnits === $expectedTotal),
            'arithmetic_once'=>(($money['arithmetic_applied'] ?? null) === ($relation === 'excluded')),
        ];
        $valid = true;
        foreach ($checks as $name=>$ok) {
            if (!$ok) { $validationFailures[$name] = ($validationFailures[$name] ?? 0) + 1; $valid = false; }
        }
        if ($valid) ++$directionValid;

        if ($listingUnits === null) ++$listingInvalid;
        elseif ($totalUnits !== null && $listingUnits === $totalUnits) ++$listingEqualsTotal;
        elseif ($baseUnits !== null && $listingUnits === $baseUnits) ++$listingEqualsBase;
        else ++$listingOther;
        if ($listingUnits === null || $totalUnits === null || $listingUnits !== $totalUnits) ++$listingMismatch;
        odfr_set_add($ruleAmounts, $rule['amount'] ?? null);
        odfr_set_add($ruleCurrencies, $currency);
        odfr_set_add($ruleUnits, $unit);
        odfr_set_add($ruleRelations, $relation);
        odfr_set_add($ruleNativeTotals, $rule['applied_native_total'] ?? null);
        odfr_set_add($ruleAuthorities, $authority);
        odfr_set_add($fuelSources, $fuel['source'] ?? null);
        odfr_set_add($rulePassengers, $passengerCount);
        odfr_set_add($ruleDirections, $directionCount);
        if ($currency !== 'RUB') odfr_set_add($fxRates, $rate);
        odfr_set_add($fuelCharges, $fuel['amount'] ?? null);
        odfr_set_add($basePrices, $base['amount'] ?? null);
        odfr_set_add($derivedTotals, $total['amount'] ?? null);
        odfr_set_add($displayPrices, odfr_money((string)($row['display_price'] ?? '')));
    }
    ksort($validationFailures, SORT_STRING);

    return [
        'operator_family'=>$family,
        'direction'=>$expectedDirection,
        'stored_target_count'=>$targetStored,
        'ready_target_count'=>$targetReady,
        'verified_target_count'=>$targetVerified,
        'confirmation_target_count'=>$targetConfirmation,
        'direction_rule_rows'=>$directionRows,
        'direction_rule_valid_rows'=>$directionValid,
        'ready_target_without_direction_rule'=>$targetReadyNoDirection,
        'ready_non_target_count'=>$readyNonTarget,
        'bad_ready_boundary_count'=>$badReadyBoundary,
        'payload_hash_invalid_count'=>$payloadHashInvalid,
        'db_rows_missing_retained_count'=>$targetMissingRetained,
        'payload_listing_vs_total_mismatch_count'=>$listingMismatch,
        'payload_listing_equals_base_count'=>$listingEqualsBase,
        'payload_listing_equals_total_count'=>$listingEqualsTotal,
        'payload_listing_invalid_count'=>$listingInvalid,
        'payload_listing_other_count'=>$listingOther,
        'validation_failure_counts'=>$validationFailures,
        'actual_rule_direction_summaries'=>odfr_values($actualDirectionSummaries),
        'actual_rule_direction_summary_sha256'=>odfr_values($actualDirectionDigests),
        'rule_amounts'=>odfr_values($ruleAmounts),
        'rule_currencies'=>odfr_values($ruleCurrencies),
        'rule_units'=>odfr_values($ruleUnits),
        'rule_relations'=>odfr_values($ruleRelations),
        'rule_native_totals'=>odfr_values($ruleNativeTotals),
        'rule_authorities'=>odfr_values($ruleAuthorities),
        'fuel_sources'=>odfr_values($fuelSources),
        'rule_passenger_counts'=>odfr_values($rulePassengers),
        'rule_direction_counts'=>odfr_values($ruleDirections),
        'fx_rates'=>odfr_values($fxRates),
        'fuel_charges_rub'=>odfr_values($fuelCharges),
        'base_prices_rub'=>odfr_values($basePrices),
        'derived_totals_rub'=>odfr_values($derivedTotals),
        'stored_display_prices_rub'=>odfr_values($displayPrices),
    ];
}

function odfr_run(): array {
    $operation = odfr_operation(getenv('INT_DIRECTION_FUEL_READBACK_OPERATION'));
    $family = odfr_expected_family(getenv('INT_DIRECTION_FUEL_OPERATOR_FAMILY'));
    $departure = odfr_positive_ref(getenv('INT_DIRECTION_FUEL_DEPARTURE_ID'), 'departure_invalid');
    $country = odfr_positive_ref(getenv('INT_DIRECTION_FUEL_COUNTRY_ID'), 'country_invalid');
    $home = rtrim((string)getenv('HOME'), '/');
    if ($home === '') odfr_fail('home_missing');
    $root = $home . '/www/anytoour.ru';
    $opDir = $home . '/.anytoour-int-executor/' . $operation;
    $res = odfr_json($opDir . '/reservation.json', 65536);
    $terminal = odfr_json($opDir . '/result.json', 1048576);
    if (!is_array($res) || ($res['operation_id'] ?? null) !== $operation
        || !is_array($terminal) || ($terminal['operation_id'] ?? null) !== $operation
        || ($terminal['status'] ?? null) !== 'complete') odfr_fail('operation_receipt_missing');
    $from = $res['reserved_at'] ?? null;
    $to = filemtime($opDir . '/result.json');
    if (!is_int($from) || $from < 1 || !is_int($to) || $to < $from) odfr_fail('operation_window_invalid');

    $localRows = $terminal['local_readback'] ?? null;
    if (!is_array($localRows) || !array_is_list($localRows) || $localRows === []) odfr_fail('operation_local_receipt_invalid');
    $local = null;
    foreach ($localRows as $candidate) {
        if (is_array($candidate) && ($candidate['status'] ?? null) === 'complete'
            && (($candidate['providerOfferCounts']['andromeda'] ?? 0) > 0)) { $local = $candidate; break; }
    }
    $scope = is_array($local) ? odfr_digest($local['scopeDigest'] ?? null) : null;
    if ($scope === null) odfr_fail('operation_local_receipt_invalid');

    $generation = 2100000000 - (hexdec(substr(hash('sha256', $operation), 0, 6)) % 1000000);
    $searches = $home . '/.anytoour-andromeda/searches';
    if (!is_dir($searches) || is_link($searches)) odfr_fail('searches_invalid');
    $retainedFamilies=[]; $searchRefs=[]; $fileCount=0; $pageCount=0;
    foreach (new DirectoryIterator($searches) as $entry) {
        if ($entry->isDot()) continue;
        if (++$fileCount > ODFR_MAX_FILES) odfr_fail('inventory_too_large');
        if ($entry->isLink() || !$entry->isFile()) continue;
        $mtime = $entry->getMTime();
        if ($mtime < $from - 3 || $mtime > $to + 3) continue;
        $state = odfr_json($entry->getPathname());
        if (!is_array($state) || ($state['generation'] ?? null) !== $generation) continue;
        $snap = $state['store']['snapshot'] ?? null;
        if (!is_array($snap) || ($snap['provider'] ?? null) !== 'andromeda'
            || ($snap['generation'] ?? null) !== $generation || !is_array($snap['offers'] ?? null)) continue;
        $ref = $snap['search_ref'] ?? null; $page = $snap['page'] ?? null;
        if (!is_string($ref) || preg_match('/\A[a-f0-9]{64}\z/D', $ref) !== 1 || !is_int($page) || $page < 1) continue;
        $searchRefs[$ref] = true; ++$pageCount;
        foreach ($snap['offers'] as $offer) {
            if (!is_array($offer)) continue;
            $offerRef = $offer['offer_ref'] ?? null;
            if (!is_string($offerRef) || preg_match('/\Aoffer_[a-f0-9]{64}\z/D', $offerRef) !== 1) continue;
            $digest = hash('sha256', $offerRef);
            $of = odfr_operator_family($offer['operator'] ?? null) ?? 'other';
            if (isset($retainedFamilies[$digest]) && $retainedFamilies[$digest] !== $of) odfr_fail('retained_offer_conflict');
            $retainedFamilies[$digest] = $of;
        }
    }
    if (count($searchRefs) !== 1 || $pageCount < 1 || $retainedFamilies === []) odfr_fail('retained_cohort_not_unique');

    $dbPath = is_file($root.'/data/db-v1.php') ? $root.'/data/db-v1.php' : $root.'/v2/data/db-v1.php';
    if (!is_file($dbPath) || is_link($dbPath)) odfr_fail('db_runtime_missing');
    require_once $dbPath;
    $db = v2_data_db();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('START TRANSACTION READ ONLY');
    try {
        $stateQ = $db->prepare("SELECT latest_complete_refresh_token FROM anytour_offer_scope_state WHERE provider='andromeda' AND scope_sha256=:scope LIMIT 1");
        $stateQ->execute(['scope'=>$scope]);
        $refresh = $stateQ->fetchColumn();
        if (!is_string($refresh) || preg_match('/\A[a-f0-9]{64}\z/D', $refresh) !== 1) odfr_fail('latest_refresh_missing');
        $q = $db->prepare(
            "SELECT offer_ref_digest,display_price,currency,final_price_ready,final_price_verified,payload_json,payload_sha256 "
            ."FROM anytour_offers WHERE provider='andromeda' AND scope_sha256=:scope AND last_refresh_token=:refresh "
            ."AND is_active=1 ORDER BY id ASC"
        );
        $q->execute(['scope'=>$scope,'refresh'=>$refresh]);
        $dbRows = $q->fetchAll(PDO::FETCH_ASSOC);
        $summary = odfr_evaluate_rows($dbRows, $retainedFamilies, $family, $departure, $country);
        $summary = [
            'source'=>'int-operator-direction-fuel-mass-readback-v1',
            'operation_id'=>$operation,
            'scope_sha256'=>$scope,
            'refresh_sha256'=>$refresh,
            'stored_scope_count'=>count($dbRows),
            'retained_offer_count'=>count($retainedFamilies),
            'supplier_calls'=>0,
            'db_writes'=>0,
        ] + $summary;
        $summary['final_price_verified_promotions'] = $summary['verified_target_count'];
        $db->rollBack();
        return $summary;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

if (PHP_SAPI === 'cli' && getenv('INT_DIRECTION_FUEL_READBACK_LIBRARY_ONLY') !== '1') {
    try {
        echo json_encode(odfr_run(), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR), "\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "INT_OPERATOR_DIRECTION_FUEL_READBACK_ERROR:" . $e->getMessage() . "\n");
        exit(1);
    }
}
