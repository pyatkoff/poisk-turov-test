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

$factoryCalls = 0;
$transportCalls = [];
$additionalFactory = static function () use (&$factoryCalls, &$transportCalls) {
    ++$factoryCalls;
    return new AnyTourAnexAdditionalPricesClient('test-token',
        static function (string $url, array $headers, array $options) use (&$transportCalls): array {
            $transportCalls[] = ['url' => $url, 'headers' => $headers, 'options' => $options];
            return ['status' => 200, 'body' => json_encode(['data' => [[
                'price_adult' => '120', 'price_chd' => '120', 'cashrate' => '104.23',
                'price_converted_adult' => '12507.6', 'price_converted_chd' => '12507.6',
                'tour' => 987654321, 'currency' => 345, 'dateBeg' => '2026-09-20T00:00:00', 'nights' => 7,
            ]], 'totalCount' => 1, 'totalPages' => 1], JSON_THROW_ON_ERROR)];
        });
};

$state = $stateTemplate;
$result = anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $additionalFactory);
$assert($result['status'] === 'additional_prices', 'completed status');
$assert($factoryCalls === 1 && count($transportCalls) === 1 && $checkpoints === 1, 'one real client transport after one checkpoint');
$assert(strpos($transportCalls[0]['url'], 'tour=987654321') !== false
    && strpos($transportCalls[0]['url'], 'dateBeg=2026-09-20') !== false
    && strpos($transportCalls[0]['url'], 'nights=7') !== false
    && strpos($transportCalls[0]['url'], 'currency=345') !== false, 'private retained criteria drive real client request');
$evidence = $result['additional_prices'];
$assert($evidence['rows'][0]['price_adult'] === '120' && $evidence['rows'][0]['cashrate'] === '104.23'
    && $evidence['rows'][0]['price_converted_adult'] === '12507.6', 'money facts preserved as decimals');
$assert($evidence['scope'] === 'tour_program_date_nights_currency' && $evidence['offer_specific'] === false,
    'scope does not overclaim offer specificity');
$assert($evidence['currency'] === null && $evidence['converted_currency'] === null
    && $evidence['per_person_or_package'] === 'unknown', 'currency and application semantics stay unknown');
$assert($evidence['fuel_equivalence_verified'] === false && $evidence['included_in_search_price'] === 'unknown'
    && $evidence['arithmetic_applied'] === false && $evidence['final_price_verified'] === false, 'no price or fuel inference');
$json = json_encode($result, JSON_THROW_ON_ERROR);
$assert(strpos($json, '987654321') === false && strpos($json, '"currency":345') === false,
    'private supplier criteria do not cross public result');

$again = anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $additionalFactory);
$assert($again['status'] === 'additional_prices' && $factoryCalls === 1 && count($transportCalls) === 1 && $checkpoints === 1,
    'completed evidence is supplier-free cached read');

$state = $stateTemplate;
$mismatchFactoryCalls = 0;
$mismatchTransportCalls = 0;
$mismatchFactory = static function () use (&$mismatchFactoryCalls, &$mismatchTransportCalls) {
    ++$mismatchFactoryCalls;
    return new AnyTourAnexAdditionalPricesClient('test-token', static function () use (&$mismatchTransportCalls): array {
        ++$mismatchTransportCalls;
        return ['status' => 200, 'body' => json_encode(['data' => [[
            'price_adult' => '120', 'price_chd' => '120', 'cashrate' => '104.23',
            'price_converted_adult' => '12507.6', 'price_converted_chd' => '12507.6',
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

echo "ANEX retained additional-prices real-client binding: {$checks} checks passed; network=0\n";
