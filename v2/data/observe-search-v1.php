<?php
/** Accept a completed search id, refetch trusted Tourvisor rows and persist price observations. */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length > 32768) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'payload too large']);
    exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid JSON']);
    exit;
}

$positiveInt = static function ($value): ?int {
    $v = filter_var($value, FILTER_VALIDATE_INT);
    return $v !== false && (int)$v > 0 ? (int)$v : null;
};

$searchId = $positiveInt($payload['searchId'] ?? null);
$departureId = $positiveInt($payload['departureId'] ?? null);
$countryId = $positiveInt($payload['countryId'] ?? null);
$adults = $positiveInt($payload['adults'] ?? 2);
$childs = is_array($payload['childs'] ?? null) ? array_slice($payload['childs'], 0, 3) : [];

if ($searchId === null || $departureId === null || $countryId === null || $adults === null || $adults > 6) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid search context']);
    exit;
}
foreach ($childs as $age) {
    $v = filter_var($age, FILTER_VALIDATE_INT);
    if ($v === false || (int)$v < 0 || (int)$v > 17) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid child age']);
        exit;
    }
}

http_response_code(202);
echo json_encode(['ok' => true, 'accepted' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
else {
    @ob_flush();
    @flush();
}

ignore_user_abort(true);
@set_time_limit(30);

try {
    require_once __DIR__ . '/tourvisor-client-v1.php';
    require_once __DIR__ . '/price-observer-v1.php';

    $rows = v2_data_tv_get('/tours/search/' . $searchId, ['limit' => 100]);
    if (!array_is_list($rows)) {
        $rows = is_array($rows['hotels'] ?? null) ? $rows['hotels'] : (is_array($rows['items'] ?? null) ? $rows['items'] : []);
    }

    // Country is part of the segmentation contract. Ignore mismatching hotel rows
    // rather than allowing a malformed client context to pollute another country.
    $trusted = [];
    foreach ($rows as $hotel) {
        if (!is_array($hotel)) continue;
        $hotelCountry = is_array($hotel['country'] ?? null) ? (int)($hotel['country']['id'] ?? 0) : 0;
        if ($hotelCountry > 0 && $hotelCountry !== $countryId) continue;
        $trusted[] = $hotel;
    }

    $result = v2_data_observe_search_results($trusted, [
        'searchId' => $searchId,
        'departureId' => $departureId,
        'countryId' => $countryId,
        'adults' => $adults,
        'childs' => $childs,
        'currency' => 'RUB',
    ]);
    error_log('ANYTOUR_PRICE_OBSERVER search=' . $searchId . ' rows=' . count($trusted) . ' written=' . (int)($result['written'] ?? 0) . ' ignored=' . (int)($result['ignored'] ?? 0));
} catch (Throwable $e) {
    // Persistence is deliberately fail-open: a data-layer failure must never
    // affect the completed live search that triggered this background request.
    error_log('ANYTOUR_PRICE_OBSERVER_FAILED search=' . $searchId . ' ' . mb_substr($e->getMessage(), 0, 800));
}
