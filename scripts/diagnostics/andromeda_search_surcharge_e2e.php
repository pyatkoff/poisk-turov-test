<?php
declare(strict_types=1);

/** One immutable P0 proof: retained PRICE -> selected offer -> saved surcharge -> retained list. */
const ANYTOUR_ANDROMEDA_SURCHARGE_E2E_OPERATION = 'andromeda-search-surcharge-e2e-1717-v1-egypt-2026-12-10-2a';
const ANYTOUR_ANDROMEDA_SURCHARGE_E2E_RUNTIME_SOURCE = 'b32993261eb8657004e7d4fd146b65a95264d4bc';

function anytour_andromeda_surcharge_e2e_request(): array
{
    return [
        'generation' => 17171210,
        'page' => 1,
        'params' => [
            'countryId' => '1', 'departureId' => '1',
            'dateFrom' => '2026-12-10', 'dateTo' => '2026-12-10',
            'nightsFrom' => 10, 'nightsTo' => 10, 'adults' => 2, 'childs' => [],
            'meal' => '7', 'hotelCategory' => '', 'hotelIds' => [], 'regionIds' => [],
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
    $fact = $after['search_surcharge'] ?? null;
    $display = $after['price'] ?? null;
    if (!is_array($base) || ($after['base_search_price'] ?? null) !== $base || !is_array($fact) || !is_array($display)
        || ($fact['search_price'] ?? null) !== $base || ($fact['state'] ?? null) !== 'estimated'
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
        'display_estimate' => ['amount' => $display['amount'], 'currency' => $display['currency']],
        'arithmetic_applied' => true, 'final_price_verified' => false,
    ];
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
    $result = ['status' => 'blocked', 'operation' => ANYTOUR_ANDROMEDA_SURCHARGE_E2E_OPERATION,
        'supplier_scenario' => 1, 'mapping_writes' => 0, 'booking_calls' => 0, 'calc_calls' => 0];
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
        require_once $target . '/api-andromeda-search3-preview.php';
        require_once $target . '/app/integrations/andromeda-saved-package-runtime.php';
        foreach (['anytour_andromeda_search3_run', 'anytour_andromeda_search3_detail',
            'anytour_andromeda_capture_selected_package', 'anytour_andromeda_read_saved_surcharge'] as $function) {
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

        $operationDir = $private . '/' . ANYTOUR_ANDROMEDA_SURCHARGE_E2E_OPERATION;
        if (file_exists($operationDir) || is_link($operationDir)) throw new RuntimeException('operation_exists_no_replay');
        if (!mkdir($operationDir, 0700)) throw new RuntimeException('reservation_failed');
        $request = anytour_andromeda_surcharge_e2e_request();
        anytour_andromeda_surcharge_e2e_durable($operationDir . '/reservation.json', [
            'operation' => ANYTOUR_ANDROMEDA_SURCHARGE_E2E_OPERATION,
            'runtime_source' => ANYTOUR_ANDROMEDA_SURCHARGE_E2E_RUNTIME_SOURCE,
            'scenario_sha256' => hash('sha256', json_encode($request, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'created_at' => time(),
        ]);
        $reserved = true;

        $pdo = v2_data_db();
        if (!$pdo instanceof PDO) throw new RuntimeException('database_missing');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('START TRANSACTION READ ONLY'); $readOnly = true;
        $catalog = anytour_andromeda_search3_catalog($config, $request);
        $session = hash('sha256', ANYTOUR_ANDROMEDA_SURCHARGE_E2E_OPERATION . '|session');

        $beforeProjection = anytour_andromeda_search3_run($request, $pdo, $catalog, $config, $session);
        $picked = anytour_andromeda_surcharge_e2e_pick($beforeProjection); $beforeTour = $picked['tour'];
        $detailRequest = $request; $detailRequest['offer_context'] = $beforeTour['offer_context'];
        $detail = anytour_andromeda_search3_detail($detailRequest, $pdo, $catalog, $config, $session);
        $selection = $detail['selected_offer'] ?? null;
        if (!is_array($selection) || ($selection['provider'] ?? null) !== 'andromeda'
            || ($selection['local_id'] ?? null) !== $picked['local_id']) throw new RuntimeException('selected_offer_invalid');

        $capture = anytour_andromeda_capture_selected_package($config, $catalog, $selection, $pdo,
            ANYTOUR_ANDROMEDA_SURCHARGE_E2E_RUNTIME_SOURCE, true, null, null, true);
        if (($capture['status'] ?? null) !== 'captured' || !is_array($capture['surcharge'] ?? null)) {
            throw new RuntimeException('surcharge_capture_invalid');
        }
        if (($capture['surcharge']['status'] ?? null) !== 'complete' || !is_array($capture['surcharge']['fact'] ?? null)) {
            $result = array_replace($result, [
                'status' => 'complete', 'outcome' => 'surcharge_unavailable',
                'received_offers' => (int)($beforeProjection['received_offers'] ?? 0),
                'mapped_offers' => (int)($beforeProjection['mapped_offers'] ?? 0),
                'local_hotel_id' => $picked['local_id'],
                'package_reused' => (bool)($capture['reused'] ?? false),
                'surcharge_reused' => (bool)($capture['surcharge']['reused'] ?? false),
            ]);
        } else {
            $afterProjection = anytour_andromeda_search3_run($request, $pdo, $catalog, $config, $session);
            $afterTour = anytour_andromeda_surcharge_e2e_find($afterProjection, (string)$beforeTour['offer_ref']);
            $money = anytour_andromeda_surcharge_e2e_verify($beforeTour, $afterTour);
            $result = array_replace($result, [
                'status' => 'complete', 'outcome' => 'listing_estimate_applied',
                'received_offers' => (int)($beforeProjection['received_offers'] ?? 0),
                'mapped_offers' => (int)($beforeProjection['mapped_offers'] ?? 0),
                'local_hotel_id' => $picked['local_id'],
                'offer_ref_sha256' => hash('sha256', (string)$beforeTour['offer_ref']),
                'package_reused' => (bool)($capture['reused'] ?? false),
                'surcharge_reused' => (bool)($capture['surcharge']['reused'] ?? false),
                'retained_reprojection' => true, 'money' => $money,
            ]);
        }
        $pdo->rollBack(); $readOnly = false;
        anytour_andromeda_surcharge_e2e_durable($operationDir . '/result.json', $result);
        return $result;
    } catch (Throwable $error) {
        if ($readOnly && $pdo instanceof PDO && $pdo->inTransaction()) { try { $pdo->rollBack(); } catch (Throwable $ignored) {} }
        $result['status'] = $reserved ? 'unknown' : 'blocked';
        $result['reason'] = anytour_andromeda_surcharge_e2e_reason($error);
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
    exit(($result['status'] ?? null) === 'complete' ? 0 : 1);
}
