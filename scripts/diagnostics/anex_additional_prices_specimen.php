<?php
declare(strict_types=1);

/** One owner-authorized read-only specimen: fresh direct-ANEX tour -> AdditionalPricesDaily. */
function anytour_anex_additional_specimen_run(array $input): array
{
    if (PHP_SAPI !== 'cli' || $input !== [
        'operation_id' => 'anex-additional-prices-specimen-20260912-v1',
        'date' => '2026-10-31',
        'nights' => 8,
        'adults' => 1,
    ]) {
        throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_INPUT');
    }

    $home = (string) getenv('HOME');
    $root = realpath($home . '/www/anytoour.ru');
    $preview = realpath($home . '/www/anytoour.ru/_preview/search3-anex-candidate');
    if (!$root || !$preview || $preview !== $root . '/_preview/search3-anex-candidate') {
        throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_RUNTIME');
    }
    $config = $root . '/config.php';
    if (!is_file($config) || is_link($config)) {
        throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_CONFIG');
    }
    require_once $config;
    require_once $home . '/.anytoour-anex/search3-preview.php';
    if (!defined('ANEX_B2B_TOKEN') || !is_string(ANEX_B2B_TOKEN) || trim(ANEX_B2B_TOKEN) === '') {
        throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_B2B_TOKEN');
    }
    if (stripos(ANEX_B2B_TOKEN, 'Bearer ') === 0 || preg_match('/[\x00-\x20\x7f]/', ANEX_B2B_TOKEN)) {
        throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_B2B_TOKEN_FORMAT');
    }
    if (defined('ANEX_B2B_USER_AGENT') && ANEX_B2B_USER_AGENT !== 'TourismPlus') {
        throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_USER_AGENT');
    }
    if (!defined('ANEX_API_TOKEN') || !is_string(ANEX_API_TOKEN) || trim(ANEX_API_TOKEN) === '') {
        throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_SEARCH_TOKEN');
    }

    require_once $preview . '/app/integrations/anex-client.php';
    require_once $preview . '/app/integrations/anex-normalizer.php';
    require_once $preview . '/app/integrations/anex-search.php';
    $_SERVER['SCRIPT_FILENAME'] = '';
    require_once $preview . '/api-anex-search3-preview.php';

    $client = new AnyTourAnexClient(ANEX_API_TOKEN);
    $cache = [];
    $departure = anytour_anex_search3_dictionary_id(
        anytour_anex_search3_dictionary($client, 'SearchTour_TOWNFROMS', [], $cache),
        ['Москва', 'Moscow']
    );
    $country = anytour_anex_search3_dictionary_id(
        anytour_anex_search3_dictionary($client, 'SearchTour_STATES', ['TOWNFROMINC' => $departure], $cache),
        ['Турция', 'Turkey']
    );
    $dated = [
        'TOWNFROMINC' => $departure,
        'STATEINC' => $country,
        'CHECKIN_BEG' => '20261031',
        'CHECKIN_END' => '20261031',
        'ADULT' => 1,
        'CHILD' => 0,
    ];
    $currency = anytour_anex_search3_dictionary_id(
        anytour_anex_search3_dictionary($client, 'SearchTour_CURRENCIES', $dated, $cache),
        ['RUB', 'RUR', 'Рубль', 'Рубли', 'Руб']
    );
    $search = new AnyTourAnexSearch($client, null, [ANEX_API_TOKEN, ANEX_B2B_TOKEN]);
    $result = $search->search([
        'supplier_namespace' => 'anex_online',
        'departure_id' => $departure,
        'destination_id' => $country,
        'currency_id' => $currency,
        'checkin_begin' => '2026-10-31',
        'checkin_end' => '2026-10-31',
        'nights_from' => 8,
        'nights_till' => 8,
        'adults' => 1,
        'children' => 0,
        'child_ages' => [],
    ]);

    $offer = null;
    foreach ($result['offers'] as $candidate) {
        if (($candidate['kind'] ?? null) === 'concrete'
            && is_string($candidate['supplier_offer_id'] ?? null)
            && preg_match('/^[1-9][0-9]{0,8}$/D', $candidate['supplier_offer_id'])) {
            $offer = $candidate;
            break;
        }
    }
    if ($offer === null) {
        foreach ($result['offers'] as $candidate) {
            if (($candidate['kind'] ?? null) !== 'group_minimum') continue;
            $expanded = $search->expand((string) $candidate['offer_key']);
            foreach ($expanded['offers'] as $concrete) {
                if (is_string($concrete['supplier_offer_id'] ?? null)
                    && preg_match('/^[1-9][0-9]{0,8}$/D', $concrete['supplier_offer_id'])) {
                    $offer = $concrete;
                    break 2;
                }
            }
            break;
        }
    }
    if ($offer === null) {
        return [
            'schema_version' => 1,
            'operation_id' => $input['operation_id'],
            'status' => 'blocked',
            'reason' => 'NO_NUMERIC_CONCRETE_TOUR',
            'direct_anex_requests' => $client->requestsMade(),
            'additional_prices_requests' => 0,
            'booking_calls' => 0,
            'mapping_writes' => 0,
        ];
    }

    $tour = (int) $offer['supplier_offer_id'];
    $additional = new AnyTourAnexAdditionalPricesClient(ANEX_B2B_TOKEN);
    $payload = $additional->additionalPricesDaily([
        'page' => 1,
        'pageSize' => 10,
        'tour' => $tour,
        'dateBeg' => (string) $offer['checkin'],
        'nights' => (int) $offer['nights'],
        'currency' => $currency,
    ]);

    return [
        'schema_version' => 1,
        'operation_id' => $input['operation_id'],
        'status' => 'completed',
        'criteria' => [
            'country' => 'Turkey',
            'dateBeg' => (string) $offer['checkin'],
            'nights' => (int) $offer['nights'],
            'adults' => 1,
            'currency_id' => $currency,
        ],
        'selected_offer' => [
            'hotel_external_id' => $offer['hotel']['external_id'] ?? null,
            'hotel_name' => $offer['hotel']['name'] ?? null,
            'room' => $offer['room'] ?? null,
            'placement' => $offer['hotel_place'] ?? null,
            'meal' => $offer['meal'] ?? null,
            'search_price' => $offer['price'] ?? null,
            'tour_id_sha256' => hash('sha256', (string) $tour),
        ],
        'additional_prices_payload' => $payload,
        'direct_anex_requests' => $client->requestsMade(),
        'additional_prices_requests' => $additional->requestsMade(),
        'additional_request_diagnostics' => $additional->lastRequestDiagnostics(),
        'money_semantics' => [
            'search_price_separate' => true,
            'additional_payload_uninterpreted' => true,
            'fuel_inclusion_verified' => false,
            'final_price_verified' => false,
            'arithmetic_applied' => false,
        ],
        'booking_calls' => 0,
        'mapping_writes' => 0,
    ];
}

if (!defined('ANYTOUR_ANEX_ADDITIONAL_SPECIMEN_LIBRARY_ONLY')) {
    try {
        $raw = file_get_contents('php://stdin', false, null, 0, 4097);
        if (!is_string($raw) || strlen($raw) > 4096) throw new RuntimeException('ANEX_ADDITIONAL_SPECIMEN_INPUT');
        $input = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        $result = anytour_anex_additional_specimen_run($input);
        fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        exit(($result['status'] ?? null) === 'completed' ? 0 : 2);
    } catch (Throwable $e) {
        fwrite(STDOUT, json_encode([
            'schema_version' => 1,
            'operation_id' => 'anex-additional-prices-specimen-20260912-v1',
            'status' => 'unknown',
            'error' => preg_match('/^ANEX_[A-Z0-9_]+$/D', $e->getMessage()) ? $e->getMessage() : 'ANEX_ADDITIONAL_SPECIMEN_FAILED',
            'automatic_retry' => false,
            'supplier_replay_allowed' => false,
        ], JSON_THROW_ON_ERROR) . "\n");
        exit(1);
    }
}
