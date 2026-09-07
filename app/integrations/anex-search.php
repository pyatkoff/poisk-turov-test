<?php
declare(strict_types=1);

require_once __DIR__ . '/anex-client.php';
require_once __DIR__ . '/anex-normalizer.php';

/** Internal, read-only search session. It is not a public HTTP endpoint. */
final class AnyTourAnexSearch
{
    private $client;
    private $resolver;
    private $sensitive;
    private $context = [];
    private $params = [];
    private $offers = [];

    public function __construct(AnyTourAnexClient $client, ?callable $resolver = null, array $sensitive = [])
    {
        $this->client = $client;
        $this->resolver = $resolver;
        $this->sensitive = $sensitive;
    }

    public function search(array $criteria): array
    {
        // A failed replacement search must never leave selectable stale offers.
        $this->offers = $this->params = $this->context = [];
        $params = $this->buildParams($criteria);
        // Validate the normalizer's full context before making a network request.
        anytour_anex_normalize_prices(['prices' => []], $criteria, $this->resolver, $this->sensitive);
        $data = $this->client->request('SearchTour_PRICES', $params);
        $result = anytour_anex_normalize_prices($data, $criteria, $this->resolver, $this->sensitive);
        if (isset($criteria['hotel_ids'])) {
            $requestedHotels = array_map('strval', $criteria['hotel_ids']);
            $accepted = [];
            foreach ($result['offers'] as $offer) {
                if (in_array($offer['hotel']['external_id'], $requestedHotels, true)) {
                    $accepted[] = $offer;
                } else {
                    $result['rejected_count']++;
                }
            }
            $result['offers'] = $accepted;
        }
        $this->params = $params;
        $this->context = $criteria;
        $this->remember($result['offers']);
        return $result;
    }

    /** Export the minimum server-only state needed by a later HTTP request. */
    public function snapshot(): array
    {
        if ($this->context === [] || $this->params === []) {
            throw new LogicException('ANEX_SEARCH_NOT_STARTED');
        }
        return ['schema_version' => 1, 'context' => $this->context,
            'offers' => array_values($this->offers)];
    }

    /** Restore a server-owned snapshot without serializing the token-bearing client. */
    public function restore(array $snapshot): void
    {
        $this->offers = $this->params = $this->context = [];
        if (!self::exactKeys($snapshot, ['schema_version', 'context', 'offers'])
            || $snapshot['schema_version'] !== 1 || !is_array($snapshot['context'])
            || !is_array($snapshot['offers']) || count($snapshot['offers']) > 600
            || ($snapshot['offers'] !== []
                && array_keys($snapshot['offers']) !== range(0, count($snapshot['offers']) - 1))) {
            throw new InvalidArgumentException('ANEX_INVALID_SESSION');
        }
        $params = $this->buildParams($snapshot['context']);
        anytour_anex_normalize_prices(
            ['prices' => []], $snapshot['context'], $this->resolver, $this->sensitive
        );
        $offers = [];
        foreach ($snapshot['offers'] as $offer) {
            if (!is_array($offer) || !self::exactKeys($offer, [
                'offer_key', 'supplier_offer_id', 'kind', 'hotel_external_id'
            ]) || !is_string($offer['offer_key'])
                || !preg_match('/\Aanex_online:[a-f0-9]{64}\z/D', $offer['offer_key'])
                || isset($offers[$offer['offer_key']]) || !is_string($offer['supplier_offer_id'])
                || !preg_match('~\A[A-Za-z0-9][A-Za-z0-9_.:,;\~@+/=|\-]{0,2047}\z~D', $offer['supplier_offer_id'])
                || strpos($offer['supplier_offer_id'], '://') !== false
                || !in_array($offer['kind'], ['group_minimum', 'concrete'], true)
                || !is_string($offer['hotel_external_id'])
                || !preg_match('/\A[1-9][0-9]{0,7}\z/D', $offer['hotel_external_id'])) {
                throw new InvalidArgumentException('ANEX_INVALID_SESSION');
            }
            $offers[$offer['offer_key']] = [
                'offer_key' => $offer['offer_key'],
                'supplier_offer_id' => $offer['supplier_offer_id'],
                'kind' => $offer['kind'],
                'hotel' => ['external_id' => $offer['hotel_external_id']],
            ];
        }
        $this->params = $params;
        $this->context = $snapshot['context'];
        $this->offers = $offers;
    }

    private function buildParams(array $criteria): array
    {
        if (($criteria['supplier_namespace'] ?? '') !== 'anex_online') {
            throw new InvalidArgumentException('ANEX_SUPPLIER_NAMESPACE_REQUIRED');
        }
        $allowed = ['supplier_namespace', 'departure_id', 'destination_id', 'currency_id',
            'checkin_begin', 'checkin_end', 'nights_from', 'nights_till',
            'adults', 'children', 'child_ages', 'hotel_ids'];
        if (array_diff(array_keys($criteria), $allowed)) {
            throw new InvalidArgumentException('ANEX_UNSUPPORTED_CRITERIA');
        }
        $params = [];
        foreach (['departure_id' => 'TOWNFROMINC', 'destination_id' => 'STATEINC',
            'currency_id' => 'CURRENCY', 'nights_from' => 'NIGHTS_FROM',
            'nights_till' => 'NIGHTS_TILL', 'adults' => 'ADULT', 'children' => 'CHILD'] as $key => $param) {
            if (!array_key_exists($key, $criteria)) throw new InvalidArgumentException('ANEX_MISSING_CRITERIA');
            $params[$param] = $criteria[$key];
        }
        foreach (['checkin_begin' => 'CHECKIN_BEG', 'checkin_end' => 'CHECKIN_END'] as $key => $param) {
            $date = $criteria[$key] ?? null;
            if (!is_string($date) || !preg_match('/^(?:[0-9]{8}|[0-9]{4}-[0-9]{2}-[0-9]{2})$/D', $date)) {
                throw new InvalidArgumentException('ANEX_INVALID_DATE');
            }
            $params[$param] = str_replace('-', '', $date);
        }
        if (isset($criteria['child_ages'])) {
            if (!is_array($criteria['child_ages'])) throw new InvalidArgumentException('ANEX_INVALID_AGES');
            foreach ($criteria['child_ages'] as $age) {
                if (!is_int($age) || $age < 0 || $age > 17) throw new InvalidArgumentException('ANEX_INVALID_AGES');
            }
            if (count($criteria['child_ages']) !== (int)$criteria['children']) throw new InvalidArgumentException('ANEX_INVALID_AGES');
            if ($criteria['child_ages']) $params['AGES'] = implode(',', $criteria['child_ages']);
        }
        if (isset($criteria['hotel_ids'])) {
            if (!is_array($criteria['hotel_ids']) || !$criteria['hotel_ids'] || count($criteria['hotel_ids']) > 10) {
                throw new InvalidArgumentException('ANEX_INVALID_HOTELS');
            }
            foreach ($criteria['hotel_ids'] as $id) {
                if (!(is_int($id) || is_string($id)) || !preg_match('/^[1-9][0-9]{0,8}$/D', (string)$id)) {
                    throw new InvalidArgumentException('ANEX_INVALID_HOTELS');
                }
            }
            $params['HOTELS'] = implode(',', $criteria['hotel_ids']);
        }
        $params += ['FREIGHT' => 1, 'FILTER' => 1, 'PRICEPAGE' => 1,
            'PARTITION_PRICE' => 32, 'SORT' => 'ASC', 'DYN_SEPARATE' => 1];
        return $params;
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        return count($value) === count($expected)
            && array_diff($expected, array_keys($value)) === [];
    }

    public function expand(string $offerKey): array
    {
        $group = $this->known($offerKey, 'group_minimum');
        $params = $this->params;
        unset($params['PARTITION_PRICE']);
        $params['CATCLAIM'] = $group['supplier_offer_id'];
        $params['HOTELS'] = $group['hotel']['external_id'];
        $result = anytour_anex_normalize_prices(
            $this->client->request('SearchTour_PRICES', $params),
            $this->context, $this->resolver, $this->sensitive
        );
        $accepted = [];
        foreach ($result['offers'] as $offer) {
            if ($offer['kind'] !== 'concrete' || $offer['hotel']['external_id'] !== $group['hotel']['external_id']) {
                $result['rejected_count']++;
            } else {
                $accepted[] = $offer;
            }
        }
        $result['offers'] = $accepted;
        $this->remember($accepted);
        return $result;
    }

    public function flights(string $offerKey): array
    {
        $offer = $this->known($offerKey, 'concrete');
        $data = $this->client->request('FreightMonitor_FREIGHTSBYPACKET', ['CATCLAIM' => $offer['supplier_offer_id']]);
        if (!isset($data['routes']) || !is_array($data['routes'])) throw new RuntimeException('ANEX_INVALID_FLIGHTS');
        $result = ['provider' => 'anex', 'offer_key' => $offerKey, 'routes' => [],
            'selected' => false, 'included_in_search_price_verified' => false,
            'itinerary_details_available' => false,
            'final_price_verified' => false, 'truncated' => count($data['routes']) > 6];
        foreach (array_slice($data['routes'], 0, 6) as $route) {
            if (!is_array($route) || !is_array($route['info'] ?? null) || !is_array($route['freights'] ?? null)) {
                throw new RuntimeException('ANEX_INVALID_FLIGHTS');
            }
            $info = $route['info'];
            $entry = ['date' => $this->label($info['date'] ?? null),
                'from' => $this->label($info['sourceTown'] ?? null),
                'to' => $this->label($info['targetTown'] ?? null), 'options' => []];
            $result['truncated'] = $result['truncated'] || count($route['freights']) > 60;
            foreach (array_slice($route['freights'], 0, 60) as $freight) {
                if (!is_array($freight)) throw new RuntimeException('ANEX_INVALID_FLIGHTS');
                $item = ['name' => $this->label($freight['name'] ?? null),
                    'carrier' => $this->label($freight['transportCompany'] ?? null),
                    'transport_type' => $this->label($freight['transportType'] ?? null), 'classes' => []];
                foreach (['departure', 'arrival'] as $leg) {
                    $location = is_array($freight[$leg] ?? null) ? $freight[$leg] : [];
                    $item[$leg] = ['airport' => $this->label($location['port'] ?? null),
                        'airport_code' => $this->label($location['portAlias'] ?? null),
                        'time' => $this->label($location['time'] ?? null)];
                }
                $item['itinerary_details_available'] = $item['name'] !== null && $item['carrier'] !== null
                    && $item['departure']['airport_code'] !== null && $item['departure']['time'] !== null
                    && $item['arrival']['airport_code'] !== null && $item['arrival']['time'] !== null;
                $result['itinerary_details_available'] = $result['itinerary_details_available'] || $item['itinerary_details_available'];
                $places = $freight['places'] ?? [];
                if (!is_array($places)) throw new RuntimeException('ANEX_INVALID_FLIGHTS');
                $result['truncated'] = $result['truncated'] || count($places) > 10;
                foreach (array_slice($places, 0, 10) as $place) {
                    if (!is_array($place)) throw new RuntimeException('ANEX_INVALID_FLIGHTS');
                    $baggage = is_array($place['baggage'] ?? null) ? $place['baggage'] : [];
                    $item['classes'][] = ['name' => $this->label($place['class'] ?? null),
                        'availability' => $this->flightAvailability($place['status'] ?? null),
                        'baggage' => $this->baggage($baggage['baggage'] ?? null),
                        'hand_baggage' => $this->baggage($baggage['baggageHand'] ?? null),
                        'infant_baggage' => $this->baggage($baggage['baggageInfant'] ?? null)];
                }
                $entry['options'][] = $item;
            }
            $result['routes'][] = $entry;
        }
        return $result;
    }

    private function remember(array $offers): void
    {
        foreach ($offers as $offer) {
            if (count($this->offers) >= 600 && !isset($this->offers[$offer['offer_key']])) break;
            $this->offers[$offer['offer_key']] = [
                'offer_key' => $offer['offer_key'],
                'supplier_offer_id' => $offer['supplier_offer_id'],
                'kind' => $offer['kind'],
                'hotel' => ['external_id' => $offer['hotel']['external_id']],
            ];
        }
    }

    private function known(string $key, string $kind): array
    {
        $offer = $this->offers[$key] ?? null;
        if (!$offer || $offer['kind'] !== $kind) throw new InvalidArgumentException('ANEX_UNKNOWN_OR_WRONG_OFFER');
        return $offer;
    }

    private function label($value): ?string
    {
        return anytour_anex_normalizer_label($value,
            anytour_anex_normalizer_sensitive($this->sensitive), 180);
    }

    private function baggage($value): ?string
    {
        if (is_int($value) || is_float($value)) {
            if (!is_finite((float)$value) || $value < 0) return null;
            $value = (string)$value;
        }
        // No unit or package-inclusion claim is invented from a bare number.
        return $this->label($value);
    }

    private function flightAvailability($value): ?string
    {
        if ($value === 'yesplace') return 'Y';
        if ($value === 'noplace') return 'N';
        return in_array($value, ['Y', 'N', 'F', 'R'], true) ? $value : null;
    }
}
