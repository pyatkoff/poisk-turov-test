<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/anex-client.php';

$passed = 0;
function clientCheck(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $label);
    }
}
function clientFailure(callable $operation, string $expected): void
{
    try {
        $operation();
    } catch (RuntimeException $error) {
        clientCheck($error->getMessage() === $expected, 'fixed failure code');
        clientCheck($error->getPrevious() === null, 'exception has no supplier cause');
        clientCheck(strpos($error->getMessage(), 'test-secret') === false, 'no secret in message');
        return;
    }
    throw new RuntimeException('Expected controlled failure');
}
function clientTest(string $name, callable $test): void
{
    global $passed;
    $test();
    ++$passed;
    echo "PASS " . $name . "\n";
}
function clientWithResponse(int $status, string $body): AnyTourAnexClient
{
    return new AnyTourAnexClient('test-secret', static function () use ($status, $body): array {
        return ['status' => $status, 'body' => $body];
    });
}

clientTest('missing token', static function (): void {
    clientFailure(static function (): void { new AnyTourAnexClient('  '); }, 'ANEX_TOKEN_REQUIRED');
});
clientTest('fixed route and transport policy', static function (): void {
    $client = new AnyTourAnexClient(' test-secret ', static function (string $url, array $options): array {
        $parts = parse_url($url);
        clientCheck($parts['scheme'] === 'https' && $parts['host'] === 'parser.anextour.ru'
            && $parts['path'] === '/export/default.php', 'fixed endpoint');
        parse_str($parts['query'], $query);
        clientCheck($query === ['samo_action' => 'api', 'version' => '1.0', 'type' => 'json',
            'action' => 'SearchTour_TOWNFROMS', 'oauth_token' => 'test-secret'], 'documented GET fields');
        clientCheck($options === ['verify_peer' => true, 'verify_host' => 2, 'follow_redirects' => false,
            'proxy' => '', 'timeout' => 20, 'connect_timeout' => 10,
            'max_response_bytes' => 2097152, 'protocol' => 'https'], 'strict transport contract');
        return ['status' => 200, 'body' => '{"SearchTour_TOWNFROMS":[{"id":1,"name":"Moscow"}]}'];
    });
    clientCheck($client->request('SearchTour_TOWNFROMS')[0]['id'] === 1, 'unwrapped payload');
    clientCheck($client->requestsMade() === 1, 'request count');
    ob_start();
    var_dump($client);
    $debug = ob_get_clean();
    clientCheck(strpos($debug, 'test-secret') === false, 'debug redacts token');
});
clientTest('write actions and routing overrides rejected before network', static function (): void {
    $client = new AnyTourAnexClient('test-secret', static function (): array {
        throw new RuntimeException('Network should not run');
    });
    foreach (['Booking_BOOK', 'SearchTour_ACTUALIZE', '', 'Hotels_DETAILS&action=Booking_BOOK'] as $action) {
        clientFailure(static function () use ($client, $action): void { $client->request($action); }, 'ANEX_ACTION_NOT_ALLOWED');
    }
    foreach (['oauth_token', 'action', 'baseURL', 'samo_action', 'version', 'type', 'URL'] as $key) {
        clientFailure(static function () use ($client, $key): void {
            $client->request('SearchTour_PRICES', [$key => 'override']);
        }, 'ANEX_INVALID_PARAMS');
    }
    clientCheck($client->requestsMade() === 0, 'invalid input never sent');
});
clientTest('HTTP errors and redirects never reveal body', static function (): void {
    foreach ([301, 302, 307, 400, 401, 500] as $status) {
        $client = clientWithResponse($status, 'test-secret https://supplier/?oauth_token=test-secret');
        clientFailure(static function () use ($client): void { $client->request('SearchTour_TOWNFROMS'); }, 'ANEX_HTTP_ERROR');
    }
});
clientTest('transport exception stripped', static function (): void {
    $client = new AnyTourAnexClient('test-secret', static function (string $url): array {
        throw new RuntimeException($url . ' test-secret');
    });
    clientFailure(static function () use ($client): void { $client->request('SearchTour_TOWNFROMS'); }, 'ANEX_TRANSPORT_ERROR');
});
clientTest('invalid JSON envelope and scalar payload', static function (): void {
    foreach (['<html>test-secret</html>', '{}', 'null', '{"Other":[]}', '{"SearchTour_TOWNFROMS":"test-secret"}'] as $body) {
        $client = clientWithResponse(200, $body);
        clientFailure(static function () use ($client): void { $client->request('SearchTour_TOWNFROMS'); }, 'ANEX_INVALID_RESPONSE');
    }
});
clientTest('supplier errors do not escape', static function (): void {
    foreach (['{"error":"test-secret"}', '{"SearchTour_TOWNFROMS":{"error":"test-secret"}}'] as $body) {
        $client = clientWithResponse(200, $body);
        clientFailure(static function () use ($client): void { $client->request('SearchTour_TOWNFROMS'); }, 'ANEX_SUPPLIER_ERROR');
    }
});
clientTest('response size limit', static function (): void {
    $client = clientWithResponse(200, str_repeat('x', 2097153));
    clientFailure(static function () use ($client): void { $client->request('SearchTour_TOWNFROMS'); }, 'ANEX_RESPONSE_TOO_LARGE');
});
clientTest('twelve attempted requests maximum', static function (): void {
    $client = clientWithResponse(500, '');
    for ($i = 0; $i < 12; ++$i) {
        clientFailure(static function () use ($client): void { $client->request('SearchTour_TOWNFROMS'); }, 'ANEX_HTTP_ERROR');
    }
    clientFailure(static function () use ($client): void { $client->request('SearchTour_TOWNFROMS'); }, 'ANEX_REQUEST_LIMIT');
    clientCheck($client->requestsMade() === 12, 'failed calls count toward budget');
});
clientTest('party dates and bounded identifiers', static function (): void {
    $client = clientWithResponse(200, '{"SearchTour_PRICES":{"prices":[]}}');
    $invalid = [
        ['CHILD' => 1], ['CHILD' => 2, 'AGES' => '5'], ['CHILD' => 1, 'AGES' => '18'],
        ['CHILD' => 0, 'AGES' => '5'], ['ADULT' => 0], ['ADULT' => true],
        ['HOTELS' => '1,0'], ['HOTELS' => ['1']], ['CATCLAIM' => 'a&oauth_token=b'],
        ['CHECKIN_BEG' => '20260230'], ['CHECKIN_BEG' => '20260915', 'CHECKIN_END' => '20260914'],
        ['NIGHTS_FROM' => 10, 'NIGHTS_TILL' => 7], ['PRICEPAGE' => 101], ['SORT' => 'OTHER'],
    ];
    foreach ($invalid as $params) {
        clientFailure(static function () use ($client, $params): void { $client->request('SearchTour_PRICES', $params); }, 'ANEX_INVALID_PARAMS');
    }
    clientCheck($client->requestsMade() === 0, 'invalid searches not sent');
    clientCheck($client->request('SearchTour_PRICES', ['ADULT' => 2, 'CHILD' => 2, 'AGES' => '0,17',
        'CHECKIN_BEG' => '20260914', 'CHECKIN_END' => '20260921', 'NIGHTS_FROM' => 7,
        'NIGHTS_TILL' => 7, 'CATCLAIM' => '0x0123456789-ABC', 'HOTELS' => '43689,30160']) === ['prices' => []], 'valid search');
});
clientTest('hotel details empty outcomes and method parameters', static function (): void {
    foreach (['null', 'false', '""', '[]', '{}'] as $empty) {
        clientCheck(clientWithResponse(200, '{"Hotels_DETAILS":' . $empty . '}')
            ->request('Hotels_DETAILS', ['HOTELINC' => 43689]) === [], 'empty details');
    }
    $client = clientWithResponse(200, '{"Hotels_DETAILS":[]}');
    clientFailure(static function () use ($client): void { $client->request('Hotels_DETAILS'); }, 'ANEX_INVALID_PARAMS');
    clientFailure(static function () use ($client): void {
        $client->request('FreightMonitor_FREIGHTSBYPACKET', ['CATCLAIM' => '0x123', 'HOTELS' => 1]);
    }, 'ANEX_INVALID_PARAMS');
    clientCheck(clientWithResponse(200, '{"FreightMonitor_FREIGHTSBYPACKET":[]}')
        ->request('FreightMonitor_FREIGHTSBYPACKET', ['CATCLAIM' => '0x123']) === [], 'read-only flight method');
});

clientTest('recursive supplier echoes and encoded keys are redacted', static function (): void {
    $payload = ['id' => 43689, 'name' => 'Safe Hotel', 'nested' => [
        'raw' => 'Hotel test-secret', 'html' => 'Hotel &#116;est-secret',
        'url' => 'Hotel %74est-secret', 'double' => 'Hotel %2574est-secret',
        'mixed' => 'Hotel &#37;74est-secret',
        'test-secret-key' => 'remove this key', '%74est-secret-key' => 'remove encoded key',
        '&#116;est-secret-key' => ['otherwise' => 'safe'],
        'list' => ['safe', 'test-secret', ['label' => '%74est-secret', 'id' => 12]],
        'ordinary' => 'Room %20 &amp; Breakfast',
    ]];
    $client = clientWithResponse(200, json_encode(['Hotels_DETAILS' => $payload]));
    $result = $client->request('Hotels_DETAILS', ['HOTELINC' => 43689]);
    clientCheck($result['name'] === 'Safe Hotel' && $result['id'] === 43689, 'valid fields preserved');
    foreach (['raw', 'html', 'url', 'double', 'mixed'] as $key) {
        clientCheck(array_key_exists($key, $result['nested']) && $result['nested'][$key] === null, 'echo becomes null');
    }
    clientCheck(count($result['nested']) === 7, 'contaminated keys removed');
    clientCheck($result['nested']['list'] === ['safe', null, ['label' => null, 'id' => 12]], 'nested arrays keep indices');
    clientCheck($result['nested']['ordinary'] === 'Room %20 &amp; Breakfast', 'clean strings are not rewritten');
    clientCheck(strpos(json_encode($result), 'test-secret') === false, 'no raw echo exported');
});
clientTest('default service caller needs no duplicate token filter', static function (): void {
    require_once __DIR__ . '/../app/integrations/anex-search.php';
    $client = new AnyTourAnexClient('test-secret', static function (string $url): array {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $action = $query['action'];
        if ($action === 'FreightMonitor_FREIGHTSBYPACKET') {
            $payload = ['routes' => [[
                'info' => ['date' => '20260914', 'sourceTown' => 'Moscow', 'targetTown' => 'Antalya'],
                'freights' => [['name' => 'Fixture 123', 'transportCompany' => '&#116;est-secret',
                    'places' => [['class' => 'Economy', 'status' => 'Y',
                        'baggage' => ['baggage' => '20 kg', 'baggageHand' => '%74est-secret']]]]],
            ]]];
        } else {
            $payload = ['prices' => [[
                'id' => 'safe-concrete-fixture', 'hotelKey' => 469, 'hotel' => 'Safe Hotel',
                'checkIn' => '20260914', 'checkOut' => '20260921', 'nights' => 7,
                'adult' => 2, 'child' => 0, 'packetType' => 0, 'price' => '1234.50',
                'currency' => 'EUR', 'grouped' => 0, 'bron' => 1,
                'meal' => 'BB test-secret', 'room' => 'Room %74est-secret', 'star' => '&#116;est-secret',
            ]]];
        }
        return ['status' => 200, 'body' => json_encode([$action => $payload])];
    });
    $search = new AnyTourAnexSearch($client);
    $result = $search->search(['supplier_namespace' => 'anex_online', 'departure_id' => 1,
        'destination_id' => 2, 'currency_id' => 3, 'checkin_begin' => '20260914',
        'checkin_end' => '20260914', 'nights_from' => 7, 'nights_till' => 7, 'adults' => 2, 'children' => 0]);
    clientCheck(count($result['offers']) === 1, 'legitimate offer survives redaction');
    $offer = $result['offers'][0];
    clientCheck($offer['meal'] === null && $offer['room'] === null && $offer['hotel']['star'] === null, 'search labels protected by client');
    $flights = $search->flights($offer['offer_key']);
    $flight = $flights['routes'][0]['options'][0];
    clientCheck($flight['carrier'] === null && $flight['classes'][0]['hand_baggage'] === null, 'nested flight labels protected by client');
    clientCheck($flight['classes'][0]['baggage'] === '20 kg', 'clean baggage preserved');
    clientCheck(strpos(json_encode([$result, $flights]), 'test-secret') === false, 'service exposes no token');
});

echo 'ANEX client: ' . $passed . " tests passed\n";
