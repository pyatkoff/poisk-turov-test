<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('FIRST_SNAPSHOT_CLI_ONLY');
}

$siteRoot = isset($argv[1]) ? realpath((string)$argv[1]) : false;
if (!is_string($siteRoot) || $siteRoot === '' || !is_file($siteRoot . '/config.php')) {
    throw new RuntimeException('FIRST_SNAPSHOT_SITE_ROOT');
}

$sourceRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $siteRoot;

$tvClient = is_file($siteRoot . '/data/tourvisor-client-v1.php')
    ? $siteRoot . '/data/tourvisor-client-v1.php'
    : $siteRoot . '/v2/data/tourvisor-client-v1.php';
if (!is_file($tvClient)) {
    throw new RuntimeException('FIRST_SNAPSHOT_TOURVISOR_CLIENT');
}
require_once $tvClient;
require_once $sourceRoot . '/app/integrations/tourvisor-anytour-offer-autosave.php';

const FIRST_SNAPSHOT_OPERATION = 'int-tourvisor-first-snapshot-20260917-v1';
const FIRST_SNAPSHOT_RESULTS_LIMIT = 100;
const FIRST_SNAPSHOT_STATUS_POLLS = 10;

function first_snapshot_tv_get(string $path, array $params, string $token, array &$requestLog): array
{
    if (!preg_match('~\A/tours/search(?:/[1-9][0-9]{0,17}(?:/status)?)?\z~D', $path)) {
        throw new RuntimeException('FIRST_SNAPSHOT_PATH');
    }

    $url = 'https://api.tourvisor.ru/search/api/v1' . $path;
    $query = v2_data_query_string($params);
    if ($query !== '') $url .= '?' . $query;

    $body = '';
    $tooLarge = false;
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('FIRST_SNAPSHOT_CURL');
    try {
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge): int {
                if (strlen($body) + strlen($chunk) > 8 * 1024 * 1024) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $requestLog[] = [
            'path' => preg_replace('~/[1-9][0-9]{5,}~', '/{id}', $path),
            'http' => $http,
            'bytes' => strlen($body),
        ];
        if ($tooLarge || $ok === false || $http < 200 || $http >= 300) {
            throw new RuntimeException('FIRST_SNAPSHOT_HTTP_' . $http);
        }
        $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) throw new RuntimeException('FIRST_SNAPSHOT_JSON');
        return $decoded;
    } finally {
        curl_close($ch);
    }
}

function first_snapshot_search_id(array $response): int
{
    foreach ([$response, $response['result'] ?? null, $response['data'] ?? null] as $row) {
        if (!is_array($row)) continue;
        foreach (['searchId', 'search_id', 'id'] as $key) {
            $value = filter_var($row[$key] ?? null, FILTER_VALIDATE_INT);
            if ($value !== false && (int)$value > 0) return (int)$value;
        }
    }
    throw new RuntimeException('FIRST_SNAPSHOT_SEARCH_ID');
}

function first_snapshot_terminal(array $response): bool
{
    foreach ([$response, $response['result'] ?? null, $response['data'] ?? null] as $row) {
        if (!is_array($row)) continue;
        if (is_numeric($row['progress'] ?? null) && (int)$row['progress'] === 100) return true;
        if (strtolower(trim((string)($row['status'] ?? ''))) === 'complete') return true;
    }
    return false;
}

function first_snapshot_label(mixed $value): string
{
    if (is_string($value)) return trim($value);
    if (!is_array($value)) return '';
    foreach (['russianName', 'name', 'title', 'label'] as $key) {
        if (isset($value[$key]) && is_string($value[$key]) && trim($value[$key]) !== '') {
            return trim($value[$key]);
        }
    }
    return '';
}

function first_snapshot_operator_family(array $tour): ?string
{
    $raw = first_snapshot_label($tour['operatorName'] ?? $tour['operator'] ?? null);
    if ($raw === '') return null;
    $value = mb_strtolower(str_replace('ё', 'е', $raw), 'UTF-8');
    $compact = preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
    if (str_contains($compact, 'pegas') || str_contains($compact, 'пегас')) return 'pegas';
    if (str_contains($compact, 'coral') || str_contains($compact, 'корал')) return 'coral';
    if (str_contains($compact, 'sunmar') || str_contains($compact, 'санмар')) return 'sunmar';
    return null;
}

function first_snapshot_route_results(array $results, array &$routeCounts): array
{
    if (!array_is_list($results)) throw new RuntimeException('FIRST_SNAPSHOT_RESULTS_NOT_LIST');
    $out = [];
    foreach ($results as $hotel) {
        if (!is_array($hotel) || !is_array($hotel['tours'] ?? null)) continue;
        $tours = [];
        foreach ($hotel['tours'] as $tour) {
            if (!is_array($tour)) continue;
            $family = first_snapshot_operator_family($tour);
            if ($family === null) continue;
            $routeCounts[$family] = ($routeCounts[$family] ?? 0) + 1;
            $tours[] = $tour;
        }
        if ($tours === []) continue;
        $hotel['tours'] = $tours;
        $out[] = $hotel;
    }
    return $out;
}

function first_snapshot_scalar(PDO $db, string $sql): int
{
    $value = $db->query($sql)->fetchColumn();
    return is_numeric($value) ? (int)$value : 0;
}

$token = v2_data_tourvisor_token();
if ($token === '') throw new RuntimeException('FIRST_SNAPSHOT_TOKEN');

$scope = [
    'departureId' => 1,
    'countryId' => 4,
    'dateFrom' => '2026-10-12',
    'dateTo' => '2026-10-12',
    'nightsFrom' => 7,
    'nightsTo' => 7,
    'adults' => 2,
    'childs' => [],
    'meal' => '',
    'hotelCategory' => '',
    'hotelRating' => '',
    'hotelTypes' => [],
    'hotelIds' => [],
    'hotelServices' => [],
    'arrivalId' => null,
    'regionIds' => [],
    'subregionIds' => [],
    'operatorIds' => [],
    'priceFrom' => null,
    'priceTo' => null,
    'currency' => 'RUB',
    'onlyCharter' => false,
    'onlyDirect' => false,
];

$requestLog = [];
$startedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$start = first_snapshot_tv_get('/tours/search', $scope, $token, $requestLog);
$searchId = first_snapshot_search_id($start);
$startReceipt = AnyTourTourvisorOfferAutosaveV1::captureSearchStart($scope, $start, $startedAt);
if (($startReceipt['ok'] ?? null) !== true) throw new RuntimeException('FIRST_SNAPSHOT_STATE_START');

$status = [];
$terminal = false;
for ($poll = 0; $poll < FIRST_SNAPSHOT_STATUS_POLLS; $poll++) {
    if ($poll > 0) sleep(4);
    $status = first_snapshot_tv_get('/tours/search/' . $searchId . '/status', ['operatorStatus' => false], $token, $requestLog);
    if (first_snapshot_terminal($status)) {
        $terminal = true;
        break;
    }
}
if (!$terminal) throw new RuntimeException('FIRST_SNAPSHOT_NOT_TERMINAL');
$statusReceipt = AnyTourTourvisorOfferAutosaveV1::captureSearchStatus(
    $searchId,
    $status,
    new DateTimeImmutable('now', new DateTimeZone('UTC'))
);
if (($statusReceipt['ok'] ?? null) !== true) throw new RuntimeException('FIRST_SNAPSHOT_STATE_STATUS');

$results = first_snapshot_tv_get('/tours/search/' . $searchId, ['limit' => FIRST_SNAPSHOT_RESULTS_LIMIT], $token, $requestLog);
$routeCounts = ['pegas' => 0, 'coral' => 0, 'sunmar' => 0];
$routed = first_snapshot_route_results($results, $routeCounts);
$routedOffers = array_sum($routeCounts);
if ($routedOffers < 1 || $routed === []) throw new RuntimeException('FIRST_SNAPSHOT_NO_ROUTED_TOURS');

$published = AnyTourTourvisorOfferAutosaveV1::autosaveSearchResults(
    $searchId,
    FIRST_SNAPSHOT_RESULTS_LIMIT,
    $routed,
    new DateTimeImmutable('now', new DateTimeZone('UTC'))
);
if (($published['published'] ?? null) !== true) {
    throw new RuntimeException('FIRST_SNAPSHOT_NOT_PUBLISHED_' . (string)($published['reason'] ?? 'unknown'));
}

$db = v2_data_db();
$readback = [
    'refreshes' => first_snapshot_scalar($db, "SELECT COUNT(*) FROM anytour_offer_refreshes WHERE provider='tourvisor' AND status='completed'"),
    'scopes' => first_snapshot_scalar($db, "SELECT COUNT(*) FROM anytour_offer_scope_state WHERE provider='tourvisor' AND latest_complete_refresh_token IS NOT NULL"),
    'offers' => first_snapshot_scalar($db, "SELECT COUNT(*) FROM anytour_offers WHERE provider='tourvisor' AND is_active=1"),
    'hotels' => first_snapshot_scalar($db, "SELECT COUNT(DISTINCT anytour_hotel_id) FROM anytour_offers WHERE provider='tourvisor' AND is_active=1"),
    'ready' => first_snapshot_scalar($db, "SELECT COUNT(*) FROM anytour_offers WHERE provider='tourvisor' AND is_active=1 AND final_price_ready=1 AND currency='RUB'"),
];
if ($readback['refreshes'] < 1 || $readback['scopes'] < 1 || $readback['offers'] < 1 || $readback['ready'] < 1) {
    throw new RuntimeException('FIRST_SNAPSHOT_READBACK_ZERO');
}

$receipt = [
    'schema_version' => 1,
    'operation' => FIRST_SNAPSHOT_OPERATION,
    'status' => 'completed',
    'provider' => 'tourvisor',
    'search_id' => $searchId,
    'scope' => [
        'departureId' => $scope['departureId'],
        'countryId' => $scope['countryId'],
        'dateFrom' => $scope['dateFrom'],
        'dateTo' => $scope['dateTo'],
        'nightsFrom' => $scope['nightsFrom'],
        'nightsTo' => $scope['nightsTo'],
        'adults' => $scope['adults'],
        'children' => count($scope['childs']),
    ],
    'routed_source_offers' => $routeCounts,
    'routed_offer_count' => $routedOffers,
    'published_offer_count' => (int)($published['publishedOfferCount'] ?? $published['offerCount'] ?? 0),
    'readback' => $readback,
    'supplier_requests' => count($requestLog),
    'supplier_request_log' => $requestLog,
    'booking_calls' => 0,
    'lead_calls' => 0,
    'mapping_writes' => 0,
    'production_webroot_writes' => 0,
    'authoritative_empty' => false,
];

echo json_encode($receipt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
