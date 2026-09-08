<?php
declare(strict_types=1);

/** Initial Search3 supplier page only. No booking or Tourvisor transport. */
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

/** Projection is deliberately separate from both suppliers' booking IDs. */
function anytour_anex_search3_project(array $offers, array $metadata, array $params): array
{
    $hotels = [];
    foreach (array_slice($offers, 0, 300) as $offer) {
        $id = $offer['hotel']['local_id'] ?? null;
        $row = is_int($id) && $id > 0 ? ($metadata[$id] ?? null) : null;
        if (!$row || ($offer['hotel']['mapping_status'] ?? '') !== 'resolved'
            || (int) $row['country_id'] !== (int) $params['countryId']) continue;
        $fits = true;
        foreach (['hotelIds' => 'id', 'regionIds' => 'region_id', 'subregionIds' => 'subregion_id'] as $filter => $field) {
            if (!empty($params[$filter]) && !in_array((string) ($row[$field] ?? ''), array_map('strval', $params[$filter]), true)) $fits = false;
        }
        $rating = ['2' => 3.0, '3' => 3.5, '4' => 4.0, '5' => 4.5][(string) ($params['hotelRating'] ?? '')] ?? 0;
        if (!$fits || (float) ($row['category'] ?? 0) < (float) ($params['hotelCategory'] ?? 0)
            || (float) ($row['rating'] ?? 0) < $rating) continue;
        if (!empty($params['meal']) && !in_array(anytour_anex_search3_name((string) ($offer['meal'] ?? '')),
            ['ai', 'all', 'all inclusive', 'uai', 'ultra all inclusive', 'все включено', 'ультра все включено'], true)) continue;
        $price = ($offer['price']['currency'] ?? '') === 'RUB' ? $offer['price'] : ($offer['converted_price'] ?? null);
        if (!$price || $price['currency'] !== 'RUB' || !anytour_anex_normalizer_decimal($price['amount'] ?? null)) continue;
        if ((!empty($params['priceFrom']) && (float) $price['amount'] < (float) $params['priceFrom'])
            || (!empty($params['priceTo']) && (float) $price['amount'] > (float) $params['priceTo'])) continue;
        if (!isset($hotels[$id])) {
            $hotels[$id] = ['local_id' => $id, 'name' => (string) $row['name'], 'category' => (int) ($row['category'] ?? 0),
                'country' => (string) $row['country_name'], 'region' => (string) ($row['region_name'] ?? ''), 'tours' => []];
        }
        $hotels[$id]['tours'][] = ['price' => $price, 'checkin' => $offer['checkin'], 'nights' => $offer['nights'],
            'adults' => $offer['adults'], 'children' => $offer['children'], 'meal' => $offer['meal'], 'room' => $offer['room'],
            'kind' => $offer['kind'], 'final_price_verified' => false];
    }
    foreach ($hotels as &$hotel) {
        usort($hotel['tours'], static function ($a, $b) { return (float) $a['price']['amount'] <=> (float) $b['price']['amount']; });
        $hotel['tours'] = array_slice($hotel['tours'], 0, 5);
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

/** Exactly one price operation; errors never become empty success or recursive requests. */
function anytour_anex_search3_prices($client, callable $resolver, array $criteria): array
{
    return (new AnyTourAnexSearch($client, $resolver))->search(anytour_anex_search3_week($criteria));
}

function anytour_anex_search3_run(array $request, PDO $pdo, $client, array &$cache, ?array &$diagnostics = null, ?callable $observer = null): array
{
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
    $result = anytour_anex_search3_prices($client, $resolver, $criteria);
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
    $ids = [];
    foreach ($result['offers'] as $offer) if (is_int($offer['hotel']['local_id']) && $offer['hotel']['local_id'] > 0) $ids[$offer['hotel']['local_id']] = true;
    $metadata = [];
    if ($ids) {
        $hydrate = $pdo->prepare('SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,category,rating'
            . ' FROM catalog_hotels WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') AND is_active=1 LIMIT 300');
        $hydrate->execute(array_keys($ids));
        foreach ($hydrate->fetchAll(PDO::FETCH_ASSOC) as $row) $metadata[(int) $row['id']] = $row;
    }
    $projected = anytour_anex_search3_project($result['offers'], $metadata, $params);
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
    return ['generation' => $request['generation'], 'provider' => 'anex',
        'date_range' => ['from' => $criteria['checkin_begin'], 'to' => $criteria['checkin_end']], 'hotels' => $projected,
        'external_search_pending' => $result['external_search_pending'], 'first_page_only' => true];
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
    $token = trim((string) getenv('ANEX_API_TOKEN'));
    if ($token === '' && defined('ANEX_API_TOKEN')) $token = trim((string) ANEX_API_TOKEN);
    if ($token === '') anytour_anex_search3_out(['ok' => false, 'error' => 'temporarily_unavailable'], 503);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('ANYTOUR_ANEX_SEARCH3');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/_preview/search3-anex-candidate/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    if (!session_start()) anytour_anex_search3_out(['ok' => false, 'error' => 'temporarily_unavailable'], 503);
    try {
        $recent = array_filter($_SESSION['requests'] ?? [], static function ($at) { return is_int($at) && $at > time() - 60; });
        if (count($recent) >= 6) {
            session_write_close();
            header('Retry-After: 60');
            anytour_anex_search3_out(['ok' => false, 'error' => 'rate_limited'], 429);
        }
        $recent[] = time();
        $_SESSION['requests'] = array_values($recent);
        if (!is_array($_SESSION['dictionaries'] ?? null)) $_SESSION['dictionaries'] = [];
        $app = is_file(__DIR__ . '/app/integrations/anex-search.php') ? __DIR__ . '/app/integrations' : __DIR__ . '/../app/integrations';
        require_once $app . '/anex-search.php';
        require_once $app . '/anex-search-mapping-registry.php';
        require_once $app . '/anex-search-observations.php';
        $root = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($root === false || basename($root) !== 'anytoour.ru') throw new RuntimeException('ANEX_DATABASE_UNAVAILABLE');
        $helper = is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php';
        require_once $helper;
        $client = new AnyTourAnexClient($token);
        $pdo = v2_data_db();
        $diagnostics = null;
        $observer = static function (array $offers, array $context) use ($pdo): array {
            return AnyTourAnexSearchObservations::record($pdo, $offers, $context);
        };
        $data = anytour_anex_search3_run($request, $pdo, $client, $_SESSION['dictionaries'], $diagnostics, $observer);
        session_write_close();
        anytour_anex_search3_out(['ok' => true, 'data' => $data], 200);
    } catch (InvalidArgumentException $error) {
        session_write_close();
        $unsupported = in_array($error->getMessage(), ['ANEX_FILTER_UNSUPPORTED', 'ANEX_DESTINATION_UNSUPPORTED'], true);
        anytour_anex_search3_out(['ok' => false, 'error' => $unsupported ? 'search_not_supported' : 'invalid_request'], $unsupported ? 422 : 400);
    } catch (Throwable $ignored) {
        session_write_close();
        $last = isset($client) ? $client->lastRequestDiagnostics() : [];
        if (($last['http_status'] ?? null) === 429) {
            header('Retry-After: 60');
            anytour_anex_search3_out(['ok' => false, 'error' => 'rate_limited'], 429);
        }
        $code = ($last['supplier_code'] ?? null) === 101 ? 'supplier_conditions_rejected'
            : (($last['curl_errno'] ?? null) === 28 ? 'supplier_timeout' : 'supplier_unavailable');
        anytour_anex_search3_out(['ok' => false, 'error' => $code], $code === 'supplier_conditions_rejected' ? 422 : 502);
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) anytour_anex_search3_http();
