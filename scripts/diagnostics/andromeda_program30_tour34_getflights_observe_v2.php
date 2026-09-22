<?php
declare(strict_types=1);

const INT_GF_WINDOW_FROM = 1790108545;
const INT_GF_WINDOW_TO = 1790108712;
const INT_GF_PROGRAM = '30';
const INT_GF_TOUR = '34';
const INT_GF_EXPECTED_GROUP = 12;
const INT_GF_OPERATION = 'int-andromeda-program30-tour34-getflights-20260922-v2';
const INT_GF_SAMPLE_INDEX = 1;
const INT_GF_V1_OFFER_SHA256 = '2ecccef6a5c420bde860dceb1132957b584dea44f243371a2bf4a5115ce39a00';

function intgf_read_json(string $path, int $maxBytes = 3000000): ?array {
    if (!is_file($path) || is_link($path)) return null;
    $size = filesize($path);
    if (!is_int($size) || $size < 2 || $size > $maxBytes) return null;
    try {
        $value = json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        return is_array($value) ? $value : null;
    } catch (Throwable $ignored) {
        return null;
    }
}
function intgf_safe_text(mixed $value, int $max = 160): ?string {
    if (!is_string($value)) return null;
    $value = trim($value);
    if ($value === '' || strlen($value) > $max || preg_match('/[\x00-\x1F\x7F]/', $value)) return null;
    return $value;
}
function intgf_ref(mixed $value): ?string {
    if (is_int($value) && $value > 0) return (string)$value;
    return is_string($value) && preg_match('/\A[1-9][0-9]{0,18}\z/D', $value) === 1 ? $value : null;
}
function intgf_money_units(mixed $value): ?int {
    if (is_int($value)) $value = (string)$value;
    if (is_float($value) && is_finite($value)) $value = rtrim(rtrim(sprintf('%.2F', $value), '0'), '.');
    if (!is_string($value) || preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?\z/D', $value, $m) !== 1) return null;
    $whole = (int)strtok($value, '.');
    if ($whole > intdiv(PHP_INT_MAX, 100)) return null;
    return $whole * 100 + (int)str_pad($m[1] ?? '', 2, '0');
}
function intgf_durable(string $path, array $value): void {
    $bytes = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (strlen($bytes) > 65536) throw new RuntimeException('checkpoint_too_large');
    $handle = fopen($path, 'x');
    if (!$handle) throw new RuntimeException('checkpoint_exists');
    try {
        if (fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle)) throw new RuntimeException('checkpoint_write');
        if (function_exists('fsync') && !fsync($handle)) throw new RuntimeException('checkpoint_sync');
    } finally {
        fclose($handle);
    }
    if (hash_file('sha256', $path) !== hash('sha256', $bytes)) throw new RuntimeException('checkpoint_readback');
}
function intgf_counter(string $private): array {
    $path = $private . '/monthly-requests.json';
    $month = gmdate('Y-m');
    if (!file_exists($path)) return ['month'=>$month,'reserved_requests'=>0,'monthly_limit'=>5000000,'remaining'=>5000000];
    if (!is_file($path) || is_link($path) || filesize($path) > 4096) throw new RuntimeException('monthly_counter_invalid');
    $state = json_decode((string)file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
    if (($state['month'] ?? null) !== $month) return ['month'=>$month,'reserved_requests'=>0,'monthly_limit'=>5000000,'remaining'=>5000000];
    $reserved = $state['reserved_requests'] ?? null;
    $limit = $state['monthly_limit'] ?? null;
    if (!is_int($reserved) || $reserved < 0 || $limit !== 5000000 || $reserved > $limit) throw new RuntimeException('monthly_counter_invalid');
    return ['month'=>$month,'reserved_requests'=>$reserved,'monthly_limit'=>$limit,'remaining'=>$limit-$reserved];
}
function intgf_safe_reason(Throwable $error): string {
    $message = $error->getMessage();
    return is_string($message) && preg_match('/\A[A-Za-z0-9_.:-]{1,96}\z/D', $message) === 1
        ? $message : 'operation_unconfirmed';
}
function intgf_context(array $offer): array {
    $tc = is_array($offer['transport_context'] ?? null) ? $offer['transport_context'] : [];
    return [
        'operator'=>intgf_safe_text($offer['operator'] ?? null),
        'program_key'=>intgf_ref($tc['program_ref'] ?? null),
        'program_label'=>intgf_safe_text($tc['program_label'] ?? null),
        'tour_key'=>intgf_ref($tc['tour_ref'] ?? null),
        'tour_label'=>intgf_safe_text($tc['tour_label'] ?? null),
        'spo_key'=>intgf_ref($tc['spo_ref'] ?? null),
        'spo_label'=>intgf_safe_text($tc['spo_label'] ?? null),
        'freight_external'=>is_bool($tc['freight_external'] ?? null) ? $tc['freight_external'] : null,
    ];
}
function intgf_selected_flight(array $flight): array {
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
                $value = intgf_safe_text($detail[$inKey] ?? null, $max);
                if ($value !== null && ($pattern === null || preg_match($pattern, $value) === 1)) $sets[$outKey][$value] = true;
            }
            $units = intgf_money_units($detail['markup'] ?? null);
            $currency = $detail['currency'] ?? null;
            if ($units !== null && is_string($currency) && preg_match('/\A[A-Z0-9_]{2,8}\z/D', $currency) === 1) {
                $markup[$currency . ':' . $units] = [
                    'amount'=>number_format($units / 100, 2, '.', ''),
                    'currency'=>$currency,
                ];
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

$out = [
    'schema_version'=>1,
    'source'=>'andromeda-program30-tour34-getflights-observe-v2',
    'operation'=>INT_GF_OPERATION,
    'status'=>'blocked',
    'target'=>['program_key'=>INT_GF_PROGRAM,'tour_key'=>INT_GF_TOUR],
    'supplier_calls'=>['package'=>0,'get_flights'=>0,'changeservice'=>0,'calc'=>0,'booking'=>0],
    'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0,
    'operation_replayed'=>false,'final_price_verified'=>false,
];
$lock = null; $private = ''; $operationDir = null; $reserved = false; $before = null;
try {
    if (PHP_SAPI !== 'cli') throw new RuntimeException('cli_required');
    $root = realpath(getcwd());
    if (!$root || basename($root) !== 'anytoour.ru') throw new RuntimeException('project_invalid');
    $preview = $root . '/_preview/search3-anex-candidate';
    $private = dirname($root, 2) . '/.anytoour-andromeda';
    $searches = $private . '/searches';
    if (!is_dir($preview) || is_link($preview) || !is_dir($searches) || is_link($searches)) throw new RuntimeException('runtime_missing');

    $lockPath = $private . '/grouped-search-update.lock';
    if (!is_file($lockPath) || is_link($lockPath)) throw new RuntimeException('publication_lock_missing');
    $lock = fopen($lockPath, 'r+b');
    if (!$lock || !flock($lock, LOCK_SH | LOCK_NB)) throw new RuntimeException('publication_busy');

    $checkpoints = [];
    $seen = 0;
    foreach (new DirectoryIterator($searches) as $entry) {
        if ($entry->isDot()) continue;
        if (++$seen > 100000) throw new RuntimeException('inventory_too_large');
        if ($entry->isLink() || !$entry->isFile()) continue;
        $mtime = $entry->getMTime();
        if ($mtime < INT_GF_WINDOW_FROM || $mtime > INT_GF_WINDOW_TO) continue;
        if (!str_ends_with($entry->getFilename(), '-surcharge-v1.json')) continue;
        $row = intgf_read_json($entry->getPathname(), 65536);
        $ctx = is_array($row['context'] ?? null) ? $row['context'] : [];
        $ref = $ctx['search_ref'] ?? null; $offerRef = $ctx['offer_ref'] ?? null;
        $page = $ctx['page'] ?? null; $generation = $ctx['generation'] ?? null;
        if (!is_string($ref) || preg_match('/\A[a-f0-9]{64}\z/D', $ref) !== 1
            || !is_string($offerRef) || preg_match('/\Aoffer_[a-f0-9]{64}\z/D', $offerRef) !== 1
            || !is_int($page) || $page < 1 || !is_int($generation) || $generation < 1) continue;
        $checkpoints[] = ['search_ref'=>$ref,'offer_ref'=>$offerRef,'page'=>$page,'generation'=>$generation];
    }
    if (count($checkpoints) !== 1) throw new RuntimeException('checkpoint_count_' . count($checkpoints));
    $checkpoint = $checkpoints[0];

    $firstPath = $searches . '/' . $checkpoint['search_ref'] . '-1.json';
    $first = intgf_read_json($firstPath);
    if (!is_array($first) || !is_array($first['store'] ?? null)
        || !is_array($first['store']['snapshot'] ?? null)
        || !is_int($first['store']['created_at'] ?? null)) throw new RuntimeException('first_page_invalid');
    $created = $first['store']['created_at'];
    $prefix = $checkpoint['search_ref'] . '-' . $created . '-';
    $paths = [1=>$firstPath];
    foreach (new DirectoryIterator($searches) as $entry) {
        if ($entry->isDot() || $entry->isLink() || !$entry->isFile()) continue;
        $name = $entry->getFilename();
        if (!str_starts_with($name, $prefix) || !str_ends_with($name, '.json')) continue;
        $middle = substr($name, strlen($prefix), -5);
        if (preg_match('/\A[1-9][0-9]{0,3}\z/D', $middle) !== 1) continue;
        $paths[(int)$middle] = $entry->getPathname();
    }
    ksort($paths, SORT_NUMERIC);

    $captured = null; $candidates = [];
    foreach ($paths as $page=>$path) {
        $state = intgf_read_json($path);
        $store = is_array($state['store'] ?? null) ? $state['store'] : [];
        $snapshot = is_array($store['snapshot'] ?? null) ? $store['snapshot'] : [];
        if (($snapshot['generation'] ?? null) !== $checkpoint['generation']
            || ($snapshot['page'] ?? null) !== $page || !is_array($snapshot['offers'] ?? null)) continue;
        $rawIds = is_array($store['raw_ids'] ?? null) ? $store['raw_ids'] : [];
        foreach ($snapshot['offers'] as $offer) {
            if (!is_array($offer)) continue;
            $offerRef = $offer['offer_ref'] ?? null;
            if (!is_string($offerRef) || preg_match('/\Aoffer_[a-f0-9]{64}\z/D', $offerRef) !== 1) continue;
            if ($offerRef === $checkpoint['offer_ref']) $captured = $offer;
            $ctx = intgf_context($offer);
            if ($ctx['program_key'] !== INT_GF_PROGRAM || $ctx['tour_key'] !== INT_GF_TOUR) continue;
            $supplierId = $rawIds[$offerRef] ?? null;
            if (is_int($supplierId)) $supplierId = (string)$supplierId;
            if (!is_string($supplierId) || $supplierId === '' || strlen($supplierId) > 512
                || preg_match('/[\x00-\x1F\x7F]/', $supplierId)) continue;
            $price = is_array($offer['price'] ?? null) ? $offer['price'] : [];
            $units = intgf_money_units($price['amount'] ?? null);
            $currency = $price['currency'] ?? null;
            if ($units === null || !is_string($currency) || preg_match('/\A[A-Z0-9_]{2,8}\z/D', $currency) !== 1) continue;
            $candidates[] = ['offer'=>$offer,'context'=>$ctx,'supplier_id'=>$supplierId,'price_units'=>$units,'currency'=>$currency];
        }
    }
    if (!is_array($captured)) throw new RuntimeException('captured_offer_missing');
    $capturedContext = intgf_context($captured);
    if ($capturedContext['operator'] === null) throw new RuntimeException('operator_missing');
    $candidates = array_values(array_filter($candidates, static fn(array $row): bool =>
        $row['context']['operator'] === $capturedContext['operator']));
    if (count($candidates) !== INT_GF_EXPECTED_GROUP) throw new RuntimeException('target_group_count_' . count($candidates));
    $currencies = array_values(array_unique(array_column($candidates, 'currency')));
    if (count($currencies) !== 1) throw new RuntimeException('target_currency_ambiguous');
    usort($candidates, static function(array $a, array $b): int {
        $price = $a['price_units'] <=> $b['price_units'];
        return $price !== 0 ? $price : strcmp($a['offer']['offer_ref'], $b['offer']['offer_ref']);
    });
    if (!isset($candidates[INT_GF_SAMPLE_INDEX])) throw new RuntimeException('sample_index_missing');
    $picked = $candidates[INT_GF_SAMPLE_INDEX];
    if (hash('sha256', $picked['offer']['offer_ref']) === INT_GF_V1_OFFER_SHA256) throw new RuntimeException('v1_offer_reused');

    require_once $preview . '/api-andromeda-quote-preview.php';
    if (!function_exists('anytour_andromeda_quote_supplier')
        || !class_exists('AnyTourAndromedaSearchSurcharge')
        || !class_exists('AnyTourAndromedaSelectedQuote')) throw new RuntimeException('runtime_not_installed');
    $configPath = $preview . '/.andromeda-private.php';
    if (!is_file($configPath) || is_link($configPath)) throw new RuntimeException('private_runtime_missing');
    $config = require $configPath;
    if (!is_array($config) || ($config['enabled'] ?? null) !== true) throw new RuntimeException('private_runtime_missing');

    $before = intgf_counter($private);
    $out['supplier_counter_before'] = $before;
    if ($before['remaining'] < 20) throw new RuntimeException('monthly_quota_headroom');
    $operationDir = $private . '/' . INT_GF_OPERATION;
    if (file_exists($operationDir) || is_link($operationDir)) throw new RuntimeException('operation_exists_no_replay');
    if (!mkdir($operationDir, 0700)) throw new RuntimeException('reservation_failed');
    intgf_durable($operationDir . '/reservation.json', [
        'operation'=>INT_GF_OPERATION,
        'program_key'=>INT_GF_PROGRAM,'tour_key'=>INT_GF_TOUR,
        'offer_ref_sha256'=>hash('sha256', $picked['offer']['offer_ref']),
        'created_at'=>time(),
    ]);
    $reserved = true;

    $out['target'] = [
        'operator'=>$picked['context']['operator'],
        'program_key'=>INT_GF_PROGRAM,
        'program_label'=>$picked['context']['program_label'],
        'tour_key'=>INT_GF_TOUR,
        'tour_label'=>$picked['context']['tour_label'],
        'spo_key'=>$picked['context']['spo_key'],
        'spo_label'=>$picked['context']['spo_label'],
        'retained_freight_external'=>$picked['context']['freight_external'],
        'retained_group_offer_count'=>count($candidates),
        'selected_listing_price'=>$picked['offer']['price'],
        'selected_offer_ref_sha256'=>hash('sha256', $picked['offer']['offer_ref']),
    ];

    [$client, $actions] = anytour_andromeda_quote_supplier($config);
    $package = $client->package($picked['supplier_id']);
    $out['supplier_calls']['package'] = 1;
    $doc = $package['claimDocument'][0] ?? null;
    if (!is_array($doc)) throw new RuntimeException('claim_invalid');
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
        $safeSelected = [];
        foreach ($selection['selected'] as $flight) if (is_array($flight)) $safeSelected[] = intgf_selected_flight($flight);
        $out['cheapest_selection'] = [
            'candidate_counts'=>$selection['candidate_counts'],
            'target_currency'=>$selection['target_currency'],
            'selected_flights'=>$safeSelected,
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
        $out['status'] = $reserved ? 'unknown' : 'blocked';
        $out['reason'] = intgf_safe_reason($error);
    }
} finally {
    if ($private !== '') {
        try {
            $after = intgf_counter($private);
            $out['supplier_counter_after'] = $after;
            $out['supplier_counter_delta'] = is_array($before) && $after['month'] === $before['month']
                ? max(0, $after['reserved_requests'] - $before['reserved_requests']) : null;
            $out['supplier_counter_delta_is_exact_operation_billing'] = false;
        } catch (Throwable $ignored) {}
    }
    if ($reserved && is_string($operationDir) && is_dir($operationDir) && !file_exists($operationDir . '/result.json')) {
        try { intgf_durable($operationDir . '/result.json', $out); } catch (Throwable $ignored) {}
    }
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
