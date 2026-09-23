<?php
declare(strict_types=1);

/**
 * Generic retained-program getFlights evidence probe for INT.
 *
 * The permanent executor reserves the parent operation before this script runs.
 * This script never books, changes service or calls calc. It selects one retained
 * offer from one exact operator/program/tour + distinct-SPO ordinal, then performs
 * only login/package/get_flights through the installed private Andromeda runtime.
 */

function ipf_fail(string $reason): never { throw new RuntimeException($reason); }
function ipf_json(string $path, int $max = 3000000): ?array {
    if (!is_file($path) || is_link($path)) return null;
    $size = filesize($path);
    if (!is_int($size) || $size < 2 || $size > $max) return null;
    try {
        $value = json_decode((string)file_get_contents($path), true, 96, JSON_THROW_ON_ERROR);
        return is_array($value) ? $value : null;
    } catch (Throwable $ignored) { return null; }
}
function ipf_text(mixed $value, int $max = 180): ?string {
    if (!is_string($value)) return null;
    $value = trim($value);
    if ($value === '' || strlen($value) > $max || preg_match('/[\x00-\x1F\x7F]/', $value)) return null;
    return $value;
}
function ipf_ref(mixed $value): ?string {
    if (is_int($value) && $value > 0) return (string)$value;
    return is_string($value) && preg_match('/\A[1-9][0-9]{0,18}\z/D', $value) === 1 ? $value : null;
}
function ipf_positive_int_env(string $name, int $max): int {
    $value = getenv($name);
    if (!is_string($value) || preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D', $value) !== 1) ipf_fail('env_'.$name);
    $n = (int)$value;
    if ($n < 0 || $n > $max) ipf_fail('env_'.$name);
    return $n;
}
function ipf_money_units(mixed $value): ?int {
    if (is_int($value)) $value = (string)$value;
    if (is_float($value) && is_finite($value)) $value = rtrim(rtrim(sprintf('%.2F', $value), '0'), '.');
    if (!is_string($value) || preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?\z/D', $value, $m) !== 1) return null;
    $whole = (int)strtok($value, '.');
    if ($whole > intdiv(PHP_INT_MAX, 100)) return null;
    return $whole * 100 + (int)str_pad($m[1] ?? '', 2, '0');
}
function ipf_context(array $offer): array {
    $tc = is_array($offer['transport_context'] ?? null) ? $offer['transport_context'] : [];
    return [
        'operator' => ipf_text($offer['operator'] ?? null),
        'program_key' => ipf_ref($tc['program_ref'] ?? null),
        'program_label' => ipf_text($tc['program_label'] ?? null),
        'tour_key' => ipf_ref($tc['tour_ref'] ?? null),
        'tour_label' => ipf_text($tc['tour_label'] ?? null),
        'spo_key' => ipf_ref($tc['spo_ref'] ?? null),
        'spo_label' => ipf_text($tc['spo_label'] ?? null),
        'freight_external' => is_bool($tc['freight_external'] ?? null) ? $tc['freight_external'] : null,
    ];
}
function ipf_operator_matches(?string $operator, string $family): bool {
    if ($operator === null) return false;
    $value = mb_strtolower($operator);
    return match ($family) {
        'funsun' => (str_contains($value, 'fun') && str_contains($value, 'sun')) || str_contains($value, 'фан'),
        'intourist' => str_contains($value, 'intourist') || str_contains($value, 'интурист'),
        default => false,
    };
}
function ipf_sort_representatives(array $rows): array {
    usort($rows, static function(array $a,array $b): int {
        $mapped = ($a['mapped'] ? 0 : 1) <=> ($b['mapped'] ? 0 : 1);
        if ($mapped !== 0) return $mapped;
        $price = $a['price_units'] <=> $b['price_units'];
        return $price !== 0 ? $price : strcmp($a['offer']['offer_ref'], $b['offer']['offer_ref']);
    });
    return $rows;
}
function ipf_choose_representatives(array $spoCandidates,array $charterHotelCandidates,string $family,array $freightCounts): array {
    if ($spoCandidates !== []) {
        return ['basis'=>'distinct_spo','rows'=>ipf_sort_representatives(array_values($spoCandidates))];
    }
    if ($family !== 'intourist') ipf_fail('target_group_missing');
    if (($freightCounts['true'] ?? 0) !== 0 || ($freightCounts['null'] ?? 0) !== 0
        || ($freightCounts['false'] ?? 0) < 2) ipf_fail('charter_group_not_strict');
    $rows=ipf_sort_representatives(array_values($charterHotelCandidates));
    if (count($rows) < 2) ipf_fail('charter_independent_samples_missing');
    return ['basis'=>'distinct_mapped_hotel','rows'=>$rows];
}
function ipf_counter(string $directory): array {
    $path = $directory . '/monthly-requests.json';
    $month = gmdate('Y-m');
    if (!file_exists($path)) return ['month'=>$month,'reserved_requests'=>0,'monthly_limit'=>5000000,'remaining'=>5000000];
    if (!is_file($path) || is_link($path) || filesize($path) > 4096) ipf_fail('monthly_counter_invalid');
    $state = json_decode((string)file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
    if (($state['month'] ?? null) !== $month) return ['month'=>$month,'reserved_requests'=>0,'monthly_limit'=>5000000,'remaining'=>5000000];
    $reserved = $state['reserved_requests'] ?? null;
    $limit = $state['monthly_limit'] ?? null;
    if (!is_int($reserved) || $reserved < 0 || $limit !== 5000000 || $reserved > $limit) ipf_fail('monthly_counter_invalid');
    return ['month'=>$month,'reserved_requests'=>$reserved,'monthly_limit'=>$limit,'remaining'=>$limit-$reserved];
}
function ipf_safe_reason(Throwable $error): string {
    $message = $error->getMessage();
    return is_string($message) && preg_match('/\A[A-Za-z0-9_.:-]{1,96}\z/D', $message) === 1
        ? $message : 'operation_unconfirmed';
}
function ipf_selected_flight(array $flight): array {
    $sets = [
        'flight_numbers'=>[], 'airline_codes'=>[], 'airline_names'=>[],
        'departure_airports'=>[], 'arrival_airports'=>[],
        'departure_datetimes'=>[], 'arrival_datetimes'=>[], 'baggage'=>[],
    ];
    $markup = [];
    foreach (($flight['details'] ?? []) as $block) {
        if (!is_array($block) || !is_array($block['detail'] ?? null)) continue;
        foreach ($block['detail'] as $detail) {
            if (!is_array($detail)) continue;
            $spec = [
                'flight_numbers'=>['flight_number',32,'/\A[A-Za-z0-9 .()\/-]{1,32}\z/D'],
                'airline_codes'=>['marketing_airline',8,'/\A[A-Za-z0-9]{2,8}\z/D'],
                'airline_names'=>['full_marketing_airline',120,null],
                'departure_airports'=>['departureAirportCode',8,'/\A[A-Z0-9]{2,8}\z/D'],
                'arrival_airports'=>['arrivalAirportCode',8,'/\A[A-Z0-9]{2,8}\z/D'],
                'departure_datetimes'=>['depart_datetime',32,'/\A[0-9T:+Z-]{10,32}\z/D'],
                'arrival_datetimes'=>['arrival_datetime',32,'/\A[0-9T:+Z-]{10,32}\z/D'],
                'baggage'=>['bagage',32,'/\A[A-Za-z0-9 +._\/-]{1,32}\z/D'],
            ];
            foreach ($spec as $outKey => [$inKey,$max,$pattern]) {
                $value = ipf_text($detail[$inKey] ?? null, $max);
                if ($value !== null && ($pattern === null || preg_match($pattern, $value) === 1)) $sets[$outKey][$value] = true;
            }
            $units = ipf_money_units($detail['markup'] ?? null);
            $currency = $detail['currency'] ?? null;
            if ($units !== null && is_string($currency) && preg_match('/\A[A-Z0-9_]{2,8}\z/D', $currency) === 1) {
                $markup[$currency.':'.$units] = ['amount'=>number_format($units / 100, 2, '.', ''),'currency'=>$currency];
            }
        }
    }
    $out = ['direction'=>(string)($flight['direction'] ?? '')];
    foreach ($sets as $key=>$values) {
        $list = array_keys($values); sort($list, SORT_STRING); $out[$key] = $list;
    }
    $out['markup'] = count($markup) === 1 ? array_values($markup)[0] : null;
    return $out;
}

if (getenv('INT_PROGRAM_PROBE_LIBRARY_ONLY') === '1') return;

$out = [
    'schema_version'=>1,
    'source'=>'int-andromeda-program-getflights-probe-v1',
    'status'=>'blocked',
    'supplier_calls'=>[
        'login_attempted'=>false,'package'=>0,'get_flights'=>0,
        'changeservice'=>0,'calc'=>0,'booking'=>0,
    ],
    'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0,
    'final_price_verified'=>false,
];
$accessStarted = false;
$counterDirectory = null;
$beforeCounter = null;

try {
    if (PHP_SAPI !== 'cli') ipf_fail('cli_required');
    $root = realpath(getcwd());
    if (!$root || basename($root) !== 'anytoour.ru') ipf_fail('project_invalid');

    $targetOperation = getenv('INT_PROGRAM_PROBE_TARGET_OPERATION');
    $family = getenv('INT_PROGRAM_PROBE_OPERATOR_FAMILY');
    $program = getenv('INT_PROGRAM_PROBE_PROGRAM_KEY');
    $tour = getenv('INT_PROGRAM_PROBE_TOUR_KEY');
    $sampleIndex = ipf_positive_int_env('INT_PROGRAM_PROBE_SAMPLE_INDEX', 1000);
    if (!is_string($targetOperation)
        || preg_match('/\Aint-andromeda-[a-z0-9-]{8,80}-v[1-9][0-9]*\z/D', $targetOperation) !== 1) ipf_fail('target_operation');
    if (!is_string($family) || !in_array($family, ['funsun','intourist'], true)) ipf_fail('operator_family');
    if (!is_string($program) || preg_match('/\A[1-9][0-9]{0,18}\z/D', $program) !== 1) ipf_fail('program_key');
    if (!is_string($tour) || preg_match('/\A[1-9][0-9]{0,18}\z/D', $tour) !== 1) ipf_fail('tour_key');

    $home = rtrim((string)getenv('HOME'), '/');
    if ($home === '') ipf_fail('home_missing');
    $opDir = $home.'/.anytoour-int-executor/'.$targetOperation;
    $reservation = ipf_json($opDir.'/reservation.json', 65536);
    $terminal = ipf_json($opDir.'/result.json', 1048576);
    if (!is_array($reservation) || ($reservation['operation_id'] ?? null) !== $targetOperation
        || !is_array($terminal) || ($terminal['operation_id'] ?? null) !== $targetOperation) ipf_fail('target_receipt_missing');
    if (!in_array($terminal['status'] ?? null, ['complete','reconciled_read_only'], true)) ipf_fail('target_not_terminal');

    $generation = 2100000000 - (hexdec(substr(hash('sha256', $targetOperation), 0, 6)) % 1000000);
    $searches = $home.'/.anytoour-andromeda/searches';
    if (!is_dir($searches) || is_link($searches)) ipf_fail('searches_invalid');

    $paths = [];
    $searchRefs = [];
    $seen = 0;
    foreach (new DirectoryIterator($searches) as $entry) {
        if ($entry->isDot()) continue;
        if (++$seen > 100000) ipf_fail('inventory_too_large');
        if ($entry->isLink() || !$entry->isFile()) continue;
        $state = ipf_json($entry->getPathname());
        $snapshot = is_array($state['store']['snapshot'] ?? null) ? $state['store']['snapshot'] : null;
        if (!is_array($snapshot) || ($snapshot['generation'] ?? null) !== $generation
            || !is_array($snapshot['offers'] ?? null)) continue;
        $ref = $snapshot['search_ref'] ?? null;
        $page = $snapshot['page'] ?? null;
        if (!is_string($ref) || preg_match('/\A[a-f0-9]{64}\z/D', $ref) !== 1 || !is_int($page) || $page < 1) continue;
        $searchRefs[$ref] = true;
        $paths[$page] = $entry->getPathname();
    }
    if (count($searchRefs) !== 1 || $paths === []) ipf_fail('target_search_not_unique');
    ksort($paths, SORT_NUMERIC);

    $candidates = [];
    $charterHotelCandidates = [];
    $groupFreightCounts = ['true'=>0,'false'=>0,'null'=>0];
    $allGroupOffers = 0;
    foreach ($paths as $page=>$path) {
        $state = ipf_json($path);
        $store = is_array($state['store'] ?? null) ? $state['store'] : [];
        $snapshot = is_array($store['snapshot'] ?? null) ? $store['snapshot'] : [];
        if (($snapshot['generation'] ?? null) !== $generation || ($snapshot['page'] ?? null) !== $page) continue;
        $rawIds = is_array($store['raw_ids'] ?? null) ? $store['raw_ids'] : [];
        foreach (($snapshot['offers'] ?? []) as $offer) {
            if (!is_array($offer)) continue;
            $ctx = ipf_context($offer);
            if (!ipf_operator_matches($ctx['operator'], $family)
                || $ctx['program_key'] !== $program || $ctx['tour_key'] !== $tour) continue;
            ++$allGroupOffers;
            ++$groupFreightCounts[$ctx['freight_external']===true?'true':($ctx['freight_external']===false?'false':'null')];
            $offerRef = $offer['offer_ref'] ?? null;
            $spo = $ctx['spo_key'];
            if (!is_string($offerRef) || preg_match('/\Aoffer_[a-f0-9]{64}\z/D', $offerRef) !== 1) continue;
            $supplierId = $rawIds[$offerRef] ?? null;
            if (is_int($supplierId)) $supplierId = (string)$supplierId;
            if (!is_string($supplierId) || $supplierId === '' || strlen($supplierId) > 512
                || preg_match('/[\x00-\x1F\x7F]/', $supplierId)) continue;
            $price = is_array($offer['price'] ?? null) ? $offer['price'] : [];
            $units = ipf_money_units($price['amount'] ?? null);
            $currency = $price['currency'] ?? null;
            if ($units === null || !is_string($currency) || preg_match('/\A[A-Z0-9_]{2,8}\z/D', $currency) !== 1) continue;
            $localHotelId = is_int($offer['local_hotel_id'] ?? null) && $offer['local_hotel_id'] > 0
                ? $offer['local_hotel_id'] : null;
            $row = [
                'offer'=>$offer,'context'=>$ctx,'supplier_id'=>$supplierId,
                'price_units'=>$units,'currency'=>$currency,'mapped'=>$localHotelId!==null,
            ];
            if ($spo !== null) {
                if (!isset($candidates[$spo])) {
                    $candidates[$spo] = $row;
                } else {
                    $current = $candidates[$spo];
                    $rank = [$row['mapped'] ? 0 : 1, $row['price_units'], $offerRef];
                    $currentRank = [$current['mapped'] ? 0 : 1, $current['price_units'], $current['offer']['offer_ref']];
                    if (($rank <=> $currentRank) < 0) $candidates[$spo] = $row;
                }
            } elseif ($family === 'intourist' && $ctx['freight_external'] === false && $localHotelId !== null) {
                $hotelKey=(string)$localHotelId;
                if (!isset($charterHotelCandidates[$hotelKey])
                    || [$row['price_units'],$offerRef] < [$charterHotelCandidates[$hotelKey]['price_units'],$charterHotelCandidates[$hotelKey]['offer']['offer_ref']]) {
                    $charterHotelCandidates[$hotelKey]=$row;
                }
            }
        }
    }
    if ($allGroupOffers < 1) ipf_fail('target_group_missing');
    $selection=ipf_choose_representatives($candidates,$charterHotelCandidates,$family,$groupFreightCounts);
    $sampleBasis=$selection['basis'];
    $representatives=$selection['rows'];
    if (!isset($representatives[$sampleIndex])) ipf_fail('sample_index_missing');
    $picked = $representatives[$sampleIndex];

    $preview = $root.'/_preview/search3-anex-candidate';
    require_once $preview.'/api-andromeda-quote-preview.php';
    if (!function_exists('anytour_andromeda_quote_supplier')
        || !class_exists('AnyTourAndromedaSearchSurcharge')
        || !class_exists('AnyTourAndromedaSelectedQuote')) ipf_fail('runtime_not_installed');
    $configPath = $preview.'/.andromeda-private.php';
    if (!is_file($configPath) || is_link($configPath)) ipf_fail('private_runtime_missing');
    $config = require $configPath;
    if (!is_array($config) || ($config['enabled'] ?? null) !== true
        || !is_string($config['catalog_path'] ?? null)) ipf_fail('private_runtime_missing');

    $counterDirectory = dirname($config['catalog_path']);
    $beforeCounter = ipf_counter($counterDirectory);
    $out['supplier_counter_before'] = $beforeCounter;
    if ($beforeCounter['remaining'] < 20) ipf_fail('monthly_quota_headroom');

    $ctx = $picked['context'];
    $out['target'] = [
        'target_operation'=>$targetOperation,
        'operator_family'=>$family,
        'operator'=>$ctx['operator'],
        'program_key'=>$program,
        'program_label'=>$ctx['program_label'],
        'tour_key'=>$tour,
        'tour_label'=>$ctx['tour_label'],
        'sample_basis'=>$sampleBasis,
        'sample_distinct_spo_index'=>$sampleBasis==='distinct_spo'?$sampleIndex:null,
        'sample_distinct_mapped_hotel_index'=>$sampleBasis==='distinct_mapped_hotel'?$sampleIndex:null,
        'retained_group_offer_count'=>$allGroupOffers,
        'retained_distinct_spo_count'=>count($candidates),
        'retained_distinct_mapped_hotel_count'=>count($charterHotelCandidates),
        'spo_key'=>$ctx['spo_key'],
        'spo_label'=>$ctx['spo_label'],
        'retained_freight_external'=>$ctx['freight_external'],
        'mapped_local_hotel'=>$picked['mapped'],
        'selected_listing_price'=>$picked['offer']['price'],
        'selected_offer_ref_sha256'=>hash('sha256', $picked['offer']['offer_ref']),
    ];

    $accessStarted = true;
    $out['supplier_calls']['login_attempted'] = true;
    [$client, $actions] = anytour_andromeda_quote_supplier($config);
    $package = $client->package($picked['supplier_id']);
    $out['supplier_calls']['package'] = 1;
    $doc = $package['claimDocument'][0] ?? null;
    if (!is_array($doc)) ipf_fail('claim_invalid');
    $external = $doc['freightExternal'] ?? null;
    if (is_string($external) && preg_match('/\A[01]\z/D', $external) === 1) $external = (int)$external;
    $out['package_freight_external'] = is_int($external) ? $external : null;

    $flights = $actions->getFlights($package);
    $out['supplier_calls']['get_flights'] = 1;
    $out['get_flights_diagnostic'] = AnyTourAndromedaSearchSurcharge::diagnostic($flights);
    $out['search_surcharge_estimate'] = AnyTourAndromedaSearchSurcharge::estimate($flights, $picked['offer']['price']);
    $out['fuel_surcharges_reported'] = AnyTourAndromedaSelectedQuote::reportedFuelSurcharges($flights);
    $selection = AnyTourAndromedaSearchSurcharge::cheapestRequiredFlightSelection($flights, $picked['offer']['price']);
    if ($selection === null) {
        $out['cheapest_selection'] = null;
    } else {
        $selected = [];
        foreach ($selection['selected'] as $flight) if (is_array($flight)) $selected[] = ipf_selected_flight($flight);
        $out['cheapest_selection'] = [
            'candidate_counts'=>$selection['candidate_counts'],
            'target_currency'=>$selection['target_currency'],
            'selected_flights'=>$selected,
        ];
    }
    $out['status'] = 'complete';
    $out['outcome'] = 'targeted_get_flights_observed_no_selection_applied';
} catch (Throwable $error) {
    if (class_exists('AnyTourAndromedaSupplierException') && $error instanceof AnyTourAndromedaSupplierException) {
        $out['status'] = 'supplier_rejected';
        $out['reason'] = 'ANDROMEDA_SUPPLIER_ERROR';
        $out['supplier_error_facts'] = $error->diagnosticFacts();
    } else {
        $out['status'] = $accessStarted ? 'unknown_no_replay' : 'blocked_before_supplier';
        $out['reason'] = ipf_safe_reason($error);
    }
} finally {
    if (is_string($counterDirectory)) {
        try {
            $after = ipf_counter($counterDirectory);
            $out['supplier_counter_after'] = $after;
            $out['supplier_counter_delta'] = is_array($beforeCounter) && $after['month'] === $beforeCounter['month']
                ? max(0, $after['reserved_requests'] - $beforeCounter['reserved_requests']) : null;
            $out['supplier_counter_delta_is_exact_operation_billing'] = false;
        } catch (Throwable $ignored) {}
    }
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
