<?php
declare(strict_types=1);

const ANEX_ADDITIONAL_SPECIMEN_OPERATION = 'anex-additional-prices-778-nights-20260915-v11';
const ANEX_ADDITIONAL_SPECIMEN_TOUR = 778;
const ANEX_ADDITIONAL_SPECIMEN_DATE = '2026-10-18';
const ANEX_ADDITIONAL_SPECIMEN_NIGHTS = [10, 14];
const ANEX_ADDITIONAL_SPECIMEN_CURRENCY = 3;

function anytour_anex_additional_specimen_decimal($value): ?string
{
    if (is_int($value) || (is_float($value) && is_finite($value))) $value = (string) $value;
    if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,6})?\z/D', $value)) return null;
    return $value;
}

function anytour_anex_additional_specimen_context_date($value): ?string
{
    if (!is_string($value) || !preg_match('/\A(\d{4}-\d{2}-\d{2})(?:T00:00:00)?\z/D', $value, $match)) return null;
    return $match[1];
}

/**
 * Project only the supplier money/context facts needed by the existing direct-ANEX
 * AdditionalPricesDaily runtime. No fuel/package/final-price semantics are assigned.
 */
function anytour_anex_additional_specimen_fact(array $payload, array $criteria): array
{
    $rows = $payload['data'] ?? null;
    $total = $payload['totalCount'] ?? null;
    if (!is_array($rows) || ($rows !== [] && array_keys($rows) !== range(0, count($rows) - 1))) {
        throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_RESPONSE');
    }
    if (is_string($total) && preg_match('/\A[0-9]{1,9}\z/D', $total)) $total = (int) $total;
    if (!is_int($total) || $total < 0 || $total > 100000000 || $total < count($rows)) {
        throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_RESPONSE');
    }

    $safe = [];
    foreach (array_slice($rows, 0, 100) as $row) {
        if (!is_array($row)) throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_RESPONSE');
        $tour = $row['tour'] ?? null;
        $currency = $row['currency'] ?? null;
        $nights = $row['nights'] ?? null;
        if ((is_int($tour) || is_string($tour)) && preg_match('/\A[1-9][0-9]{0,8}\z/D', (string) $tour)) $tour = (int) $tour; else $tour = null;
        if ((is_int($currency) || is_string($currency)) && preg_match('/\A[1-9][0-9]{0,8}\z/D', (string) $currency)) $currency = (int) $currency; else $currency = null;
        if ((is_int($nights) || is_string($nights)) && preg_match('/\A[0-9]{1,2}\z/D', (string) $nights)) $nights = (int) $nights; else $nights = null;
        $date = anytour_anex_additional_specimen_context_date($row['dateBeg'] ?? null);
        if ($tour !== $criteria['tour'] || $currency !== $criteria['currency'] || $date !== $criteria['dateBeg'] || $nights !== $criteria['nights']) {
            throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_CONTEXT');
        }
        $safe[] = [
            'tour' => $tour,
            'currency' => $currency,
            'date_beg' => $date,
            'nights' => $nights,
            'price_adult' => anytour_anex_additional_specimen_decimal($row['price_adult'] ?? null),
            'price_child' => anytour_anex_additional_specimen_decimal($row['price_chd'] ?? null),
            'cashrate' => anytour_anex_additional_specimen_decimal($row['cashrate'] ?? null),
            'price_converted_adult' => anytour_anex_additional_specimen_decimal($row['price_converted_adult'] ?? null),
            'price_converted_child' => anytour_anex_additional_specimen_decimal($row['price_converted_chd'] ?? null),
        ];
    }

    return [
        'state' => $safe === [] ? 'empty_unknown' : 'observed',
        'total_count' => $total,
        'retained_row_count' => count($safe),
        'rows' => $safe,
        'truncated' => count($rows) > 100,
        'context_verified' => true,
        'money_semantics' => [
            'additional_prices_source' => 'AdditionalPricesDaily',
            'supplier_program_context' => 'authoritative_program_778_existing_evidence',
            'currency_namespace' => 'provider_scoped',
            'fuel_equivalence_verified' => false,
            'selected_flight_specific' => false,
            'package_identity_verified' => false,
            'final_price_verified' => false,
            'arithmetic_applied' => false,
            'empty_means_zero' => false,
        ],
    ];
}

function anytour_anex_additional_specimen_run(array $input): array
{
    if (!class_exists('AnyTourAnexAdditionalPricesClient', false)) throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_CLIENT');
    if (PHP_SAPI !== 'cli' || array_keys($input) !== ['operation_id', 'source_sha']
        || $input['operation_id'] !== ANEX_ADDITIONAL_SPECIMEN_OPERATION
        || !is_string($input['source_sha']) || !preg_match('/\A[a-f0-9]{40}\z/D', $input['source_sha'])) {
        throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_INPUT');
    }

    $home = (string) getenv('HOME');
    $root = realpath($home . '/www/anytoour.ru');
    $private = realpath($home . '/.anytoour-anex');
    if (!$root || !$private || $private !== $home . '/.anytoour-anex') throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_RUNTIME');
    foreach ([$root . '/config.php', $private . '/search3-preview.php'] as $config) {
        if (!is_file($config) || is_link($config)) throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_CONFIG');
        require_once $config;
    }
    if (!defined('ANEX_B2B_TOKEN') || !is_string(ANEX_B2B_TOKEN) || trim(ANEX_B2B_TOKEN) === '') {
        throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_TOKEN');
    }
    if (stripos(ANEX_B2B_TOKEN, 'Bearer ') === 0 || preg_match('/[\x00-\x20\x7f]/', ANEX_B2B_TOKEN)) {
        throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_TOKEN');
    }

    $criteriaSet = [];
    foreach (ANEX_ADDITIONAL_SPECIMEN_NIGHTS as $nights) {
        $criteriaSet[] = [
            'page' => 1,
            'pageSize' => 10,
            'tour' => ANEX_ADDITIONAL_SPECIMEN_TOUR,
            'dateBeg' => ANEX_ADDITIONAL_SPECIMEN_DATE,
            'nights' => $nights,
            'currency' => ANEX_ADDITIONAL_SPECIMEN_CURRENCY,
        ];
    }

    umask(0077);
    $directory = $private . '/' . ANEX_ADDITIONAL_SPECIMEN_OPERATION;
    if (!@mkdir($directory, 0700)) throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_NO_REPLAY');
    $reservation = json_encode([
        'state' => 'unknown_reserved',
        'replay_allowed' => false,
        'operation_id' => ANEX_ADDITIONAL_SPECIMEN_OPERATION,
        'source_sha' => $input['source_sha'],
        'criteria' => $criteriaSet,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
    if (file_put_contents($directory . '/reservation.json', $reservation, LOCK_EX) !== strlen($reservation)) {
        throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_RESERVATION');
    }

    $result = [
        'schema_version' => 1,
        'operation_id' => ANEX_ADDITIONAL_SPECIMEN_OPERATION,
        'source_sha' => $input['source_sha'],
        'observed_at' => gmdate('c'),
        'supplier_replay_allowed' => false,
        'criteria' => $criteriaSet,
        'additional_prices_requests' => 0,
        'additional_prices_by_nights' => [],
        'booking_calls' => 0,
        'mapping_writes' => 0,
    ];
    try {
        $client = new AnyTourAnexAdditionalPricesClient(ANEX_B2B_TOKEN, null, $private . '/apd-daily-cache-v1');
        foreach ($criteriaSet as $criteria) {
            $payload = $client->additionalPricesDaily($criteria);
            $result['additional_prices_requests'] = $client->requestsMade();
            $result['additional_prices_by_nights'][] = [
                'nights' => $criteria['nights'],
                'request_diagnostics' => $client->lastRequestDiagnostics(),
                'additional_prices' => anytour_anex_additional_specimen_fact($payload, $criteria),
            ];
        }
        $result['status'] = 'completed';
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $result['status'] = 'unknown';
        $result['error'] = preg_match('/\AANEX_[A-Z0-9_]+\z/D', $message) ? $message : 'ANEX_ADDITIONAL_SPECIMEN_FAILED';
        $result['automatic_retry'] = false;
    }

    $encoded = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (strpos($encoded, ANEX_B2B_TOKEN) !== false || stripos($encoded, 'Bearer ') !== false) {
        throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_SECRET_OUTPUT');
    }
    if (file_put_contents($directory . '/result.json', $encoded, LOCK_EX) !== strlen($encoded)) {
        throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_RECEIPT');
    }
    return $result;
}

if (!defined('ANYTOUR_ANEX_ADDITIONAL_SPECIMEN_LIBRARY_ONLY')) {
    try {
        $raw = file_get_contents('php://stdin', false, null, 0, 4097);
        if (!is_string($raw) || strlen($raw) > 4096) throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_INPUT');
        $input = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($input)) throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_INPUT');
        $result = anytour_anex_additional_specimen_run($input);
        fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        exit(($result['status'] ?? null) === 'completed' ? 0 : 2);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        fwrite(STDOUT, json_encode([
            'schema_version' => 1,
            'operation_id' => ANEX_ADDITIONAL_SPECIMEN_OPERATION,
            'status' => 'unknown',
            'error' => preg_match('/\AANEX_[A-Z0-9_]+\z/D', $message) ? $message : 'ANEX_ADDITIONAL_SPECIMEN_FAILED',
            'automatic_retry' => false,
            'supplier_replay_allowed' => false,
        ], JSON_THROW_ON_ERROR) . "\n");
        exit(1);
    }
}
