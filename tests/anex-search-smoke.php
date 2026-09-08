<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/anex-search.php';
require_once __DIR__ . '/../app/integrations/anex-preview-gateway.php';

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

$batchCalls = [];
$batchClient = new AnyTourAnexClient('batch-secret', static function (string $url) use (&$batchCalls): array {
    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
    $batchCalls[] = $params;
    return ['status' => 200, 'body' => json_encode([$params['action'] => ['prices' => []]])];
});
$batchSearch = new AnyTourAnexSearch($batchClient, null, ['batch-secret']);
$hotelIds = array_map('strval', range(469, 498));
$batchSearch->search(array_replace($criteria, ['hotel_ids' => $hotelIds]));
search_check(count($batchCalls) === 1
    && $batchCalls[0]['HOTELS'] === implode(',', $hotelIds), '30-hotel batch was not serialized');
search_reject(static function () use ($batchSearch, $criteria): void {
    $batchSearch->search(array_replace($criteria, ['hotel_ids' => array_map('strval', range(469, 499))]));
});
search_check(count($batchCalls) === 1, '31-hotel batch reached supplier');
$batchSearch->search(array_replace($criteria, ['hotel_ids' => ['469', 469, '470']]));
search_check($batchCalls[1]['HOTELS'] === '469,470', 'duplicate hotel ids were not normalized');

$clock = 1000;
$gatewayCalls = [];
$gatewayFactory = static function () use (&$gatewayCalls, $baseRow): AnyTourAnexClient {
    return new AnyTourAnexClient('gateway-secret', static function (string $url) use (&$gatewayCalls, $baseRow): array {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        $gatewayCalls[] = $params;
        $method = $params['action'];
        if ($method === 'FreightMonitor_FREIGHTSBYPACKET') {
            $payload = ['routes' => []];
        } elseif (isset($params['CATCLAIM'])) {
            $payload = ['prices' => [array_replace($baseRow, [
                'id' => 'gateway-concrete', 'grouped' => 0, 'bron' => 1,
            ])]];
        } else {
            $payload = ['prices' => [$baseRow]];
        }
        return ['status' => 200, 'body' => json_encode([$method => $payload])];
    });
};
$gateway = new AnyTourAnexPreviewGateway($gatewayFactory,
    static function (string $provider, string $id): ?int {
        return $provider === 'anex_online' && $id === '469' ? 245 : null;
    }, ['gateway-secret'], static function () use (&$clock): int { return $clock; });
$session = [];
$publicGroups = $gateway->handle(['action' => 'search', 'criteria' => $criteria], $session);
$publicGroup = $publicGroups['offers'][0];
search_check(!isset($publicGroup['supplier_offer_id'])
    && $publicGroup['hotel']['local_id'] === 245, 'gateway exposes only public mapped group');
search_check(isset($session['search']['offers'][0]['supplier_offer_id'])
    && strpos(json_encode($session), 'gateway-secret') === false, 'session keeps reference without token');
$publicExpanded = $gateway->handle(['action' => 'expand', 'offer_key' => $publicGroup['offer_key']], $session);
$publicConcrete = $publicExpanded['offers'][0];
search_check(!isset($publicConcrete['supplier_offer_id'])
    && count($session['search']['offers']) === 2, 'gateway restores and extends server state');
$publicFlights = $gateway->handle(['action' => 'flights', 'offer_key' => $publicConcrete['offer_key']], $session);
search_check($publicFlights['routes'] === [] && count($gatewayCalls) === 3, 'gateway spans HTTP-style calls');
search_reject(static function () use ($gateway, &$session): void {
    $gateway->handle(['action' => 'expand', 'offer_key' => 'anex_online:' . str_repeat('0', 64)], $session);
});
$corrupt = $session;
$corrupt['search']['offers'][0]['supplier_offer_id'] = 'https://invalid/?oauth_token=gateway-secret';
$beforeCorrupt = count($gatewayCalls);
search_reject(static function () use ($gateway, &$corrupt, $publicGroup): void {
    $gateway->handle(['offer_key' => $publicGroup['offer_key'], 'action' => 'expand'], $corrupt);
});
search_check(count($gatewayCalls) === $beforeCorrupt, 'corrupt session cannot reach supplier');
$wrongBinding = $session;
$wrongBinding['search']['offers'][0]['supplier_offer_id'] = 'other-valid-reference';
search_reject(static function () use ($gateway, &$wrongBinding, $publicGroup): void {
    $gateway->handle(['action' => 'expand', 'offer_key' => $publicGroup['offer_key']], $wrongBinding);
});
search_check(count($gatewayCalls) === $beforeCorrupt, 'offer key binding is verified before supplier call');
$clock += 901;
search_reject(static function () use ($gateway, &$session, $publicConcrete): void {
    $gateway->handle(['action' => 'flights', 'offer_key' => $publicConcrete['offer_key']], $session);
});
search_check(!isset($session['search']), 'expired session drops supplier references');
$freshSession = [];
search_reject(static function () use ($gateway, &$freshSession, $criteria): void {
    $gateway->handle(['action' => 'search', 'criteria' => array_replace($criteria, ['extra' => 1])], $freshSession);
});
search_check(!isset($freshSession['search']), 'failed replacement leaves no stale search');

$rateClock = 2000;
$rateCalls = 0;
$rateFactory = static function () use (&$rateCalls): AnyTourAnexClient {
    return new AnyTourAnexClient('rate-secret', static function (string $url) use (&$rateCalls): array {
        ++$rateCalls;
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        return ['status' => 200, 'body' => json_encode([$params['action'] => ['prices' => []]])];
    });
};
$rateGateway = new AnyTourAnexPreviewGateway($rateFactory, null, ['rate-secret'],
    static function () use (&$rateClock): int { return $rateClock; });
$rateSession = [];
for ($i = 0; $i < 10; ++$i) {
    $rateGateway->handle(['action' => 'search', 'criteria' => $criteria], $rateSession);
}
search_reject(static function () use ($rateGateway, &$rateSession, $criteria): void {
    $rateGateway->handle(['action' => 'search', 'criteria' => $criteria], $rateSession);
});
search_check($rateCalls === 10, 'burst limit reached supplier');
++$rateClock;
$rateGateway->handle(['action' => 'search', 'criteria' => $criteria], $rateSession);
search_check($rateCalls === 11, 'burst window did not reopen');

echo "ANEX_SEARCH_SMOKE_OK search/expand/flights/stale-state/source-identity/30-hotels/rate-limit\n";
