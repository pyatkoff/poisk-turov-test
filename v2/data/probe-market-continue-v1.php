<?php
/** One-shot Tourvisor continue-search capacity probe. CLI only, no DB writes. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/tourvisor-client-v1.php';

function probe_continue_rows(array $payload): array
{
    if (array_is_list($payload)) return $payload;
    foreach (['hotels','items','results'] as $key) {
        if (is_array($payload[$key] ?? null)) return $payload[$key];
    }
    return [];
}

function probe_continue_search_id(array $payload): ?int
{
    foreach (['searchId','id'] as $key) {
        $id = filter_var($payload[$key] ?? null, FILTER_VALIDATE_INT);
        if ($id !== false && (int)$id > 0) return (int)$id;
    }
    return null;
}

function probe_continue_complete(array $status): bool
{
    if ((int)($status['progress'] ?? 0) >= 100) return true;
    return strtolower(trim((string)($status['status'] ?? ''))) === 'complete';
}

function probe_continue_wait(int $searchId, int $attempts = 30, int $sleepSeconds = 2): void
{
    for ($i = 1; $i <= $attempts; $i++) {
        sleep($sleepSeconds);
        $status = v2_data_tv_get('/tours/search/' . $searchId . '/status', ['operatorStatus'=>false]);
        if (probe_continue_complete($status)) return;
    }
    throw new RuntimeException('Tourvisor search did not complete within bounded polling window');
}

function probe_continue_stats(array $rows): array
{
    $hotelIds = [];
    $tourIds = [];
    $tourCount = 0;
    foreach ($rows as $hotel) {
        if (!is_array($hotel)) continue;
        $hotelId = (string)($hotel['id'] ?? '');
        if ($hotelId !== '') $hotelIds[$hotelId] = true;
        $tours = is_array($hotel['tours'] ?? null) ? $hotel['tours'] : [];
        foreach ($tours as $tour) {
            if (!is_array($tour)) continue;
            $tourCount++;
            $tourId = trim((string)($tour['id'] ?? ''));
            if ($tourId !== '') $tourIds[$tourId] = true;
        }
    }
    return [
        'hotels'=>count($rows),
        'unique_hotels'=>count($hotelIds),
        'tours'=>$tourCount,
        'unique_tours'=>count($tourIds),
        'tour_ids'=>$tourIds,
        'hotel_ids'=>$hotelIds,
    ];
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

$startedAt = microtime(true);
echo "PROBE_CONTINUE_START departure=1:Moscow country=4:Turkey from={$search['dateFrom']} to={$search['dateTo']} nights=5-14 adults=2\n";

// Paid request #1: initial search.
$start = v2_data_tv_get('/tours/search', $search);
$searchId = probe_continue_search_id($start);
if ($searchId === null) throw new RuntimeException('Tourvisor search response has no searchId');
probe_continue_wait($searchId);

$beforeRows = probe_continue_rows(v2_data_tv_get('/tours/search/' . $searchId, ['limit'=>10000]));
$before = probe_continue_stats($beforeRows);
echo "PROBE_CONTINUE_BEFORE search={$searchId} limit=10000 hotels={$before['hotels']} tours={$before['tours']} unique_tours={$before['unique_tours']}\n";

// Paid request #2: exactly one continuation. Tourvisor documents this as an
// additional operator request whose results accumulate under the same searchId.
$continueStarted = microtime(true);
$continue = v2_data_tv_get('/tours/search/' . $searchId . '/continue');
$requestCount = (int)($continue['requestCount'] ?? 0);
echo "PROBE_CONTINUE_REQUEST search={$searchId} request_count={$requestCount}\n";
probe_continue_wait($searchId);

$afterRows = probe_continue_rows(v2_data_tv_get('/tours/search/' . $searchId, ['limit'=>10000]));
$after = probe_continue_stats($afterRows);
$newTourIds = array_diff_key($after['tour_ids'], $before['tour_ids']);
$newHotelIds = array_diff_key($after['hotel_ids'], $before['hotel_ids']);
$elapsed = round(microtime(true) - $startedAt, 2);
$continueElapsed = round(microtime(true) - $continueStarted, 2);

$hotelDelta = $after['hotels'] - $before['hotels'];
$tourDelta = $after['tours'] - $before['tours'];
echo "PROBE_CONTINUE_AFTER search={$searchId} limit=10000 hotels={$after['hotels']} tours={$after['tours']} unique_tours={$after['unique_tours']} hotel_delta={$hotelDelta} tour_delta={$tourDelta} new_unique_hotels=" . count($newHotelIds) . " new_unique_tours=" . count($newTourIds) . " continue_seconds={$continueElapsed} elapsed_seconds={$elapsed}\n";
