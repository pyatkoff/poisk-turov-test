<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-normalizer.php';
require_once __DIR__ . '/../app/integrations/anex-additional-prices-client.php';
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';

$checks = 0;
$assert = static function ($condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    ++$checks;
};

$searchRef = str_repeat('a', 32);
$offerRef = 'anex_online:' . str_repeat('b', 64);
$offer = [
    'offer_key' => $offerRef,
    'provider' => 'anex', 'supplier_namespace' => 'anex_online', 'kind' => 'concrete',
    'hotel' => ['external_id' => '8121', 'local_id' => 6319, 'mapping_status' => 'resolved',
        'name' => 'APERION BEACH', 'star' => '4', 'country' => null, 'region' => null, 'town' => 'Side',
        'external_town_id' => '12'],
    'checkin' => '2026-09-20', 'checkout' => '2026-09-27', 'nights' => 7,
    'adults' => 2, 'children' => 0, 'infants' => null, 'meal' => 'AI', 'external_meal_id' => '7',
    'room' => 'STANDARD', 'external_room_id' => '10', 'hotel_place' => 'DBL', 'external_hotel_place_id' => '2',
    'price' => ['amount' => '100000', 'currency' => 'RUB'], 'converted_price' => null,
    'availability' => ['hotel' => 'Y', 'flight_outbound_economy' => 'Y', 'flight_return_economy' => 'Y'],
    'supplier_booking_flag' => true, 'final_price_verified' => false,
];
$stateTemplate = [
    'generation' => 9,
    'params' => ['countryId' => 1, 'hotelIds' => [], 'regionIds' => [], 'subregionIds' => [],
        'hotelRating' => '', 'hotelCategory' => '', 'meal' => '', 'priceFrom' => '', 'priceTo' => ''],
    'gateway' => [
        'saved_offers' => ['search_ref' => $searchRef, 'created_at' => 1789220000, 'expires_at' => 1789220900,
            'offers' => [$offerRef => ['offer' => $offer, 'observed_at' => 1789220010,
                'supplier_tour_program_id' => '987654321', 'supplier_currency_id' => '345']]],
        'search' => ['offers' => [['offer_key' => $offerRef, 'kind' => 'concrete', 'hotel_external_id' => '8121']]],
    ],
    'expansions' => [], 'additional_prices' => [],
];
$request = ['action' => 'additional_prices', 'generation' => 9, 'search_ref' => $searchRef,
    'offer_ref' => $offerRef, 'local_hotel_id' => 6319];
$resolver = static function (string $namespace, $external): ?int {
    return $namespace === 'anex_online' && (string) $external === '8121' ? 6319 : null;
};
$metadata = static function (array $offers): array {
    return [6319 => ['id' => 6319, 'name' => 'APERION BEACH', 'country_id' => 1, 'country_name' => 'Turkey',
        'region_id' => 2, 'region_name' => 'Side', 'subregion_id' => 3, 'subregion_name' => 'Kizilagac',
        'category' => 4, 'rating' => 4.5]];
};
$clock = static function (): int { return 1789220100; };
$directFactory = static function () { throw new RuntimeException('DIRECT_CLIENT_MUST_NOT_RUN'); };
$checkpoints = 0;
$checkpoint = static function (array &$state) use (&$checkpoints, $assert): void {
    ++$checkpoints;
    $attempts = array_values($state['additional_prices'] ?? []);
    $assert(count($attempts) === 1 && ($attempts[0]['status'] ?? null) === 'unknown', 'reservation before transport');
};

/** Focused offline group: no real cache files, network, database, or Unix permission assumptions. */
function retained_empty_apd_capture_fixture(callable $assert, array $template, array $request, callable $resolver,
    callable $directFactory, callable $metadata, callable $clock): void
{
    $criteria = ['page' => 1, 'pageSize' => 10, 'tour' => 987654321, 'dateBeg' => '2026-09-20', 'nights' => 7, 'currency' => 345];
    $digest = hash('sha256', implode("\0", ['987654321', '345', '2026-09-20', '7']));
    $binding = ['generation' => 9, 'search_ref' => $request['search_ref'], 'offer_ref' => $request['offer_ref'], 'digest' => $digest];
    $facts = ['observed_at_basis' => 'local_consumption', 'source' => 'supplier_transport', 'supplier_calls' => 1,
        'cache_status' => 'miss', 'http_status' => 200, 'response_bytes' => 41, 'elapsed_ms' => 106, 'action' => 'AdditionalPricesDaily'];
    $payload = ['data' => [], 'totalCount' => 0, 'totalPages' => 0, 'success' => true, 'isSuccess' => false,
        'state' => 4, 'status' => 'PRIVATE_STATUS', 'code' => 17, 'errorCode' => 23,
        'error' => 'PRIVATE_ERROR', 'errors' => [['PRIVATE_NESTED_KEY' => 'PRIVATE_NESTED_VALUE']],
        'message' => 'PRIVATE_MESSAGE', 'PRIVATE_TOKEN_KEY' => 'PRIVATE_TOKEN'];
    $capture = anytour_anex_search3_apd_empty_capture($payload, $criteria, $facts, $binding, 3);
    $assert(is_array($capture) && $capture['query'] === $criteria && $capture['_binding'] === $binding
        && $capture['selected_search_currency'] === 3 && $capture['basis'] === 'api_arguments', 'private capture freezes exact API arguments and internal binding');
    $assert(strlen(json_encode($capture, JSON_THROW_ON_ERROR)) <= 2048, 'capture stays within private 2 KiB budget');
    $markers = $capture['response']['fields'];
    $assert($markers['totalPages'] === ['type' => 'integer', 'value' => 0, 'empty' => null]
        && $markers['success']['value'] === true && $markers['isSuccess']['value'] === false
        && $markers['state']['value'] === 4 && $markers['code']['value'] === 17 && $markers['errorCode']['value'] === 23,
        'known counts flags and bounded integer codes remain descriptive facts');
    $assert($markers['error'] === ['type' => 'string', 'value' => null, 'empty' => false]
        && $markers['errors'] === ['type' => 'array', 'value' => null, 'empty' => false]
        && $capture['response']['other_key_count'] === 1 && !str_contains(json_encode($capture), 'PRIVATE_'),
        'supplier strings nested keys unknown keys tokens and payload values are absent');
    $assert(anytour_anex_search3_apd_envelope_valid($capture['response']), 'captured fixed envelope passes strict export schema');
    foreach (['code', 'errorCode', 'state', 'status'] as $codeField) {
        $minus = anytour_anex_search3_apd_empty_capture(['data' => [], 'totalCount' => 0, $codeField => '-1'], $criteria, $facts, $binding, 3);
        $zero = anytour_anex_search3_apd_empty_capture(['data' => [], 'totalCount' => 0, $codeField => '0'], $criteria, $facts, $binding, 3);
        $assert($minus['response']['fields'][$codeField] === ['type' => 'string', 'value' => -1, 'empty' => false]
            && $zero['response']['fields'][$codeField] === ['type' => 'string', 'value' => 0, 'empty' => false]
            && anytour_anex_search3_apd_envelope_valid($minus['response']) && anytour_anex_search3_apd_envelope_valid($zero['response']),
            'canonical numeric string code facts preserve original type and distinguish minus one from zero');
        foreach (['00', '-0', '+1', '01', '-01', '1e2', '1.0', ' 1', '100000001', '-100000001', '12345678901234567890', 'PRIVATE_CODE'] as $rawCode) {
            $safe = anytour_anex_search3_apd_empty_capture(['data' => [], 'totalCount' => 0, $codeField => $rawCode], $criteria, $facts, $binding, 3);
            $assert($safe['response']['fields'][$codeField] === ['type' => 'string', 'value' => null, 'empty' => false],
                'noncanonical out-of-bound and arbitrary code strings retain no value');
        }
    }
    $typedOnly = anytour_anex_search3_apd_empty_capture(['data' => [], 'totalCount' => 0, 'success' => '0', 'isSuccess' => '1', 'message' => '-1'],
        $criteria, $facts, $binding, 3);
    $assert($typedOnly['response']['fields']['success']['value'] === null && $typedOnly['response']['fields']['isSuccess']['value'] === null
        && $typedOnly['response']['fields']['message']['value'] === null, 'no undocumented string boolean or arbitrary string coercion');
    $badMarker = $capture['response']; $badMarker['fields']['message']['value'] = -1;
    $assert(!anytour_anex_search3_apd_envelope_valid($badMarker), 'strict export validator rejects numeric string facts on arbitrary fields');
    $search = ['checkin_begin' => '2026-09-20', 'checkin_end' => '2026-09-20', 'nights_from' => 7, 'nights_till' => 7,
        'adults' => 2, 'children' => 0, 'child_ages' => [], 'currency_id' => '3'];
    $exportTemplate = $template;
    $exportTemplate['gateway']['saved_offers']['search'] = $search;
    $exportTemplate['gateway']['search']['schema_version'] = 1;
    $exportTemplate['gateway']['search']['context'] = $search;
    $exportCodeFacts = [];
    foreach (['-1', '0'] as $rawCode) {
        $body = ['data' => [], 'totalCount' => 0, 'code' => $rawCode];
        $empty = anytour_anex_search3_additional_evidence($body);
        $empty['observed_at'] = gmdate('Y-m-d\TH:i:s\Z', $clock()); $empty['_read_diagnostics'] = $facts;
        $empty['_empty_apd_capture'] = anytour_anex_search3_apd_empty_capture($body, $criteria, $facts, $binding, 3);
        $exportTemplate['additional_prices'] = [$digest => ['status' => 'complete', 'evidence' => $empty]];
        $exported = anytour_anex_search3_apd_diagnostic_export(['offer_context' => $exportTemplate]);
        $assert($exported['status'] === 'complete' && $exported['response']['fields']['code']['type'] === 'string'
            && $exported['response']['fields']['code']['value'] === (int) $rawCode, 'private export preserves the distinct bounded string code fact');
        $exportCodeFacts[] = $exported['response']['fields']['code'];
    }
    $assert($exportCodeFacts[0] !== $exportCodeFacts[1], 'download no longer collapses code minus one and zero');
    $withUnsafeFacts = $facts + ['headers' => ['Authorization' => 'PRIVATE_TOKEN'], 'url' => 'PRIVATE_URL', 'body' => 'PRIVATE_BODY'];
    $assert(anytour_anex_search3_apd_empty_capture($payload, $criteria, $withUnsafeFacts, $binding, null)['read_facts'] === $facts,
        'raw metadata never enters private capture');
    foreach ([['supplier_calls' => 0, 'cache_status' => 'hit'], ['cache_status' => 'unknown'], ['cache_status' => 'unavailable'],
        ['http_status' => 201], ['http_status' => '200'], ['supplier_calls' => true], ['response_bytes' => 524289], ['elapsed_ms' => -1],
        ['action' => 'OtherAction'], ['observed_at_basis' => 'unknown']] as $change) {
        $assert(anytour_anex_search3_apd_empty_capture($payload, $criteria, array_replace($facts, $change), $binding, 3) === null,
            'cache unknown invalid or nonfresh read cannot acquire a new capture');
    }
    $assert(anytour_anex_search3_apd_empty_capture(['data' => [], 'totalCount' => '000'], $criteria, $facts, $binding, 3) !== null,
        'existing valid string totalCount remains accepted without exposing its raw value');
    foreach ([['data' => [[]], 'totalCount' => 1], ['data' => [], 'totalCount' => 1], ['data' => null, 'totalCount' => 0]] as $other) {
        $assert(anytour_anex_search3_apd_empty_capture($other, $criteria, $facts, $binding, 3) === null, 'nonempty or invalid envelope does not receive empty capture');
    }
    $assert(anytour_anex_search3_apd_empty_capture($payload, $criteria + ['selector' => 'PRIVATE'], $facts, $binding, 3) === null
        && anytour_anex_search3_apd_empty_capture($payload, $criteria, $facts, array_replace($binding, ['digest' => str_repeat('0', 64)]), 3) === null,
        'wrong criteria shape and digest fail capture closed');
    $oversize = ['data' => [], 'totalCount' => 0];
    for ($i = 0; $i < 64; ++$i) $oversize['PRIVATE_' . $i] = 'PRIVATE';
    $assert(anytour_anex_search3_apd_empty_capture($oversize, $criteria, $facts, $binding, 3) === null, 'bounded root shape skips capture without changing evidence');
    $assert(anytour_anex_search3_additional_evidence($oversize)['total_count'] === 0, 'capture failure leaves existing evidence interpretation unchanged');

    $wire = []; $factoryCalls = 0;
    // Controlled successful-client provenance fixture only. Custom transport stays cache-off;
    // mark the client metadata miss explicitly, without touching a real shared daily-cache root.
    $makeFactory = static function (array $body, string $cacheStatus = 'miss') use (&$wire, &$factoryCalls): callable {
        return static function () use ($body, $cacheStatus, &$wire, &$factoryCalls): AnyTourAnexAdditionalPricesClient {
            ++$factoryCalls; $client = null;
            $client = new AnyTourAnexAdditionalPricesClient('offline-private-token',
                static function (string $url) use ($body, $cacheStatus, &$wire, &$client): array {
                    parse_str((string) parse_url($url, PHP_URL_QUERY), $query); $wire[] = $query;
                    $property = new ReflectionProperty(AnyTourAnexAdditionalPricesClient::class, 'lastRequest');
                    $last = $property->getValue($client); $last['cache_status'] = $cacheStatus; $property->setValue($client, $last);
                    return ['status' => 200, 'body' => json_encode($body, JSON_THROW_ON_ERROR)];
                });
            return $client;
        };
    };
    $template['gateway']['search']['context']['currency_id'] = '3';
    $checkpoint = static function (array &$state) use ($assert): void {
        $assert(count($state['additional_prices']) === 1 && array_values($state['additional_prices'])[0]['status'] === 'unknown',
            'fresh ordinary or batch still reserves unknown before the one client call');
    };
    $state = $template; $factory = $makeFactory($payload);
    $reply = anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $factory);
    $stored = $state['additional_prices'][$digest]['evidence']; $captured = $stored['_empty_apd_capture'] ?? null;
    $assert($factoryCalls === 1 && count($wire) === 1 && $captured['query'] === $criteria
        && $captured['_binding'] === $binding && $captured['selected_search_currency'] === 3, 'ordinary client call captures one exact native tuple and selected search currency separately');
    $expectedWire = array_map(static fn($value): string => (string) $value, $criteria);
    $assert($wire[0] === $expectedWire, 'wire criteria are exactly the same six frozen API arguments');
    $assert($reply['status'] === 'additional_prices' && !isset($reply['additional_prices']['_empty_apd_capture'])
        && !isset($reply['additional_prices']['_read_diagnostics']) && $reply['additional_prices']['application_state'] === 'unknown'
        && $reply['additional_prices']['application_reason'] === 'rate_rows_unconfirmed'
        && $reply['additional_prices']['party_surcharge'] === null && $reply['additional_prices']['arithmetic_applied'] === false
        && $reply['additional_prices']['final_price_verified'] === false, 'ordinary public empty reply preserves unknown money and excludes both private blocks');
    $again = anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint,
        static function () { throw new RuntimeException('REPLAY_MUST_NOT_RUN'); });
    $assert($again === $reply && $state['additional_prices'][$digest]['evidence'] === $stored && count($wire) === 1,
        'existing completed ordinary attempt retains capture without another client call');
    $state = $template;
    $reply = anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $makeFactory($payload, 'unknown'));
    $assert($reply['status'] === 'additional_prices' && !isset($state['additional_prices'][$digest]['evidence']['_empty_apd_capture']),
        'unconfirmed cache origin preserves successful APD response but skips capture');
    $state = $template;
    $reply = anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $makeFactory($oversize));
    $assert($reply['status'] === 'additional_prices' && !isset($state['additional_prices'][$digest]['evidence']['_empty_apd_capture']),
        'ordinary best-effort capture failure does not replace or fail valid empty APD');

    $batchRequest = ['action' => 'additional_prices_batch', 'generation' => 9, 'search_ref' => $request['search_ref'],
        'items' => [['offer_ref' => $request['offer_ref'], 'local_hotel_id' => 6319]]];
    $state = $template; $before = count($wire);
    $batch = anytour_anex_search3_additional_batch($batchRequest, $state, $resolver, $metadata, $clock, $checkpoint, $makeFactory($payload));
    $assert(count($wire) === $before + 1 && $wire[$before] === $expectedWire
        && $state['additional_prices'][$digest]['evidence']['_empty_apd_capture']['query'] === $criteria
        && $state['additional_prices'][$digest]['evidence']['_empty_apd_capture']['_binding'] === $binding,
        'single-target batch captures exactly the once-called native tuple');
    $assert($batch['offers'][0]['status'] === 'additional_prices' && $batch['offers'][0]['finalPriceReady'] === false
        && $batch['offers'][0]['finalPrice'] === null && !isset($batch['offers'][0]['additional_prices']['_empty_apd_capture']),
        'batch public pricing and private boundary remain unchanged');
    $state = $template; $otherKey = 'anex_online:' . str_repeat('e', 64);
    $other = $state['gateway']['saved_offers']['offers'][$request['offer_ref']]; $other['offer']['offer_key'] = $otherKey;
    $state['gateway']['saved_offers']['offers'][$otherKey] = $other;
    $state['gateway']['search']['offers'][] = ['offer_key' => $otherKey, 'kind' => 'concrete', 'hotel_external_id' => '8121'];
    $batchRequest['items'][] = ['offer_ref' => $otherKey, 'local_hotel_id' => 6319]; $before = count($wire);
    $batch = anytour_anex_search3_additional_batch($batchRequest, $state, $resolver, $metadata, $clock, $checkpoint, $makeFactory($payload));
    $assert(count($wire) === $before + 1 && count($batch['offers']) === 2
        && !isset($state['additional_prices'][$digest]['evidence']['_empty_apd_capture']),
        'shared batch tuple still calls once but never first-picks an offer for private export binding');
}

retained_empty_apd_capture_fixture($assert, $stateTemplate, $request, $resolver, $directFactory, $metadata, $clock);
if (in_array('--empty-apd-capture-fixture', $argv ?? [], true)) {
    echo "ANEX empty APD capture: {$checks} checks passed; network=0; filesystem=0\n";
    exit(0);
}

$factoryCalls = 0;
$transportCalls = [];
$additionalFactory = static function () use (&$factoryCalls, &$transportCalls) {
    ++$factoryCalls;
    return new AnyTourAnexAdditionalPricesClient('test-token',
        static function (string $url, array $headers, array $options) use (&$transportCalls): array {
            $transportCalls[] = ['url' => $url, 'headers' => $headers, 'options' => $options];
            return ['status' => 200, 'body' => json_encode(['data' => [[
                'price_adult' => '120', 'price_chd' => '60', 'cashrate' => '104.23',
                'price_converted_adult' => '12507.6', 'price_converted_chd' => '6253.8',
                'tour' => 987654321, 'currency' => 345, 'dateBeg' => '2026-09-20T00:00:00', 'nights' => 7,
            ]], 'totalCount' => 1, 'totalPages' => 1], JSON_THROW_ON_ERROR)];
        });
};

$state = $stateTemplate;
$result = anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $additionalFactory);
$assert($result['status'] === 'additional_prices', 'completed status');
$attempt = array_values($state['additional_prices'])[0];
$readFacts = $attempt['evidence']['_read_diagnostics'] ?? [];
$assert($readFacts['source'] === 'supplier_transport' && $readFacts['supplier_calls'] === 1
    && $readFacts['observed_at_basis'] === 'local_consumption' && $readFacts['http_status'] === 200
    && is_int($readFacts['elapsed_ms'] ?? null), 'ordinary success retains actual current-read diagnostics');
$assert(!array_key_exists('_read_diagnostics', $result['additional_prices']), 'private diagnostics excluded from public ordinary reply');

$assert($factoryCalls === 1 && count($transportCalls) === 1 && $checkpoints === 1, 'one real client transport after one checkpoint');
$assert(strpos($transportCalls[0]['url'], 'tour=987654321') !== false
    && strpos($transportCalls[0]['url'], 'dateBeg=2026-09-20') !== false
    && strpos($transportCalls[0]['url'], 'nights=7') !== false
    && strpos($transportCalls[0]['url'], 'currency=345') !== false, 'private retained criteria drive real client request');
$evidence = $result['additional_prices'];
$assert($evidence['application_reason'] === 'applied', 'fixed application diagnostic is not final-price authority');
$assert($evidence['rows'][0]['price_adult'] === '120' && $evidence['rows'][0]['cashrate'] === '104.23'
    && $evidence['rows'][0]['price_converted_adult'] === '12507.6', 'raw supplier money facts preserved');
$assert($evidence['scope'] === 'tour_program_date_nights_currency' && $evidence['offer_specific'] === false,
    'supplier scope remains program/date based');
$assert($evidence['converted_currency'] === 'RUB' && $evidence['per_person_or_package'] === 'per_person_by_party_type',
    'retained party application identifies converted per-person rates');
$assert($evidence['fuel_equivalence_verified'] === false && $evidence['included_in_search_price'] === false
    && $evidence['arithmetic_applied'] === true && $evidence['final_price_verified'] === false,
    'program addition is applied without claiming Tourvisor fuel or final quote equivalence');
$assert($evidence['party'] === ['adults' => 2, 'children' => 0]
    && $evidence['rates']['adult'] === ['amount' => '12507.6', 'currency' => 'RUB']
    && $evidence['rates']['child'] === null, 'two-adult party uses adult program rate only');
$assert($evidence['party_surcharge'] === ['amount' => '25015.2', 'currency' => 'RUB', 'source' => 'anex_b2b_additional_prices_daily'],
    'two-adult program surcharge summed exactly');
$assert($evidence['search_price'] === ['amount' => '100000', 'currency' => 'RUB', 'source' => 'direct_anex_search']
    && $evidence['search_plus_additional']['amount'] === '125015.2'
    && $evidence['search_plus_additional']['currency'] === 'RUB', 'base search price and price with additional stay separate');
$json = json_encode($result, JSON_THROW_ON_ERROR);
$assert(strpos($json, '987654321') === false && strpos($json, '"currency":345') === false,
    'private supplier criteria do not cross public result');

$again = anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $additionalFactory);
$assert($again['status'] === 'additional_prices' && $factoryCalls === 1 && count($transportCalls) === 1 && $checkpoints === 1,
    'completed evidence is supplier-free cached read');
$assert($again === $result, 'cached application returns the same diagnostic and money facts without another request');
$assert($again['additional_prices']['party_surcharge']['amount'] === '25015.2'
    && $again['additional_prices']['search_plus_additional']['amount'] === '125015.2', 'cached raw evidence reapplies the same party arithmetic');

$childOffer = $offer;
$childOffer['children'] = 1;
$childApplied = anytour_anex_search3_additional_application(anytour_anex_search3_additional_evidence(['data' => [[
    'price_adult' => '120', 'price_chd' => '60', 'cashrate' => '104.23',
    'price_converted_adult' => '12507.6', 'price_converted_chd' => '6253.8',
]], 'totalCount' => 1]), $childOffer);
$assert($childApplied['party_surcharge']['amount'] === '31269'
    && $childApplied['search_plus_additional']['amount'] === '131269', 'child program rate is added once for one child');
$missingChild = anytour_anex_search3_additional_application(anytour_anex_search3_additional_evidence(['data' => [[
    'price_adult' => '120', 'cashrate' => '104.23', 'price_converted_adult' => '12507.6',
]], 'totalCount' => 1]), $childOffer);
$assert($missingChild['application_state'] === 'unknown' && $missingChild['party_surcharge'] === null
    && $missingChild['search_plus_additional'] === null && $missingChild['arithmetic_applied'] === false,
    'missing child rate is unknown and never treated as zero');
$assert($missingChild['application_reason'] === 'money_unconfirmed', 'missing child rate has a local money diagnostic');
$ambiguous = anytour_anex_search3_additional_application(anytour_anex_search3_additional_evidence(['data' => [[
    'price_adult' => '120', 'price_chd' => '60', 'cashrate' => '104.23',
    'price_converted_adult' => '12507.6', 'price_converted_chd' => '6253.8',
], [
    'price_adult' => '121', 'price_chd' => '61', 'cashrate' => '104.23',
    'price_converted_adult' => '12611.83', 'price_converted_chd' => '6358.03',
]], 'totalCount' => 2]), $offer);
$assert($ambiguous['application_state'] === 'unknown' && $ambiguous['arithmetic_applied'] === false,
    'multiple program/date rate rows do not choose one silently');
$assert($ambiguous['application_reason'] === 'rate_rows_unconfirmed', 'ambiguous rates have a local cardinality diagnostic');

// Diagnostics are derived locally from this application, never copied from an
// input reason or interpreted as supplier HTTP/capability/price authority.
$diagnosticCases = [
    [array_replace($evidence, ['rows' => [], 'total_count' => 0]), $offer, 'rate_rows_unconfirmed'],
    [array_replace($evidence, ['truncated' => true]), $offer, 'rate_rows_unconfirmed'],
    [$evidence, array_replace($offer, ['infants' => 1]), 'party_unconfirmed'],
    [$evidence, array_replace($offer, ['adults' => '2']), 'party_unconfirmed'],
    [$evidence, array_replace($offer, ['price' => ['amount' => '100000', 'currency' => 'USD']]), 'money_unconfirmed'],
];
foreach ($diagnosticCases as [$input, $party, $reason]) {
    $input['application_reason'] = 'untrusted supplier text/token/URL';
    $diagnostic = anytour_anex_search3_additional_application($input, $party);
    $assert($diagnostic['application_reason'] === $reason
        && $diagnostic['application_state'] === 'unknown' && $diagnostic['arithmetic_applied'] === false
        && $diagnostic['party_surcharge'] === null && $diagnostic['search_price'] === null
        && $diagnostic['search_plus_additional'] === null && $diagnostic['final_price_verified'] === false,
        'fixed local reason preserves unknown money and final-price guards');
}
$reapplied = anytour_anex_search3_additional_application(array_replace($evidence,
    ['application_reason' => 'untrusted supplier text/token/URL']), $offer);
$assert($reapplied === $evidence, 'successful reapplication also replaces an untrusted reason without changing other facts');
$assert($factoryCalls === 1 && count($transportCalls) === 1 && $checkpoints === 1,
    'local diagnostic classification never makes a new request or reservation');

$state = $stateTemplate;
$mismatchFactoryCalls = 0;
$mismatchTransportCalls = 0;
$mismatchFactory = static function () use (&$mismatchFactoryCalls, &$mismatchTransportCalls) {
    ++$mismatchFactoryCalls;
    return new AnyTourAnexAdditionalPricesClient('test-token', static function () use (&$mismatchTransportCalls): array {
        ++$mismatchTransportCalls;
        return ['status' => 200, 'body' => json_encode(['data' => [[
            'price_adult' => '120', 'price_chd' => '60', 'cashrate' => '104.23',
            'price_converted_adult' => '12507.6', 'price_converted_chd' => '6253.8',
            'tour' => 987654320, 'currency' => 345, 'dateBeg' => '2026-09-20', 'nights' => 7,
        ]], 'totalCount' => 1, 'totalPages' => 1], JSON_THROW_ON_ERROR)];
    });
};
$mismatchFailed = false;
try {
    anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $mismatchFactory);
} catch (RuntimeException $error) {
    $mismatchFailed = $error->getMessage() === 'ANEX_B2B_CONTEXT_MISMATCH';
}
$assert($mismatchFailed && $mismatchFactoryCalls === 1 && $mismatchTransportCalls === 1,
    'wrong supplier program fails closed in real client');
$attempts = array_values($state['additional_prices']);
$assert(count($attempts) === 1 && $attempts[0]['status'] === 'unknown', 'context mismatch remains no-replay unknown');
$never = static function () { throw new RuntimeException('REPLAY_FORBIDDEN'); };
$unknown = anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $never);
$assert($unknown['status'] === 'additional_prices_unknown', 'context mismatch is not replayed');

$state = $stateTemplate;
$badFactoryCalls = 0;
$badTransportCalls = 0;
$badFactory = static function () use (&$badFactoryCalls, &$badTransportCalls) {
    ++$badFactoryCalls;
    return new AnyTourAnexAdditionalPricesClient('test-token', static function () use (&$badTransportCalls): array {
        ++$badTransportCalls;
        return ['status' => 200, 'body' => json_encode(['data' => [[
            'price_adult' => 'bad', 'tour' => 987654321, 'currency' => 345,
            'dateBeg' => '2026-09-20', 'nights' => 7,
        ]], 'totalCount' => 1, 'totalPages' => 1], JSON_THROW_ON_ERROR)];
    });
};
$failed = false;
try {
    anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $badFactory);
} catch (RuntimeException $error) {
    $failed = $error->getMessage() === 'ANEX_INVALID_ADDITIONAL_PRICES';
}
$assert($failed && $badFactoryCalls === 1 && $badTransportCalls === 1, 'malformed supplier money fails closed after valid context');
$attempts = array_values($state['additional_prices']);
$assert(count($attempts) === 1 && $attempts[0]['status'] === 'unknown', 'malformed money remains no-replay unknown');
$unknown = anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $never);
$assert($unknown['status'] === 'additional_prices_unknown', 'malformed money is not replayed');

$cacheDir = sys_get_temp_dir() . '/anytour-anex-apd-single-' . bin2hex(random_bytes(6));
if (!mkdir($cacheDir, 0700, true) && !is_dir($cacheDir)) throw new RuntimeException('FAIL: cache fixture directory');
$criteria = ['page' => 1, 'pageSize' => 10, 'tour' => 987654321, 'dateBeg' => '2026-09-20', 'nights' => 7, 'currency' => 345];
$seedTransportCalls = 0;
$seedClient = new AnyTourAnexAdditionalPricesClient('test-token',
    static function () use (&$seedTransportCalls): array {
        ++$seedTransportCalls;
        throw new RuntimeException('seed transport failure');
    }, $cacheDir, $clock);
$seedFailed = false;
try { $seedClient->additionalPricesDaily($criteria); }
catch (RuntimeException $error) { $seedFailed = $error->getMessage() === 'ANEX_B2B_TRANSPORT_ERROR'; }
$assert($seedFailed && $seedTransportCalls === 1 && $seedClient->requestsMade() === 1,
    'first process leaves a durable same-day shared unknown after transport failure');

$state = $stateTemplate;
$dailyFactoryCalls = 0;
$dailyTransportCalls = 0;
$dailyCheckpoints = 0;
$dailyClient = null;
$dailyCheckpoint = static function (array &$state) use (&$dailyCheckpoints, $assert): void {
    ++$dailyCheckpoints;
    $attempts = array_values($state['additional_prices'] ?? []);
    $assert(count($attempts) === 1 && ($attempts[0]['status'] ?? null) === 'unknown',
        'single retained action reserves session unknown before shared-cache read');
};
$dailyFactory = static function () use (&$dailyFactoryCalls, &$dailyTransportCalls, &$dailyClient, $cacheDir, $clock) {
    ++$dailyFactoryCalls;
    return $dailyClient = new AnyTourAnexAdditionalPricesClient('test-token',
        static function () use (&$dailyTransportCalls): array {
            ++$dailyTransportCalls;
            throw new RuntimeException('shared unknown must prevent transport');
        }, $cacheDir, $clock);
};
$dailyUnknown = anytour_anex_search3_followup(
    $request, $state, $resolver, $directFactory, $metadata, $clock, $dailyCheckpoint, $dailyFactory);
$assert($dailyUnknown['status'] === 'additional_prices_unknown'
    && $dailyFactoryCalls === 1 && $dailyTransportCalls === 0 && $dailyCheckpoints === 1
    && $dailyClient instanceof AnyTourAnexAdditionalPricesClient && $dailyClient->requestsMade() === 0,
    'same-day shared unknown is a normal unknown result with zero supplier replay');
$attempts = array_values($state['additional_prices']);
$assert(count($attempts) === 1 && $attempts[0]['status'] === 'unknown',
    'shared daily unknown keeps the single-offer session attempt unknown');
$dailyAgain = anytour_anex_search3_followup(
    $request, $state, $resolver, $directFactory, $metadata, $clock, $dailyCheckpoint, $dailyFactory);
$assert($dailyAgain['status'] === 'additional_prices_unknown'
    && $dailyFactoryCalls === 1 && $dailyTransportCalls === 0 && $dailyCheckpoints === 1,
    'same session does not re-open a shared or supplier attempt after daily unknown');
foreach (glob($cacheDir . '/*/*') ?: [] as $path) @unlink($path);
foreach (glob($cacheDir . '/*') ?: [] as $path) is_dir($path) ? @rmdir($path) : @unlink($path);
@rmdir($cacheDir);

$state = $stateTemplate;
$freshTransportCalls = 0;
$freshTransportFactory = static function () use (&$freshTransportCalls) {
    return new AnyTourAnexAdditionalPricesClient('test-token', static function () use (&$freshTransportCalls): array {
        ++$freshTransportCalls;
        throw new RuntimeException('fresh transport failure');
    });
};
$freshTransportFailed = false;
try {
    anytour_anex_search3_followup(
        $request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $freshTransportFactory);
} catch (RuntimeException $error) {
    $freshTransportFailed = $error->getMessage() === 'ANEX_B2B_TRANSPORT_ERROR';
}
$assert($freshTransportFailed && $freshTransportCalls === 1,
    'fresh transport failure still propagates and is not converted to cached unknown');
$attempts = array_values($state['additional_prices']);
$assert(count($attempts) === 1 && $attempts[0]['status'] === 'unknown',
    'fresh transport failure remains durable no-replay unknown');

$state = $stateTemplate;
$state['gateway']['saved_offers']['offers'][$offerRef]['supplier_currency_id'] = null;
$missing = anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $never);
$assert($missing['status'] === 'additional_prices_unavailable' && $state['additional_prices'] === [],
    'missing native currency stays unavailable without supplier call');

$state = $stateTemplate;
$state['gateway']['saved_offers']['offers'][$offerRef]['offer']['kind'] = 'group_minimum';
$state['gateway']['search']['offers'][0]['kind'] = 'group_minimum';
$group = anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $never);
$assert($group['status'] === 'not_concrete' && $state['additional_prices'] === [], 'group minimum cannot request additional evidence');

// Diagnostic provenance is independent of price, and daily cache hits have no original transport authority.
$diagCache = sys_get_temp_dir() . '/anex-apd-read-facts-' . bin2hex(random_bytes(6));
$diagClock = static fn(): int => 1789220100;
$diagCriteria = ['page' => 1, 'pageSize' => 10, 'tour' => 987654321, 'dateBeg' => '2026-09-20', 'nights' => 7, 'currency' => 345];
try {
    $fresh = new AnyTourAnexAdditionalPricesClient('fresh-token', static fn(): array =>
        ['status' => 200, 'body' => '{"data":[],"totalCount":0}'], $diagCache, $diagClock);
    $fresh->additionalPricesDaily($diagCriteria);
    $freshFacts = anytour_anex_search3_apd_capture_diagnostics($fresh);
    $assert($freshFacts['source'] === 'supplier_transport' && $freshFacts['supplier_calls'] === 1
        && $freshFacts['cache_status'] === 'miss' && $freshFacts['http_status'] === 200,
        'fresh empty successful transport distinguished from cache');
    $cached = new AnyTourAnexAdditionalPricesClient('cached-token', static function (): array {
        throw new RuntimeException('CACHE_MUST_NOT_CALL_SUPPLIER');
    }, $diagCache, $diagClock);
    $cached->additionalPricesDaily($diagCriteria);
    $cacheFacts = anytour_anex_search3_apd_capture_diagnostics($cached);
    $assert($cacheFacts['source'] === 'daily_cache' && $cacheFacts['supplier_calls'] === 0
        && $cacheFacts['cache_status'] === 'hit' && $cacheFacts['observed_at_basis'] === 'local_consumption'
        && array_intersect_key($cacheFacts, array_flip(['http_status', 'elapsed_ms', 'response_bytes'])) === [],
        'cache hit cannot acquire fresh or original supplier HTTP/time facts');
    $legacyFacts = anytour_anex_search3_apd_read_diagnostics(null);
    $assert($legacyFacts['observed_at_basis'] === 'unknown' && $legacyFacts['supplier_calls'] === null,
        'legacy retained observation is not relabeled as measured supplier time');
    $malicious = ['action' => 'AdditionalPricesDaily', 'observed_at_basis' => 'local_consumption',
        'supplier_calls' => 1, 'cache_status' => 'miss', 'http_status' => '200', 'elapsed_ms' => -1,
        'response_bytes' => 524289, 'url' => 'PRIVATE_CANARY', 'body' => 'PRIVATE_CANARY', 'curl_errno' => 28];
    $scrubbed = anytour_anex_search3_apd_read_diagnostics($malicious);
    $assert(array_keys($scrubbed) === ['observed_at_basis', 'source', 'supplier_calls', 'cache_status']
        && !str_contains(json_encode($scrubbed), 'PRIVATE_CANARY'), 'typed numeric bounds and fixed allowlist reject raw fields');
    $malicious['supplier_calls'] = true;
    $assert(anytour_anex_search3_apd_read_diagnostics($malicious) === $legacyFacts, 'boolean call count cannot imply one supplier request');
    $privateEmpty = anytour_anex_search3_additional_evidence(['data' => [], 'totalCount' => 0]);
    $privateEmpty['_read_diagnostics'] = $freshFacts;
    $publicEmpty = anytour_anex_search3_additional_application($privateEmpty, $offer);
    $assert(!array_key_exists('_read_diagnostics', $publicEmpty) && $publicEmpty['party_surcharge'] === null
        && $publicEmpty['final_price_verified'] === false, 'empty public application remains unknown and receipt-only facts stay private');
} finally {
    foreach (glob($diagCache . '/*/*') ?: [] as $path) @unlink($path);
    foreach (glob($diagCache . '/*', GLOB_ONLYDIR) ?: [] as $path) @rmdir($path);
    @rmdir($diagCache);
}

// The existing foreground workflow executes this harness; run the changed client contract in an isolated process too.
$clientPipes = [];
$clientProcess = proc_open([PHP_BINARY, '-n', __DIR__ . '/anex-additional-prices-client-test.php'],
    [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $clientPipes);
$assert(is_resource($clientProcess), 'isolated client contract process starts');
$clientOutput = stream_get_contents($clientPipes[1]); $clientErrors = stream_get_contents($clientPipes[2]);
fclose($clientPipes[1]); fclose($clientPipes[2]); $clientCode = proc_close($clientProcess);
$assert($clientCode === 0 && $clientErrors === '' && str_contains($clientOutput, 'network=0.'),
    'existing client contract including unchanged failure diagnostics passes without network');

echo "ANEX retained additional-prices party arithmetic: {$checks} checks passed; network=0\n";
