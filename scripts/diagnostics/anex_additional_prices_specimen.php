<?php
declare(strict_types=1);

/** One owner-authorized read-only specimen: ANEX tour/program -> AdditionalPricesDaily. */
function anytour_anex_additional_specimen_run(array $input): array
{
    if (PHP_SAPI !== 'cli' || $input !== [
        'operation_id' => 'anex-additional-prices-specimen-20260912-v4',
        'date' => '2026-09-20',
        'nights' => 7,
        'tour' => 778,
        'currency' => 3,
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

    // Validate the owner-provided tour/program identity against the current supplier dictionary
    // before the single B2B request. It is not a hotel ID and is never mapped locally here.
    $programs = anytour_anex_additional_tour_programs(ANEX_API_TOKEN, $departure, $country);
    $program = null;
    foreach ($programs as $candidate) {
        if (($candidate['id'] ?? null) === 778) {
            $program = $candidate;
            break;
        }
    }
    if ($program === null) {
        return [
            'schema_version' => 1,
            'operation_id' => $input['operation_id'],
            'status' => 'blocked',
            'reason' => 'TOUR_PROGRAM_778_NOT_CURRENT',
            'direct_anex_requests' => $client->requestsMade() + 1,
            'additional_prices_requests' => 0,
            'booking_calls' => 0,
            'mapping_writes' => 0,
        ];
    }

    $additional = new AnyTourAnexAdditionalPricesClient(ANEX_B2B_TOKEN);
    $payload = $additional->additionalPricesDaily([
        'page' => 1,
        'pageSize' => 10,
        'tour' => 778,
        'dateBeg' => '2026-09-20',
        'nights' => 7,
        'currency' => 3,
    ]);

    return [
        'schema_version' => 1,
        'operation_id' => $input['operation_id'],
        'status' => 'completed',
        'criteria' => [
            'country' => 'Turkey',
            'dateBeg' => '2026-09-20',
            'nights' => 7,
            'tour_program_id_sha256' => hash('sha256', '778'),
            'currency_id' => 3,
            'currency_label' => null,
        ],
        'tour_program' => [
            'namespace' => 'anex_online',
            'id_sha256' => hash('sha256', (string) $program['id']),
            'name' => $program['name'],
            'available_program_count' => count($programs),
            'semantics' => 'supplier_tour_program_dictionary_identity',
        ],
        'additional_prices_payload' => $payload,
        'direct_anex_requests' => $client->requestsMade() + 1,
        'additional_prices_requests' => $additional->requestsMade(),
        'additional_request_diagnostics' => $additional->lastRequestDiagnostics(),
        'money_semantics' => [
            'service_scope' => 'minimum_daily_air_surcharge_service',
            'additional_payload_uninterpreted' => true,
            'per_person_or_package' => 'unknown',
            'included_in_search_price' => 'unknown',
            'fuel_equivalence_verified' => false,
            'final_price_verified' => false,
            'arithmetic_applied' => false,
        ],
        'booking_calls' => 0,
        'mapping_writes' => 0,
    ];
}

/** Bounded one-shot access to the read-only SearchTour_TOURS dictionary. */
function anytour_anex_additional_tour_programs(string $token, int $departure, int $country): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('ANEX_ADDITIONAL_TOURS_TRANSPORT');
    if ($departure < 1 || $country < 1) throw new RuntimeException('ANEX_ADDITIONAL_TOURS_CRITERIA');
    $url = 'https://parser.anextour.ru/export/default.php?' . http_build_query([
        'samo_action' => 'api', 'version' => '1.0', 'type' => 'json',
        'action' => 'SearchTour_TOURS', 'oauth_token' => $token,
        'TOWNFROMINC' => $departure, 'STATEINC' => $country,
    ], '', '&', PHP_QUERY_RFC3986);
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('ANEX_ADDITIONAL_TOURS_TRANSPORT');
    $body = '';
    $tooLarge = false;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROXY => '',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge): int {
            if (strlen($body) + strlen($chunk) > 2097152) { $tooLarge = true; return 0; }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    try {
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($ok === false || $tooLarge || $status !== 200) throw new RuntimeException('ANEX_ADDITIONAL_TOURS_TRANSPORT');
    } finally {
        curl_close($ch);
    }
    try {
        $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
    } catch (Throwable $ignored) {
        throw new RuntimeException('ANEX_ADDITIONAL_TOURS_RESPONSE');
    }
    if (!is_array($decoded) || array_key_exists('error', $decoded)
        || !array_key_exists('SearchTour_TOURS', $decoded) || !is_array($decoded['SearchTour_TOURS'])) {
        throw new RuntimeException('ANEX_ADDITIONAL_TOURS_RESPONSE');
    }
    $payload = $decoded['SearchTour_TOURS'];
    if (array_key_exists('error', $payload)) throw new RuntimeException('ANEX_ADDITIONAL_TOURS_RESPONSE');
    $rows = isset($payload['tours']) && is_array($payload['tours']) ? $payload['tours'] : $payload;
    if ($rows !== [] && array_keys($rows) !== range(0, count($rows) - 1)) {
        throw new RuntimeException('ANEX_ADDITIONAL_TOURS_RESPONSE');
    }
    $result = [];
    foreach (array_slice($rows, 0, 500) as $row) {
        if (!is_array($row)) continue;
        $id = $row['id'] ?? $row['tourKey'] ?? $row['tourId'] ?? null;
        if (is_string($id) && preg_match('/^[1-9][0-9]{0,8}$/D', $id)) $id = (int) $id;
        if (!is_int($id) || $id < 1 || $id > 999999999) continue;
        $name = $row['name'] ?? $row['tour'] ?? null;
        if (!is_string($name) || $name === '' || strlen($name) > 240 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $name)) {
            $name = null;
        }
        $result[$id] = ['id' => $id, 'name' => $name];
    }
    return array_values($result);
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
            'operation_id' => 'anex-additional-prices-specimen-20260912-v4',
            'status' => 'unknown',
            'error' => preg_match('/^ANEX_[A-Z0-9_]+$/D', $e->getMessage()) ? $e->getMessage() : 'ANEX_ADDITIONAL_SPECIMEN_FAILED',
            'automatic_retry' => false,
            'supplier_replay_allowed' => false,
        ], JSON_THROW_ON_ERROR) . "\n");
        exit(1);
    }
}
