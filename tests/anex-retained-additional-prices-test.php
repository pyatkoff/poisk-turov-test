<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-normalizer.php';
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

$client = new class {
    public $calls = [];
    public function additionalPricesDaily(array $params): array
    {
        $this->calls[] = $params;
        return ['data' => [[
            'price_adult' => '120', 'price_chd' => '120', 'cashrate' => '104.23',
            'price_converted_adult' => '12507.6', 'price_converted_chd' => '12507.6',
            'tour' => 987654321, 'currency' => 345,
        ]], 'totalCount' => 1];
    }
};
$factoryCalls = 0;
$additionalFactory = static function () use ($client, &$factoryCalls) {
    ++$factoryCalls;
    return $client;
};
$checkpoints = 0;
$checkpoint = static function (array &$state) use (&$checkpoints, $assert): void {
    ++$checkpoints;
    $attempts = array_values($state['additional_prices'] ?? []);
    $assert(count($attempts) === 1 && ($attempts[0]['status'] ?? null) === 'unknown', 'reservation before transport');
};

$state = $stateTemplate;
$result = anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $additionalFactory);
$assert($result['status'] === 'additional_prices', 'completed status');
$assert($factoryCalls === 1 && count($client->calls) === 1 && $checkpoints === 1, 'one client call after one checkpoint');
$assert($client->calls[0] === ['page' => 1, 'pageSize' => 10, 'tour' => 987654321,
    'dateBeg' => '2026-09-20', 'nights' => 7, 'currency' => 345], 'private retained criteria drive request');
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
$assert($again['status'] === 'additional_prices' && $factoryCalls === 1 && count($client->calls) === 1 && $checkpoints === 1,
    'completed evidence is supplier-free cached read');

$state = $stateTemplate;
$badClient = new class {
    public $calls = 0;
    public function additionalPricesDaily(array $params): array { ++$this->calls; return ['data' => [['price_adult' => 'bad']], 'totalCount' => 1]; }
};
$badFactoryCalls = 0;
$badFactory = static function () use ($badClient, &$badFactoryCalls) { ++$badFactoryCalls; return $badClient; };
$failed = false;
try {
    anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $badFactory);
} catch (RuntimeException $error) {
    $failed = $error->getMessage() === 'ANEX_INVALID_ADDITIONAL_PRICES';
}
$assert($failed && $badFactoryCalls === 1 && $badClient->calls === 1, 'malformed supplier money fails closed');
$attempts = array_values($state['additional_prices']);
$assert(count($attempts) === 1 && $attempts[0]['status'] === 'unknown', 'failed transport/result remains no-replay unknown');
$never = static function () { throw new RuntimeException('REPLAY_FORBIDDEN'); };
$unknown = anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $never);
$assert($unknown['status'] === 'additional_prices_unknown', 'unknown attempt is not replayed');

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

echo "ANEX retained additional-prices binding: {$checks} checks passed\n";
