<?php
declare(strict_types=1);

/** One immutable P0/P1 proof: retained PRICE -> saved surcharge -> served response -> verified quote. */
const ANYTOUR_ANDROMEDA_SURCHARGE_E2E_OPERATION = 'andromeda-search-surcharge-e2e-1717-v4-egypt-2026-12-27-2a2c5-11-8n';
const ANYTOUR_ANDROMEDA_SURCHARGE_E2E_RUNTIME_SOURCE = '067bba00e664b0f76d7239d72306f8fb70252f00';
const ANYTOUR_ANDROMEDA_SURCHARGE_E2E_MIN_HEADROOM = 100;

function anytour_andromeda_surcharge_e2e_request(): array
{
    return [
        'generation' => 17171227,
        'page' => 1,
        'params' => [
            'countryId' => '1', 'departureId' => '1',
            'dateFrom' => '2026-12-27', 'dateTo' => '2026-12-27',
            'nightsFrom' => 8, 'nightsTo' => 8, 'adults' => 2, 'childs' => [5, 11],
            'meal' => '', 'hotelCategory' => '', 'hotelIds' => [], 'regionIds' => [],
            'subregionIds' => [], 'operatorIds' => [], 'currency' => 'RUB',
        ],
        'andromeda_operator_ids' => ['5'],
    ];
}

function anytour_andromeda_surcharge_e2e_pick(array $projection): array
{
    foreach ($projection['hotels'] ?? [] as $hotel) {
        if (!is_array($hotel) || !is_int($hotel['local_id'] ?? null) || $hotel['local_id'] < 1) continue;
        foreach ($hotel['tours'] ?? [] as $tour) {
            $context = is_array($tour) ? ($tour['offer_context'] ?? null) : null;
            $price = is_array($tour) ? ($tour['price'] ?? null) : null;
            if (!is_array($context) || ($context['provider'] ?? null) !== 'andromeda'
                || !is_string($context['offer_ref'] ?? null) || !preg_match('/^offer_[a-f0-9]{64}$/D', $context['offer_ref'])
                || !is_array($price) || !is_string($price['amount'] ?? null) || !is_string($price['currency'] ?? null)) continue;
            return ['local_id' => $hotel['local_id'], 'tour' => $tour];
        }
    }
    throw new RuntimeException('no_mapped_offer');
}

function anytour_andromeda_surcharge_e2e_find(array $projection, string $offerRef): array
{
    foreach ($projection['hotels'] ?? [] as $hotel) foreach ($hotel['tours'] ?? [] as $tour) {
        if (is_array($tour) && ($tour['offer_ref'] ?? null) === $offerRef) return $tour;
    }
    throw new RuntimeException('retained_offer_missing');
}

function anytour_andromeda_surcharge_e2e_verify(array $before, array $after): array
{
    $base = $before['price'] ?? null;
    // Keep the complete original base below; the calculated fact contains money only.
    $baseMoney = is_array($base) ? ['amount' => $base['amount'] ?? null, 'currency' => $base['currency'] ?? null] : null;
    $fact = $after['search_surcharge'] ?? null;
    $display = $after['price'] ?? null;
    if (!is_array($base) || ($after['base_search_price'] ?? null) !== $base || !is_array($fact) || !is_array($display)
        || ($fact['search_price'] ?? null) !== $baseMoney || ($fact['state'] ?? null) !== 'estimated'
        || ($fact['arithmetic_applied'] ?? null) !== true || ($fact['final_price_verified'] ?? null) !== false
        || ($fact['search_price_with_surcharge'] ?? null) !== $display || !is_array($fact['party_surcharge'] ?? null)) {
        throw new RuntimeException('listing_surcharge_not_applied');
    }
    foreach ([$base, $fact['party_surcharge'], $display] as $money) {
        if (!is_string($money['amount'] ?? null) || !is_string($money['currency'] ?? null)
            || $money['currency'] !== $base['currency']) throw new RuntimeException('listing_money_invalid');
    }
    return [
        'base' => ['amount' => $base['amount'], 'currency' => $base['currency']],
        'surcharge' => ['amount' => $fact['party_surcharge']['amount'], 'currency' => $fact['party_surcharge']['currency']],
        'served_price' => ['amount' => $display['amount'], 'currency' => $display['currency']],
        'price_basis' => 'transport_surcharge_estimate',
        'arithmetic_applied' => true, 'final_price_verified' => false,
    ];
}

function anytour_andromeda_surcharge_e2e_verify_quote(array $listedTour, array $quote): array
{
    $reference = $listedTour['listing_price_ref'] ?? null;
    $observation = $quote['served_price_observation'] ?? null;
    $priceBasis = isset($listedTour['search_surcharge']) ? 'transport_surcharge_estimate' : 'search_base';
    // The listing carries provenance; the server receipt intentionally carries money only.
    $servedMoney = $listedTour['price'] ?? null;
    if (is_array($servedMoney) && array_key_exists('source', $servedMoney)) {
        if ($priceBasis !== 'transport_surcharge_estimate' || $servedMoney['source'] !== 'derived_search_estimate') {
            throw new RuntimeException('served_quote_observation_invalid');
        }
        unset($servedMoney['source']);
    }
    if ($priceBasis === 'search_base' && is_array($servedMoney)) {
        // Only the normalizer's known metadata may be separated from receipt money.
        if ((array_key_exists('currency_id', $servedMoney) && (!is_string($servedMoney['currency_id'])
                || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $servedMoney['currency_id'])))
            || (array_key_exists('kind', $servedMoney) && $servedMoney['kind'] !== 'offer')
            || (array_key_exists('fees', $servedMoney) && $servedMoney['fees'] !== 'unknown')
            || (array_key_exists('final', $servedMoney) && $servedMoney['final'] !== false)) {
            throw new RuntimeException('served_quote_observation_invalid');
        }
        unset($servedMoney['currency_id'], $servedMoney['kind'], $servedMoney['fees'], $servedMoney['final']);
    }
    if (!is_string($reference) || !preg_match('/^listing_[a-f0-9]{64}$/D', $reference)
        || ($quote['state'] ?? null) !== 'quote_verified' || ($quote['final_price_verified'] ?? null) !== true
        || !is_array($quote['final_price'] ?? null) || !is_array($observation)
        || ($observation['schema_version'] ?? null) !== 1 || ($observation['provider'] ?? null) !== 'andromeda'
        || ($observation['basis'] ?? null) !== 'search_api_response' || ($observation['state'] ?? null) !== 'comparable'
        || ($observation['price_basis'] ?? null) !== $priceBasis
        || ($observation['served_price'] ?? null) !== $servedMoney
        || ($observation['final_price'] ?? null) !== $quote['final_price']
        || ($observation['final_price_verified'] ?? null) !== true
        || !is_string($observation['signed_delta_amount'] ?? null)
        || !is_string($observation['absolute_delta_amount'] ?? null)
        || !is_int($observation['relative_delta_bps'] ?? null) || $observation['relative_delta_bps'] < 0) {
        throw new RuntimeException('served_quote_observation_invalid');
    }
    return [
        'listing_price_ref_sha256' => hash('sha256', $reference),
        'price_basis' => $priceBasis,
        'served_price' => $observation['served_price'],
        'final_price' => $observation['final_price'],
        'signed_delta_amount' => $observation['signed_delta_amount'],
        'absolute_delta_amount' => $observation['absolute_delta_amount'],
        'relative_delta_bps' => $observation['relative_delta_bps'],
        'final_price_verified' => true,
    ];
}

/** Integration counter evidence only; concurrent consumers mean its delta is not exact operation billing. */
function anytour_andromeda_surcharge_e2e_counter(string $directory): array
{
    $path = $directory . '/monthly-requests.json';
    $month = gmdate('Y-m');
    if (!file_exists($path)) {
        return ['month' => $month, 'reserved_requests' => 0, 'monthly_limit' => 5000000,
            'remaining' => 5000000, 'scope' => 'this_integration'];
    }
    if (!is_file($path) || is_link($path) || filesize($path) > 4096) throw new RuntimeException('monthly_counter_invalid');
    $state = json_decode((string)file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($state)) throw new RuntimeException('monthly_counter_invalid');
    if (($state['month'] ?? null) !== $month) {
        return ['month' => $month, 'reserved_requests' => 0, 'monthly_limit' => 5000000,
            'remaining' => 5000000, 'scope' => 'this_integration'];
    }
    $reserved = $state['reserved_requests'] ?? null;
    $limit = $state['monthly_limit'] ?? null;
    if (!is_int($reserved) || $reserved < 0 || !is_int($limit) || $limit !== 5000000
        || ($state['scope'] ?? null) !== 'this_integration' || $reserved > $limit) {
        throw new RuntimeException('monthly_counter_invalid');
    }
    return ['month' => $month, 'reserved_requests' => $reserved, 'monthly_limit' => $limit,
        'remaining' => $limit - $reserved, 'scope' => 'this_integration'];
}

function anytour_andromeda_surcharge_e2e_listing_receipts(): array
{
    if (session_status() !== PHP_SESSION_NONE || session_id() === '') throw new RuntimeException('listing_session_invalid');
    if (!session_start()) throw new RuntimeException('listing_session_invalid');
    try {
        $receipts = $_SESSION['andromeda_listing_prices_v1'] ?? [];
        if (!is_array($receipts)) throw new RuntimeException('listing_session_invalid');
        return $receipts;
    } finally {
        if (!session_write_close()) throw new RuntimeException('listing_session_invalid');
    }
}

function anytour_andromeda_surcharge_e2e_durable(string $path, array $value): void
{
    $bytes = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (strlen($bytes) > 65536) throw new RuntimeException('checkpoint_too_large');
    $file = fopen($path, 'x'); if (!$file) throw new RuntimeException('checkpoint_exists');
    try {
        if (fwrite($file, $bytes) !== strlen($bytes) || !fflush($file)) throw new RuntimeException('checkpoint_write');
        if (function_exists('fsync') && !fsync($file)) throw new RuntimeException('checkpoint_sync');
    } finally { fclose($file); }
    if (!is_file($path) || is_link($path) || hash_file('sha256', $path) !== hash('sha256', $bytes)) {
        throw new RuntimeException('checkpoint_readback');
    }
}

function anytour_andromeda_surcharge_e2e_reason(Throwable $error): string
{
    $reason = $error->getMessage();
    return is_string($reason) && preg_match('/^[A-Za-z0-9_.:-]{1,96}$/D', $reason) ? $reason : 'operation_unconfirmed';
}

function anytour_andromeda_surcharge_e2e_run(string $root): array
{
    $lock = null; $pdo = null; $readOnly = false; $reserved = false; $operationDir = null;
    $phase = 'preflight'; $quoteStarted = false; $private = '';
    $result = ['status' => 'blocked', 'operation' => ANYTOUR_ANDROMEDA_SURCHARGE_E2E_OPERATION,
        'supplier_scenario' => 1, 'mapping_writes' => 0, 'booking_calls' => 0, 'calc_calls' => 0,
        'phase' => $phase];
    try {
        if (PHP_SAPI !== 'cli' || $root === '' || basename($root) !== 'anytoour.ru' || realpath($root) !== $root) {
            throw new RuntimeException('project_invalid');
        }
        $target = $root . '/_preview/search3-anex-candidate';
        $private = dirname($root, 2) . '/.anytoour-andromeda';
        foreach ([$target, $target . '/app/integrations', $private] as $directory) {
            if (!is_dir($directory) || is_link($directory)) throw new RuntimeException('runtime_missing');
        }
        $lockPath = $private . '/grouped-search-update.lock';
        if (!is_file($lockPath) || is_link($lockPath)) throw new RuntimeException('publication_lock_missing');
        $lock = fopen($lockPath, 'r+b');
        if (!$lock || !flock($lock, LOCK_SH | LOCK_NB)) throw new RuntimeException('publication_busy');

        global $andromedaApp;
        require_once $target . '/api-andromeda-quote-preview.php';
        require_once $target . '/app/integrations/andromeda-saved-package-runtime.php';
        foreach (['anytour_andromeda_search3_run', 'anytour_andromeda_search3_detail',
            'anytour_andromeda_search3_record_response', 'anytour_andromeda_capture_selected_package',
            'anytour_andromeda_quote_run'] as $function) {
            if (!function_exists($function)) throw new RuntimeException('runtime_not_installed');
        }
        $dbPath = $root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
        $configPath = $target . '/.andromeda-private.php';
        if (!is_file($dbPath) || is_link($dbPath) || !is_file($configPath) || is_link($configPath)) {
            throw new RuntimeException('private_runtime_missing');
        }
        require_once $dbPath;
        $config = require $configPath;
        if (!is_array($config) || ($config['enabled'] ?? null) !== true) throw new RuntimeException('private_runtime_missing');

        $counterBefore = anytour_andromeda_surcharge_e2e_counter($private);
        $result['supplier_counter_before'] = $counterBefore;
        if ($counterBefore['remaining'] < ANYTOUR_ANDROMEDA_SURCHARGE_E2E_MIN_HEADROOM) {
            throw new OverflowException('monthly_quota_headroom');
        }

        $operationDir = $private . '/' . ANYTOUR_ANDROMEDA_SURCHARGE_E2E_OPERATION;
        if (file_exists($operationDir) || is_link($operationDir)) throw new RuntimeException('operation_exists_no_replay');
        if (!mkdir($operationDir, 0700)) throw new RuntimeException('reservation_failed');
        $request = anytour_andromeda_surcharge_e2e_request();
        anytour_andromeda_surcharge_e2e_durable($operationDir . '/reservation.json', [
            'operation' => ANYTOUR_ANDROMEDA_SURCHARGE_E2E_OPERATION,
            'runtime_source' => ANYTOUR_ANDROMEDA_SURCHARGE_E2E_RUNTIME_SOURCE,
            'scenario_sha256' => hash('sha256', json_encode($request, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'minimum_counter_headroom' => ANYTOUR_ANDROMEDA_SURCHARGE_E2E_MIN_HEADROOM,
            'created_at' => time(),
        ]);
        $reserved = true; $phase = 'reserved'; $result['phase'] = $phase;

        $pdo = v2_data_db();
        if (!$pdo instanceof PDO) throw new RuntimeException('database_missing');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('START TRANSACTION READ ONLY'); $readOnly = true;
        $catalog = anytour_andromeda_search3_catalog($config, $request);

        session_name('ANYTOUR_ANDROMEDA_SEARCH3');
        ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1');
        if (!session_start()) throw new RuntimeException('listing_session_invalid');
        $session = session_id();
        $_SESSION = [];
        if (!session_write_close() || $session === '') throw new RuntimeException('listing_session_invalid');

        $phase = 'search'; $result['phase'] = $phase;
        $beforeProjection = anytour_andromeda_search3_run($request, $pdo, $catalog, $config, $session);
        $picked = anytour_andromeda_surcharge_e2e_pick($beforeProjection); $beforeTour = $picked['tour'];
        $result['local_hotel_id'] = $picked['local_id'];
        $detailRequest = $request; $detailRequest['offer_context'] = $beforeTour['offer_context'];
        $detail = anytour_andromeda_search3_detail($detailRequest, $pdo, $catalog, $config, $session);
        $selection = $detail['selected_offer'] ?? null;
        if (!is_array($selection) || ($selection['provider'] ?? null) !== 'andromeda'
            || ($selection['local_id'] ?? null) !== $picked['local_id']) throw new RuntimeException('selected_offer_invalid');

        $phase = 'surcharge_capture'; $result['phase'] = $phase;
        $capture = anytour_andromeda_capture_selected_package($config, $catalog, $selection, $pdo,
            ANYTOUR_ANDROMEDA_SURCHARGE_E2E_RUNTIME_SOURCE, true, null, null, true);
        $surchargeStatus = $capture['surcharge']['status'] ?? null;
        if (($capture['status'] ?? null) !== 'captured' || !is_array($capture['surcharge'] ?? null)
            || !in_array($surchargeStatus, ['complete', 'unavailable'], true)) {
            throw new RuntimeException('surcharge_capture_invalid');
        }

        $phase = 'retained_listing'; $result['phase'] = $phase;
        $afterProjection = anytour_andromeda_search3_run($request, $pdo, $catalog, $config, $session);
        $afterTour = anytour_andromeda_surcharge_e2e_find($afterProjection, (string)$beforeTour['offer_ref']);
        if ($surchargeStatus === 'complete') {
            $listingMoney = anytour_andromeda_surcharge_e2e_verify($beforeTour, $afterTour);
        } else {
            if (($afterTour['price'] ?? null) !== ($beforeTour['price'] ?? null) || isset($afterTour['search_surcharge'])) {
                throw new RuntimeException('unavailable_surcharge_changed_listing');
            }
            $listingMoney = ['base' => $beforeTour['price'], 'surcharge' => null,
                'served_price' => $afterTour['price'], 'price_basis' => 'search_base',
                'arithmetic_applied' => false, 'final_price_verified' => false];
        }
        // Retain verified earlier facts even if a later quote is refused or unknown.
        $result['listing_money'] = $listingMoney;

        $phase = 'listing_recorded'; $result['phase'] = $phase;
        $recordedProjection = anytour_andromeda_search3_record_response($afterProjection);
        $listedTour = anytour_andromeda_surcharge_e2e_find($recordedProjection, (string)$beforeTour['offer_ref']);
        $listingRef = $listedTour['listing_price_ref'] ?? null;
        if (!is_string($listingRef) || !preg_match('/^listing_[a-f0-9]{64}$/D', $listingRef)) {
            throw new RuntimeException('listing_price_ref_missing');
        }
        $listingPrices = anytour_andromeda_surcharge_e2e_listing_receipts();
        if (!isset($listingPrices[$listingRef])) throw new RuntimeException('listing_price_receipt_missing');

        $quoteRequest = $request;
        $quoteRequest['action'] = 'quote';
        $quoteRequest['offer_context'] = $listedTour['offer_context'];
        $quoteRequest['listing_price_ref'] = $listingRef;
        $phase = 'quote'; $result['phase'] = $phase; $quoteStarted = true;
        $quote = anytour_andromeda_quote_run($quoteRequest, $pdo, $catalog, $config, $session, $listingPrices);
        $quoteEvidence = anytour_andromeda_surcharge_e2e_verify_quote($listedTour, $quote);
        $result['calc_calls'] = 1;

        $counterAfter = anytour_andromeda_surcharge_e2e_counter($private);
        $counterDelta = $counterAfter['month'] === $counterBefore['month']
            ? max(0, $counterAfter['reserved_requests'] - $counterBefore['reserved_requests']) : null;
        $phase = 'complete';
        $result = array_replace($result, [
            'status' => 'complete', 'outcome' => 'served_quote_compared', 'phase' => $phase,
            'received_offers' => (int)($beforeProjection['received_offers'] ?? 0),
            'mapped_offers' => (int)($beforeProjection['mapped_offers'] ?? 0),
            'local_hotel_id' => $picked['local_id'],
            'offer_ref_sha256' => hash('sha256', (string)$beforeTour['offer_ref']),
            'package_reused' => (bool)($capture['reused'] ?? false),
            'surcharge_status' => $surchargeStatus,
            'surcharge_reused' => (bool)($capture['surcharge']['reused'] ?? false),
            'retained_reprojection' => true,
            'listing_money' => $listingMoney,
            'quote_observation' => $quoteEvidence,
            'supplier_counter_after' => $counterAfter,
            'supplier_counter_delta' => $counterDelta,
            'supplier_counter_delta_is_exact_operation_billing' => false,
        ]);
        $pdo->rollBack(); $readOnly = false;
        anytour_andromeda_surcharge_e2e_durable($operationDir . '/result.json', $result);
        return $result;
    } catch (Throwable $error) {
        if ($readOnly && $pdo instanceof PDO && $pdo->inTransaction()) { try { $pdo->rollBack(); } catch (Throwable $ignored) {} }
        $result['status'] = $reserved ? 'unknown' : 'blocked';
        $result['phase'] = $phase;
        $result['reason'] = anytour_andromeda_surcharge_e2e_reason($error);
        if ($quoteStarted && ($result['calc_calls'] ?? 0) === 0) $result['calc_calls'] = null;
        if ($private !== '') {
            try {
                $after = anytour_andromeda_surcharge_e2e_counter($private);
                $result['supplier_counter_after'] = $after;
                $before = $result['supplier_counter_before'] ?? null;
                $result['supplier_counter_delta'] = is_array($before) && $after['month'] === ($before['month'] ?? null)
                    ? max(0, $after['reserved_requests'] - $before['reserved_requests']) : null;
                $result['supplier_counter_delta_is_exact_operation_billing'] = false;
            } catch (Throwable $ignored) {}
        }
        if ($reserved && is_string($operationDir) && is_dir($operationDir) && !file_exists($operationDir . '/result.json')) {
            try { anytour_andromeda_surcharge_e2e_durable($operationDir . '/result.json', $result); } catch (Throwable $ignored) {}
        }
        return $result;
    } finally {
        if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    }
}

if (!defined('ANYTOUR_ANDROMEDA_SURCHARGE_E2E_TEST_MODE')) {
    ini_set('display_errors', '0'); ini_set('log_errors', '0'); ini_set('zend.exception_ignore_args', '1');
    error_reporting(0); umask(0077); ob_start();
    $result = anytour_andromeda_surcharge_e2e_run((string)getcwd());
    while (ob_get_level()) ob_end_clean();
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    // ssh_php discards nonzero-exit output. Preserve application failure JSON;
    // the owner-controlled caller must validate status and fail AFTER saving it.
    exit(0);
}