<?php
/** Hardened V2-only Tourvisor gateway. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$privateConfig = __DIR__ . '/config.php';
if (is_file($privateConfig)) require_once $privateConfig;
require_once __DIR__ . '/catalog-cache-v1.php';

function out($data, int $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jwt()
{
    $token = trim((string)getenv('TOURVISOR_JWT'));
    if ($token !== '') return $token;
    if (defined('TOURVISOR_JWT')) return trim((string)TOURVISOR_JWT);
    return '';
}

function bounded_int($value, int $min, int $max, int $fallback)
{
    $v = filter_var($value, FILTER_VALIDATE_INT);
    if ($v === false) return $fallback;
    return max($min, min($max, (int)$v));
}

function strict_int($value, int $min, int $max, string $name)
{
    $v = filter_var($value, FILTER_VALIDATE_INT);
    if ($v === false || (int)$v < $min || (int)$v > $max) {
        out(['ok' => false, 'error' => $name . ' must be between ' . $min . ' and ' . $max], 400);
    }
    return (int)$v;
}

function optional_int($value)
{
    if ($value === null || $value === '') return null;
    $v = filter_var($value, FILTER_VALIDATE_INT);
    return $v === false ? null : (int)$v;
}

function short_text($value, int $max = 200)
{
    return mb_substr(trim((string)$value), 0, $max, 'UTF-8');
}

function request_array(string $key, int $maxItems = 100)
{
    $value = $_GET[$key] ?? [];
    if ($value === '' || $value === null) return [];
    $list = is_array($value) ? $value : [$value];
    $out = [];
    foreach (array_slice($list, 0, $maxItems) as $v) {
        $v = short_text($v, 80);
        if ($v !== '') $out[] = $v;
    }
    return array_values(array_unique($out));
}

function child_ages()
{
    $ages = request_array('childs', 3);
    $out = [];
    foreach ($ages as $age) {
        $v = filter_var($age, FILTER_VALIDATE_INT);
        if ($v === false || (int)$v < 0 || (int)$v > 17) {
            out(['ok' => false, 'error' => 'child age must be between 0 and 17'], 400);
        }
        $out[] = (int)$v;
    }
    return $out;
}

function search_id()
{
    $id = bounded_int($_GET['searchId'] ?? 0, 1, PHP_INT_MAX, 0);
    if ($id <= 0) out(['ok' => false, 'error' => 'searchId is required'], 400);
    return $id;
}

function query_string(array $params)
{
    $parts = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') continue;
        if (is_bool($value)) $value = $value ? 'true' : 'false';
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($item === '' || $item === null) continue;
                $parts[] = rawurlencode($key) . '=' . rawurlencode((string)$item);
            }
        } else {
            $parts[] = rawurlencode($key) . '=' . rawurlencode((string)$value);
        }
    }
    return implode('&', $parts);
}

function tv_get(string $path, array $params = [])
{
    $token = jwt();
    if ($token === '') out(['ok' => false, 'error' => 'Tourvisor is not configured'], 500);

    $url = 'https://api.tourvisor.ru/search/api/v1' . $path;
    $qs = query_string($params);
    if ($qs !== '') $url .= '?' . $qs;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) out(['ok' => false, 'error' => 'Tourvisor connection error'], 502);

    $decoded = json_decode((string)$body, true);
    if ($code < 200 || $code >= 300) {
        $status = ($code >= 400 && $code < 600) ? $code : 502;
        out(['ok' => false, 'error' => 'Tourvisor request failed', 'upstreamStatus' => $code], $status);
    }

    return is_array($decoded) ? $decoded : ['raw' => short_text((string)$body, 1000)];
}

function bool_param(string $key)
{
    return filter_var($_GET[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
}

function date_param(string $key)
{
    $raw = short_text($_GET[$key] ?? '', 20);
    if ($raw === '') return null;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$d || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) return null;
    return $d->format('Y-m-d');
}

$action = short_text($_GET['action'] ?? $_POST['action'] ?? 'health', 60);

switch ($action) {
    case 'health':
        $data = tv_get('/departures');
        out(['ok' => true, 'source' => 'tourvisor-direct', 'departuresCount' => is_array($data) ? count($data) : 0, 'gatewayVersion' => 2]);

    case 'departures':
        out(v2_catalog_get('/departures', ['departureCountryId' => bounded_int($_GET['departureCountryId'] ?? 1, 1, 9999, 1)], 3600));

    case 'countries':
        out(v2_catalog_get('/countries', [
            'departureId' => optional_int($_GET['departureId'] ?? null),
            'onlyCharter' => bool_param('onlyCharter'),
            'onlyDirect' => bool_param('onlyDirect'),
        ], 900));

    case 'arrivals':
        out(v2_catalog_get('/arrivals', [
            'departureId' => optional_int($_GET['departureId'] ?? null),
            'onlyCharter' => bool_param('onlyCharter'),
            'onlyDirect' => bool_param('onlyDirect'),
        ], 900));

    case 'regions':
        out(v2_catalog_get('/regions', ['countryId' => optional_int($_GET['countryId'] ?? null), 'arrivalId' => optional_int($_GET['arrivalId'] ?? null)], 3600));

    case 'subregions':
        out(v2_catalog_get('/subregions', ['countryId' => optional_int($_GET['countryId'] ?? null), 'regionId' => optional_int($_GET['regionId'] ?? null)], 3600));

    case 'meals':
        out(v2_catalog_get('/meals', [], 21600));

    case 'operators':
        out(v2_catalog_get('/operators', ['departureId' => optional_int($_GET['departureId'] ?? null), 'countryId' => optional_int($_GET['countryId'] ?? null)], 900));

    case 'hotel_types':
        out(v2_catalog_get('/hotel-types', ['countryId' => optional_int($_GET['countryId'] ?? null)], 21600));

    case 'hotel_services':
        out(v2_catalog_get('/hotel-group-services', ['countryId' => optional_int($_GET['countryId'] ?? null), 'regionIds' => request_array('regionIds', 50)], 21600));

    case 'hotels':
        out(tv_get('/hotels', [
            'countryId' => optional_int($_GET['countryId'] ?? null),
            'regionId' => optional_int($_GET['regionId'] ?? null),
            'category' => short_text($_GET['category'] ?? '', 20),
            'types' => request_array('types', 30),
            'rating' => short_text($_GET['rating'] ?? '', 20),
            'page' => bounded_int($_GET['page'] ?? 1, 1, 1000, 1),
            'limit' => bounded_int($_GET['limit'] ?? 100, 1, 100, 100),
        ]));

    case 'hotel_details':
        $id = bounded_int($_GET['hotelId'] ?? 0, 1, PHP_INT_MAX, 0);
        if ($id <= 0) out(['ok' => false, 'error' => 'hotelId is required'], 400);
        out(tv_get('/hotels/' . $id));

    case 'dates':
        out(tv_get('/tours/dates', [
            'departureId' => optional_int($_GET['departureId'] ?? null),
            'countryId' => optional_int($_GET['countryId'] ?? null),
            'arrivalId' => optional_int($_GET['arrivalId'] ?? null),
            'onlyCharter' => bool_param('onlyCharter'),
        ]));

    case 'search_start':
        $departureId = optional_int($_GET['departureId'] ?? null);
        $countryId = optional_int($_GET['countryId'] ?? null);
        $dateFrom = date_param('dateFrom');
        $dateTo = date_param('dateTo');
        $nightsFrom = strict_int($_GET['nightsFrom'] ?? 5, 1, 28, 'nightsFrom');
        $nightsTo = strict_int($_GET['nightsTo'] ?? 14, 1, 28, 'nightsTo');
        $adults = strict_int($_GET['adults'] ?? 2, 1, 6, 'adults');
        $childs = child_ages();
        $priceFrom = optional_int($_GET['priceFrom'] ?? null);
        $priceTo = optional_int($_GET['priceTo'] ?? null);
        if (!$departureId || !$countryId || !$dateFrom || !$dateTo) out(['ok' => false, 'error' => 'Invalid required search parameters'], 400);
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        if ($dateFrom < $today) out(['ok' => false, 'error' => 'dateFrom must not be in the past'], 400);
        if ($dateTo < $dateFrom) out(['ok' => false, 'error' => 'dateTo must not be before dateFrom'], 400);
        $dateSpan = (new DateTimeImmutable($dateFrom))->diff(new DateTimeImmutable($dateTo))->days;
        if ($dateSpan === false || $dateSpan > 21) out(['ok' => false, 'error' => 'date range must not exceed 21 days'], 400);
        if ($nightsTo < $nightsFrom) out(['ok' => false, 'error' => 'nightsTo must not be less than nightsFrom'], 400);
        if (($nightsTo - $nightsFrom) > 10) out(['ok' => false, 'error' => 'nights range must not exceed 10'], 400);
        if ($priceFrom !== null && $priceFrom < 0) out(['ok' => false, 'error' => 'priceFrom must be positive'], 400);
        if ($priceTo !== null && $priceTo < 0) out(['ok' => false, 'error' => 'priceTo must be positive'], 400);
        if ($priceFrom !== null && $priceTo !== null && $priceTo < $priceFrom) out(['ok' => false, 'error' => 'priceTo must not be less than priceFrom'], 400);
        out(tv_get('/tours/search', [
            'departureId' => $departureId,
            'countryId' => $countryId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'nightsFrom' => $nightsFrom,
            'nightsTo' => $nightsTo,
            'adults' => $adults,
            'childs' => $childs,
            'meal' => short_text($_GET['meal'] ?? '', 40),
            'hotelCategory' => short_text($_GET['hotelCategory'] ?? '', 20),
            'hotelRating' => short_text($_GET['hotelRating'] ?? '', 20),
            'hotelTypes' => request_array('hotelTypes', 20),
            'hotelIds' => request_array('hotelIds', 50),
            'hotelServices' => request_array('hotelServices', 100),
            'arrivalId' => optional_int($_GET['arrivalId'] ?? null),
            'regionIds' => request_array('regionIds', 50),
            'subregionIds' => request_array('subregionIds', 50),
            'operatorIds' => request_array('operatorIds', 50),
            'priceFrom' => $priceFrom,
            'priceTo' => $priceTo,
            'currency' => short_text($_GET['currency'] ?? 'RUB', 8) ?: 'RUB',
            'onlyCharter' => bool_param('onlyCharter'),
            'onlyDirect' => bool_param('onlyDirect'),
        ]));

    case 'search_continue':
        $id = search_id();
        out(tv_get('/tours/search/' . $id . '/continue'));

    case 'search_status':
        $id = search_id();
        out(tv_get('/tours/search/' . $id . '/status', ['operatorStatus' => false]));

    case 'search_results':
        $id = search_id();
        out(tv_get('/tours/search/' . $id, ['limit' => bounded_int($_GET['limit'] ?? 25, 1, 100, 25)]));

    case 'tour':
        $id = short_text($_GET['tourId'] ?? '', 200);
        if ($id === '') out(['ok' => false, 'error' => 'tourId is required'], 400);
        out(tv_get('/tours/' . rawurlencode($id), ['currency' => short_text($_GET['currency'] ?? 'RUB', 8) ?: 'RUB']));

    case 'flights':
        $id = short_text($_GET['tourId'] ?? '', 200);
        if ($id === '') out(['ok' => false, 'error' => 'tourId is required'], 400);
        out(tv_get('/tours/' . rawurlencode($id) . '/flights', ['currency' => short_text($_GET['currency'] ?? 'RUB', 8) ?: 'RUB']));

    case 'rooms':
        out(tv_get('/rooms', ['ids' => request_array('ids', 30)]));

    default:
        out(['ok' => false, 'error' => 'Unknown V2 action'], 404);
}
