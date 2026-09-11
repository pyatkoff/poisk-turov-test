<?php
declare(strict_types=1);

/** MATCH #1971: read-only probe for the separate owner-configured Tourvisor ANEX credential. */

function match_tv_anex_emit(array $value, int $exitCode = 0): void
{
    echo 'MATCH_JSON:' . json_encode(
        $value,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
    exit($exitCode);
}

function match_tv_anex_fail(string $stage, string $reason, array $extra = []): void
{
    $safeStage = preg_match('/\A[a-z0-9_]{1,40}\z/D', $stage) ? $stage : 'probe';
    $safeReason = preg_match('/\A[a-z0-9_]{1,80}\z/D', $reason) ? $reason : 'probe_failed';
    match_tv_anex_emit(array_merge([
        'status' => 'failed',
        'stage' => $safeStage,
        'reason' => $safeReason,
        'token_value_recorded' => false,
        'database_calls' => 0,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'continue_calls' => 0,
        'tour_detail_calls' => 0,
        'booking_calls' => 0,
    ], $extra), 2);
}

function match_tv_anex_complete(array $node): bool
{
    foreach ($node as $key => $value) {
        if (is_array($value) && match_tv_anex_complete($value)) return true;
        if (is_string($key) && strtolower($key) === 'progress' && is_numeric($value) && (float)$value >= 100) return true;
        if (is_string($key) && strtolower($key) === 'status' && is_string($value)
            && in_array(strtolower($value), ['complete', 'completed', 'ready', 'done'], true)) return true;
    }
    return false;
}

function match_tv_anex_rows(array $data): array
{
    if (array_is_list($data)) return $data;
    foreach (['items', 'hotels', 'results', 'data'] as $key) {
        if (isset($data[$key]) && is_array($data[$key])) {
            $rows = match_tv_anex_rows($data[$key]);
            if ($rows !== []) return $rows;
        }
    }
    return [];
}

function match_tv_anex_operator_hints($node, array &$result): void
{
    if (!is_array($node) || count($result) >= 100) return;
    foreach ($node as $key => $value) {
        if (is_string($key) && strpos(strtolower($key), 'operator') !== false && is_scalar($value)) {
            $item = $key . '=' . substr((string)$value, 0, 160);
            $result[$item] = true;
            if (count($result) >= 100) return;
        }
        if (is_array($value)) match_tv_anex_operator_hints($value, $result);
    }
}

try {
    ini_set('display_errors', '0');
    ini_set('log_errors', '0');

    $root = realpath(getcwd());
    if (!$root || basename($root) !== 'anytoour.ru') match_tv_anex_fail('config', 'server_root_invalid');
    $config = $root . '/config.php';
    if (!is_file($config) || is_link($config)) match_tv_anex_fail('config', 'config_missing');

    ob_start();
    require_once $config;
    ob_end_clean();

    if (!defined('TOURVISOR_ANEX_JWT')) match_tv_anex_fail('config', 'anex_jwt_constant_missing');
    $token = trim((string)constant('TOURVISOR_ANEX_JWT'));
    if (stripos($token, 'Bearer ') === 0) $token = trim(substr($token, 7));
    if ($token === '' || strlen($token) < 20) match_tv_anex_fail('config', 'anex_jwt_empty');

    putenv('TOURVISOR_JWT=' . $token);
    $client = is_file($root . '/data/tourvisor-client-v1.php')
        ? $root . '/data/tourvisor-client-v1.php'
        : $root . '/v2/data/tourvisor-client-v1.php';
    if (!is_file($client) || is_link($client)) match_tv_anex_fail('config', 'tourvisor_client_missing');
    require_once $client;

    $base = [
        'token_configured' => true,
        'token_value_recorded' => false,
        'database_calls' => 0,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'continue_calls' => 0,
        'tour_detail_calls' => 0,
        'booking_calls' => 0,
    ];

    try {
        $departures = v2_data_tv_get('/departures');
    } catch (Throwable $e) {
        match_tv_anex_fail('departures', 'tourvisor_error', $base + [
            'message' => substr((string)$e->getMessage(), 0, 160),
        ]);
    }
    $base['departures_count'] = count(match_tv_anex_rows($departures));

    try {
        $start = v2_data_tv_get('/tours/search', [
            'departureId' => 1,
            'countryId' => 4,
            'dateFrom' => '2026-09-18',
            'dateTo' => '2026-09-18',
            'nightsFrom' => 8,
            'nightsTo' => 8,
            'adults' => 2,
            'currency' => 'RUB',
        ]);
    } catch (Throwable $e) {
        match_tv_anex_fail('search_start', 'tourvisor_error', $base + [
            'message' => substr((string)$e->getMessage(), 0, 160),
        ]);
    }

    $searchId = (int)($start['searchId'] ?? $start['id'] ?? 0);
    if ($searchId <= 0) match_tv_anex_fail('search_start', 'search_id_missing', $base);

    $complete = false;
    $polls = 0;
    for ($attempt = 0; $attempt < 30; $attempt++) {
        if ($attempt > 0) sleep(2);
        try {
            $status = v2_data_tv_get('/tours/search/' . $searchId . '/status', ['operatorStatus' => false]);
        } catch (Throwable $e) {
            match_tv_anex_fail('status', 'tourvisor_error', $base + [
                'status_polls' => $polls,
                'message' => substr((string)$e->getMessage(), 0, 160),
            ]);
        }
        $polls++;
        if (match_tv_anex_complete($status)) {
            $complete = true;
            break;
        }
    }
    if (!$complete) match_tv_anex_fail('status', 'search_not_complete', $base + ['status_polls' => $polls]);

    try {
        $results = v2_data_tv_get('/tours/search/' . $searchId, ['limit' => 100]);
    } catch (Throwable $e) {
        match_tv_anex_fail('results', 'tourvisor_error', $base + [
            'status_polls' => $polls,
            'message' => substr((string)$e->getMessage(), 0, 160),
        ]);
    }

    $hints = [];
    match_tv_anex_operator_hints($results, $hints);
    $operatorHints = array_keys($hints);
    $anexHints = array_values(array_filter(
        $operatorHints,
        static fn(string $item): bool => strpos(strtolower($item), 'anex') !== false
    ));

    match_tv_anex_emit($base + [
        'status' => 'completed',
        'status_polls' => $polls,
        'result_row_count' => count(match_tv_anex_rows($results)),
        'result_top_level_keys' => array_slice(array_map('strval', array_keys($results)), 0, 20),
        'operator_hints' => $operatorHints,
        'anex_operator_hints' => $anexHints,
        'anex_only_observed' => count($operatorHints) > 0 && count($operatorHints) === count($anexHints),
        'search_contract' => [
            'departureId' => 1,
            'countryId' => 4,
            'date' => '2026-09-18',
            'nights' => 8,
            'adults' => 2,
        ],
    ]);
} catch (Throwable $e) {
    match_tv_anex_fail('probe', 'probe_exception', [
        'exception_class' => get_class($e),
        'message' => preg_match('/\A[a-zA-Z0-9_ .:-]{1,160}\z/D', (string)$e->getMessage())
            ? (string)$e->getMessage() : '',
    ]);
}
