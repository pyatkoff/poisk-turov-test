<?php
declare(strict_types=1);

require_once __DIR__ . '/data/hotel-details-v1.php';

/** Preview Search3 supplier boundary. No booking or Tourvisor transport. */
function anytour_anex_search3_name(string $name): string
{
    $name = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', str_replace(['ё', 'Ё'], 'е', $name)));
}

/** Exact, unique dictionary identity; local form IDs never become supplier IDs. */
function anytour_anex_search3_dictionary_id(array $rows, array $names): int
{
    $names = array_map('anytour_anex_search3_name', $names);
    $matches = [];
    foreach (array_slice($rows, 0, 10000) as $row) {
        if (!is_array($row) || !preg_match('/\A[1-9][0-9]{0,7}\z/D', (string) ($row['id'] ?? ''))) continue;
        foreach (['name', 'nameAlt', 'alias', 'currencyISO'] as $key) {
            if (is_string($row[$key] ?? null) && in_array(anytour_anex_search3_name($row[$key]), $names, true)) {
                $matches[(int) $row['id']] = true;
            }
        }
    }
    if (count($matches) !== 1) throw new InvalidArgumentException('ANEX_DESTINATION_UNSUPPORTED');
    return (int) array_key_first($matches);
}

function anytour_anex_search3_dictionary($client, string $action, array $params, array &$cache): array
{
    $key = hash('sha256', $action . json_encode($params));
    $entry = $cache[$key] ?? null;
    if (is_array($entry) && ($entry['expires'] ?? 0) > time() && is_array($entry['rows'] ?? null)) return $entry['rows'];
    foreach ($cache as $oldKey => $old) if (!is_array($old) || ($old['expires'] ?? 0) <= time()) unset($cache[$oldKey]);
    if (count($cache) >= 24) array_shift($cache);
    $rows = $client->request($action, $params);
    if (count($rows) > 10000) throw new RuntimeException('ANEX_INVALID_DICTIONARY');
    $cache[$key] = ['expires' => time() + 900, 'rows' => $rows];
    return $rows;
}

function anytour_anex_search3_core(array $params): array
{
    foreach (['departureId', 'countryId'] as $key) {
        if (!is_scalar($params[$key] ?? null) || !preg_match('/\A[1-9][0-9]{0,9}\z/D', (string) $params[$key])) {
            throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
        }
    }
    $core = ['checkin_begin' => $params['dateFrom'] ?? null, 'checkin_end' => $params['dateTo'] ?? null,
        'nights_from' => $params['nightsFrom'] ?? null, 'nights_till' => $params['nightsTo'] ?? null,
        'adults' => $params['adults'] ?? null, 'children' => count(is_array($params['childs'] ?? null) ? $params['childs'] : []),
        'child_ages' => $params['childs'] ?? null];
    $core = anytour_anex_normalizer_context($core);
    if ($core['adults'] > 6 || $core['children'] > 3 || $core['nights_till'] > 28
        || $core['nights_till'] - $core['nights_from'] > 10
        || (new DateTimeImmutable($core['checkin_begin']))->diff(new DateTimeImmutable($core['checkin_end']))->days > 21
        || $core['checkin_begin'] < (new DateTimeImmutable('today', new DateTimeZone('Europe/Moscow')))->format('Y-m-d')) {
        throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
    }
    // These constraints require verified supplier dictionaries/flight details.
    foreach (['arrivalId', 'operatorIds', 'hotelServices', 'hotelTypes'] as $key) {
        if (!empty($params[$key])) throw new InvalidArgumentException('ANEX_FILTER_UNSUPPORTED');
    }
    foreach (['onlyDirect', 'onlyCharter'] as $key) {
        if (!in_array($params[$key] ?? 'false', ['false', false, '', '0', 0], true)) throw new InvalidArgumentException('ANEX_FILTER_UNSUPPORTED');
    }
    // Search3's established food=7 means All Inclusive. Other meal IDs are not translated by number.
    if (!in_array($params['meal'] ?? '', ['', '7', 7], true)) throw new InvalidArgumentException('ANEX_FILTER_UNSUPPORTED');
    if (!in_array((string) ($params['hotelRating'] ?? ''), ['', '2', '3', '4', '5'], true)) throw new InvalidArgumentException('ANEX_FILTER_UNSUPPORTED');
    foreach (['hotelCategory', 'hotelRating', 'priceFrom', 'priceTo'] as $key) {
        if (isset($params[$key]) && $params[$key] !== '' && (!is_scalar($params[$key])
            || !preg_match('/\A[0-9]{1,12}(?:\.[0-9]{1,2})?\z/D', (string) $params[$key]))) {
            throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
        }
    }
    foreach (['hotelIds', 'regionIds', 'subregionIds'] as $key) {
        $values = $params[$key] ?? [];
        if (!is_array($values) || count($values) > 30) throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
        foreach ($values as $value) if (!is_scalar($value) || !preg_match('/\A[1-9][0-9]{0,9}\z/D', (string) $value)) throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
    }
    if (($params['currency'] ?? 'RUB') !== 'RUB') throw new InvalidArgumentException('ANEX_FILTER_UNSUPPORTED');
    return anytour_anex_search3_week($core);
}

/** Plain, bounded catalog excerpts; supplier HTML never becomes card markup. */
function anytour_anex_search3_catalog_text($value, int $limit): ?string
{
    if (!is_string($value)) return null;
    $value = preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~is', '', $value);
    $value = preg_replace('~<br\s*/?>|</(?:p|div|li|h[1-6])\s*>~i', "\n", (string) $value);
    $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = trim((string) preg_replace('/[\p{Z}\s]+/u', ' ', $value));
    if ($value === '') return null;
    return function_exists('mb_substr') ? mb_substr($value, 0, $limit, 'UTF-8') : substr($value, 0, $limit);
}

/** Optional local reads only. Missing content storage must not hide available tours. */
function anytour_anex_search3_catalog_hydrate(PDO $pdo, array $metadata): array
{
    if (!$metadata) return $metadata;
    $ids = array_slice(array_keys($metadata), 0, 300);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $queries = [
        'SELECT id AS hotel_id,primary_image_url FROM catalog_hotels WHERE id IN (' . $placeholders . ') LIMIT 300',
        'SELECT hotel_id,primary_image_url,LEFT(description,16000) AS description,address'
            . ' FROM catalog_hotel_details WHERE hotel_id IN (' . $placeholders . ") AND status='success' LIMIT 300",
    ];
    foreach ($queries as $index => $sql) {
        try {
            $query = $pdo->prepare($sql);
            $query->execute($ids);
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $content) {
                $id = (int) $content['hotel_id'];
                if (!isset($metadata[$id]) || (int) $metadata[$id]['id'] !== $id) continue;
                $image = v2_hotel_detail_https_url($content['primary_image_url'] ?? null);
                if ($image !== null && empty($metadata[$id]['primary_image_url'])) $metadata[$id]['primary_image_url'] = $image;
                if ($index === 1) {
                    $metadata[$id]['description'] = $content['description'];
                    $metadata[$id]['address'] = $content['address'];
                }
            }
        } catch (Throwable $ignored) {
            error_log('ANEX_CATALOG_CONTENT_UNAVAILABLE_' . $index);
        }
    }
    return $metadata;
}

/** Shared current catalog read for initial results and retained-offer actions. */
function anytour_anex_search3_metadata(PDO $pdo, array $offers): array
{
    $ids = [];
    foreach (array_slice($offers, 0, 300) as $offer) {
        $id = $offer['hotel']['local_id'] ?? null;
        if (is_int($id) && $id > 0) $ids[$id] = true;
    }
    if (!$ids) return [];
    $hydrate = $pdo->prepare('SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,rating'
        . ' FROM catalog_hotels WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') AND is_active=1 LIMIT 300');
    $hydrate->execute(array_keys($ids));
    $metadata = [];
    foreach ($hydrate->fetchAll(PDO::FETCH_ASSOC) as $row) $metadata[(int) $row['id']] = $row;
    return anytour_anex_search3_catalog_hydrate($pdo, $metadata);
}

/** Projection is deliberately separate from both suppliers' booking IDs. */
function anytour_anex_search3_project(array $offers, array $metadata, array $params, ?string $searchRef = null): array
{
    if ($searchRef !== null && !preg_match('/\A[a-f0-9]{32}\z/D', $searchRef)) throw new InvalidArgumentException('ANEX_INVALID_SESSION');
    $hotels = [];
    foreach (array_slice($offers, 0, 300) as $offer) {
        $id = $offer['hotel']['local_id'] ?? null;
        $row = is_int($id) && $id > 0 ? ($metadata[$id] ?? null) : null;
        if (!$row || (int) ($row['id'] ?? 0) !== $id || ($offer['hotel']['mapping_status'] ?? '') !== 'resolved'
            || (int) $row['country_id'] !== (int) $params['countryId']) continue;
        $fits = true;
        foreach (['hotelIds' => 'id', 'regionIds' => 'region_id', 'subregionIds' => 'subregion_id'] as $filter => $field) {
            if (!empty($params[$filter]) && !in_array((string) ($row[$field] ?? ''), array_map('strval', $params[$filter]), true)) $fits = false;
        }
        $rating = ['2' => 3.0, '3' => 3.5, '4' => 4.0, '5' => 4.5][(string) ($params['hotelRating'] ?? '')] ?? 0;
        if (!$fits || (float) ($row['category'] ?? 0) < (float) ($params['hotelCategory'] ?? 0)
            || (float) ($row['rating'] ?? 0) < $rating) continue;
        if (!empty($params['meal']) && !in_array(anytour_anex_search3_name((string) ($offer['meal'] ?? '')),
            ['ai', 'all', 'all inclusive', 'uai', 'ultra all inclusive', 'ai without alcohol',
                'все включено', 'ультра все включено', 'все включено без алкоголя'], true)) continue;
        $price = ($offer['price']['currency'] ?? '') === 'RUB' ? $offer['price'] : ($offer['converted_price'] ?? null);
        if (!$price || $price['currency'] !== 'RUB' || !anytour_anex_normalizer_decimal($price['amount'] ?? null)) continue;
        if ((!empty($params['priceFrom']) && (float) $price['amount'] < (float) $params['priceFrom'])
            || (!empty($params['priceTo']) && (float) $price['amount'] > (float) $params['priceTo'])) continue;
        if (!isset($hotels[$id])) {
            $hotels[$id] = ['local_id' => $id, 'name' => (string) $row['name'], 'category' => (int) ($row['category'] ?? 0),
                'rating' => (float) ($row['rating'] ?? 0),
                'country' => (string) $row['country_name'], 'region' => (string) ($row['region_name'] ?? ''),
                'catalog' => ['hotel_id' => $id, 'source' => 'tourvisor',
                    'image_url' => v2_hotel_detail_https_url($row['primary_image_url'] ?? null),
                    'description' => anytour_anex_search3_catalog_text($row['description'] ?? null, 2000),
                    'address' => anytour_anex_search3_catalog_text($row['address'] ?? null, 1000),
                    'subregion' => anytour_anex_search3_catalog_text($row['subregion_name'] ?? null, 180),
                    // The stored catalog has no normalized distance field. Do not infer one.
                    'sea_distance' => null], 'tours' => []];
        }
        $tour = ['price' => $price, 'checkin' => $offer['checkin'], 'nights' => $offer['nights'],
            'adults' => $offer['adults'], 'children' => $offer['children'], 'meal' => $offer['meal'], 'room' => $offer['room'],
            'kind' => $offer['kind'], 'final_price_verified' => false];
        if ($searchRef !== null) {
            if (!is_string($offer['offer_key'] ?? null) || !preg_match('/\Aanex_online:[a-f0-9]{64}\z/D', $offer['offer_key'])) {
                throw new InvalidArgumentException('ANEX_INVALID_SESSION');
            }
            $tour += ['search_ref' => $searchRef, 'offer_ref' => $offer['offer_key'], 'selection_enabled' => false];
        }
        $hotels[$id]['tours'][] = $tour;
    }
    foreach ($hotels as &$hotel) {
        usort($hotel['tours'], static function ($a, $b) { return (float) $a['price']['amount'] <=> (float) $b['price']['amount']; });
        // Keep the bounded received set so later local filters can find every matching tour.
    }
    unset($hotel);
    return array_values($hotels);
}

/** Owner policy: ANEX searches only the first seven departure dates of the form interval. */
function anytour_anex_search3_week(array $criteria): array
{
    $weekEnd = (new DateTimeImmutable($criteria['checkin_begin']))->modify('+6 days')->format('Y-m-d');
    if ($criteria['checkin_end'] > $weekEnd) $criteria['checkin_end'] = $weekEnd;
    return $criteria;
}

/** Exactly one price operation; the existing gateway owns optional retained facts. */
function anytour_anex_search3_prices($client, callable $resolver, array $criteria, ?array &$session = null, ?callable $clock = null): array
{
    if ($session === null) return (new AnyTourAnexSearch($client, $resolver))->search(anytour_anex_search3_week($criteria));
    $gateway = new AnyTourAnexPreviewGateway(static function () use ($client) { return $client; }, $resolver, [], $clock);
    return $gateway->handle(['action' => 'search', 'criteria' => anytour_anex_search3_week($criteria)], $session);
}

function anytour_anex_search3_run(array $request, PDO $pdo, $client, array &$cache, ?array &$diagnostics = null, ?callable $observer = null, ?array &$state = null): array
{
    // Invalidate before validating a replacement, including unsupported criteria.
    if ($state !== null) $state = [];
    if (!is_int($request['generation'] ?? null) || $request['generation'] < 1 || $request['generation'] > 2147483647
        || !is_array($request['params'] ?? null) || count($request['params']) > 40) throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
    $params = $request['params'];
    $criteria = anytour_anex_search3_core($params);
    // Browser labels are captured for UI continuity only; trusted DB names select dictionaries.
    $lookup = $pdo->prepare('SELECT d.name AS departure_name,c.name AS country_name FROM catalog_departures d'
        . ' CROSS JOIN catalog_countries c WHERE d.id=? AND c.id=? AND d.is_active=1 AND c.is_active=1 LIMIT 1');
    $lookup->execute([(int) $params['departureId'], (int) $params['countryId']]);
    $names = $lookup->fetch(PDO::FETCH_ASSOC);
    if (!$names) throw new InvalidArgumentException('ANEX_DESTINATION_UNSUPPORTED');
    $criteria['supplier_namespace'] = 'anex_online';
    $criteria['departure_id'] = anytour_anex_search3_dictionary_id(
        anytour_anex_search3_dictionary($client, 'SearchTour_TOWNFROMS', [], $cache), [$names['departure_name']]);
    $criteria['destination_id'] = anytour_anex_search3_dictionary_id(
        anytour_anex_search3_dictionary($client, 'SearchTour_STATES', ['TOWNFROMINC' => $criteria['departure_id']], $cache), [$names['country_name']]);
    $dated = ['TOWNFROMINC' => $criteria['departure_id'], 'STATEINC' => $criteria['destination_id'],
        'CHECKIN_BEG' => str_replace('-', '', $criteria['checkin_begin']), 'CHECKIN_END' => str_replace('-', '', $criteria['checkin_end']),
        'ADULT' => $criteria['adults'], 'CHILD' => $criteria['children']];
    if ($criteria['child_ages']) $dated['AGES'] = implode(',', $criteria['child_ages']);
    $criteria['currency_id'] = anytour_anex_search3_dictionary_id(
        anytour_anex_search3_dictionary($client, 'SearchTour_CURRENCIES', $dated, $cache), ['RUB', 'RUR', 'Рубль', 'Рубли', 'Руб']);
    $resolver = AnyTourAnexSearchMappingRegistry::fromPdo($pdo)->previewResolver();
    $session = $state === null ? null : [];
    $result = anytour_anex_search3_prices($client, $resolver, $criteria, $session);
    // Bound the first page before catalog hydration; cheapest RUB offers first.
    usort($result['offers'], static function ($a, $b) {
        $amount = static function ($offer) {
            $price = ($offer['price']['currency'] ?? '') === 'RUB' ? $offer['price'] : ($offer['converted_price'] ?? []);
            return ($price['currency'] ?? '') === 'RUB' ? (float) $price['amount'] : PHP_FLOAT_MAX;
        };
        return $amount($a) <=> $amount($b);
    });
    $result['offers'] = array_slice($result['offers'], 0, 300);
    // Optional server-only observation for the deployment probe; never projected into HTTP output.
    if ($diagnostics !== null) {
        $diagnostics = ['supplier_offers' => count($result['offers']), 'mapped_offers' => 0,
            'rejected_count' => $result['rejected_count'], 'external_search_pending' => $result['external_search_pending'],
            'samples' => [], 'unmapped_hotel_ids' => []];
        $unmapped = [];
        foreach ($result['offers'] as $offer) {
            if (is_int($offer['hotel']['local_id'])) $diagnostics['mapped_offers']++;
            if (is_int($offer['hotel']['local_id']) && count($diagnostics['samples']) < 3) $diagnostics['samples'][] = [
                'anex_hotel_id' => (int) $offer['hotel']['external_id'], 'catalog_hotel_id' => $offer['hotel']['local_id']];
            if ($offer['hotel']['local_id'] === null && count($unmapped) < 300
                && preg_match('/\A[1-9][0-9]{0,7}\z/D', $offer['hotel']['external_id'])) {
                $unmapped[(int) $offer['hotel']['external_id']] = true;
            }
        }
        $diagnostics['unmapped_hotel_ids'] = array_keys($unmapped);
    }
    $metadata = anytour_anex_search3_metadata($pdo, $result['offers']);
    $searchRef = $session === null ? null : $result['search_ref'];
    $projected = anytour_anex_search3_project($result['offers'], $metadata, $params, $searchRef);
    // Capture only successful normalized supplier responses, before local filters discard unmapped hotels.
    // A storage problem must not turn available tours into a search error.
    if ($observer !== null) {
        try {
            $observation = $observer($result['offers'], ['country_id' => (int)$params['countryId'],
                'anex_country_id' => $criteria['destination_id'], 'checkin_from' => $criteria['checkin_begin'],
                'checkin_to' => $criteria['checkin_end']]);
        } catch (Throwable $ignored) {
            $observation = ['status' => 'storage_unavailable'];
            error_log('ANEX_OBSERVATION_WRITE_FAILED');
        }
        if ($diagnostics !== null) $diagnostics['observation'] = $observation;
    }
    $data = ['generation' => $request['generation'], 'provider' => 'anex',
        'date_range' => ['from' => $criteria['checkin_begin'], 'to' => $criteria['checkin_end']], 'hotels' => $projected,
        'external_search_pending' => $result['external_search_pending'], 'first_page_only' => true];
    if ($state !== null) {
        $state = ['generation' => $request['generation'], 'params' => $params, 'gateway' => $session,
            'expansions' => [], 'additional_prices' => []];
        $data['search_ref'] = $searchRef;
    }
    return $data;
}

/** Fixed original lifetime, not the gateway's sliding request/rate-limit lifetime. */
function anytour_anex_search3_current(array $state, int $now): bool
{
    $saved = $state['gateway']['saved_offers'] ?? null;
    return is_array($saved) && is_array($state['params'] ?? null)
        && is_int($saved['created_at'] ?? null) && is_int($saved['expires_at'] ?? null)
        && $saved['expires_at'] === $saved['created_at'] + 900
        && $now >= $saved['created_at'] && $now < $saved['expires_at'];
}

/** AdditionalPricesDaily amounts are supplier-reported facts, never inferred totals. */
function anytour_anex_search3_additional_decimal($value): ?string
{
    if (is_int($value) || (is_float($value) && is_finite($value))) $value = (string) $value;
    return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,4})?\z/D', $value) ? $value : null;
}

/** Public-safe evidence: no tour/currency/provider IDs and no arithmetic interpretation. */
function anytour_anex_search3_additional_evidence(array $payload): array
{
    $rows = $payload['data'] ?? null;
    $total = $payload['totalCount'] ?? null;
    if (!is_array($rows) || ($rows !== [] && array_keys($rows) !== range(0, count($rows) - 1))) {
        throw new RuntimeException('ANEX_INVALID_ADDITIONAL_PRICES');
    }
    if (is_string($total) && preg_match('/\A[0-9]{1,9}\z/D', $total)) $total = (int) $total;
    if (!is_int($total) || $total < 0 || $total > 100000000 || $total < count($rows)) {
        throw new RuntimeException('ANEX_INVALID_ADDITIONAL_PRICES');
    }
    $safe = [];
    foreach (array_slice($rows, 0, 10) as $row) {
        if (!is_array($row)) throw new RuntimeException('ANEX_INVALID_ADDITIONAL_PRICES');
        $item = [];
        foreach (['price_adult', 'price_chd', 'cashrate', 'price_converted_adult', 'price_converted_chd'] as $field) {
            if (!array_key_exists($field, $row)) {
                $item[$field] = null;
                continue;
            }
            $item[$field] = anytour_anex_search3_additional_decimal($row[$field]);
            if ($item[$field] === null) throw new RuntimeException('ANEX_INVALID_ADDITIONAL_PRICES');
        }
        $safe[] = $item;
    }
    return ['source' => 'anex_b2b_additional_prices_daily', 'rows' => $safe, 'total_count' => $total,
        'truncated' => $total > count($safe), 'scope' => 'tour_program_date_nights_currency',
        'offer_specific' => false, 'currency' => null, 'converted_currency' => null,
        'per_person_or_package' => 'unknown', 'fuel_equivalence_verified' => false,
        'included_in_search_price' => 'unknown', 'arithmetic_applied' => false, 'final_price_verified' => false];
}

/** Same session/DTO owner for explicit expansion, saved reads and bounded additional-price evidence. */
function anytour_anex_search3_followup(array $request, array &$state, callable $resolver, callable $clientFactory,
    callable $metadataReader, ?callable $clock = null, ?callable $checkpoint = null, ?callable $additionalFactory = null): array
{
    $keys = ['action', 'generation', 'search_ref', 'offer_ref', 'local_hotel_id'];
    if (count($request) !== count($keys) || array_diff($keys, array_keys($request))
        || !in_array($request['action'] ?? null, ['offer', 'expand', 'additional_prices'], true)
        || !is_int($request['generation'] ?? null) || $request['generation'] < 1 || $request['generation'] > 2147483647
        || !is_string($request['search_ref'] ?? null) || !preg_match('/\A[a-f0-9]{32}\z/D', $request['search_ref'])
        || !is_string($request['offer_ref'] ?? null) || !preg_match('/\Aanex_online:[a-f0-9]{64}\z/D', $request['offer_ref'])
        || !is_int($request['local_hotel_id'] ?? null) || $request['local_hotel_id'] < 1 || $request['local_hotel_id'] > 999999999) {
        throw new InvalidArgumentException('ANEX_INVALID_REQUEST');
    }
    $clock = $clock ?? static function (): int { return time(); };
    $now = $clock();
    if (!is_int($now) || $now < 1) throw new RuntimeException('ANEX_CLOCK_ERROR');
    $reply = ['provider' => 'anex', 'generation' => $request['generation'], 'search_ref' => $request['search_ref'],
        'offer_ref' => $request['offer_ref'], 'status' => 'expired', 'offer' => null, 'selection_state' => 'disabled'];
    if (!anytour_anex_search3_current($state, $now)) return $reply;
    $saved = $state['gateway']['saved_offers'];
    if (($state['generation'] ?? null) !== $request['generation'] || $saved['search_ref'] !== $request['search_ref']) {
        return array_replace($reply, ['status' => 'mismatch']);
    }
    $key = $request['offer_ref'];
    $savedEntry = $saved['offers'][$key] ?? null;
    $offer = is_array($savedEntry) ? ($savedEntry['offer'] ?? null) : null;
    $known = array_column($state['gateway']['search']['offers'] ?? [], null, 'offer_key');
    if (!is_array($offer) || !isset($known[$key])) return array_replace($reply, ['status' => 'not_loaded']);
    if (($offer['offer_key'] ?? null) !== $key || $known[$key]['kind'] !== ($offer['kind'] ?? null)
        || $known[$key]['hotel_external_id'] !== ($offer['hotel']['external_id'] ?? null)) {
        throw new InvalidArgumentException('ANEX_INVALID_SESSION');
    }
    $local = $resolver('anex_online', $offer['hotel']['external_id']);
    if ($local === null) return array_replace($reply, ['status' => 'identity_unresolved']);
    if ($local !== $request['local_hotel_id'] || $local !== ($offer['hotel']['local_id'] ?? null)) {
        return array_replace($reply, ['status' => 'identity_changed']);
    }
    $metadata = $metadataReader([$offer]);
    if (anytour_anex_search3_project([$offer], $metadata, $state['params']) === []) {
        return array_replace($reply, ['status' => 'not_available']);
    }
    if ($request['action'] === 'additional_prices') {
        if (($offer['kind'] ?? null) !== 'concrete') return array_replace($reply, ['status' => 'not_concrete']);
        $tour = $savedEntry['supplier_tour_program_id'] ?? null;
        $currency = $savedEntry['supplier_currency_id'] ?? null;
        $checkin = $offer['checkin'] ?? null;
        $nights = $offer['nights'] ?? null;
        if (!is_string($tour) || !preg_match('/\A[1-9][0-9]{0,17}\z/D', $tour)
            || !is_string($currency) || !preg_match('/\A[1-9][0-9]{0,17}\z/D', $currency)
            || !is_string($checkin) || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $checkin)
            || !is_int($nights) || $nights < 1 || $nights > 60) {
            return array_replace($reply, ['status' => 'additional_prices_unavailable']);
        }
        $digest = hash('sha256', implode("\0", [$tour, $currency, $checkin, (string) $nights]));
        $attempt = $state['additional_prices'][$digest] ?? null;
        if (is_array($attempt) && ($attempt['status'] ?? null) === 'complete' && is_array($attempt['evidence'] ?? null)) {
            return array_replace($reply, ['status' => 'additional_prices', 'additional_prices' => $attempt['evidence']]);
        }
        if ($attempt !== null) return array_replace($reply, ['status' => 'additional_prices_unknown']);
        if ($checkpoint === null || $additionalFactory === null) throw new RuntimeException('ANEX_RESERVATION_REQUIRED');
        // Persist before B2B transport. Unknown/reserved means no semantic replay.
        $state['additional_prices'][$digest] = ['status' => 'unknown'];
        $checkpoint($state);
        if (!anytour_anex_search3_current($state, $clock())) return $reply;
        $additional = $additionalFactory();
        if (!is_object($additional) || !is_callable([$additional, 'additionalPricesDaily'])) {
            throw new RuntimeException('ANEX_ADDITIONAL_CLIENT_UNAVAILABLE');
        }
        $payload = $additional->additionalPricesDaily(['page' => 1, 'pageSize' => 10, 'tour' => (int) $tour,
            'dateBeg' => $checkin, 'nights' => $nights, 'currency' => (int) $currency]);
        if (!is_array($payload)) throw new RuntimeException('ANEX_INVALID_ADDITIONAL_PRICES');
        $evidence = anytour_anex_search3_additional_evidence($payload);
        $evidence['observed_at'] = gmdate('Y-m-d\TH:i:s\Z', $clock());
        $state['additional_prices'][$digest] = ['status' => 'complete', 'evidence' => $evidence];
        if (!anytour_anex_search3_current($state, $clock())) return $reply;
        return array_replace($reply, ['status' => 'additional_prices', 'additional_prices' => $evidence]);
    }
    $gateway = new AnyTourAnexPreviewGateway($clientFactory, $resolver, [], $clock);
    if ($request['action'] === 'offer') {
        $result = $gateway->handle(['action' => 'offer', 'search_ref' => $request['search_ref'],
            'offer_key' => $key, 'local_hotel_id' => $local], $state['gateway']);
        unset($result['offer_key']);
        return array_replace($reply, $result);
    }
    if ($offer['kind'] !== 'group_minimum') return array_replace($reply, ['status' => 'not_grouped']);
    $attempt = $state['expansions'][$key] ?? null;
    if ($attempt !== null && ($attempt['status'] ?? null) !== 'complete') {
        return array_replace($reply, ['status' => 'expansion_unknown']);
    }
    if ($attempt === null) {
        if ($checkpoint === null) throw new RuntimeException('ANEX_RESERVATION_REQUIRED');
        // Persist before transport. A crash/timeout cannot silently replay CATCLAIM.
        $state['expansions'][$key] = ['status' => 'unknown'];
        $checkpoint($state);
        if (!anytour_anex_search3_current($state, $clock())) return $reply;
        $expanded = $gateway->handle(['action' => 'expand', 'offer_key' => $key], $state['gateway']);
        $state['expansions'][$key] = ['status' => 'complete', 'keys' => array_column($expanded['offers'], 'offer_key'),
            'external_search_pending' => $expanded['external_search_pending']];
        $attempt = $state['expansions'][$key];
    }
    if (!anytour_anex_search3_current($state, $clock())) return $reply;
    $offers = [];
    $known = array_column($state['gateway']['search']['offers'] ?? [], null, 'offer_key');
    foreach ($attempt['keys'] as $expandedKey) {
        $entry = $state['gateway']['saved_offers']['offers'][$expandedKey]['offer'] ?? null;
        if (!is_array($entry) || !isset($known[$expandedKey])) return array_replace($reply, ['status' => 'not_loaded']);
        if ($entry['kind'] !== 'concrete' || $entry['hotel']['external_id'] !== $offer['hotel']['external_id']
            || $entry['hotel']['local_id'] !== $local) throw new InvalidArgumentException('ANEX_INVALID_SESSION');
        $offers[] = $entry;
    }
    return array_replace($reply, ['status' => 'expanded', 'hotels' => anytour_anex_search3_project(
        $offers, $metadata, $state['params'], $request['search_ref']),
        'external_search_pending' => $attempt['external_search_pending'], 'first_page_only' => true]);
}

/** Flush and read back the reservation, then regain the same session lock. */
function anytour_anex_search3_checkpoint(array &$state): void
{
    $expected = $state;
    if (!session_write_close() || !session_start()) throw new RuntimeException('ANEX_SESSION_UNAVAILABLE');
    if (($_SESSION['offer_context'] ?? null) !== $expected) throw new InvalidArgumentException('ANEX_SESSION_CHANGED');
    // session_start replaced the container; keep the caller's reference attached.
    $_SESSION['offer_context'] =& $state;
}

function anytour_anex_search3_out(array $data, int $status): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function anytour_anex_search3_http(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    $private = __DIR__ . '/.anex-private.php';
    if (is_file($private)) require_once $private;
    $enabled = getenv('ANYTOUR_ANEX_PREVIEW_ENABLED') === '1' || (defined('ANYTOUR_ANEX_PREVIEW_ENABLED')
        && in_array(ANYTOUR_ANEX_PREVIEW_ENABLED, [true, 1, '1'], true));
    if (!$enabled) anytour_anex_search3_out(['ok' => false, 'error' => 'not_found'], 404);
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        anytour_anex_search3_out(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }
    if (!in_array(strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin')), ['same-origin', 'none'], true)) {
        anytour_anex_search3_out(['ok' => false, 'error' => 'forbidden'], 403);
    }
    $type = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($type !== 'application/json' || (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) anytour_anex_search3_out(['ok' => false, 'error' => 'invalid_request'], 400);
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    if (!is_string($raw) || $raw === '' || strlen($raw) > 16384) anytour_anex_search3_out(['ok' => false, 'error' => 'invalid_request'], 400);
    try {
        $request = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($request)) throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
    } catch (Throwable $ignored) { anytour_anex_search3_out(['ok' => false, 'error' => 'invalid_request'], 400); }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('ANYTOUR_ANEX_SEARCH3');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/_preview/search3-anex-candidate/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    if (!session_start()) anytour_anex_search3_out(['ok' => false, 'error' => 'temporarily_unavailable'], 503);
    try {
        $action = $request['action'] ?? 'search';
        if (!in_array($action, ['search', 'offer', 'expand', 'additional_prices'], true)) throw new InvalidArgumentException('ANEX_INVALID_ACTION');
        if ($action === 'search' || !is_array($_SESSION['offer_context'] ?? null)) $_SESSION['offer_context'] = [];
        if (!is_array($_SESSION['dictionaries'] ?? null)) $_SESSION['dictionaries'] = [];
        $app = is_file(__DIR__ . '/app/integrations/anex-search.php') ? __DIR__ . '/app/integrations' : __DIR__ . '/../app/integrations';
        require_once $app . '/anex-preview-gateway.php';
        require_once $app . '/anex-search-mapping-registry.php';
        require_once $app . '/anex-search-observations.php';
        require_once $app . '/anex-additional-prices-client.php';
        $root = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($root === false || basename($root) !== 'anytoour.ru') throw new RuntimeException('ANEX_DATABASE_UNAVAILABLE');
        $helper = is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php';
        require_once $helper;
        // Supplier budget/credentials belong only to actual supplier operations.
        $reserveSupplierRequest = static function (): void {
            $recent = array_filter($_SESSION['requests'] ?? [], static function ($at) { return is_int($at) && $at > time() - 60; });
            if (count($recent) >= 6) throw new RuntimeException('ANEX_RATE_LIMIT');
            $recent[] = time();
            $_SESSION['requests'] = array_values($recent);
        };
        $clientFactory = static function () use (&$client, $reserveSupplierRequest): AnyTourAnexClient {
            $reserveSupplierRequest();
            $token = trim((string) getenv('ANEX_API_TOKEN'));
            if ($token === '' && defined('ANEX_API_TOKEN')) $token = trim((string) ANEX_API_TOKEN);
            if ($token === '') throw new RuntimeException('ANEX_CLIENT_UNAVAILABLE');
            return $client = new AnyTourAnexClient($token);
        };
        $additionalFactory = static function () use (&$additionalClient, $reserveSupplierRequest): AnyTourAnexAdditionalPricesClient {
            $reserveSupplierRequest();
            $token = trim((string) getenv('ANEX_B2B_TOKEN'));
            if ($token === '' && defined('ANEX_B2B_TOKEN')) $token = trim((string) ANEX_B2B_TOKEN);
            if ($token === '') throw new RuntimeException('ANEX_ADDITIONAL_CLIENT_UNAVAILABLE');
            return $additionalClient = new AnyTourAnexAdditionalPricesClient($token);
        };
        $pdo = v2_data_db();
        if ($action === 'search') {
            $diagnostics = null;
            $observer = static function (array $offers, array $context) use ($pdo): array {
                return AnyTourAnexSearchObservations::record($pdo, $offers, $context);
            };
            $data = anytour_anex_search3_run($request, $pdo, $clientFactory(), $_SESSION['dictionaries'],
                $diagnostics, $observer, $_SESSION['offer_context']);
        } else {
            $data = anytour_anex_search3_followup($request, $_SESSION['offer_context'],
                AnyTourAnexSearchMappingRegistry::fromPdo($pdo)->previewResolver(), $clientFactory,
                static function (array $offers) use ($pdo): array { return anytour_anex_search3_metadata($pdo, $offers); },
                null, 'anytour_anex_search3_checkpoint', $additionalFactory);
        }
        session_write_close();
        anytour_anex_search3_out(['ok' => true, 'data' => $data], 200);
    } catch (InvalidArgumentException $error) {
        session_write_close();
        $unsupported = in_array($error->getMessage(), ['ANEX_FILTER_UNSUPPORTED', 'ANEX_DESTINATION_UNSUPPORTED'], true);
        anytour_anex_search3_out(['ok' => false, 'error' => $unsupported ? 'search_not_supported' : 'invalid_request'], $unsupported ? 422 : 400);
    } catch (Throwable $error) {
        session_write_close();
        $last = isset($additionalClient) ? $additionalClient->lastRequestDiagnostics()
            : (isset($client) ? $client->lastRequestDiagnostics() : []);
        if ($error->getMessage() === 'ANEX_RATE_LIMIT' || ($last['http_status'] ?? null) === 429) {
            header('Retry-After: 60');
            anytour_anex_search3_out(['ok' => false, 'error' => 'rate_limited'], 429);
        }
        if (in_array($error->getMessage(), ['ANEX_CLIENT_UNAVAILABLE', 'ANEX_ADDITIONAL_CLIENT_UNAVAILABLE'], true)) {
            anytour_anex_search3_out(['ok' => false, 'error' => 'temporarily_unavailable'], 503);
        }
        $code = ($last['supplier_code'] ?? null) === 101 ? 'supplier_conditions_rejected'
            : (($last['curl_errno'] ?? null) === 28 ? 'supplier_timeout' : 'supplier_unavailable');
        anytour_anex_search3_out(['ok' => false, 'error' => $code], $code === 'supplier_conditions_rejected' ? 422 : 502);
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) anytour_anex_search3_http();