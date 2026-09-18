<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(2); }
if ($argc !== 3) { fwrite(STDERR, "Usage: int_andromeda_selected_flight_proof_v5.php <site-root> <source-sha>\n"); exit(2); }

$site = realpath($argv[1]);
$source = $argv[2];
$runtime = realpath(dirname(__DIR__, 2));
if ($site === false || basename($site) !== 'anytoour.ru' || $runtime === false
    || !preg_match('/\A[a-f0-9]{40}\z/D', $source)) {
    throw new RuntimeException('ANDROMEDA_SELECTED_PROOF_ROOT');
}

$private = dirname($site, 2) . '/.anytoour-andromeda/search3-preview.php';
if (!is_file($private) || is_link($private)) throw new RuntimeException('ANDROMEDA_SELECTED_PROOF_PRIVATE_CONFIG');

require_once $runtime . '/v2/api-andromeda-search3-preview.php';
require_once $runtime . '/app/integrations/andromeda-local-offer-collector.php';
require_once $runtime . '/app/integrations/andromeda-saved-package-runtime.php';

$config = require $private;
if (!is_array($config) || ($config['enabled'] ?? null) !== true
    || !is_string($config['catalog_path'] ?? null) || $config['catalog_path'] === '') {
    throw new RuntimeException('ANDROMEDA_SELECTED_PROOF_CONFIG');
}

require_once $site . '/config.php';
$dbFile = is_file($site . '/data/db-v1.php') ? $site . '/data/db-v1.php' : $site . '/v2/data/db-v1.php';
require_once $dbFile;
$pdo = v2_data_db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$count = static function(PDO $pdo): array {
    $one = static fn(string $sql): int => (int)$pdo->query($sql)->fetchColumn();
    return [
        'andromeda_offers' => $one("SELECT COUNT(*) FROM anytour_offers WHERE provider='andromeda'"),
        'andromeda_active' => $one("SELECT COUNT(*) FROM anytour_offers WHERE provider='andromeda' AND is_active=1"),
        'andromeda_ready' => $one("SELECT COUNT(*) FROM anytour_offers WHERE provider='andromeda' AND final_price_ready=1"),
        'all_offers' => $one('SELECT COUNT(*) FROM anytour_offers'),
    ];
};
$before = $count($pdo);

$directory = dirname($config['catalog_path']) . '/searches';
if (!is_dir($directory) || is_link($directory) || basename($directory) !== 'searches') {
    throw new RuntimeException('ANDROMEDA_SELECTED_PROOF_SEARCH_DIR');
}

$request = [
    'generation' => 17171834,
    'params' => [
        'departureId' => '1', 'countryId' => '4',
        'dateFrom' => '2026-10-22', 'dateTo' => '2026-10-22',
        'nightsFrom' => 7, 'nightsTo' => 7,
        'adults' => 2, 'childs' => [], 'meal' => '7',
        'hotelCategory' => '', 'hotelRating' => '', 'hotelTypes' => [], 'hotelIds' => [],
        'hotelServices' => [], 'arrivalId' => '', 'regionIds' => [], 'subregionIds' => [],
        'operatorIds' => [], 'priceFrom' => '', 'priceTo' => '', 'currency' => 'RUB',
        'onlyCharter' => false, 'onlyDirect' => false,
    ],
];

$saved = anytour_andromeda_search3_catalog($config, $request);
$saved['excluded_operator_ids'] = $config['excluded_operator_ids'] ?? [];
$session = 'intsel' . bin2hex(random_bytes(12));
$search = anytour_andromeda_search3_run_pages(
    $request,
    static fn(array $pageRequest): array => anytour_andromeda_search3_run($pageRequest, $pdo, $saved, $config, $session)
);
$status = is_array($search) ? ($search['status'] ?? null) : null;
$drainedPartial = is_array($search)
    && $status === 'partial'
    && is_int($search['page'] ?? null)
    && is_int($search['pages_count'] ?? null)
    && $search['pages_count'] > 1
    && $search['page'] === $search['pages_count']
    && ($search['grouped'] ?? null) === true
    && ($search['first_page_only'] ?? null) === false
    && ($search['external_search_pending'] ?? null) === false;
if (!is_array($search)
    || ($search['provider'] ?? null) !== 'andromeda'
    || !is_string($search['search_ref'] ?? null)
    || !preg_match('/\A[a-f0-9]{64}\z/D', $search['search_ref'])
    || !is_int($search['pages_count'] ?? null)
    || $search['pages_count'] < 1
    || ($status !== 'complete' && !$drainedPartial)) {
    throw new RuntimeException('ANDROMEDA_SELECTED_PROOF_SEARCH');
}
$ref = $search['search_ref'];

$read = static function(string $path, int $maxBytes = 3000000): ?array {
    if (is_link($path) || !is_file($path)) return null;
    $size = filesize($path);
    if (!is_int($size) || $size < 2 || $size > $maxBytes) return null;
    try {
        $data = json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        return is_array($data) ? $data : null;
    } catch (Throwable) {
        return null;
    }
};

$firstPath = $directory . '/' . $ref . '-1.json';
$first = $read($firstPath);
if (!is_array($first) || ($first['generation'] ?? null) !== $request['generation']
    || !is_array($first['store']['snapshot'] ?? null)
    || !is_int($first['store']['created_at'] ?? null)) {
    throw new RuntimeException('ANDROMEDA_SELECTED_PROOF_COHORT');
}
$created = $first['store']['created_at'];
$target = $first['store']['snapshot']['pages_count'] ?? null;
if (!is_int($target) || $target < 0 || $target > AnyTourAndromedaPaginationV1::MAX_PAGES) {
    throw new RuntimeException('ANDROMEDA_SELECTED_PROOF_COHORT');
}

$rows = [];
$page = 1;
$currentTarget = max(1, $target);
while ($page <= $currentTarget) {
    $path = $page === 1 ? $firstPath : $directory . '/' . $ref . '-' . $created . '-' . $page . '.json';
    $state = $read($path);
    $snapshot = is_array($state) ? ($state['store']['snapshot'] ?? null) : null;
    if (!is_array($snapshot)
        || ($snapshot['provider'] ?? null) !== 'andromeda'
        || ($snapshot['search_ref'] ?? null) !== $ref
        || ($snapshot['generation'] ?? null) !== $request['generation']
        || ($snapshot['page'] ?? null) !== $page
        || !is_array($snapshot['offers'] ?? null) || !array_is_list($snapshot['offers'])
        || !is_array($snapshot['rejected'] ?? null) || !array_is_list($snapshot['rejected'])) {
        throw new RuntimeException('ANDROMEDA_SELECTED_PROOF_COHORT');
    }
    $decision = AnyTourAndromedaPaginationV1::nextTarget(
        $page,
        (int)$snapshot['pages_count'],
        count($snapshot['offers']),
        (string)($state['status'] ?? ''),
        count($snapshot['rejected']),
        $currentTarget
    );
    if (($decision['terminal'] ?? false) === true) break;
    foreach ($snapshot['offers'] as $offer) $rows[] = ['page' => $page, 'offer' => $offer, 'state' => $state];
    $currentTarget = $decision['target'];
    ++$page;
}

$selection = null;
$selectedState = null;
$selectedStem = null;
$owned = 0;
$mapped = 0;
$external = 0;
foreach ($rows as $row) {
    $offer = $row['offer'];
    if (!is_array($offer) || !AnyTourAndromedaLocalOfferCollectorV1::ownsOperator((string)($offer['operator'] ?? ''))) continue;
    ++$owned;
    $offerRef = $offer['offer_ref'] ?? null;
    $local = $offer['local_hotel_id'] ?? null;
    $operatorRef = $offer['operator_ref'] ?? null;
    if (!is_string($offerRef) || !preg_match('/\Aoffer_[a-f0-9]{64}\z/D', $offerRef)
        || !is_int($local) || $local < 1
        || (!is_string($operatorRef) && !is_int($operatorRef)) || (string)$operatorRef === '') continue;
    if (!anytour_andromeda_search3_mapping_allows($pdo, 4, $offer)) continue;
    $legacy = $pdo->prepare("SELECT COUNT(*) FROM anytour_hotel_sources s JOIN anytour_hotels h ON h.id=s.anytour_hotel_id WHERE s.namespace='legacy_catalog' AND s.external_key=? AND h.is_active=1");
    $legacy->execute([(string)$local]);
    if ((int)$legacy->fetchColumn() !== 1) continue;
    ++$mapped;
    if (($offer['transport_context']['freight_external'] ?? null) !== true) continue;
    ++$external;

    $candidate = [
        'provider' => 'andromeda',
        'search_ref' => $ref,
        'generation' => $request['generation'],
        'page' => $row['page'],
        'offer_ref' => $offerRef,
        'hotel_scope' => null,
        'operator_ref' => (string)$operatorRef,
        'local_id' => $local,
    ];
    $stem = $directory . '/' . $ref . '-' . $created . '-' . $row['page'] . '-' . $offerRef;
    if (file_exists($stem . '-package-attempt-v2.json')
        || file_exists($stem . '-package.json')
        || file_exists($stem . '-surcharge-v1.json')) {
        throw new RuntimeException('ANDROMEDA_SELECTED_PROOF_FRESH_COLLISION');
    }
    $selection = $candidate;
    $selectedState = $row['state'];
    $selectedStem = $stem;
    break;
}
if ($selection === null || !is_array($selectedState) || !is_string($selectedStem)) {
    throw new RuntimeException('ANDROMEDA_SELECTED_PROOF_NO_CANDIDATE');
}

$terminalBase = static function(array $after, string $failureStage, ?string $diagnosticCode = null) use (
    $before, $search, $rows, $owned, $mapped, $external
): array {
    $result = [
        'status' => 'failed_terminal_no_replay',
        'provider' => 'andromeda',
        'scope' => [
            'departure_id' => 1, 'country_id' => 4,
            'date_from' => '2026-10-22', 'date_to' => '2026-10-22',
            'nights' => 7, 'adults' => 2, 'meal' => 'AI',
        ],
        'search' => [
            'pages' => $search['pages_count'],
            'received_offers' => count($rows),
            'owned_operator_offers' => $owned,
            'current_mapped_offers_seen' => $mapped,
            'external_freight_mapped_seen' => $external,
        ],
        'failure_stage' => $failureStage,
        'db_before' => $before,
        'db_after' => $after,
        'db_writes' => 0,
        'booking_calls' => 0,
        'lead_calls' => 0,
        'mapping_writes' => 0,
        'autosave_calls' => 0,
        'search3_publication' => 0,
        'production_webroot_writes' => 0,
        'replay_allowed' => false,
    ];
    if ($diagnosticCode !== null) $result['package_diagnostic_code'] = $diagnosticCode;
    return $result;
};

try {
    $receipt = anytour_andromeda_capture_selected_package(
        $config,
        $saved,
        $selection,
        $pdo,
        $source,
        true,
        null,
        null,
        true
    );
} catch (Throwable) {
    $after = $count($pdo);
    if ($after !== $before) throw new RuntimeException('ANDROMEDA_SELECTED_PROOF_DB_MUTATION');
    $package = $read($selectedStem . '-package.json', 32768);
    $record = is_array($package) && is_array($package['record'] ?? null) ? $package['record'] : [];
    $allowedDiagnostics = [
        'ANDROMEDA_PACKAGE_DISABLED',
        'ANDROMEDA_PACKAGE_REPLAY_REFUSED',
        'ANDROMEDA_INVALID_PACKAGE_ID',
        'ANDROMEDA_LOGIN_REQUIRED',
        'ANDROMEDA_DISABLED',
        'ANDROMEDA_REQUEST_BUDGET',
        'ANDROMEDA_TRANSPORT_ERROR',
        'ANDROMEDA_INVALID_RESPONSE',
        'ANDROMEDA_HTTP_ERROR',
        'ANDROMEDA_RESPONSE_TOO_LARGE',
        'ANDROMEDA_SUPPLIER_ERROR',
        'ANDROMEDA_SECRET_ECHO',
        'ANDROMEDA_INVALID_PACKAGE_RESPONSE',
        'ANDROMEDA_PACKAGE_FAILURE_UNCLASSIFIED',
    ];
    $diagnostic = ($record['status'] ?? null) === 'unknown'
        && is_string($record['diagnostic_code'] ?? null)
        && in_array($record['diagnostic_code'], $allowedDiagnostics, true)
        ? $record['diagnostic_code'] : null;
    $result = $terminalBase($after, $diagnostic !== null ? 'package_outcome_unknown' : 'selected_actualization_abort', $diagnostic);
    echo json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n";
    exit(3);
}

if (!is_array($receipt) || ($receipt['status'] ?? null) !== 'captured'
    || ($receipt['reused'] ?? null) !== false) {
    throw new RuntimeException('ANDROMEDA_SELECTED_PROOF_CAPTURE');
}

$currentAllows = static function(array $offer) use ($pdo, $selection): bool {
    return ($offer['local_hotel_id'] ?? null) === $selection['local_id']
        && anytour_andromeda_search3_mapping_allows($pdo, 4, $offer);
};
$pricing = anytour_andromeda_read_saved_pricing(
    $directory,
    $selectedState['store'],
    $created,
    $selection,
    $currentAllows,
    time()
);
$verified = is_array($pricing)
    && ($pricing['state'] ?? null) === 'verified'
    && is_array($pricing['verified_quote'] ?? null);
$quote = $verified ? $pricing['verified_quote'] : [];
$amount = is_string($quote['final_price']['amount'] ?? null) ? $quote['final_price']['amount'] : '';
$positiveRub = $verified
    && ($quote['final_price']['currency'] ?? null) === 'RUB'
    && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $amount)
    && preg_match('/[1-9]/', $amount);

$sidecarPath = $selectedStem . '-surcharge-v1.json';
$sidecar = $read($sidecarPath, 32768);
$actualization = is_array($sidecar) && is_array($sidecar['actualization'] ?? null)
    ? $sidecar['actualization'] : [];
$diagnostic = is_array($sidecar) && is_array($sidecar['transport_money_diagnostic'] ?? null)
    ? $sidecar['transport_money_diagnostic'] : [];

$actualizationState = is_string($actualization['state'] ?? null)
    && preg_match('/\A[a-z_]{1,32}\z/D', $actualization['state'])
    ? $actualization['state'] : 'missing';
$failureClass = is_string($actualization['failure_class'] ?? null)
    && preg_match('/\A[A-Z0-9_:-]{1,96}\z/D', $actualization['failure_class'])
    ? $actualization['failure_class'] : null;
$actionsUsed = is_int($actualization['actions_used'] ?? null) ? $actualization['actions_used'] : null;
$diagInt = static function(array $d, string $key): ?int {
    $value = $d[$key] ?? null;
    return is_int($value) && $value >= 0 && $value <= 10000 ? $value : null;
};
$diagCurrencies = static function(array $d, string $key): array {
    $values = $d[$key] ?? null;
    if (!is_array($values) || !array_is_list($values) || count($values) > 32) return [];
    $out = [];
    foreach ($values as $value) {
        if (is_string($value) && preg_match('/\A[A-Z0-9_]{2,8}\z/D', $value)) $out[$value] = true;
    }
    $out = array_keys($out); sort($out, SORT_STRING); return $out;
};

$after = $count($pdo);
if ($after !== $before) throw new RuntimeException('ANDROMEDA_SELECTED_PROOF_DB_MUTATION');

$completed = $verified && $positiveRub && $actualizationState === 'verified';
$result = [
    'status' => $completed ? 'completed' : 'failed_terminal_no_replay',
    'provider' => 'andromeda',
    'scope' => [
        'departure_id' => 1, 'country_id' => 4,
        'date_from' => '2026-10-22', 'date_to' => '2026-10-22',
        'nights' => 7, 'adults' => 2, 'meal' => 'AI',
    ],
    'search' => [
        'pages' => $search['pages_count'],
        'received_offers' => count($rows),
        'owned_operator_offers' => $owned,
        'current_mapped_offers_seen' => $mapped,
        'external_freight_mapped_seen' => $external,
    ],
    'package_capture_reused' => false,
    'surcharge_status' => $receipt['surcharge']['status'] ?? null,
    'final_price_verified' => ($receipt['surcharge']['final_price_verified'] ?? null) === true && $verified,
    'verified_positive_rub_final' => (bool)$positiveRub,
    'quote_state' => $verified ? ($quote['quote_state'] ?? null) : null,
    'actualization_state' => $actualizationState,
    'actualization_actions_used' => $actionsUsed,
    'actualization_failure_class' => $failureClass,
    'transport_money_diagnostic' => [
        'ttavia_option_count' => $diagInt($diagnostic, 'ttavia_option_count'),
        'transport_detail_count' => $diagInt($diagnostic, 'transport_detail_count'),
        'markup_key_count' => $diagInt($diagnostic, 'markup_key_count'),
        'valid_markup_fact_count' => $diagInt($diagnostic, 'valid_markup_fact_count'),
        'distinct_markup_count' => $diagInt($diagnostic, 'distinct_markup_count'),
        'detail_currencies' => $diagCurrencies($diagnostic, 'detail_currencies'),
        'markup_currencies' => $diagCurrencies($diagnostic, 'markup_currencies'),
        'operator_rate_currencies' => $diagCurrencies($diagnostic, 'operator_rate_currencies'),
    ],
    'db_before' => $before,
    'db_after' => $after,
    'db_writes' => 0,
    'booking_calls' => 0,
    'lead_calls' => 0,
    'mapping_writes' => 0,
    'autosave_calls' => 0,
    'search3_publication' => 0,
    'production_webroot_writes' => 0,
    'replay_allowed' => false,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n";
exit($completed ? 0 : 3);
