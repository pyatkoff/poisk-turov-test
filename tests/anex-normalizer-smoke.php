<?php
declare(strict_types=1);

// Offline fixtures contain fabricated identifiers, never live supplier tokens.
require_once __DIR__ . '/../app/integrations/anex-normalizer.php';

function anex_normalizer_check(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('ANEX normalizer failed: ' . $label);
    }
}

$context = [
    'checkin_begin' => '20260914', 'checkin_end' => '20260916',
    'nights_from' => 7, 'nights_till' => 10, 'adults' => 2, 'children' => 0,
    'departure_id' => 1, 'destination_id' => '2', 'currency_id' => 3,
];
$row = json_decode('{
    "id":"fixture-offer-1", "hotelKey":30160,
    "hotel":"Грейс Кристалл", "star":"3*", "meal":"BB", "room":"Standard",
    "checkIn":"20260914", "checkOut":"20260921", "nights":7,
    "adult":2, "child":0, "packetType":0, "price":"790.00", "currency":"EUR",
    "convertedPrice":"84 617.00 RUB", "grouped":1, "bron":1,
    "hotelAvailability":"YYYY", "freights":{"econom":{"in":"Y", "out":"R"}},
    "description":"Never copy descriptions", "url":"https://private.invalid/secret"
}', true, 32, JSON_THROW_ON_ERROR);
$resolverCalls = [];
$resolver = static function (string $provider, string $externalId) use (&$resolverCalls): ?int {
    $resolverCalls[] = [$provider, $externalId];
    return $provider === 'anex_online' && $externalId === '30160' ? 40430 : null;
};
$grouped = anytour_anex_normalize_prices(['SearchTour_PRICES' => ['prices' => [$row]]], $context, $resolver);
$group = $grouped['offers'][0];
anex_normalizer_check($grouped['schema_version'] === 1 && $grouped['rejected_count'] === 0, 'schema');
anex_normalizer_check($group['kind'] === 'group_minimum' && !$group['final_price_verified'], 'group is not confirmed');
anex_normalizer_check($group['supplier_booking_flag'] === true, 'bron is separate from final verification');
anex_normalizer_check($group['hotel']['local_id'] === 40430 && $resolverCalls === [['anex_online', '30160']], 'provider-scoped mapping');
anex_normalizer_check($group['price'] === ['amount' => '790.00', 'currency' => 'EUR'], 'native decimal precision');
anex_normalizer_check($group['converted_price'] === ['amount' => '84617.00', 'currency' => 'RUB'], 'supplier conversion parsed separately');
anex_normalizer_check($group['availability']['flight_outbound_economy'] === 'Y'
    && $group['availability']['flight_return_economy'] === 'R', 'observed flight marker direction');
anex_normalizer_check($group['supplier_offer_id'] === 'fixture-offer-1'
    && preg_match('/^anex_online:[a-f0-9]{64}$/D', $group['offer_key']) === 1, 'internal id and public hash');

$concreteRow = array_replace($row, ['grouped' => '0', 'hotelKey' => '30536', 'price' => 793, 'bron' => null]);
unset($concreteRow['freights'], $concreteRow['hotelAvailability']);
$concrete = anytour_anex_normalize_prices(['prices' => [$concreteRow]], $context, $resolver)['offers'][0];
anex_normalizer_check($concrete['kind'] === 'concrete' && !$concrete['final_price_verified'], 'concrete is not final quote');
anex_normalizer_check($concrete['supplier_booking_flag'] === null
    && $concrete['availability'] === ['hotel' => null, 'flight_outbound_economy' => null, 'flight_return_economy' => null], 'missing availability stays unknown');
anex_normalizer_check($concrete['hotel']['local_id'] === null
    && $concrete['hotel']['external_id'] === '30536', 'unmapped retains only external identity');
anex_normalizer_check($group['offer_key'] !== $concrete['offer_key'], 'group and concrete keys are distinct');
anex_normalizer_check($group['offer_key'] === anytour_anex_normalize_prices(['prices' => [$row]], $context)['offers'][0]['offer_key'], 'stable key');

$invalid = [
    ['id' => ''], ['id' => 'https://private.invalid/?token=value'], ['id' => ['nested']],
    ['hotelKey' => 0], ['hotelKey' => true], ['hotelKey' => '01'], ['hotelKey' => '30160x'],
    ['price' => -1], ['price' => 0], ['price' => 'NaN'], ['price' => INF], ['price' => NAN],
    ['price' => '1e3'], ['price' => '100.001'], ['price' => true], ['currency' => ''],
    ['currency' => 'RUB<script>'], ['grouped' => null], ['grouped' => 'unknown'], ['grouped' => 2],
    ['checkIn' => '20260230'], ['checkOut' => '20261301'], ['checkOut' => '20260914'],
    ['checkIn' => '20260913'], ['checkIn' => '20260914junk'],
    ['nights' => 6], ['adult' => 1], ['child' => 1], ['packetType' => 1], ['hotel' => ''],
    ['infant' => 1], ['infant' => 'unknown'],
];
$badRows = [];
foreach ($invalid as $fields) {
    $badRows[] = array_replace($row, $fields);
}
$rejected = anytour_anex_normalize_prices(['prices' => array_merge([$row], $badRows, [null])], $context);
anex_normalizer_check(count($rejected['offers']) === 1
    && $rejected['rejected_count'] === count($badRows) + 1, 'malformed rows cannot become offers');

foreach ([['prices' => 'bad'], ['prices' => ['key' => $row]], ['error' => 'private message'],
    ['SearchTour_PRICES' => ['error' => 'private message']], []] as $payload) {
    try {
        anytour_anex_normalize_prices($payload, $context);
        throw new RuntimeException('Expected envelope rejection');
    } catch (InvalidArgumentException $error) {
        anex_normalizer_check($error->getMessage() === 'invalid_anex_prices', 'fixed envelope error');
    }
}
foreach ([['checkin_begin' => '20260230'], ['checkin_end' => '20260901'], ['nights_from' => 11],
    ['adults' => true], ['children' => -1], ['children' => 1], ['child_ages' => [8]],
    ['children' => 1, 'child_ages' => [18]], ['departure_id' => '1&oauth_token=value']] as $fields) {
    try {
        anytour_anex_normalize_prices(['prices' => []], array_replace($context, $fields));
        throw new RuntimeException('Expected context rejection');
    } catch (InvalidArgumentException $error) {
        anex_normalizer_check($error->getMessage() === 'invalid_anex_search_context', 'fixed context error');
    }
}

$secret = 'fixture-private-api-token';
$hostile = array_replace($row, [
    'hotel' => "<b>Грейс</b>\x01 Кристалл", 'meal' => 'BB ' . $secret,
    'room' => 'https://private.invalid/?oauth_token=' . $secret,
    'star' => '&#102;ixture-private-api-token',
    'message' => $secret, 'description' => $secret, 'extra_total' => '999999',
    'freights' => ['econom' => ['in' => '?', 'out' => false], 'flightNumber' => 'SU000'],
    'hotelAvailability' => 'unknown',
]);
$clean = anytour_anex_normalize_prices(['prices' => [$hostile]], $context, null, ['  ' . $secret . '  ']);
$serialized = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
anex_normalizer_check($clean['offers'][0]['hotel']['name'] === 'Грейс Кристалл', 'strip labels only');
foreach ([$secret, 'private.invalid', 'SU000', 'description', 'extra_total', 'oauth_token'] as $untrusted) {
    anex_normalizer_check(strpos($serialized, $untrusted) === false, 'no untrusted field or credential copied');
}
anex_normalizer_check($clean['offers'][0]['availability']['hotel'] === null
    && $clean['offers'][0]['availability']['flight_outbound_economy'] === null, 'unknown markers preserved as unknown');
foreach (['Hotel ' . $secret, '&#102;ixture-private-api-token', 'https://private.invalid/hotel'] as $name) {
    $hidden = anytour_anex_normalize_prices(['prices' => [array_replace($row, ['hotel' => $name])]], $context, null, [$secret]);
    anex_normalizer_check($hidden['rejected_count'] === 1, 'secret or URL hotel rejected');
}
$longName = anytour_anex_normalize_prices(['prices' => [array_replace($row, ['hotel' => str_repeat('Ж', 300)])]], $context);
anex_normalizer_check($longName['offers'][0]['hotel']['name'] === str_repeat('Ж', 180), 'UTF-8 bound');

foreach (['1,234 RUB', '12 34.00 RUB', '-10 RUB', 'Infinity RUB', '10.00 rub', '10.00 RUB extra'] as $converted) {
    $offer = anytour_anex_normalize_prices(['prices' => [array_replace($row, ['convertedPrice' => $converted])]], $context)['offers'][0];
    anex_normalizer_check($offer['converted_price'] === null && $offer['price']['currency'] === 'EUR', 'unsafe conversion omitted');
}
$usd = anytour_anex_normalize_prices(['prices' => [array_replace($row, ['currency' => 'USD', 'price' => '1250.50', 'convertedPrice' => "112\xc2\xa0545,00 RUB"])]], $context)['offers'][0];
anex_normalizer_check($usd['price'] === ['amount' => '1250.50', 'currency' => 'USD']
    && $usd['converted_price'] === ['amount' => '112545.00', 'currency' => 'RUB'], 'currency amount separation');

$partial = anytour_anex_normalize_prices(['prices' => [$row, $concreteRow]], $context, $resolver);
anex_normalizer_check(count($partial['offers']) === 2 && $partial['offers'][0]['hotel']['local_id'] === 40430
    && $partial['offers'][1]['hotel']['local_id'] === null, 'partial mapping does not discard offers');
$resolverError = anytour_anex_normalize_prices(['prices' => [$row]], $context, static function () use ($secret) {
    throw new RuntimeException($secret);
});
anex_normalizer_check($resolverError['offers'][0]['hotel']['local_id'] === null, 'resolver error stays unmapped');
$wrongResolver = anytour_anex_normalize_prices(['prices' => [$row]], $context, static function () { return '40430'; });
anex_normalizer_check($wrongResolver['offers'][0]['hotel']['local_id'] === null, 'invalid resolver return not trusted');
$bounded = anytour_anex_normalize_prices(['prices' => array_fill(0, 305, $row)], $context);
anex_normalizer_check(count($bounded['offers']) === 300 && $bounded['truncated_count'] === 5
    && $bounded['rejected_count'] === 0, 'bounded page count');
$empty = anytour_anex_normalize_prices(['prices' => []], $context);
anex_normalizer_check($empty['offers'] === [] && $empty['rejected_count'] === 0, 'valid empty result');
$external = anytour_anex_normalize_prices(['prices' => [], 'searchKey' => 'private-external-search'], $context);
anex_normalizer_check($external['external_search_pending'] === true
    && strpos(json_encode($external), 'private-external-search') === false, 'pending external results not represented as complete');
$children = anytour_anex_normalize_prices(['prices' => [array_replace($row, ['child' => 1, 'infant' => 0])]],
    array_replace($context, ['children' => 1, 'child_ages' => ['8']]));
anex_normalizer_check($children['search']['child_ages'] === [8] && count($children['offers']) === 1
    && $children['offers'][0]['infants'] === 0, 'explicit child age context retained');
$travelNights = anytour_anex_normalize_prices(['prices' => [array_replace($row, ['checkOut' => '20260922'])]], $context);
anex_normalizer_check(count($travelNights['offers']) === 1, 'hotel dates not equated to transport-inclusive nights');

echo "ANEX normalizer smoke: passed\n";
