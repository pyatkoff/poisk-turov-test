<?php
declare(strict_types=1);

require_once __DIR__ . '/data/hotel-details-v1.php';
$mealFamilyFile = is_file(__DIR__ . '/app/integrations/three-provider-meal-family.php')
    ? __DIR__ . '/app/integrations/three-provider-meal-family.php'
    : __DIR__ . '/../app/integrations/three-provider-meal-family.php';
require_once $mealFamilyFile;
unset($mealFamilyFile);
$additionalBatchFile = is_file(__DIR__ . '/app/integrations/anex-additional-prices-batch.php')
    ? __DIR__ . '/app/integrations/anex-additional-prices-batch.php'
    : __DIR__ . '/../app/integrations/anex-additional-prices-batch.php';
require_once $additionalBatchFile;
unset($additionalBatchFile);

$programObservationFile = is_file(__DIR__ . '/app/integrations/anex-program-observation-runtime.php')
    ? __DIR__ . '/app/integrations/anex-program-observation-runtime.php'
    : __DIR__ . '/../app/integrations/anex-program-observation-runtime.php';
require_once $programObservationFile;
unset($programObservationFile);

$packageQuoteFile = is_file(__DIR__ . '/app/integrations/anex-package-quote-runtime.php')
    ? __DIR__ . '/app/integrations/anex-package-quote-runtime.php'
    : __DIR__ . '/../app/integrations/anex-package-quote-runtime.php';
require_once $packageQuoteFile;
unset($packageQuoteFile);
$handoffFile = is_file(__DIR__ . '/app/integrations/anex-package-quote-handoff.php')
    ? __DIR__ . '/app/integrations/anex-package-quote-handoff.php'
    : __DIR__ . '/../app/integrations/anex-package-quote-handoff.php';
require_once $handoffFile;
unset($handoffFile);

$normalizerFile = is_file(__DIR__ . '/app/integrations/anex-normalizer.php')
    ? __DIR__ . '/app/integrations/anex-normalizer.php'
    : __DIR__ . '/../app/integrations/anex-normalizer.php';
require_once $normalizerFile;
unset($normalizerFile);

$messengerHandoffFile = is_file(__DIR__ . '/app/integrations/search3-messenger-handoff.php')
    ? __DIR__ . '/app/integrations/search3-messenger-handoff.php'
    : __DIR__ . '/../app/integrations/search3-messenger-handoff.php';
require_once $messengerHandoffFile;
unset($messengerHandoffFile);

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

/** Search3 operator IDs are Tourvisor-native; use observed names only to decide whether this ANEX-only source applies. */
function anytour_anex_search3_operator_scope(PDO $pdo, $values): string
{
    if ($values === null || $values === '') $values = [];
    if (!is_array($values) || count($values) > 30) throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
    if ($values === []) return 'all';
    $wanted = [];
    foreach ($values as $value) {
        if (is_bool($value) || !is_scalar($value) || !preg_match('/\A[1-9][0-9]{0,9}\z/D', (string) $value)) {
            throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
        }
        $wanted[(string) $value] = true;
    }
    try {
        $query = $pdo->prepare('SELECT operator_id,operator_name FROM tour_operator_identity_observations WHERE operator_id IN ('
            . implode(',', array_fill(0, count($wanted), '?')) . ") AND operator_name IS NOT NULL AND operator_name<>'' ORDER BY last_seen_at DESC,id DESC");
        $query->execute(array_keys($wanted));
        $seen = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (string) ($row['operator_id'] ?? '');
            if (!isset($wanted[$id]) || !is_string($row['operator_name'] ?? null)) continue;
            $name = anytour_anex_search3_name($row['operator_name']);
            if ($name === '') continue;
            $tokens = explode(' ', $name);
            $kind = in_array('anex', $tokens, true) || in_array('анекс', $tokens, true) || $name === 'анекстур' ? 'anex' : 'other';
            $seen[$id][$kind] = true;
        }
    } catch (Throwable $ignored) {
        throw new InvalidArgumentException('ANEX_FILTER_UNSUPPORTED');
    }
    $hasAnex = false;
    foreach (array_keys($wanted) as $id) {
        $kinds = array_keys($seen[$id] ?? []);
        if ($kinds === [] || count($kinds) !== 1) throw new InvalidArgumentException('ANEX_FILTER_UNSUPPORTED');
        if ($kinds[0] === 'anex') $hasAnex = true;
    }
    return $hasAnex ? 'include' : 'exclude';
}

/** Search3 canonical meal IDs -> local direct-ANEX meal families. This is not a supplier-ID mapping. */
function anytour_anex_search3_meal_families($value): ?array
{
    $value = (string) $value;
    if ($value === '') return null;
    $families = [
        '2' => ['ro'],
        '3' => ['bb'],
        '4' => ['hb'],
        '5' => ['fb'],
        '7' => ['ai', 'uai'],
        '9' => ['uai'],
    ][$value] ?? null;
    if ($families === null) throw new InvalidArgumentException('ANEX_FILTER_UNSUPPORTED');
    return $families;
}

/** Match only verified human-readable meal labels; unknown labels fail closed when a meal is selected. */
function anytour_anex_search3_meal_matches($raw, $filter): bool
{
    $families = anytour_anex_search3_meal_families($filter);
    if ($families === null) return true;
    if (!is_string($raw) || trim($raw) === '') return false;
    if (anytour_anex_search3_name($raw) === 'all') return in_array('ai', $families, true);
    try {
        $normalized = AnyTourThreeProviderMealFamily::normalize($raw);
    } catch (InvalidArgumentException $ignored) {
        return false;
    }
    return ($normalized['family_verified'] ?? false) === true
        && is_string($normalized['family'] ?? null)
        && in_array($normalized['family'], $families, true);
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
        if (is_bool($params[$key] ?? null) || !is_scalar($params[$key] ?? null) || !preg_match('/\A[1-9][0-9]{0,9}\z/D', (string) $params[$key])) {
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
    foreach (['arrivalId', 'operatorIds', 'hotelServices', 'hotelTypes'] as $key) {
        if (!empty($params[$key])) throw new InvalidArgumentException('ANEX_FILTER_UNSUPPORTED');
    }
    foreach (['onlyDirect', 'onlyCharter'] as $key) {
        if (!in_array($params[$key] ?? 'false', ['false', false, '', '0', 0], true)) throw new InvalidArgumentException('ANEX_FILTER_UNSUPPORTED');
    }
    anytour_anex_search3_meal_families($params['meal'] ?? '');
    if (!in_array((string) ($params['hotelRating'] ?? ''), ['', '2', '3', '4', '5'], true)) throw new InvalidArgumentException('ANEX_FILTER_UNSUPPORTED');
    foreach (['hotelCategory', 'hotelRating', 'priceFrom', 'priceTo'] as $key) {
        if (isset($params[$key]) && $params[$key] !== '' && (is_bool($params[$key]) || !is_scalar($params[$key])
            || !preg_match('/\A[0-9]{1,12}(?:\.[0-9]{1,2})?\z/D', (string) $params[$key]))) {
            throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
        }
    }
    foreach (['hotelIds', 'regionIds', 'subregionIds'] as $key) {
        $values = $params[$key] ?? [];
        if (!is_array($values) || count($values) > 30) throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
        foreach ($values as $value) if (is_bool($value) || !is_scalar($value) || !preg_match('/\A[1-9][0-9]{0,9}\z/D', (string) $value)) throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
    }
    if (($params['currency'] ?? 'RUB') !== 'RUB') throw new InvalidArgumentException('ANEX_FILTER_UNSUPPORTED');
    return anytour_anex_search3_week($core);
}

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

function anytour_anex_search3_metadata(PDO $pdo, array $offers, int $limit = 300): array
{
    if ($limit < 1 || $limit > 4800) throw new InvalidArgumentException('ANEX_INVALID_METADATA_LIMIT');
    $ids = [];
    foreach (array_slice($offers, 0, $limit) as $offer) {
        $id = $offer['hotel']['local_id'] ?? null;
        if (is_int($id) && $id > 0) $ids[$id] = true;
    }
    if (!$ids) return [];
    $metadata = [];
    foreach (array_chunk(array_keys($ids), 300) as $chunk) {
        $hydrate = $pdo->prepare('SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,rating'
            . ' FROM catalog_hotels WHERE id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ') AND is_active=1 LIMIT 300');
        $hydrate->execute($chunk);
        foreach ($hydrate->fetchAll(PDO::FETCH_ASSOC) as $row) $metadata[(int) $row['id']] = $row;
    }
    return anytour_anex_search3_catalog_hydrate($pdo, $metadata);
}

function anytour_anex_search3_project(array $offers, array $metadata, array $params, ?string $searchRef = null, int $limit = 300): array
{
    if ($searchRef !== null && !preg_match('/\A[a-f0-9]{32}\z/D', $searchRef)) throw new InvalidArgumentException('ANEX_INVALID_SESSION');
    if ($limit < 1 || $limit > 4800) throw new InvalidArgumentException('ANEX_INVALID_PROJECT_LIMIT');
    $hotels = [];
    foreach (array_slice($offers, 0, $limit) as $offer) {
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
        if (!anytour_anex_search3_meal_matches($offer['meal'] ?? null, $params['meal'] ?? '')) continue;
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
                    'sea_distance' => null], 'tours' => []];
        }
        $tour = ['price' => $price, 'checkin' => $offer['checkin'], 'nights' => $offer['nights'],
            'adults' => $offer['adults'], 'children' => $offer['children'], 'meal' => $offer['meal'], 'room' => $offer['room'],
            'kind' => $offer['kind'], 'flight_type' => $offer['flight_type'] ?? null, 'final_price_verified' => false];
        if (is_int($offer['infants'] ?? null) && $offer['infants'] > 0) {
            // Public search money does not establish the infant charge or its inclusion.
            $tour += ['infants' => $offer['infants'],
                'infant_charge' => ['status' => 'unknown', 'amount' => null, 'included_in_search_price' => null]];
        }
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
    }
    unset($hotel);
    return array_values($hotels);
}

function anytour_anex_search3_week(array $criteria): array
{
    $weekEnd = (new DateTimeImmutable($criteria['checkin_begin']))->modify('+6 days')->format('Y-m-d');
    if ($criteria['checkin_end'] > $weekEnd) $criteria['checkin_end'] = $weekEnd;
    return $criteria;
}

function anytour_anex_search3_prices($client, callable $resolver, array $criteria, ?array &$session = null, ?callable $clock = null,
    bool $backgroundCollection = false): array
{
    if ($session === null) return (new AnyTourAnexSearch($client, $resolver))->search(anytour_anex_search3_week($criteria));
    $gateway = new AnyTourAnexPreviewGateway(
        static function () use ($client) { return $client; }, $resolver, [], $clock, !$backgroundCollection
    );
    return $gateway->handle(['action' => 'search', 'criteria' => anytour_anex_search3_week($criteria)], $session);
}

function anytour_anex_search3_run(array $request, PDO $pdo, $client, array &$cache, ?array &$diagnostics = null, ?callable $observer = null,
    ?array &$state = null, ?string $operatorScope = null, bool $backgroundCollection = false): array
{
    if ($state !== null) $state = [];
    if (!is_int($request['generation'] ?? null) || $request['generation'] < 1 || $request['generation'] > 2147483647
        || !is_array($request['params'] ?? null) || count($request['params']) > 40) throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
    $params = $request['params'];
    $operatorScope = $operatorScope ?? anytour_anex_search3_operator_scope($pdo, $params['operatorIds'] ?? []);
    if (!in_array($operatorScope, ['all', 'include', 'exclude'], true)) throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
    $coreParams = $params;
    $coreParams['operatorIds'] = [];
    $criteria = anytour_anex_search3_core($coreParams);
    if ($operatorScope === 'exclude') {
        return ['generation' => $request['generation'], 'provider' => 'anex',
            'date_range' => ['from' => $criteria['checkin_begin'], 'to' => $criteria['checkin_end']], 'hotels' => [],
            'external_search_pending' => false, 'pages_read' => 0, 'first_page_only' => true];
    }
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
    $registry = AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
    $resolver = $registry->previewResolver();
    $hotelIds = $registry->previewHotelIds($params['hotelIds'] ?? []);
    if ($hotelIds !== []) $criteria['hotel_ids'] = $hotelIds;
    $session = $state === null ? null : [];
    $result = anytour_anex_search3_prices($client, $resolver, $criteria, $session, null, $backgroundCollection);
    usort($result['offers'], static function ($a, $b) {
        $amount = static function ($offer) {
            $price = ($offer['price']['currency'] ?? '') === 'RUB' ? $offer['price'] : ($offer['converted_price'] ?? []);
            return ($price['currency'] ?? '') === 'RUB' ? (float) $price['amount'] : PHP_FLOAT_MAX;
        };
        return $amount($a) <=> $amount($b);
    });
    if (!$backgroundCollection) $result['offers'] = array_slice($result['offers'], 0, 300);
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
    $projectionLimit = $backgroundCollection ? 4800 : 300;
    $metadata = anytour_anex_search3_metadata($pdo, $result['offers'], $projectionLimit);
    $searchRef = $session === null ? null : $result['search_ref'];
    $projected = anytour_anex_search3_project($result['offers'], $metadata, $params, $searchRef, $projectionLimit);
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
        'external_search_pending' => $result['external_search_pending'],
        'pages_read' => (int)($result['pages_read'] ?? 1),
        'first_page_only' => ($result['first_page_only'] ?? true) === true];
    if ($state !== null) {
        $state = ['generation' => $request['generation'], 'params' => $params, 'gateway' => $session,
            'expansions' => [], 'additional_prices' => [],
            'handoff_context' => ['version' => 1, 'origin' => $names['departure_name'],
                'countryUiId' => (string) $params['countryId'],
                'hotels' => anytour_anex_handoff_catalog($metadata, (int) $params['countryId'])]];
        $data['search_ref'] = $searchRef;
        if (!$backgroundCollection) {
            $continuation = anytour_anex_search3_continuation_metadata($state);
            if ($continuation !== null) $data['continuation'] = $continuation;
        }
    }
    return $data;
}

/** Export only the checked page counter, never the private supplier cursor. */
function anytour_anex_search3_continuation_metadata(array $state): ?array
{
    $cursor = $state['gateway']['search']['pagination'] ?? null;
    if (!is_array($cursor) || !is_int($cursor['pages_read'] ?? null)
        || $cursor['pages_read'] < 1 || $cursor['pages_read'] > 12
        || !in_array($cursor['state'] ?? null, ['available', 'exhausted', 'blocked', 'limit'], true)
        || ($cursor['state'] === 'available' && $cursor['pages_read'] >= 12)
        || ($cursor['state'] === 'limit' && $cursor['pages_read'] !== 12)
        || !is_array($cursor['page_digests'] ?? null) || !array_is_list($cursor['page_digests'])
        || count($cursor['page_digests']) !== $cursor['pages_read']) return null;
    $seen = [];
    foreach ($cursor['page_digests'] as $digest) {
        if (!is_string($digest) || !preg_match('/\A[a-f0-9]{64}\z/D', $digest) || isset($seen[$digest])) return null;
        $seen[$digest] = true;
    }
    $stateName = $cursor['state'];
    $next = $cursor['pages_read'] + 1;
    if ($stateName === 'available' && array_key_exists($next, $state['continuation_attempts'] ?? [])) $stateName = 'blocked';
    return ['state' => $stateName, 'pages_read' => $cursor['pages_read'],
        'next_page' => $stateName === 'available' ? $next : null];
}

/** One public next-page transaction. The caller owns the existing PHP session lock. */
function anytour_anex_search3_continue(array $request, array &$state, callable $resolver, callable $clientFactory,
    callable $metadataReader, ?callable $clock = null, ?callable $checkpoint = null): array
{
    $keys = ['action', 'generation', 'search_ref', 'page'];
    if (count($request) !== count($keys) || array_diff($keys, array_keys($request))
        || ($request['action'] ?? null) !== 'continue'
        || !is_int($request['generation'] ?? null) || $request['generation'] < 1 || $request['generation'] > 2147483647
        || !is_string($request['search_ref'] ?? null) || !preg_match('/\A[a-f0-9]{32}\z/D', $request['search_ref'])
        || !is_int($request['page'] ?? null) || $request['page'] < 2 || $request['page'] > 12) {
        throw new InvalidArgumentException('ANEX_INVALID_REQUEST');
    }
    $clock = $clock ?? static function (): int { return time(); };
    $now = $clock();
    if (!is_int($now) || $now < 1) throw new RuntimeException('ANEX_CLOCK_ERROR');
    if (!anytour_anex_search3_current($state, $now)
        || ($state['generation'] ?? null) !== $request['generation']
        || ($state['gateway']['saved_offers']['search_ref'] ?? null) !== $request['search_ref']) {
        throw new InvalidArgumentException('ANEX_SESSION_REQUIRED');
    }
    $cursor = anytour_anex_search3_continuation_metadata($state);
    if ($cursor === null || $cursor['state'] !== 'available' || $cursor['next_page'] !== $request['page']) {
        throw new InvalidArgumentException('ANEX_CONTINUATION_UNAVAILABLE');
    }
    if ($checkpoint === null) throw new RuntimeException('ANEX_RESERVATION_REQUIRED');
    $before = $state;
    $page = $request['page'];
    // A restored session containing this marker cannot issue the same page, even
    // if the PHP worker dies before the gateway has had time to block its cursor.
    $state['continuation_attempts'][$page] = 'reserved';
    $checkpoint($state);
    if (($state['generation'] ?? null) !== $before['generation']
        || ($state['gateway'] ?? null) !== $before['gateway']
        || ($state['params'] ?? null) !== $before['params']
        || ($state['continuation_attempts'][$page] ?? null) !== 'reserved') {
        throw new InvalidArgumentException('ANEX_SESSION_CHANGED');
    }
    try {
        if (!anytour_anex_search3_current($state, $clock())) throw new RuntimeException('ANEX_SESSION_EXPIRED');
        $gateway = new AnyTourAnexPreviewGateway($clientFactory, $resolver, [], $clock);
        $result = $gateway->handle(['action' => 'continue', 'search_ref' => $request['search_ref'], 'page' => $page], $state['gateway']);
        if (!anytour_anex_search3_current($state, $clock())) throw new RuntimeException('ANEX_SESSION_EXPIRED');
        $saved = $state['gateway']['saved_offers'];
        if (($result['search_ref'] ?? null) !== $request['search_ref'] || ($result['page'] ?? null) !== $page
            || ($result['pages_read'] ?? null) !== 1 || ($result['first_page_only'] ?? null) !== false
            || !is_array($result['offers'] ?? null) || count($result['offers']) > 300
            || $saved['created_at'] !== $before['gateway']['saved_offers']['created_at']
            || $saved['expires_at'] !== $before['gateway']['saved_offers']['expires_at']) {
            throw new RuntimeException('ANEX_INVALID_PUBLIC_RESULT');
        }
        foreach ($before['gateway']['saved_offers']['offers'] as $key => $entry) {
            if (($saved['offers'][$key] ?? null) !== $entry) throw new RuntimeException('ANEX_INVALID_PUBLIC_RESULT');
        }
        $metadata = $metadataReader($result['offers']);
        $hotels = anytour_anex_search3_project($result['offers'], $metadata, $state['params'], $request['search_ref']);
        if (!anytour_anex_search3_current($state, $clock())) throw new RuntimeException('ANEX_SESSION_EXPIRED');
        $state['continuation_attempts'][$page] = 'complete';
        $next = anytour_anex_search3_continuation_metadata($state);
        if ($next === null || $next['pages_read'] !== $page) throw new RuntimeException('ANEX_INVALID_PUBLIC_RESULT');
        $criteria = $before['gateway']['search']['context'];
        $data = ['provider' => 'anex', 'generation' => $request['generation'], 'search_ref' => $request['search_ref'],
            'date_range' => ['from' => $criteria['checkin_begin'], 'to' => $criteria['checkin_end']],
            'hotels' => $hotels, 'page' => $page, 'pages_read' => 1, 'first_page_only' => false,
            'external_search_pending' => ($result['external_search_pending'] ?? false) === true,
            'continuation' => $next];
    } catch (Throwable $error) {
        // Keep spent gateway/session rate counters, but restore all earlier offer
        // evidence if the new page or its projection could not be accepted.
        $state['gateway']['search'] = $before['gateway']['search'];
        $state['gateway']['search']['pagination']['state'] = 'blocked';
        $state['gateway']['saved_offers'] = $before['gateway']['saved_offers'];
        $state['continuation_attempts'][$page] = 'blocked';
        $checkpoint($state);
        throw $error;
    }
    // Do not publish a success to the browser before the completed state is durable.
    // A session-change failure here must not roll back a concurrent newer search.
    $checkpoint($state);
    return $data;
}

/** Keep the original follow-up alias, backed by at most four current branches. */
function anytour_anex_search3_retain_context(array &$session, int $now): void
{
    $state = $session['offer_context'] ?? null;
    if (!is_array($state) || !anytour_anex_search3_current($state, $now)) return;
    $ref = $state['gateway']['saved_offers']['search_ref'] ?? null;
    if (!is_string($ref) || !preg_match('/\A[a-f0-9]{32}\z/D', $ref)
        || ($session['anex_context_generation'] ?? null) !== ($state['generation'] ?? null)) return;
    if (!is_array($session['anex_offer_contexts'] ?? null)) $session['anex_offer_contexts'] = [];
    if (!isset($session['anex_offer_contexts'][$ref]) && count($session['anex_offer_contexts']) >= 4) {
        throw new RuntimeException('ANEX_RATE_LIMIT');
    }
    $session['anex_offer_contexts'][$ref] = $state;
    unset($session['offer_context']);
    $session['offer_context'] =& $session['anex_offer_contexts'][$ref];
}

/** Route by both browser generation and server reference; never borrow another branch. */
function anytour_anex_search3_select_context(array $request, array &$session, int $now): void
{
    $generation = $request['generation'] ?? null;
    if (!is_int($generation) || $generation < 1 || $generation > 2147483647) {
        throw new InvalidArgumentException('ANEX_INVALID_REQUEST');
    }
    if (!is_array($session['anex_offer_contexts'] ?? null)) $session['anex_offer_contexts'] = [];
    // Import the one legacy context without granting it a guessed page cursor.
    if (!isset($session['anex_context_generation']) && is_int($session['offer_context']['generation'] ?? null)) {
        $session['anex_context_generation'] = $session['offer_context']['generation'];
    }
    foreach ($session['anex_offer_contexts'] as $ref => $state) {
        if (!is_array($state) || !anytour_anex_search3_current($state, $now)
            || ($state['generation'] ?? null) !== ($session['anex_context_generation'] ?? null)) unset($session['anex_offer_contexts'][$ref]);
    }
    anytour_anex_search3_retain_context($session, $now);
    if (($request['action'] ?? 'search') === 'search') {
        if (($session['anex_context_generation'] ?? null) !== $generation) {
            $session['anex_offer_contexts'] = [];
            $session['anex_context_generation'] = $generation;
        }
        // No silent eviction of a still-valid branch and no fifth supplier search.
        if (count($session['anex_offer_contexts']) >= 4) throw new RuntimeException('ANEX_RATE_LIMIT');
        unset($session['offer_context']);
        $session['offer_context'] = [];
        return;
    }
    $ref = $request['search_ref'] ?? null;
    unset($session['offer_context']);
    $session['offer_context'] = [];
    if (($session['anex_context_generation'] ?? null) !== $generation || !is_string($ref)
        || !preg_match('/\A[a-f0-9]{32}\z/D', $ref) || !isset($session['anex_offer_contexts'][$ref])) return;
    unset($session['offer_context']);
    $session['offer_context'] =& $session['anex_offer_contexts'][$ref];
}

function anytour_anex_search3_current(array $state, int $now): bool
{
    $saved = $state['gateway']['saved_offers'] ?? null;
    return is_array($saved) && is_array($state['params'] ?? null)
        && is_int($saved['created_at'] ?? null) && is_int($saved['expires_at'] ?? null)
        && $saved['expires_at'] === $saved['created_at'] + 900
        && $now >= $saved['created_at'] && $now < $saved['expires_at'];
}

function anytour_anex_search3_additional_decimal($value): ?string
{
    if (is_int($value) || (is_float($value) && is_finite($value))) $value = (string) $value;
    return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,4})?\z/D', $value) ? $value : null;
}

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

/** Exact 4-decimal fixed-point helpers; no binary-float money arithmetic. */
function anytour_anex_search3_money_units(string $value): ?int
{
    $value = anytour_anex_search3_additional_decimal($value);
    if ($value === null) return null;
    $parts = explode('.', $value, 2);
    $whole = $parts[0];
    $fraction = str_pad($parts[1] ?? '', 4, '0');
    if (strlen($whole) > 11 || strlen($fraction) !== 4) return null;
    return ((int) $whole * 10000) + (int) $fraction;
}

function anytour_anex_search3_money_string(int $units): string
{
    if ($units < 0) throw new InvalidArgumentException('ANEX_INVALID_ADDITIONAL_PRICES');
    $whole = intdiv($units, 10000);
    $fraction = $units % 10000;
    if ($fraction === 0) return (string) $whole;
    return $whole . '.' . rtrim(str_pad((string) $fraction, 4, '0', STR_PAD_LEFT), '0');
}

/** Apply the program/date rates to this retained party while preserving each money fact separately. */
function anytour_anex_search3_additional_application(array $evidence, array $offer): array
{
    $evidence['application_state'] = 'unknown';
    $evidence['party_surcharge'] = null;
    $evidence['search_price'] = null;
    $evidence['search_plus_additional'] = null;
    $evidence['arithmetic_applied'] = false;
    $rows = $evidence['rows'] ?? null;
    if (!is_array($rows) || count($rows) !== 1 || ($evidence['total_count'] ?? null) !== 1 || ($evidence['truncated'] ?? true)) return $evidence;
    $adults = $offer['adults'] ?? null;
    $children = $offer['children'] ?? null;
    $infants = $offer['infants'] ?? null;
    if (!is_int($adults) || $adults < 1 || $adults > 6 || !is_int($children) || $children < 0 || $children > 3
        || ($infants !== null && $infants !== 0)) return $evidence;
    $adult = anytour_anex_search3_money_units((string) ($rows[0]['price_converted_adult'] ?? ''));
    $child = $children === 0 ? 0 : anytour_anex_search3_money_units((string) ($rows[0]['price_converted_chd'] ?? ''));
    $price = ($offer['price']['currency'] ?? '') === 'RUB' ? $offer['price'] : ($offer['converted_price'] ?? null);
    $base = is_array($price) && ($price['currency'] ?? null) === 'RUB'
        ? anytour_anex_search3_money_units((string) ($price['amount'] ?? '')) : null;
    if ($adult === null || $child === null || $base === null) return $evidence;
    $party = ($adult * $adults) + ($child * $children);
    if ($party < 0 || $base > PHP_INT_MAX - $party) return $evidence;
    $total = $base + $party;
    $evidence['converted_currency'] = 'RUB';
    $evidence['per_person_or_package'] = 'per_person_by_party_type';
    $evidence['included_in_search_price'] = false;
    $evidence['application_state'] = 'applied';
    $evidence['party'] = ['adults' => $adults, 'children' => $children];
    $evidence['rates'] = [
        'adult' => ['amount' => anytour_anex_search3_money_string($adult), 'currency' => 'RUB'],
        'child' => $children === 0 ? null : ['amount' => anytour_anex_search3_money_string($child), 'currency' => 'RUB'],
    ];
    $evidence['party_surcharge'] = ['amount' => anytour_anex_search3_money_string($party), 'currency' => 'RUB',
        'source' => 'anex_b2b_additional_prices_daily'];
    $evidence['search_price'] = ['amount' => anytour_anex_search3_money_string($base), 'currency' => 'RUB',
        'source' => 'direct_anex_search'];
    $evidence['search_plus_additional'] = ['amount' => anytour_anex_search3_money_string($total), 'currency' => 'RUB',
        'formula' => 'search_price_plus_program_date_party_additional'];
    $evidence['arithmetic_applied'] = true;
    return $evidence;
}

/** Bounded visible-card batch for retained direct-ANEX AdditionalPricesDaily evidence. */
function anytour_anex_search3_additional_batch(array $request, array &$state, callable $resolver, callable $metadataReader,
    ?callable $clock = null, ?callable $checkpoint = null, ?callable $additionalFactory = null): array
{
    $keys = ['action', 'generation', 'search_ref', 'items'];
    if (count($request) !== count($keys) || array_diff($keys, array_keys($request))
        || array_diff(array_keys($request), $keys)
        || ($request['action'] ?? null) !== 'additional_prices_batch'
        || !is_int($request['generation'] ?? null) || $request['generation'] < 1 || $request['generation'] > 2147483647
        || !is_string($request['search_ref'] ?? null) || !preg_match('/\A[a-f0-9]{32}\z/D', $request['search_ref'])
        || !is_array($request['items'] ?? null)) {
        throw new InvalidArgumentException('ANEX_INVALID_REQUEST');
    }
    $clock = $clock ?? static function (): int { return time(); };
    $now = $clock();
    if (!is_int($now) || $now < 1) throw new RuntimeException('ANEX_CLOCK_ERROR');
    $reply = ['provider' => 'anex', 'generation' => $request['generation'], 'search_ref' => $request['search_ref'],
        'status' => 'expired', 'offers' => [], 'selection_state' => 'disabled'];
    if (!anytour_anex_search3_current($state, $now)) return $reply;
    $saved = $state['gateway']['saved_offers'] ?? null;
    if (!is_array($saved) || ($state['generation'] ?? null) !== $request['generation']
        || ($saved['search_ref'] ?? null) !== $request['search_ref']) {
        return array_replace($reply, ['status' => 'mismatch']);
    }

    $plan = anytour_anex_additional_prices_batch_plan($request['items'], $state);
    $offersByRef = [];
    foreach ($plan['offers'] as $item) {
        $key = $item['offer_ref'];
        $entry = $saved['offers'][$key] ?? null;
        $offer = is_array($entry) ? ($entry['offer'] ?? null) : null;
        if (!is_array($offer)) return array_replace($reply, ['status' => 'not_loaded']);
        $local = $resolver('anex_online', $offer['hotel']['external_id'] ?? null);
        if ($local === null) return array_replace($reply, ['status' => 'identity_unresolved']);
        if ($local !== $item['local_hotel_id'] || $local !== ($offer['hotel']['local_id'] ?? null)) {
            return array_replace($reply, ['status' => 'identity_changed']);
        }
        $offersByRef[$key] = $offer;
    }
    $metadata = $metadataReader(array_values($offersByRef));
    foreach ($offersByRef as $offer) {
        if (anytour_anex_search3_project([$offer], $metadata, $state['params']) === []) {
            return array_replace($reply, ['status' => 'not_available']);
        }
    }
    if ($checkpoint === null || $additionalFactory === null) throw new RuntimeException('ANEX_RESERVATION_REQUIRED');

    $persist = static function (array &$batchState, string $digest) use ($checkpoint, $clock): void {
        $checkpoint($batchState);
        $after = $clock();
        if (!is_int($after) || !anytour_anex_search3_current($batchState, $after)) {
            throw new RuntimeException('ANEX_SESSION_EXPIRED');
        }
    };
    $reader = static function (array $context) use (&$state, $additionalFactory, $clock): array {
        $before = $clock();
        if (!is_int($before) || !anytour_anex_search3_current($state, $before)) {
            throw new RuntimeException('ANEX_SESSION_EXPIRED');
        }
        $additional = $additionalFactory();
        if (!is_object($additional) || !is_callable([$additional, 'additionalPricesDaily'])) {
            throw new RuntimeException('ANEX_ADDITIONAL_CLIENT_UNAVAILABLE');
        }
        $payload = $additional->additionalPricesDaily(['page' => 1, 'pageSize' => 10,
            'tour' => (int) $context['supplier_tour_program_id'], 'dateBeg' => $context['checkin'],
            'nights' => $context['nights'], 'currency' => (int) $context['supplier_currency_id']]);
        if (!is_array($payload)) throw new RuntimeException('ANEX_INVALID_ADDITIONAL_PRICES');
        $evidence = anytour_anex_search3_additional_evidence($payload);
        $observed = $clock();
        if (!is_int($observed) || !anytour_anex_search3_current($state, $observed)) {
            throw new RuntimeException('ANEX_SESSION_EXPIRED');
        }
        $evidence['observed_at'] = gmdate('Y-m-d\TH:i:s\Z', $observed);
        return $evidence;
    };
    $batch = anytour_anex_additional_prices_batch_execute($plan, $state, $reader, $persist);
    $public = [];
    foreach ($batch['offers'] as $item) {
        $key = $item['offer_ref'];
        $complete = ($item['status'] ?? null) === 'complete' && is_array($item['additional_prices'] ?? null);
        $application = $complete
            ? anytour_anex_search3_additional_application($item['additional_prices'], $offersByRef[$key]) : null;
        $readyAmount = is_array($application)
            && ($application['application_state'] ?? null) === 'applied'
            && is_array($application['search_plus_additional'] ?? null)
            && ($application['search_plus_additional']['currency'] ?? null) === 'RUB'
            && is_string($application['search_plus_additional']['amount'] ?? null)
            && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $application['search_plus_additional']['amount'])
            && preg_match('/[1-9]/', $application['search_plus_additional']['amount'])
                ? $application['search_plus_additional']['amount'] : null;
        $retryable = ($item['retryable'] ?? null) === true
            && ($item['retry_reason'] ?? null) === 'session_budget_deferred';
        $public[] = ['offer_ref' => $key, 'local_hotel_id' => $item['local_hotel_id'],
            'status' => $complete ? 'additional_prices' : 'additional_prices_unknown',
            'finalPriceReady' => $readyAmount !== null, 'finalPrice' => $readyAmount, 'price' => $readyAmount,
            'retryable' => $retryable, 'retry_reason' => $retryable ? 'session_budget_deferred' : null,
            'additional_prices' => $application];
    }
    return array_replace($reply, ['status' => 'additional_prices_batch', 'offers' => $public]);
}

function anytour_anex_search3_followup(array $request, array &$state, callable $resolver, callable $clientFactory,
    callable $metadataReader, ?callable $clock = null, ?callable $checkpoint = null,
    ?callable $additionalFactory = null, bool $enforceGatewayRateLimit = true, ?callable $quoteFactory = null): array
{
    $keys = ['action', 'generation', 'search_ref', 'offer_ref', 'local_hotel_id'];
    if (($request['action'] ?? null) === 'quote_calculate') $keys[] = 'choice_ref';
    if (count($request) !== count($keys) || array_diff($keys, array_keys($request))
        || !in_array($request['action'] ?? null, ['offer', 'expand', 'additional_prices', 'flights', 'quote_start', 'quote_calculate'], true)
        || (($request['action'] ?? null) === 'quote_calculate' && (!is_string($request['choice_ref'] ?? null)
            || !preg_match('/\Aanex_quote:[a-f0-9]{64}\z/D', $request['choice_ref'])))
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
    if (($state['handoff_context']['version'] ?? null) === 1) {
        $state['handoff_context']['hotels'] = anytour_anex_handoff_catalog($metadata, (int) $state['params']['countryId'])
            + ($state['handoff_context']['hotels'] ?? []);
    }
    if (in_array($request['action'], ['quote_start', 'quote_calculate'], true)) {
        if ($checkpoint === null || $quoteFactory === null) throw new RuntimeException('ANEX_RESERVATION_REQUIRED');
        // /bron/start takes the currency selected in the search, not the offer's native price currency.
        $quoteEntry = $savedEntry;
        $selectedCurrency = $state['gateway']['search']['context']['currency_id'] ?? null;
        $quoteEntry['supplier_currency_id'] = is_int($selectedCurrency) || is_string($selectedCurrency) ? (string) $selectedCurrency : null;
        return array_replace($reply, anytour_anex_quote_run($request, $state, $offer, $known[$key],
            $quoteEntry, $quoteFactory, $checkpoint, $now, $clock));
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
            return array_replace($reply, ['status' => 'additional_prices',
                'additional_prices' => anytour_anex_search3_additional_application($attempt['evidence'], $offer)]);
        }
        if ($attempt !== null) return array_replace($reply, ['status' => 'additional_prices_unknown']);
        if ($checkpoint === null || $additionalFactory === null) throw new RuntimeException('ANEX_RESERVATION_REQUIRED');
        $state['additional_prices'][$digest] = ['status' => 'unknown'];
        $checkpoint($state);
        if (!anytour_anex_search3_current($state, $clock())) return $reply;
        $additional = $additionalFactory();
        if (!is_object($additional) || !is_callable([$additional, 'additionalPricesDaily'])) {
            throw new RuntimeException('ANEX_ADDITIONAL_CLIENT_UNAVAILABLE');
        }
        try {
            $payload = $additional->additionalPricesDaily(['page' => 1, 'pageSize' => 10, 'tour' => (int) $tour,
                'dateBeg' => $checkin, 'nights' => $nights, 'currency' => (int) $currency]);
        } catch (RuntimeException $error) {
            if ($error->getMessage() === 'ANEX_B2B_DAILY_UNKNOWN') {
                return array_replace($reply, ['status' => 'additional_prices_unknown']);
            }
            throw $error;
        }
        if (!is_array($payload)) throw new RuntimeException('ANEX_INVALID_ADDITIONAL_PRICES');
        $evidence = anytour_anex_search3_additional_evidence($payload);
        $evidence['observed_at'] = gmdate('Y-m-d\TH:i:s\Z', $clock());
        $state['additional_prices'][$digest] = ['status' => 'complete', 'evidence' => $evidence];
        if (!anytour_anex_search3_current($state, $clock())) return $reply;
        return array_replace($reply, ['status' => 'additional_prices',
            'additional_prices' => anytour_anex_search3_additional_application($evidence, $offer)]);
    }
    $lastClient = null;
    $trackedFactory = static function () use ($clientFactory, &$lastClient) {
        return $lastClient = $clientFactory();
    };
    $gateway = new AnyTourAnexPreviewGateway(
        $trackedFactory, $resolver, [], $clock, $enforceGatewayRateLimit
    );
    if ($request['action'] === 'flights') {
        if (($offer['kind'] ?? null) !== 'concrete') return array_replace($reply, ['status' => 'not_concrete']);
        $attempt = $state['flights'][$key] ?? null;
        if ($attempt !== null && ($attempt['status'] ?? null) !== 'complete') {
            return array_replace($reply, ['status' => 'flights_unknown']);
        }
        if ($attempt === null) {
            if ($checkpoint === null) throw new RuntimeException('ANEX_RESERVATION_REQUIRED');
            $state['flights'][$key] = ['status' => 'unknown'];
            $checkpoint($state);
            if (!anytour_anex_search3_current($state, $clock())) return $reply;
            $flights = $gateway->handle(['action' => 'flights', 'offer_key' => $key], $state['gateway']);
            unset($flights['offer_key']);
            $state['flights'][$key] = ['status' => 'complete', 'result' => $flights];
            $attempt = $state['flights'][$key];
        }
        if (!anytour_anex_search3_current($state, $clock())) return $reply;
        // Inventory is not a priced selection or package recalculation. APD is
        // deliberately not a prerequisite for this independent read method.
        return array_replace($reply, ['status' => 'flights', 'flights' => $attempt['result']]);
    }
    if ($request['action'] === 'offer') {
        $result = $gateway->handle(['action' => 'offer', 'search_ref' => $request['search_ref'],
            'offer_key' => $key, 'local_hotel_id' => $local], $state['gateway']);
        unset($result['offer_key']);
        if (($result['status'] ?? null) !== 'current' || ($offer['kind'] ?? null) !== 'concrete') {
            return array_replace($reply, $result);
        }
        $application = null;
        $readyAmount = null;
        $tour = $savedEntry['supplier_tour_program_id'] ?? null;
        $currency = $savedEntry['supplier_currency_id'] ?? null;
        $checkin = $offer['checkin'] ?? null;
        $nights = $offer['nights'] ?? null;
        if (is_string($tour) && preg_match('/\A[1-9][0-9]{0,17}\z/D', $tour)
            && is_string($currency) && preg_match('/\A[1-9][0-9]{0,17}\z/D', $currency)
            && is_string($checkin) && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $checkin)
            && is_int($nights) && $nights >= 1 && $nights <= 60) {
            $digest = hash('sha256', implode("\0", [$tour, $currency, $checkin, (string) $nights]));
            $attempt = $state['additional_prices'][$digest] ?? null;
            if (is_array($attempt) && ($attempt['status'] ?? null) === 'complete' && is_array($attempt['evidence'] ?? null)) {
                $application = anytour_anex_search3_additional_application($attempt['evidence'], $offer);
                if (($application['application_state'] ?? null) === 'applied'
                    && is_array($application['search_plus_additional'] ?? null)
                    && ($application['search_plus_additional']['currency'] ?? null) === 'RUB'
                    && is_string($application['search_plus_additional']['amount'] ?? null)
                    && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $application['search_plus_additional']['amount'])
                    && preg_match('/[1-9]/', $application['search_plus_additional']['amount'])) {
                    $readyAmount = $application['search_plus_additional']['amount'];
                }
            }
        }
        return array_replace($reply, $result, [
            'finalPriceReady' => $readyAmount !== null,
            'finalPrice' => $readyAmount,
            'price' => $readyAmount,
            'additional_prices' => $application,
        ]);
    }
    if ($offer['kind'] !== 'group_minimum') return array_replace($reply, ['status' => 'not_grouped']);
    $attempt = $state['expansions'][$key] ?? null;
    if ($attempt !== null && ($attempt['status'] ?? null) !== 'complete') {
        $failure = anytour_anex_search3_retained_expand_failure($attempt);
        return array_replace($reply, ['status' => 'expansion_unknown', 'retryable' => false], $failure === [] ? [] : ['diagnostics' => $failure]);
    }
    $newExpansion = $attempt === null;
    if ($newExpansion) {
        if ($checkpoint === null) throw new RuntimeException('ANEX_RESERVATION_REQUIRED');
        $state['expansions'][$key] = ['status' => 'unknown'];
        $checkpoint($state);
        if (!anytour_anex_search3_current($state, $clock())) return $reply;
    }
    $phase = 'gateway';
    try {
        if ($newExpansion) {
            $expanded = $gateway->handle(['action' => 'expand', 'offer_key' => $key], $state['gateway']);
            $state['expansions'][$key] = ['status' => 'complete', 'keys' => array_column($expanded['offers'], 'offer_key'),
                'external_search_pending' => $expanded['external_search_pending']];
            $attempt = $state['expansions'][$key];
        }
        $phase = 'projection';
        $result = $reply;
        $offers = [];
        $known = array_column($state['gateway']['search']['offers'] ?? [], null, 'offer_key');
        if (anytour_anex_search3_current($state, $clock())) {
            foreach ($attempt['keys'] as $expandedKey) {
                $entry = $state['gateway']['saved_offers']['offers'][$expandedKey]['offer'] ?? null;
                if (!is_array($entry) || !isset($known[$expandedKey])) {
                    $result['status'] = 'not_loaded';
                    break;
                }
                if ($entry['kind'] !== 'concrete' || $entry['hotel']['external_id'] !== $offer['hotel']['external_id']
                    || $entry['hotel']['local_id'] !== $local) throw new InvalidArgumentException('ANEX_INVALID_SESSION');
                $offers[] = $entry;
            }
            if ($result['status'] !== 'not_loaded') {
                $result = array_replace($reply, ['status' => 'expanded', 'hotels' => anytour_anex_search3_project(
                    $offers, $metadata, $state['params'], $request['search_ref']),
                    'external_search_pending' => $attempt['external_search_pending'], 'first_page_only' => true]);
            }
        }
    } catch (Throwable $error) {
        // Keep the existing no-replay reservation. Never retain exception text,
        // client objects, request URLs or supplier response bodies in the session.
        if ($newExpansion && isset($state['expansions'][$key])) {
            $last = $lastClient instanceof AnyTourAnexClient ? $lastClient->lastRequestDiagnostics() : [];
            $state['expansions'][$key]['status'] = 'unknown';
            $state['expansions'][$key]['failure'] = anytour_anex_search3_continue_failure($error, $last, 'expand');
            $state['expansions'][$key]['failure']['X-AnyTour-Anex-Phase'] = $phase;
            $checkpoint($state);
        }
        throw $error;
    }
    if ($newExpansion && $result['status'] !== 'expanded') {
        $last = $lastClient instanceof AnyTourAnexClient ? $lastClient->lastRequestDiagnostics() : [];
        $code = $result['status'] === 'expired' ? 'ANEX_SESSION_EXPIRED' : 'ANEX_EXPANSION_NOT_LOADED';
        $state['expansions'][$key]['status'] = 'unknown';
        $failure = anytour_anex_search3_continue_failure(new RuntimeException($code), $last, 'expand');
        $failure['X-AnyTour-Anex-Phase'] = $phase;
        $state['expansions'][$key]['failure'] = $failure;
        $checkpoint($state);
        $result['diagnostics'] = $failure;
        $result['retryable'] = false;
    }
    return $result;
}

function anytour_anex_search3_checkpoint(array &$state): void
{
    $expected = $state;
    $ref = $state['gateway']['saved_offers']['search_ref'] ?? null;
    $retained = is_string($ref) && isset($_SESSION['anex_offer_contexts'][$ref]);
    if ($retained) $_SESSION['anex_offer_contexts'][$ref] = $expected;
    if (!session_write_close() || !session_start()) throw new RuntimeException('ANEX_SESSION_UNAVAILABLE');
    if (($_SESSION['offer_context'] ?? null) !== $expected
        || ($retained && (($_SESSION['anex_offer_contexts'][$ref] ?? null) !== $expected
            || ($_SESSION['anex_context_generation'] ?? null) !== $expected['generation']))) {
        throw new InvalidArgumentException('ANEX_SESSION_CHANGED');
    }
    $_SESSION['offer_context'] =& $state;
    if ($retained) $_SESSION['anex_offer_contexts'][$ref] =& $state;
}

function anytour_anex_search3_out(array $data, int $status): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function anytour_anex_search3_retryable_initial_price_502(Throwable $error, array $last): bool
{
    return $error->getMessage() === 'ANEX_HTTP_ERROR'
        && ($last['action'] ?? null) === 'SearchTour_PRICES'
        && ($last['http_status'] ?? null) === 502
        && (int) ($last['curl_errno'] ?? 0) === 0;
}

/** Fixed failure codes only: no exception text, URLs, credentials or offer references. */
function anytour_anex_search3_continue_failure(Throwable $error, array $last, string $stage = 'continue'): array
{
    $allowed = ['ANEX_HTTP_ERROR', 'ANEX_TRANSPORT_ERROR', 'ANEX_SUPPLIER_ERROR',
        'ANEX_INVALID_RESPONSE', 'ANEX_RESPONSE_TOO_LARGE', 'ANEX_INVALID_PRICES',
        'ANEX_REPEATED_PRICE_PAGE', 'ANEX_INVALID_PUBLIC_RESULT', 'ANEX_REQUEST_LIMIT',
        'ANEX_SESSION_UNAVAILABLE', 'ANEX_SESSION_EXPIRED', 'ANEX_CLOCK_ERROR',
        'ANEX_RESERVATION_REQUIRED', 'ANEX_RATE_LIMIT', 'ANEX_CLIENT_UNAVAILABLE',
        'ANEX_INVALID_PARAMS', 'ANEX_SESSION_OFFER_LIMIT', 'ANEX_SEARCH_NOT_STARTED', 'ANEX_EXPANSION_NOT_LOADED',
        'ANEX_DATABASE_ERROR', 'ANEX_TYPE_ERROR', 'ANEX_INTERNAL_ERROR'];
    $code = in_array($error->getMessage(), $allowed, true) ? $error->getMessage()
        : ($error instanceof PDOException ? 'ANEX_DATABASE_ERROR'
            : ($error instanceof TypeError ? 'ANEX_TYPE_ERROR' : 'ANEX_INTERNAL_ERROR'));
    $safe = ['X-AnyTour-Anex-Failure' => $code];
    $initialSearch = $stage === 'search';
    $detailed = $initialSearch || $stage === 'expand';
    $nativeAction = $last['action'] ?? null;
    $knownAction = $initialSearch
        ? in_array($nativeAction, ['SearchTour_TOWNFROMS', 'SearchTour_STATES', 'SearchTour_CURRENCIES', 'SearchTour_PRICES'], true)
        : $nativeAction === 'SearchTour_PRICES';
    if ($detailed) {
        $safe['X-AnyTour-Anex-Stage'] = $stage;
        $safe['X-AnyTour-Anex-Category'] = [
            'ANEX_TRANSPORT_ERROR' => 'transport', 'ANEX_HTTP_ERROR' => 'http',
            'ANEX_SUPPLIER_ERROR' => 'supplier', 'ANEX_DATABASE_ERROR' => 'database',
            'ANEX_RATE_LIMIT' => 'limit', 'ANEX_REQUEST_LIMIT' => 'limit',
            'ANEX_INVALID_RESPONSE' => 'response', 'ANEX_RESPONSE_TOO_LARGE' => 'response',
            'ANEX_INVALID_PRICES' => 'response', 'ANEX_REPEATED_PRICE_PAGE' => 'response',
            'ANEX_INVALID_PUBLIC_RESULT' => 'response',
        ][$code] ?? 'application';
        // This is the last attempted native action, never a supplier URL or request payload.
        if ($knownAction) $safe['X-AnyTour-Anex-Native-Action'] = $nativeAction;
    }
    if ($knownAction) {
        $numeric = ['http_status' => ['X-AnyTour-Anex-Upstream-Status', 599],
            'supplier_code' => ['X-AnyTour-Anex-Supplier-Code', 99999],
            'curl_errno' => ['X-AnyTour-Anex-Transport-Code', 999]];
        if ($detailed) $numeric['elapsed_ms'] = ['X-AnyTour-Anex-Elapsed-Ms', 120000];
        foreach ($numeric as $key => [$header, $max]) {
            if (is_int($last[$key] ?? null) && $last[$key] >= 0 && $last[$key] <= $max) {
                $safe[$header] = (string) $last[$key];
            }
        }
    }
    return $safe;
}

/** Re-sanitize our existing attempt when an ordinary POST reads its no-replay status. */
function anytour_anex_search3_retained_expand_failure(array $attempt): array
{
    $failure = $attempt['failure'] ?? null;
    if (!is_array($failure) || ($failure['X-AnyTour-Anex-Stage'] ?? null) !== 'expand') return [];
    $last = ['action' => $failure['X-AnyTour-Anex-Native-Action'] ?? null];
    foreach (['http_status' => 'X-AnyTour-Anex-Upstream-Status', 'supplier_code' => 'X-AnyTour-Anex-Supplier-Code',
        'curl_errno' => 'X-AnyTour-Anex-Transport-Code', 'elapsed_ms' => 'X-AnyTour-Anex-Elapsed-Ms'] as $key => $header) {
        $value = $failure[$header] ?? null;
        if (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,5})\z/D', $value)) $last[$key] = (int) $value;
    }
    $code = is_string($failure['X-AnyTour-Anex-Failure'] ?? null) ? $failure['X-AnyTour-Anex-Failure'] : '';
    $safe = anytour_anex_search3_continue_failure(new RuntimeException($code), $last, 'expand');
    $phase = $failure['X-AnyTour-Anex-Phase'] ?? null;
    if (in_array($phase, ['gateway', 'projection'], true)) $safe['X-AnyTour-Anex-Phase'] = $phase;
    return $safe;
}

/** Retry authority comes from this bound no-replay attempt, never its HTTP code. */
function anytour_anex_search3_expand_failure_reply(array $request, array $session, array $safe): array
{
    $extra = ['diagnostics' => $safe];
    $context = $session['offer_context'] ?? [];
    $ref = $request['offer_ref'] ?? null;
    if (($request['action'] ?? null) !== 'expand' || !is_int($request['generation'] ?? null)
        || ($context['generation'] ?? null) !== $request['generation']
        || !is_string($request['search_ref'] ?? null)
        || ($context['gateway']['saved_offers']['search_ref'] ?? null) !== $request['search_ref']
        || !is_string($ref) || !is_array($context['expansions'][$ref] ?? null)) return $extra;
    $attempt = $context['expansions'][$ref];
    $failure = anytour_anex_search3_retained_expand_failure($attempt);
    if (isset($failure['X-AnyTour-Anex-Phase'])) $extra['diagnostics']['X-AnyTour-Anex-Phase'] = $failure['X-AnyTour-Anex-Phase'];
    // This is no-replay authority, not proof that the richer failure flush succeeded.
    if (($attempt['status'] ?? null) === 'unknown') $extra['retryable'] = false;
    return $extra;
}

/** Aggregate the current retained first page without exposing its private digest. */
function anytour_anex_search3_retained_page_receipt(array $session): ?array
{
    $state = $session['offer_context'] ?? null;
    $snapshot = $state['gateway']['search'] ?? null;
    $saved = $state['gateway']['saved_offers'] ?? null;
    if (!is_array($state) || !is_int($state['generation'] ?? null) || $state['generation'] < 1
        || !is_array($snapshot) || ($snapshot['schema_version'] ?? null) !== 1 || !is_array($saved)
        || !is_string($saved['search_ref'] ?? null)
        || preg_match('/\A[a-f0-9]{32}\z/D', $saved['search_ref']) !== 1
        || !is_array($snapshot['context'] ?? null) || !is_array($saved['search'] ?? null)
        || !is_array($snapshot['offers'] ?? null) || !array_is_list($snapshot['offers'])
        || count($snapshot['offers']) > 300) return null;
    try {
        if (anytour_anex_normalizer_context($snapshot['context']) !== anytour_anex_normalizer_context($saved['search'])) return null;
    } catch (InvalidArgumentException $ignored) { return null; }
    $page = $snapshot['pagination'] ?? null;
    if (!is_array($page) || ($page['pages_read'] ?? null) !== 1
        || !in_array($page['state'] ?? null, ['available', 'exhausted'], true)
        || !is_array($page['page_digests'] ?? null) || !array_is_list($page['page_digests'])
        || count($page['page_digests']) !== 1 || !is_string($page['page_digests'][0])
        || preg_match('/\A[a-f0-9]{64}\z/D', $page['page_digests'][0]) !== 1) return null;
    // AnyTourAnexSearch hashes the raw rows before normalization or hotel filtering.
    $empty = hash_equals(hash('sha256', '[]'), $page['page_digests'][0]);
    $count = count($snapshot['offers']);
    if ($empty && ($count !== 0 || $page['state'] !== 'exhausted')) return null;
    $receipt = ['raw_rows' => $empty ? 'empty' : 'nonempty', 'retained_offers' => $count,
        'interpretation' => $empty ? 'empty_supplier_page'
            : ($count === 0 ? 'rows_rejected_or_filtered' : 'offers_retained')];
    $diagnostics = $state['gateway']['first_page_diagnostics'] ?? null;
    if (is_array($diagnostics) && ($diagnostics['search_ref'] ?? null) === $saved['search_ref']
        && ($diagnostics['page_digest'] ?? null) === $page['page_digests'][0]
        && anytour_anex_search3_valid_normalization_counts($diagnostics['counts'] ?? null, $count, $empty)) {
        $receipt['normalization'] = $diagnostics['counts'];
    }
    return $receipt;
}

/** Fixed field codes and bounded counters only; malformed/legacy facts remain absent. */
function anytour_anex_search3_valid_normalization_counts($counts, int $retained, bool $empty): bool
{
    $keys = ['rows_seen', 'normalized_rows', 'rejected_rows', 'rejections', 'party_counts',
        'hotel_filtered_rows', 'retained_offers'];
    if (!is_array($counts) || count($counts) !== count($keys) || array_diff($keys, array_keys($counts))) return false;
    foreach (['rows_seen', 'normalized_rows', 'rejected_rows', 'hotel_filtered_rows', 'retained_offers'] as $key) {
        if (!is_int($counts[$key]) || $counts[$key] < 0 || $counts[$key] > 300) return false;
    }
    if ($counts['rows_seen'] !== $counts['normalized_rows'] + $counts['rejected_rows']
        || $counts['retained_offers'] !== $retained
        || $counts['hotel_filtered_rows'] + $retained > $counts['normalized_rows']
        || ($counts['rows_seen'] === 0) !== $empty) return false;
    $fields = ['row_type', 'supplier_reference', 'hotel_id', 'hotel_label', 'checkin', 'checkout',
        'nights', 'adult', 'child', 'date_scope', 'night_scope', 'packet_type', 'infant', 'grouped', 'price', 'currency'];
    if (!is_array($counts['rejections']) || count($counts['rejections']) > count($fields)
        || !is_array($counts['party_counts']) || count($counts['party_counts']) > 300) return false;
    $rejected = 0;
    foreach ($counts['rejections'] as $field => $number) {
        if (!in_array($field, $fields, true) || !is_int($number) || $number < 1 || $number > 300) return false;
        $rejected += $number;
    }
    $seen = 0;
    foreach ($counts['party_counts'] as $tuple => $number) {
        if (!is_string($tuple) || preg_match('~\A(?:[1-9]|missing|invalid)/(?:[0-8]|missing|invalid)/(?:[0-8]|missing|invalid)\z~D', $tuple) !== 1
            || !is_int($number) || $number < 1 || $number > 300) return false;
        $seen += $number;
    }
    return $rejected === $counts['rejected_rows'] && $seen === $counts['rows_seen'];
}

/** Read regular own-session aggregates only; never return supplier/session identifiers. */
function anytour_anex_search3_quote_receipt(array $session): array
{
    $attempts = $session['offer_context']['package_quotes'] ?? [];
    $records = [];
    $reasons = ['ANEX_QUOTE_IDENTITY_UNCONFIRMED', 'ANEX_QUOTE_TRANSPORT_UNCONFIRMED',
        'ANEX_QUOTE_PRICE_UNCONFIRMED', 'ANEX_QUOTE_SUPPLIER_REJECTED', 'ANEX_QUOTE_HTTP_ERROR',
        'ANEX_QUOTE_TRANSPORT_ERROR', 'ANEX_QUOTE_INVALID_RESPONSE', 'ANEX_QUOTE_CLIENT_UNAVAILABLE',
        'ANEX_QUOTE_RATE_LIMIT', 'ANEX_QUOTE_UNKNOWN', 'ANEX_QUOTE_EXPIRED'];
    foreach (array_slice(is_array($attempts) ? $attempts : [], 0, 40) as $attempt) {
        if (!is_array($attempt)) continue;
        $status = $attempt['public']['status'] ?? 'quote_unknown';
        $versioned = ($attempt['version'] ?? null) === 2;
        $statuses = ['quote_choices', 'quote_verified', 'quote_failed', 'quote_unknown'];
        if ($versioned) $statuses[] = 'quote_expired';
        if (!in_array($status, $statuses, true)) $status = 'quote_unknown';
        $record = ['status' => $status, 'reason' => null, 'stages' => []];
        if ($versioned) {
            $calls = $attempt['reserved_calls'] ?? null;
            $record['version'] = 2;
            $record['reserved_calls'] = is_int($calls) && $calls >= 0 && $calls <= 8 ? $calls : null;
            $record['sealed'] = !anytour_anex_quote_reprice_ready($attempt);
            if ($record['sealed'] && !in_array($status, ['quote_failed', 'quote_expired'], true)) $record['status'] = 'quote_unknown';
        }
        $reason = $attempt['public']['reason'] ?? null;
        if (in_array($reason, $reasons, true)) $record['reason'] = $reason;
        $stageFacts = [];
        foreach ($versioned ? ['start', 'transports'] : ['start', 'transports', 'SetTransport', 'calcfull'] as $stage) {
            $stageFacts[] = ['stage' => $stage, 'state' => $attempt['stages'][$stage] ?? null,
                'diagnostics' => $attempt['diagnostics'][$stage] ?? []];
        }
        if ($versioned) {
            $pairs = is_array($attempt['pairs'] ?? null) ? array_slice($attempt['pairs'], 0, 3) : [];
            $pairIndex = 0;
            foreach ($pairs as $pair) {
                ++$pairIndex;
                if (!is_array($pair)) continue;
                foreach (['SetTransport', 'calcfull'] as $stage) {
                    $stageFacts[] = ['stage' => $stage, 'state' => $pair['stages'][$stage] ?? null,
                        'diagnostics' => $pair['diagnostics'][$stage] ?? [], 'pair_index' => $pairIndex];
                }
            }
        }
        foreach ($stageFacts as $fact) {
            $stage = $fact['stage'];
            $state = $fact['state'];
            if (!in_array($state, ['unknown', 'complete'], true)) continue;
            $row = ['stage' => $stage, 'state' => $state];
            if (isset($fact['pair_index'])) $row['pair_index'] = $fact['pair_index'];
            $diagnostics = is_array($fact['diagnostics']) ? $fact['diagnostics'] : [];
            foreach (['http_status' => 599, 'response_bytes' => 2097152,
                'elapsed_ms' => 120000, 'curl_errno' => 999] as $field => $max) {
                if (in_array($field, ['elapsed_ms', 'curl_errno'], true)
                    && ($diagnostics['action'] ?? null) !== $stage) continue;
                $value = $diagnostics[$field] ?? null;
                if (is_int($value) && $value >= 0 && $value <= $max) $row[$field] = $value;
            }
            $record['stages'][] = $row;
        }
        $records[] = $record;
    }
    $receipt = ['retained_only' => true, 'supplier_calls' => 0, 'records' => $records];
    $page = anytour_anex_search3_retained_page_receipt($session);
    if ($page !== null) $receipt['retained_first_page'] = $page;
    return $receipt;
}

/** Best-effort evidence from a fresh completed TV request, after releasing the session lock. */
function anytour_anex_search3_tv_passive(array $request, array $reply, array $session, string $directory, int $now): void
{
    try {
        if (!class_exists('AnyTourTourvisorOperatorFuelPassiveIntakeV1', false)
            || !method_exists('AnyTourTourvisorOperatorFuelPassiveIntakeV1', 'captureSessionTour')) return;
        $action = $request['action'] ?? null;
        if (($request['provider'] ?? null) !== 'tourvisor' || !in_array($action, ['tv_tour','tv_flights'], true)) return;
        $context = $session['tourvisor_context'] ?? null;
        $record = $session['tourvisor_requests'][$request['request_id'] ?? ''] ?? null;
        $ref = $request['offer_ref'] ?? null;
        if (!is_array($context) || !is_string($ref)
            || ($context['state'] ?? null) !== 'ready'
            || ($session['tourvisor_context_generation'] ?? null) !== ($context['generation'] ?? null)
            || ($request['generation'] ?? null) !== $context['generation']
            || ($request['search_ref'] ?? null) !== ($context['search_ref'] ?? null)
            || ($reply['provider'] ?? null) !== 'tourvisor'
            || ($reply['generation'] ?? null) !== $context['generation']
            || ($reply['search_ref'] ?? null) !== $context['search_ref']
            || ($record['state'] ?? null) !== 'ready' || ($record['reply'] ?? null) !== $reply
            || !is_int($context['expires_at'] ?? null) || $now >= $context['expires_at']
            || !is_int($record['expires_at'] ?? null) || $now >= $record['expires_at']) return;
        $offer = $context['offers'][$ref] ?? null; $checked = $offer['checked'] ?? null;
        if (!is_array($offer) || !is_array($checked) || !empty($offer['expired'])
            || ($offer['operations'][$action] ?? null) !== $request['request_id']
            || $ref !== 'tourvisor:'.hash('sha256','tourvisor:tour:'.($offer['id'] ?? ''))
            || !is_int($checked['observed_at'] ?? null) || $now < $checked['observed_at']
            || !is_int($checked['expires_at'] ?? null) || $now >= $checked['expires_at']
            || $checked['expires_at'] > $context['expires_at'] || !is_array($checked['tour'] ?? null)) return;
        $at = (new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('UTC'));
        $params = $context['params'];
        $binding = [
            'tour_id'=>$offer['id'],'hotel_id'=>$offer['hotel']['uiId'],'operators'=>$offer['operators'],
            'checkin'=>substr($offer['date'],0,10),
            'party'=>['adults'=>$params['adults'],'children'=>count($params['childs']),'child_ages'=>$params['childs']],
            'observed_at'=>$checked['observed_at'],'expires_at'=>$checked['expires_at'],
            'tour_response_sha256'=>AnyTourOperatorFuelRuleEvidenceV1::hash($checked['tour']),
        ];
        if ($action === 'tv_tour') {
            if (($reply['data'] ?? null) !== ($checked['tour'] ?? null)) return;
            AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureSessionTour($offer['id'], $checked['tour'], $binding, $directory, $at);
        } else {
            AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($offer['id'], $reply['data'], $directory, $at, null, $binding);
        }
    } catch (Throwable $ignored) { /* Evidence never replaces a successful supplier response. */ }
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
    $anexEnabled = $enabled;
    $tvEnabled = getenv('ANYTOUR_SEARCH3_TV_GATEWAY_ENABLED') === '1' || (defined('ANYTOUR_SEARCH3_TV_GATEWAY_ENABLED')
        && in_array(ANYTOUR_SEARCH3_TV_GATEWAY_ENABLED, [true, 1, '1'], true));
    if (!$anexEnabled && !$tvEnabled) anytour_anex_search3_out(['ok' => false, 'error' => 'not_found'], 404);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && $_GET === ['action'=>'tv_token']) {
        if (!$tvEnabled) anytour_anex_search3_out(['ok'=>false,'error'=>'not_found'],404);
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (!in_array($host,['anytoour.ru','www.anytoour.ru'],true)
            || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? null) !== 'same-origin'
            || (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== 'https://'.$host)) {
            anytour_anex_search3_out(['ok'=>false,'error'=>'forbidden'],403);
        }
        ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
        session_name('ANYTOUR_ANEX_SEARCH3');
        session_set_cookie_params(['lifetime'=>0,'path'=>'/_preview/search3-anex-candidate/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
        if (!session_start()) anytour_anex_search3_out(['ok'=>false,'error'=>'temporarily_unavailable'],503);
        if (!is_string($_SESSION['search3_handoff_csrf'] ?? null)) $_SESSION['search3_handoff_csrf'] = bin2hex(random_bytes(32));
        $csrf = $_SESSION['search3_handoff_csrf'];
        if (!session_write_close()) anytour_anex_search3_out(['ok'=>false,'error'=>'temporarily_unavailable'],503);
        header('Cache-Control: private, no-store, max-age=0'); header('X-Robots-Tag: noindex, nofollow');
        anytour_anex_search3_out(['ok'=>true,'csrf'=>$csrf,'provider'=>'tourvisor'],200);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
        && ($_GET === ['action' => 'quote_receipt'] || $_GET === ['action' => 'handoff_token'])) {
        if (!in_array(strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin')), ['same-origin', 'none'], true)
            || !is_string($_COOKIE['ANYTOUR_ANEX_SEARCH3'] ?? null) || $_COOKIE['ANYTOUR_ANEX_SEARCH3'] === '') {
            anytour_anex_search3_out(['ok' => false, 'error' => 'forbidden'], 403);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('ANYTOUR_ANEX_SEARCH3');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/_preview/search3-anex-candidate/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
        $handoffToken = $_GET['action'] === 'handoff_token';
        if (!session_start(['read_and_close' => !$handoffToken])) anytour_anex_search3_out(['ok' => false, 'error' => 'temporarily_unavailable'], 503);
        if ($handoffToken) {
            header('Cache-Control: private, no-store, max-age=0'); header('X-Robots-Tag: noindex, nofollow');
            if (!is_string($_SESSION['search3_handoff_csrf'] ?? null)) $_SESSION['search3_handoff_csrf'] = bin2hex(random_bytes(32));
            $token = $_SESSION['search3_handoff_csrf'];
            if (!session_write_close()) anytour_anex_search3_out(['ok' => false, 'error' => 'temporarily_unavailable'], 503);
            anytour_anex_search3_out(['ok' => true, 'csrf' => $token], 200);
        }
        anytour_anex_search3_out(['ok' => true, 'data' => anytour_anex_search3_quote_receipt($_SESSION)], 200);
    }
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
        if (is_string($action) && str_starts_with($action,'tv_')) {
            header('Cache-Control: private, no-store, max-age=0'); header('X-Robots-Tag: noindex, nofollow');
            if (!$tvEnabled) { session_write_close(); anytour_anex_search3_out(['ok'=>false,'error'=>'not_found'],404); }
            if (!AnyTourSearch3MessengerHandoff::authorize($_SERVER,$request,$_SESSION)) {
                session_write_close(); anytour_anex_search3_out(['ok'=>false,'error'=>'forbidden'],403);
            }
            $app = is_file(__DIR__.'/app/integrations/tourvisor-preview-gateway.php') ? __DIR__.'/app/integrations' : __DIR__.'/../app/integrations';
            require_once $app.'/tourvisor-preview-gateway.php';
            $runtime = static function (): string {
                $root = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
                if ($root === false || basename($root) !== 'anytoour.ru') throw new RuntimeException('tv_runtime_unavailable');
                return $root;
            };
            $catalog = static function (int $id, int $country) use ($runtime): ?array {
                $root = $runtime();
                require_once is_file($root.'/data/db-v1.php') ? $root.'/data/db-v1.php' : $root.'/v2/data/db-v1.php';
                $pdo = v2_data_db();
                $q = $pdo->prepare('SELECT id,name,country_id,region_name,subregion_name FROM catalog_hotels WHERE id=? AND country_id=? AND is_active=1 LIMIT 1');
                $q->execute([$id,$country]); $row = $q->fetch(PDO::FETCH_ASSOC);
                return !$row ? null : ['uiId'=>(int)$row['id'],'namespace'=>'search3-ui','name'=>(string)$row['name'],
                    'resort'=>(string)($row['subregion_name'] ?: $row['region_name'] ?: '')];
            };
            $passiveDirectory = null;
            $transport = static function (string $path, array $params, array &$state) use ($runtime, &$passiveDirectory): array {
                // UNKNOWN was durably reserved by the owner. The network must not hold the shared provider lock.
                $_SESSION = $state;
                if (!session_write_close()) throw new RuntimeException('tv_session_unavailable');
                try {
                    $root = $runtime();
                    if (is_file($root.'/config.php') && !is_link($root.'/config.php')) require_once $root.'/config.php';
                    $token = trim((string)getenv('TOURVISOR_JWT'));
                    if ($token === '' && defined('TOURVISOR_JWT')) $token = trim((string)TOURVISOR_JWT);
                    $response = \AnyTour\TourvisorInteractiveClient::get($path,$params,$token);
                    // Existing passive owners only; no new evidence store or monetary rule.
                    try {
                        require_once is_file($root.'/data/db-v1.php') ? $root.'/data/db-v1.php' : $root.'/v2/data/db-v1.php';
                        foreach (['tourvisor-anytour-offer-autosave.php','tourvisor-operator-fuel-passive-intake.php'] as $file) {
                            $candidate = $root.'/app/integrations/'.$file;
                            if (is_file($candidate) && !is_link($candidate)) require_once $candidate;
                        }
                        $at = new DateTimeImmutable('now',new DateTimeZone('UTC'));
                        if (class_exists('AnyTourTourvisorOfferAutosaveV1',false)) {
                            if ($path === '/tours/search') AnyTourTourvisorOfferAutosaveV1::captureSearchStart($params,$response,$at);
                            elseif (preg_match('~^/tours/search/([1-9][0-9]*)/status$~D',$path,$m)) AnyTourTourvisorOfferAutosaveV1::captureSearchStatus((int)$m[1],$response,$at);
                            elseif (preg_match('~^/tours/search/([1-9][0-9]*)$~D',$path,$m)) AnyTourTourvisorOfferAutosaveV1::autosaveSearchResults((int)$m[1],1000,$response,$at);
                        }
                        $private = $root.'/.andromeda-private.php';
                        if (class_exists('AnyTourTourvisorOperatorFuelPassiveIntakeV1',false) && is_file($private) && !is_link($private)) {
                            $cfg = require $private; $catalogPath = is_array($cfg) ? ($cfg['catalog_path'] ?? null) : null;
                            $directory = is_string($catalogPath) ? dirname($catalogPath).'/searches' : '';
                            if (is_dir($directory) && !is_link($directory) && preg_match('~^/tours/([^/]+)(/flights)?$~D',$path,$m)) {
                                $passiveDirectory = $directory;
                            }
                        }
                    } catch (Throwable $ignored) { /* Passive intake never replaces supplier JSON. */ }
                    return $response;
                } finally {
                    if (!session_start()) throw new RuntimeException('tv_session_unavailable');
                    $state = $_SESSION;
                }
            };
            $data = AnyTourTourvisorPreviewGateway::handle($request,$_SESSION,time(),$transport,$catalog,
                [AnyTourSearch3MessengerHandoff::class,'checkpoint']);
            $passiveSession = $passiveDirectory === null ? [] : $_SESSION;
            if (!session_write_close()) anytour_anex_search3_out(['ok'=>false,'error'=>'temporarily_unavailable'],503);
            // A cached reply or tv_select never enters transport and cannot refresh evidence.
            if ($passiveDirectory !== null) anytour_anex_search3_tv_passive($request,$data,$passiveSession,$passiveDirectory,time());
            anytour_anex_search3_out(['ok'=>true,'data'=>$data],200);
        }
        if (is_string($action) && str_starts_with($action, 'handoff_')) {
            header('Cache-Control: private, no-store, max-age=0'); header('X-Robots-Tag: noindex, nofollow');
            if (!AnyTourSearch3MessengerHandoff::authorize($_SERVER, $request, $_SESSION)) {
                session_write_close(); anytour_anex_search3_out(['ok' => false, 'error' => 'forbidden'], 403);
            }
            if (!in_array($action, ['handoff_freeze', 'handoff_create', 'handoff_status', 'handoff_revoke'], true)) {
                throw new InvalidArgumentException('invalid_request');
            }
            if ($action === 'handoff_freeze') {
                $data = AnyTourSearch3MessengerHandoff::freeze($request, $_SESSION, time(), [AnyTourSearch3MessengerHandoff::class, 'checkpoint']);
            } else {
                if (!is_string($request['operation_id'] ?? null) || !preg_match('/\A[a-f0-9]{32}\z/D', $request['operation_id'])) {
                    throw new InvalidArgumentException('invalid_request');
                }
                $record = $_SESSION['search3_messenger_handoffs'][$request['operation_id'] ?? ''] ?? null;
                $localOnly = is_array($record) && $record['state'] === 'prepared'
                    && in_array($action, ['handoff_status', 'handoff_revoke'], true);
                $signer = $localOnly ? [] : AnyTourSearch3MessengerHandoff::signer(
                    defined('ANYTOUR_SEARCH3_HANDOFF_KEY_FILE') ? ANYTOUR_SEARCH3_HANDOFF_KEY_FILE : null,
                    (string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
                $data = AnyTourSearch3MessengerHandoff::transact($request, $_SESSION, time(), $signer,
                    [AnyTourSearch3MessengerHandoff::class, 'checkpoint'],
                    [AnyTourSearch3MessengerHandoff::class, 'httpTransport']);
            }
            if (!session_write_close()) anytour_anex_search3_out(['ok' => false, 'error' => 'temporarily_unavailable'], 503);
            anytour_anex_search3_out(['ok' => true, 'data' => $data], 200);
        }
        if (!$anexEnabled) { session_write_close(); anytour_anex_search3_out(['ok'=>false,'error'=>'not_found'],404); }
        if (!in_array($action, ['search', 'continue', 'offer', 'expand', 'additional_prices', 'additional_prices_batch', 'flights', 'quote_start', 'quote_calculate'], true)) throw new InvalidArgumentException('ANEX_INVALID_ACTION');
        anytour_anex_search3_select_context($request, $_SESSION, time());
        if (!is_array($_SESSION['dictionaries'] ?? null)) $_SESSION['dictionaries'] = [];
        $app = is_file(__DIR__ . '/app/integrations/anex-search.php') ? __DIR__ . '/app/integrations' : __DIR__ . '/../app/integrations';
        require_once $app . '/anex-initial-week-gate.php';
        require_once $app . '/anex-preview-gateway.php';
        require_once $app . '/anex-search-mapping-registry.php';
        require_once $app . '/anex-search-observations.php';
        require_once $app . '/anex-additional-prices-client.php';
        $root = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($root === false || basename($root) !== 'anytoour.ru') throw new RuntimeException('ANEX_DATABASE_UNAVAILABLE');
        $helper = is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php';
        require_once $helper;
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
        $quoteFactory = static function () use ($reserveSupplierRequest): AnyTourAnexPackageQuoteClient {
            $reserveSupplierRequest();
            $token = trim((string) getenv('ANEX_B2B_TOKEN'));
            if ($token === '' && defined('ANEX_B2B_TOKEN')) $token = trim((string) ANEX_B2B_TOKEN);
            if ($token === '') throw new RuntimeException('ANEX_QUOTE_CLIENT_UNAVAILABLE');
            return new AnyTourAnexPackageQuoteClient($token);
        };
        $pdo = v2_data_db();
        if ($action === 'search') {
            if (!is_array($_SESSION['anex_initial_week_gate'] ?? null)) $_SESSION['anex_initial_week_gate'] = [];
            $initialWeekGate = anytour_anex_initial_week_gate($_SESSION['anex_initial_week_gate'], $request);
            if (!$initialWeekGate['allowed']) {
                session_write_close();
                anytour_anex_search3_out(['ok' => true, 'data' => anytour_anex_initial_week_skipped_response($initialWeekGate)], 200);
            }
            $diagnostics = null;
            $observer = static function (array $offers, array $context) use ($pdo): array {
                return AnyTourAnexSearchObservations::record($pdo, $offers, $context);
            };
            $operatorScope = anytour_anex_search3_operator_scope($pdo, $request['params']['operatorIds'] ?? []);
            try {
                $data = anytour_anex_search3_run($request, $pdo, $operatorScope === 'exclude' ? null : $clientFactory(),
                    $_SESSION['dictionaries'], $diagnostics, $observer, $_SESSION['offer_context'], $operatorScope);
            } catch (RuntimeException $error) {
                $last = isset($client) ? $client->lastRequestDiagnostics() : [];
                if ($operatorScope === 'exclude' || !anytour_anex_search3_retryable_initial_price_502($error, $last)) throw $error;
                $_SESSION['offer_context'] = [];
                $data = anytour_anex_search3_run($request, $pdo, $clientFactory(),
                    $_SESSION['dictionaries'], $diagnostics, $observer, $_SESSION['offer_context'], $operatorScope);
            }
        } elseif ($action === 'continue') {
            $data = anytour_anex_search3_continue($request, $_SESSION['offer_context'],
                AnyTourAnexSearchMappingRegistry::fromPdo($pdo)->previewResolver(), $clientFactory,
                static function (array $offers) use ($pdo): array { return anytour_anex_search3_metadata($pdo, $offers); },
                null, 'anytour_anex_search3_checkpoint');
        } elseif ($action === 'additional_prices_batch') {
            $data = anytour_anex_search3_additional_batch($request, $_SESSION['offer_context'],
                AnyTourAnexSearchMappingRegistry::fromPdo($pdo)->previewResolver(),
                static function (array $offers) use ($pdo): array { return anytour_anex_search3_metadata($pdo, $offers); },
                null, 'anytour_anex_search3_checkpoint', $additionalFactory);
        } else {
            $data = anytour_anex_search3_followup($request, $_SESSION['offer_context'],
                AnyTourAnexSearchMappingRegistry::fromPdo($pdo)->previewResolver(), $clientFactory,
                static function (array $offers) use ($pdo): array { return anytour_anex_search3_metadata($pdo, $offers); },
                null, 'anytour_anex_search3_checkpoint', $additionalFactory, true, $quoteFactory);
        }
        anytour_anex_search3_retain_context($_SESSION, time());
        if (in_array($action, ['search', 'expand'], true)) {
            anytour_anex_program_observation_runtime($pdo, $_SESSION['offer_context']);
        }
        session_write_close();
        anytour_anex_search3_out(['ok' => true, 'data' => $data], 200);
    } catch (Throwable $error) {
        if (is_string($action ?? null) && str_starts_with($action,'tv_')) {
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            if ($error instanceof \AnyTour\TourvisorInteractiveFailure) anytour_anex_search3_out($error->response,$error->httpStatus);
            $code = $error instanceof InvalidArgumentException ? $error->getMessage() : 'tv_temporarily_unavailable';
            anytour_anex_search3_out(['ok'=>false,'error'=>$code],$code === 'tv_invalid_request' ? 400 : ($error instanceof InvalidArgumentException ? 409 : 503));
        }
        if (is_string($action ?? null) && str_starts_with($action, 'handoff_')) {
            session_write_close();
            if ($error instanceof AnyTourSearch3HandoffMissing) {
                anytour_anex_search3_out(['ok' => false, 'error' => 'retained_fact_missing', 'field' => $error->field, 'owner' => $error->owner], 422);
            }
            $code = $error instanceof InvalidArgumentException ? 'invalid_request'
                : (in_array($error->getMessage(), ['handoff_limit', 'handoff_missing', 'handoff_stale', 'signer_unavailable', 'handoff_session_changed'], true)
                    ? $error->getMessage() : 'handoff_unavailable');
            anytour_anex_search3_out(['ok' => false, 'error' => $code], match ($code) {
                'invalid_request' => 400, 'handoff_limit' => 429, 'handoff_missing' => 404, 'handoff_stale', 'handoff_session_changed' => 409, default => 503,
            });
        }
        $last = isset($additionalClient) ? $additionalClient->lastRequestDiagnostics()
            : (isset($client) ? $client->lastRequestDiagnostics() : []);
        $extra = [];
        if (in_array($action ?? null, ['search', 'continue', 'expand'], true)
            && (!($error instanceof InvalidArgumentException) || $action === 'expand')) {
            $safe = anytour_anex_search3_continue_failure($error, $last, $action);
            if ($action === 'expand') {
                $extra = anytour_anex_search3_expand_failure_reply($request, $_SESSION, $safe);
                $safe = $extra['diagnostics'];
            }
            foreach ($safe as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        session_write_close();
        if ($error instanceof InvalidArgumentException) {
            $unsupported = in_array($error->getMessage(), ['ANEX_FILTER_UNSUPPORTED', 'ANEX_DESTINATION_UNSUPPORTED'], true);
            anytour_anex_search3_out(['ok' => false, 'error' => $unsupported ? 'search_not_supported' : 'invalid_request'] + $extra, $unsupported ? 422 : 400);
        }
        if ($error->getMessage() === 'ANEX_RATE_LIMIT' || ($last['http_status'] ?? null) === 429) {
            header('Retry-After: 60');
            anytour_anex_search3_out(['ok' => false, 'error' => 'rate_limited'] + $extra, 429);
        }
        if (in_array($error->getMessage(), ['ANEX_CLIENT_UNAVAILABLE', 'ANEX_ADDITIONAL_CLIENT_UNAVAILABLE'], true)) {
            anytour_anex_search3_out(['ok' => false, 'error' => 'temporarily_unavailable'] + $extra, 503);
        }
        $code = ($last['supplier_code'] ?? null) === 101 ? 'supplier_conditions_rejected'
            : (($last['curl_errno'] ?? null) === 28 ? 'supplier_timeout' : 'supplier_unavailable');
        anytour_anex_search3_out(['ok' => false, 'error' => $code] + $extra, $code === 'supplier_conditions_rejected' ? 422 : 502);
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) anytour_anex_search3_http();

