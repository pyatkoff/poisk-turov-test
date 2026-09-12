<?php
declare(strict_types=1);

/**
 * P0 follow-up to the completed AdditionalPricesDaily v4 specimen.
 * Only current currency dictionary evidence; the v4 supplier request is retired.
 * Consumer: v4 money evidence -> direct ANEX offer additional facts. No conversion.
 */
function anytour_anex_additional_currency_evidence($client, int $departure, int $country): array
{
    if ($departure < 1 || $country < 1) throw new RuntimeException('ANEX_CURRENCY_CONTEXT');
    $params = ['TOWNFROMINC' => $departure, 'STATEINC' => $country,
        'CHECKIN_BEG' => '20260920', 'CHECKIN_END' => '20260920'];
    $rows = $client->request('SearchTour_CURRENCIES', $params);
    if (!is_array($rows) || count($rows) > 100
        || ($rows !== [] && array_keys($rows) !== range(0, count($rows) - 1))) {
        throw new RuntimeException('ANEX_CURRENCY_DICTIONARY');
    }
    $matches = [];
    foreach ($rows as $row) {
        if (!is_array($row) || !in_array($row['id'] ?? null, [3, '3'], true)) continue;
        $fact = ['id' => 3];
        foreach (['name', 'nameAlt', 'alias', 'currencyISO'] as $key) {
            $value = $row[$key] ?? null;
            $fact[$key] = is_string($value) && $value !== '' && strlen($value) <= 120
                && !preg_match('/[\x00-\x1f\x7f<>]/', $value) ? $value : null;
        }
        $matches[] = $fact;
    }
    if (count($matches) > 1) throw new RuntimeException('ANEX_CURRENCY_AMBIGUOUS');
    $fact = $matches[0] ?? null;
    $hasLabel = $fact !== null && count(array_filter(array_slice($fact, 1), static fn ($v) => $v !== null)) > 0;
    return [
        'status' => $hasLabel ? 'completed' : 'blocked',
        'reason' => $hasLabel ? null : 'CURRENCY_3_LABEL_NOT_REPORTED',
        'dictionary_action' => 'SearchTour_CURRENCIES',
        'dictionary_context' => $params,
        'dictionary_row_count' => count($rows),
        'currency_reported' => $fact,
        // A SearchTour dictionary is not proof of the B2B namespace or conversion target.
        'additional_currency_namespace_verified' => false,
        'converted_currency' => null,
        'included_in_search_price' => 'unknown',
        'fuel_equivalence_verified' => false,
        'arithmetic_applied' => false,
    ];
}

function anytour_anex_additional_specimen_run(array $input): array
{
    if (PHP_SAPI !== 'cli' || array_keys($input) !== ['operation_id', 'source_sha']
        || $input['operation_id'] !== 'anex-additional-currency-20260912-v1'
        || !is_string($input['source_sha']) || !preg_match('/\A[a-f0-9]{40}\z/D', $input['source_sha'])) {
        throw new RuntimeException('ANEX_CURRENCY_INPUT');
    }
    $home = (string) getenv('HOME');
    $root = realpath($home . '/www/anytoour.ru');
    $preview = realpath($home . '/www/anytoour.ru/_preview/search3-anex-candidate');
    $private = realpath($home . '/.anytoour-anex');
    if (!$root || !$preview || $preview !== $root . '/_preview/search3-anex-candidate'
        || !$private || $private !== $home . '/.anytoour-anex') {
        throw new RuntimeException('ANEX_CURRENCY_RUNTIME');
    }
    foreach ([$root . '/config.php', $private . '/search3-preview.php'] as $config) {
        if (!is_file($config) || is_link($config)) throw new RuntimeException('ANEX_CURRENCY_CONFIG');
        require_once $config;
    }
    if (!defined('ANEX_API_TOKEN') || !is_string(ANEX_API_TOKEN) || trim(ANEX_API_TOKEN) === '') {
        throw new RuntimeException('ANEX_CURRENCY_TOKEN_REQUIRED');
    }
    require_once $preview . '/app/integrations/anex-client.php';
    $_SERVER['SCRIPT_FILENAME'] = '';
    require_once $preview . '/api-anex-search3-preview.php';
    $client = new AnyTourAnexClient(ANEX_API_TOKEN);

    // Exclusive persistent reservation: completed, failed and unknown attempts cannot replay.
    umask(0077);
    $directory = $private . '/' . $input['operation_id'];
    if (!@mkdir($directory, 0700)) throw new RuntimeException('ANEX_CURRENCY_NO_REPLAY');
    $reservation = json_encode($input + ['state' => 'unknown_reserved', 'replay_allowed' => false], JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($directory . '/reservation.json', $reservation, LOCK_EX) !== strlen($reservation)) {
        throw new RuntimeException('ANEX_CURRENCY_RESERVATION');
    }
    $result = ['schema_version' => 1] + $input + [
        'observed_at' => gmdate('c'), 'supplier_replay_allowed' => false,
        'additional_prices_requests' => 0, 'price_search_requests' => 0,
        'booking_calls' => 0, 'mapping_writes' => 0,
    ];
    try {
        $cache = [];
        $departure = anytour_anex_search3_dictionary_id(
            anytour_anex_search3_dictionary($client, 'SearchTour_TOWNFROMS', [], $cache), ['Москва', 'Moscow']
        );
        $country = anytour_anex_search3_dictionary_id(
            anytour_anex_search3_dictionary($client, 'SearchTour_STATES', ['TOWNFROMINC' => $departure], $cache), ['Турция', 'Turkey']
        );
        $result += anytour_anex_additional_currency_evidence($client, $departure, $country);
    } catch (Throwable $e) {
        $result += ['status' => 'unknown', 'error' => preg_match('/\AANEX_[A-Z0-9_]+\z/D', $e->getMessage())
            ? $e->getMessage() : 'ANEX_CURRENCY_FAILED', 'automatic_retry' => false];
    }
    $result['dictionary_requests'] = $client->requestsMade();
    $result['last_request_diagnostics'] = $client->lastRequestDiagnostics();
    $encoded = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($directory . '/result.json', $encoded, LOCK_EX) !== strlen($encoded)) {
        throw new RuntimeException('ANEX_CURRENCY_RECEIPT');
    }
    return $result;
}

if (!defined('ANYTOUR_ANEX_ADDITIONAL_SPECIMEN_LIBRARY_ONLY')) {
    try {
        $raw = file_get_contents('php://stdin', false, null, 0, 4097);
        if (!is_string($raw) || strlen($raw) > 4096) throw new RuntimeException('ANEX_CURRENCY_INPUT');
        $input = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($input)) throw new RuntimeException('ANEX_CURRENCY_INPUT');
        $result = anytour_anex_additional_specimen_run($input);
        fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        exit(($result['status'] ?? null) === 'completed' ? 0 : 2);
    } catch (Throwable $e) {
        fwrite(STDOUT, json_encode([
            'schema_version' => 1, 'operation_id' => 'anex-additional-currency-20260912-v1',
            'status' => 'unknown',
            'error' => preg_match('/\AANEX_[A-Z0-9_]+\z/D', $e->getMessage()) ? $e->getMessage() : 'ANEX_CURRENCY_FAILED',
            'automatic_retry' => false, 'supplier_replay_allowed' => false,
        ], JSON_THROW_ON_ERROR) . "\n");
        exit(1);
    }
}
