<?php
declare(strict_types=1);

/** MATCH #1971: isolated read-only derived-catalog probe for owner-configured Tourvisor ANEX credential. */

function mtv7_emit(array $value, int $code = 0): void
{
    echo 'MATCH_JSON:' . json_encode(
        $value,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
    exit($code);
}

function mtv7_fail(string $stage, string $reason, array $extra = []): void
{
    $safeStage = preg_match('/\A[a-z0-9_]{1,40}\z/D', $stage) ? $stage : 'probe';
    $safeReason = preg_match('/\A[a-z0-9_]{1,80}\z/D', $reason) ? $reason : 'probe_failed';
    mtv7_emit(array_merge([
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
        'search_started' => false,
    ], $extra), 2);
}

function mtv7_rows(array $data): array
{
    if (array_is_list($data)) return $data;
    foreach (['data', 'items', 'results', 'operators', 'countries', 'departures', 'hotels'] as $key) {
        if (isset($data[$key]) && is_array($data[$key])) {
            $rows = mtv7_rows($data[$key]);
            if ($rows !== []) return $rows;
        }
    }
    return [];
}

function mtv7_entities(array $data, int $max = 300): array
{
    $out = [];
    foreach (mtv7_rows($data) as $row) {
        if (!is_array($row)) continue;
        $id = $row['id'] ?? $row['operatorId'] ?? $row['countryId'] ?? $row['departureId'] ?? null;
        $name = $row['name'] ?? $row['russianName'] ?? $row['fullName'] ?? $row['operatorName'] ?? null;
        if ($id === null && !is_string($name)) continue;
        $out[] = [
            'id' => $id === null ? null : (string)$id,
            'name' => is_string($name) ? substr($name, 0, 140) : null,
        ];
        if (count($out) >= $max) break;
    }
    return $out;
}

function mtv7_dates($node, array &$out): void
{
    if (count($out) >= 1000) return;
    if (is_string($node)) {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $node)) {
            $out[$node] = true;
        } elseif (preg_match_all('/\b(20\d{2}-\d{2}-\d{2})\b/', $node, $matches)) {
            foreach ($matches[1] as $date) $out[$date] = true;
        }
        return;
    }
    if (!is_array($node)) return;
    foreach ($node as $value) mtv7_dates($value, $out);
}

function mtv7_complete(array $node): bool
{
    foreach ($node as $key => $value) {
        if (is_array($value) && mtv7_complete($value)) return true;
        if (is_string($key) && strtolower($key) === 'progress' && is_numeric($value) && (float)$value >= 100) return true;
        if (
            is_string($key)
            && strtolower($key) === 'status'
            && is_string($value)
            && in_array(strtolower($value), ['complete', 'completed', 'ready', 'done'], true)
        ) return true;
    }
    return false;
}

function mtv7_operator_hints($node, array &$out): void
{
    if (!is_array($node) || count($out) >= 100) return;
    foreach ($node as $key => $value) {
        if (is_string($key) && strpos(strtolower($key), 'operator') !== false && is_scalar($value)) {
            $out[$key . '=' . substr((string)$value, 0, 120)] = true;
            if (count($out) >= 100) return;
        }
        if (is_array($value)) mtv7_operator_hints($value, $out);
    }
}

try {
    ini_set('display_errors', '0');
    ini_set('log_errors', '0');

    $root = realpath(getcwd());
    if (!$root || basename($root) !== 'anytoour.ru') mtv7_fail('config', 'server_root_invalid');

    $config = $root . '/config.php';
    if (!is_file($config) || is_link($config)) mtv7_fail('config', 'config_missing');

    ob_start();
    require_once $config;
    ob_end_clean();

    if (!defined('TOURVISOR_ANEX_JWT')) mtv7_fail('config', 'anex_jwt_constant_missing');
    $token = trim((string)constant('TOURVISOR_ANEX_JWT'));
    if (stripos($token, 'Bearer ') === 0) $token = trim(substr($token, 7));
    if ($token === '' || strlen($token) < 20) mtv7_fail('config', 'anex_jwt_empty');

    putenv('TOURVISOR_JWT=' . $token);

    $client = is_file($root . '/data/tourvisor-client-v1.php')
        ? $root . '/data/tourvisor-client-v1.php'
        : $root . '/v2/data/tourvisor-client-v1.php';
    if (!is_file($client) || is_link($client)) mtv7_fail('config', 'tourvisor_client_missing');
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
        'search_started' => false,
    ];

    try {
        $departuresRaw = v2_data_tv_get('/departures');
    } catch (Throwable $e) {
        mtv7_fail('departures', 'tourvisor_error', $base + [
            'message' => substr((string)$e->getMessage(), 0, 160),
        ]);
    }

    $departures = mtv7_entities($departuresRaw);
    $base['departures_count'] = count($departures);

    $departureId = 0;
    foreach ($departures as $row) {
        if (($row['id'] ?? '') === '1') {
            $departureId = 1;
            break;
        }
    }
    if ($departureId <= 0) $departureId = (int)($departures[0]['id'] ?? 0);
    if ($departureId <= 0) mtv7_fail('departures', 'departure_id_missing', $base);
    $base['departure_id'] = (string)$departureId;

    try {
        $countriesRaw = v2_data_tv_get('/countries', [
            'departureId' => $departureId,
            'onlyCharter' => false,
            'onlyDirect' => false,
        ]);
    } catch (Throwable $e) {
        mtv7_fail('countries', 'tourvisor_error', $base + [
            'message' => substr((string)$e->getMessage(), 0, 160),
        ]);
    }

    $countries = mtv7_entities($countriesRaw);
    $base['countries_count'] = count($countries);
    $base['country_sample'] = array_slice($countries, 0, 20);

    $countryId = 0;
    $countryName = null;
    foreach ($countries as $row) {
        if (($row['id'] ?? '') === '4') {
            $countryId = 4;
            $countryName = $row['name'] ?? null;
            break;
        }
    }
    if ($countryId <= 0) mtv7_fail('countries', 'turkey_not_available', $base);
    $base['country_id'] = (string)$countryId;
    $base['country_name'] = $countryName;

    try {
        $operatorsRaw = v2_data_tv_get('/operators', [
            'departureId' => $departureId,
            'countryId' => $countryId,
        ]);
    } catch (Throwable $e) {
        mtv7_fail('operators', 'tourvisor_error', $base + [
            'message' => substr((string)$e->getMessage(), 0, 160),
        ]);
    }

    $operators = mtv7_entities($operatorsRaw);
    $base['operator_count'] = count($operators);
    $base['operators'] = array_slice($operators, 0, 30);

    $anex = [];
    foreach ($operators as $operator) {
        $name = strtolower((string)($operator['name'] ?? ''));
        if (strpos($name, 'anex') !== false || strpos($name, 'анекс') !== false) $anex[] = $operator;
    }
    $base['anex_operator_match_count'] = count($anex);
    $base['account_operator_scope_exact_anex'] = count($operators) === 1 && count($anex) === 1;

    if (count($anex) !== 1 || !ctype_digit((string)($anex[0]['id'] ?? ''))) {
        mtv7_fail('operators', 'anex_operator_not_unique', $base);
    }

    $operatorId = (int)$anex[0]['id'];
    $base['anex_operator_id'] = (string)$operatorId;

    try {
        $datesRaw = v2_data_tv_get('/tours/dates', [
            'departureId' => $departureId,
            'countryId' => $countryId,
            'onlyCharter' => false,
        ]);
    } catch (Throwable $e) {
        mtv7_fail('dates', 'tourvisor_error', $base + [
            'message' => substr((string)$e->getMessage(), 0, 160),
        ]);
    }

    $dateSet = [];
    mtv7_dates($datesRaw, $dateSet);
    $dates = array_keys($dateSet);
    sort($dates, SORT_STRING);
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $searchDate = null;
    foreach ($dates as $date) {
        if ($date > $today) {
            $searchDate = $date;
            break;
        }
    }
    $base['dates_count'] = count($dates);
    $base['first_dates'] = array_slice($dates, 0, 12);

    if ($searchDate === null) mtv7_fail('dates', 'future_date_missing', $base);
    $base['search_date'] = $searchDate;

    try {
        $start = v2_data_tv_get('/tours/search', [
            'departureId' => $departureId,
            'countryId' => $countryId,
            'dateFrom' => $searchDate,
            'dateTo' => $searchDate,
            'nightsFrom' => 7,
            'nightsTo' => 7,
            'adults' => 2,
            'currency' => 'RUB',
            'onlyCharter' => false,
            'onlyDirect' => false,
            'operatorIds' => [$operatorId],
        ]);
    } catch (Throwable $e) {
        mtv7_fail('search_start', 'tourvisor_error', $base + [
            'message' => substr((string)$e->getMessage(), 0, 160),
        ]);
    }

    $searchId = (int)($start['searchId'] ?? $start['id'] ?? 0);
    if ($searchId <= 0) mtv7_fail('search_start', 'search_id_missing', $base);
    $base['search_started'] = true;

    $complete = false;
    $polls = 0;
    for ($attempt = 0; $attempt < 30; $attempt++) {
        if ($attempt > 0) sleep(2);
        try {
            $status = v2_data_tv_get('/tours/search/' . $searchId . '/status', ['operatorStatus' => false]);
        } catch (Throwable $e) {
            mtv7_fail('status', 'tourvisor_error', $base + [
                'status_polls' => $polls,
                'message' => substr((string)$e->getMessage(), 0, 160),
            ]);
        }
        $polls++;
        if (mtv7_complete($status)) {
            $complete = true;
            break;
        }
    }

    if (!$complete) mtv7_fail('status', 'search_not_complete', $base + ['status_polls' => $polls]);

    try {
        $results = v2_data_tv_get('/tours/search/' . $searchId, ['limit' => 100]);
    } catch (Throwable $e) {
        mtv7_fail('results', 'tourvisor_error', $base + [
            'status_polls' => $polls,
            'message' => substr((string)$e->getMessage(), 0, 160),
        ]);
    }

    $hints = [];
    mtv7_operator_hints($results, $hints);
    $operatorHints = array_keys($hints);
    $anexHints = array_values(array_filter(
        $operatorHints,
        static fn(string $item): bool =>
            strpos(strtolower($item), 'anex') !== false || strpos(strtolower($item), 'анекс') !== false
    ));

    mtv7_emit($base + [
        'status' => 'completed',
        'search_completed' => true,
        'status_polls' => $polls,
        'result_row_count' => count(mtv7_rows($results)),
        'operator_hints' => $operatorHints,
        'anex_operator_hints' => $anexHints,
        'anex_only_observed' => count($operatorHints) > 0 && count($operatorHints) === count($anexHints),
        'search_contract' => [
            'departureId' => $departureId,
            'countryId' => $countryId,
            'date' => $searchDate,
            'nights' => 7,
            'adults' => 2,
            'operatorId' => $operatorId,
            'onlyCharter' => false,
            'onlyDirect' => false,
        ],
    ]);
} catch (Throwable $e) {
    mtv7_fail('probe', 'probe_exception', [
        'exception_class' => get_class($e),
        'message' => substr((string)$e->getMessage(), 0, 160),
    ]);
}
