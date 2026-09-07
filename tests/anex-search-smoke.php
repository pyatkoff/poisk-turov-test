<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/anex-search.php';

function search_check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function search_reject(callable $operation): void {
    try { $operation(); } catch (Throwable $e) { return; }
    throw new RuntimeException('expected rejection');
}
$criteria = ['supplier_namespace' => 'anex_online', 'departure_id' => '1',
    'destination_id' => '2', 'currency_id' => '3', 'checkin_begin' => '20260914',
    'checkin_end' => '20260914', 'nights_from' => 7, 'nights_till' => 7,
    'adults' => 2, 'children' => 0, 'hotel_ids' => ['469']];
$baseRow = ['id' => 'group-fixture', 'hotelKey' => 469, 'hotel' => 'Jaz Sharm Dreams',
    'checkIn' => '20260914', 'checkOut' => '20260921', 'nights' => 7, 'adult' => 2,
    'child' => 0, 'packetType' => 0, 'price' => '1234.50', 'currency' => 'EUR',
    'convertedPrice' => '100 123.45 RUB', 'grouped' => 1, 'bron' => 0];
$calls = [];
$transport = static function (string $url, array $options) use (&$calls, $baseRow): array {
    parse_str((string)parse_url($url, PHP_URL_QUERY), $params);
    $calls[] = $params;
    $method = $params['action'];
    if ($method === 'FreightMonitor_FREIGHTSBYPACKET') {
        search_check($params['CATCLAIM'] === 'concrete-fixture', 'wrong flight reference');
        return ['status' => 200, 'body' => json_encode([$method => ['routes' => [[
            'info' => ['date' => '20260914', 'sourceTown' => 'Москва', 'targetTown' => 'Шарм-эль-Шейх'],
            'freights' => [['name' => 'TEST 123', 'transportCompany' => 'Fixture carrier',
                'departure' => ['portAlias' => 'SVO', 'time' => '10:00'],
                'arrival' => ['portAlias' => 'SSH', 'time' => '16:00'],
                'places' => [['class' => 'Economy', 'status' => 'yesplace',
                    'baggage' => ['baggage' => 20, 'baggageHand' => 'fixture-secret', 'baggageInfant' => 3]],
                    ['class' => 'Business', 'status' => 'noplace', 'baggage' => ['baggage' => 0, 'baggageHand' => 5.5]]]]]
        ]]]])];
    }
    if (isset($params['CATCLAIM'])) {
        search_check(!isset($params['PARTITION_PRICE']), 'expanded query still grouped');
        search_check($params['CATCLAIM'] === 'group-fixture' && $params['HOTELS'] === '469', 'wrong group reference');
        $concrete = array_replace($baseRow, ['id' => 'concrete-fixture', 'grouped' => 0, 'bron' => 1]);
        $wrongHotel = array_replace($concrete, ['id' => 'wrong-hotel', 'hotelKey' => 470]);
        return ['status' => 200, 'body' => json_encode([$method => ['prices' => [$concrete, $wrongHotel, $baseRow]]])];
    }
    search_check($params['PARTITION_PRICE'] === '32', 'expected hotel minimum grouping');
    return ['status' => 200, 'body' => json_encode([$method => ['prices' => [$baseRow,
        array_replace($baseRow, ['id' => 'wrong-filter', 'hotelKey' => 470])]]])];
};
$client = new AnyTourAnexClient('fixture-secret', $transport);
$search = new AnyTourAnexSearch($client, static function (string $provider, string $id): ?int {
    return $provider === 'anex_online' && $id === '469' ? 245 : null;
}, ['fixture-secret']);
search_reject(static function () use ($search) { $search->expand('invented'); });
$groups = $search->search($criteria);
$group = $groups['offers'][0];
search_check(count($groups['offers']) === 1 && $groups['rejected_count'] === 1, 'hotel filter not enforced');
search_check($group['hotel']['local_id'] === 245, 'mapping not attached');
search_reject(static function () use ($search, $group) { $search->flights($group['offer_key']); });
$expanded = $search->expand($group['offer_key']);
search_check(count($expanded['offers']) === 1 && $expanded['rejected_count'] === 2, 'cross-hotel/group rows admitted');
$offer = $expanded['offers'][0];
search_check($offer['final_price_verified'] === false, 'search price promoted to final');
$flights = $search->flights($offer['offer_key']);
search_check($flights['routes'][0]['options'][0]['classes'][0]['baggage'] === '20', 'numeric baggage missing');
search_check($flights['routes'][0]['options'][0]['classes'][0]['availability'] === 'Y', 'yesplace missing');
search_check($flights['routes'][0]['options'][0]['classes'][1]['availability'] === 'N', 'noplace missing');
search_check($flights['routes'][0]['options'][0]['classes'][1]['baggage'] === '0', 'explicit zero lost');
search_check($flights['routes'][0]['options'][0]['classes'][1]['hand_baggage'] === '5.5', 'decimal baggage missing');
search_check($flights['itinerary_details_available'], 'complete itinerary not recognized');
search_check($flights['routes'][0]['options'][0]['classes'][0]['hand_baggage'] === null, 'secret leaked');
search_check(!$flights['selected'] && !$flights['included_in_search_price_verified'], 'flight options treated as selected');
search_check($client->requestsMade() === 3, 'unexpected requests');
search_reject(static function () use ($search, $criteria) { $search->search(array_replace($criteria, ['supplier_namespace' => 'tourvisor'])); });
search_reject(static function () use ($search, $offer) { $search->flights($offer['offer_key']); });
search_check($client->requestsMade() === 3, 'invalid search reached network');
echo "ANEX_SEARCH_SMOKE_OK search/expand/flights/stale-state/source-identity\n";
