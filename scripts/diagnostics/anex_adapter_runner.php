<?php
declare(strict_types=1);

// Loaded in memory by anex_adapter_probe.py after the exact checked modules.
// Credentials arrive only on stdin. This runner never bootstraps the site or DB.
$report = ['mode' => 'adapter', 'ok' => false, 'status' => 'ANEX_ADAPTER_ERROR'];
try {
    $input = json_decode(stream_get_contents(STDIN, 65537), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($input) || basename((string)realpath(getcwd())) !== 'anytoour.ru') {
        throw new RuntimeException('ANEX_INVALID_RUNTIME');
    }
    $token = $input['token'] ?? '';
    $registry = AnyTourHotelIdentityRegistry::fromJson($input['registry']);
    $client = new AnyTourAnexClient($token);
    $service = new AnyTourAnexSearch($client, static function (string $provider, string $id) use ($registry): ?int {
        return $registry->resolve($provider, $id, 'preview');
    }, [$token]);
    $criteria = $input['criteria'];
    $result = $service->search($criteria);
    $report['hotel_filter_fallback'] = false;
    if (!$result['offers'] && !empty($criteria['hotel_ids'])) {
        unset($criteria['hotel_ids']);
        $result = $service->search($criteria);
        $report['hotel_filter_fallback'] = true;
    }
    $report['search_offers'] = count($result['offers']);
    $report['search_mapped'] = count(array_filter($result['offers'], static function (array $offer): bool {
        return $offer['hotel']['local_id'] !== null;
    }));
    $report['rejected_count'] = $result['rejected_count'];
    $report['external_search_pending'] = $result['external_search_pending'] ?? false;
    if (!$result['offers']) throw new RuntimeException('ANEX_NO_OFFERS');
    $offers = $result['offers'];
    usort($offers, static function (array $left, array $right): int {
        return (int)($left['hotel']['local_id'] === null) <=> (int)($right['hotel']['local_id'] === null);
    });
    $first = $offers[0];
    if ($first['kind'] === 'group_minimum') {
        $expanded = $service->expand($first['offer_key']);
        $offers = $expanded['offers'];
        $report['expanded_offers'] = count($offers);
        $report['rejected_count'] += $expanded['rejected_count'];
    } else {
        $offers = array_values(array_filter($offers, static function (array $offer): bool {
            return $offer['kind'] === 'concrete';
        }));
        $report['expanded_offers'] = 0;
    }
    if (!$offers) throw new RuntimeException('ANEX_NO_CONCRETE_OFFERS');
    $report['samples'] = [];
    foreach (array_slice($offers, 0, 3) as $offer) {
        $report['samples'][] = ['anex_hotel_id' => $offer['hotel']['external_id'],
            'local_hotel_id' => $offer['hotel']['local_id'], 'hotel' => $offer['hotel']['name'],
            'kind' => $offer['kind'], 'checkin' => $offer['checkin'], 'checkout' => $offer['checkout'],
            'nights' => $offer['nights'], 'price' => $offer['price'], 'converted_price' => $offer['converted_price'],
            'meal' => $offer['meal'], 'room' => $offer['room'], 'availability' => $offer['availability'],
            'supplier_booking_flag' => $offer['supplier_booking_flag'], 'final_price_verified' => false];
    }
    $first = $offers[0];
    // Verify that a live PRICES.hotelKey resolves to the same API card.
    $card = $client->request('Hotels_DETAILS', ['HOTELINC' => $first['hotel']['external_id']]);
    $report['price_hotel_card_id_agrees'] = isset($card['id']) && (string)$card['id'] === $first['hotel']['external_id'];
    if (!$report['price_hotel_card_id_agrees']) throw new RuntimeException('ANEX_HOTEL_CARD_MISMATCH');
    try {
        $flights = $service->flights($first['offer_key']);
        $report['flights'] = ['status' => 'ok', 'route_count' => count($flights['routes']),
            'option_count' => array_sum(array_map(static function (array $route): int { return count($route['options']); }, $flights['routes'])),
            'selected' => false, 'final_price_verified' => false, 'routes' => []];
        foreach (array_slice($flights['routes'], 0, 2) as $route) {
            $route['options'] = array_slice($route['options'], 0, 1);
            $report['flights']['routes'][] = $route;
        }
    } catch (Throwable $e) {
        $code = $e->getMessage();
        $report['flights'] = ['status' => preg_match('/^ANEX_[A-Z_]+$/D', $code) ? $code : 'ANEX_FLIGHTS_UNAVAILABLE'];
    }
    $report['adapter_requests'] = $client->requestsMade();
    $report['ok'] = true;
    $report['status'] = 'ok';
} catch (Throwable $e) {
    $code = $e->getMessage();
    $report['status'] = preg_match('/^ANEX_[A-Z_]+$/D', $code) ? $code : 'ANEX_ADAPTER_ERROR';
}
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
exit($report['ok'] ? 0 : 1);
