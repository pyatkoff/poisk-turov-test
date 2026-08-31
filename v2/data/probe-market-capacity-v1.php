<?php
/** One-shot Tourvisor capacity probe. CLI only, no DB writes. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/tourvisor-client-v1.php';

function probe_rows(array $payload): array
{
    if (array_is_list($payload)) return $payload;
    foreach (['hotels','items','results'] as $key) {
        if (is_array($payload[$key] ?? null)) return $payload[$key];
    }
    return [];
}

function probe_search_id(array $payload): ?int
{
    foreach (['searchId','id'] as $key) {
        $id = filter_var($payload[$key] ?? null, FILTER_VALIDATE_INT);
        if ($id !== false && (int)$id > 0) return (int)$id;
    }
    return null;
}

function probe_complete(array $status): bool
{
    if ((int)($status['progress'] ?? 0) >= 100) return true;
    return strtolower(trim((string)($status['status'] ?? ''))) === 'complete';
}

$search = [
    'departureId'=>1,
    'countryId'=>4,
    'dateFrom'=>'2026-09-01',
    'dateTo'=>'2026-09-22',
    'nightsFrom'=>5,
    'nightsTo'=>14,
    'adults'=>2,
    'currency'=>'RUB',
    'onlyCharter'=>false,
    'onlyDirect'=>false,
];

echo "PROBE_MARKET_START departure=1:Moscow country=4:Turkey from={$search['dateFrom']} to={$search['dateTo']} nights=5-14 adults=2\n";
$startedAt = microtime(true);
$start = v2_data_tv_get('/tours/search', $search);
$searchId = probe_search_id($start);
if ($searchId === null) throw new RuntimeException('Tourvisor search response has no searchId');

$complete = false;
for ($i = 1; $i <= 30; $i++) {
    sleep(2);
    $status = v2_data_tv_get('/tours/search/' . $searchId . '/status', ['operatorStatus'=>false]);
    if (probe_complete($status)) { $complete = true; break; }
}
if (!$complete) throw new RuntimeException('Capacity probe search timed out');

$payload = v2_data_tv_get('/tours/search/' . $searchId, ['limit'=>10000]);
$rows = probe_rows($payload);
$hotels = count($rows);
$tours = 0;
$hotelsWithTours = 0;
foreach ($rows as $hotel) {
    if (!is_array($hotel)) continue;
    $hotelTours = is_array($hotel['tours'] ?? null) ? count($hotel['tours']) : 0;
    if ($hotelTours > 0) $hotelsWithTours++;
    $tours += $hotelTours;
}
$elapsed = round(microtime(true) - $startedAt, 2);
echo "PROBE_MARKET_RESULT search={$searchId} limit=10000 hotels={$hotels} hotels_with_tours={$hotelsWithTours} tours={$tours} elapsed_seconds={$elapsed}\n";
