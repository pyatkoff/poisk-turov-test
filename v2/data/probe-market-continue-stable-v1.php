<?php
/** One-shot Tourvisor continue probe with a fixed post-continue observation window. CLI only, no DB writes. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/tourvisor-client-v1.php';

function continue_probe_rows(array $payload): array
{
    if (array_is_list($payload)) return $payload;
    foreach (['hotels', 'items', 'results'] as $key) {
        if (is_array($payload[$key] ?? null)) return $payload[$key];
    }
    return [];
}

function continue_probe_search_id(array $payload): ?int
{
    foreach (['searchId', 'id'] as $key) {
        $id = filter_var($payload[$key] ?? null, FILTER_VALIDATE_INT);
        if ($id !== false && (int)$id > 0) return (int)$id;
    }
    return null;
}

function continue_probe_complete(array $status): bool
{
    if ((int)($status['progress'] ?? 0) >= 100) return true;
    return strtolower(trim((string)($status['status'] ?? ''))) === 'complete';
}

function continue_probe_wait_initial(int $searchId, int $attempts = 30, int $sleepSeconds = 2): void
{
    for ($i = 1; $i <= $attempts; $i++) {
        sleep($sleepSeconds);
        $status = v2_data_tv_get('/tours/search/' . $searchId . '/status', ['operatorStatus'=>false]);
        if (continue_probe_complete($status)) return;
    }
    throw new RuntimeException('Initial Tourvisor search did not complete within bounded polling window');
}

function continue_probe_stats(array $rows): array
{
    $hotelIds = [];
    $tourKeys = [];
    $tourCount = 0;
    foreach ($rows as $hotel) {
        if (!is_array($hotel)) continue;
        $hotelId = trim((string)($hotel['id'] ?? ''));
        if ($hotelId !== '') $hotelIds[$hotelId] = true;
        $tours = is_array($hotel['tours'] ?? null) ? $hotel['tours'] : [];
        foreach ($tours as $tour) {
            if (!is_array($tour)) continue;
            $tourCount++;
            $tourId = trim((string)($tour['id'] ?? ''));
            if ($tourId !== '') {
                $tourKeys['id:' . $tourId] = true;
                continue;
            }
            $fingerprint = implode('|', [
                $hotelId,
                (string)($tour['date'] ?? $tour['departureDate'] ?? ''),
                (string)($tour['nights'] ?? ''),
                (string)($tour['price'] ?? ''),
                (string)($tour['meal'] ?? ''),
                (string)($tour['room'] ?? ''),
            ]);
            $tourKeys['fp:' . hash('sha256', $fingerprint)] = true;
        }
    }
    return [
        'hotels'=>count($rows),
        'unique_hotels'=>count($hotelIds),
        'tours'=>$tourCount,
        'unique_tours'=>count($tourKeys),
        'hotel_ids'=>$hotelIds,
        'tour_keys'=>$tourKeys,
    ];
}

function continue_probe_fetch_stats(int $searchId): array
{
    return continue_probe_stats(continue_probe_rows(v2_data_tv_get('/tours/search/' . $searchId, ['limit'=>10000])));
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
echo "STABLE_CONTINUE_START departure=1:Moscow country=4:Turkey from={$search['dateFrom']} to={$search['dateTo']} nights=5-14 adults=2\n";

// Paid request #1.
$start = v2_data_tv_get('/tours/search', $search);
$searchId = continue_probe_search_id($start);
if ($searchId === null) throw new RuntimeException('Tourvisor search response has no searchId');
continue_probe_wait_initial($searchId);

$before = continue_probe_fetch_stats($searchId);
echo "STABLE_CONTINUE_BEFORE search={$searchId} hotels={$before['hotels']} tours={$before['tours']} unique_tours={$before['unique_tours']}\n";

// Paid request #2. Do not trust an immediately-complete status as proof that
// continued operator inventory has settled; observe cumulative results for 30s.
$continuedAt = microtime(true);
$continue = v2_data_tv_get('/tours/search/' . $searchId . '/continue');
$requestCount = (int)($continue['requestCount'] ?? 0);
echo "STABLE_CONTINUE_REQUEST search={$searchId} request_count={$requestCount}\n";

$best = $before;
for ($sample = 1; $sample <= 10; $sample++) {
    sleep(3);
    $current = continue_probe_fetch_stats($searchId);
    $elapsedContinue = round(microtime(true) - $continuedAt, 2);
    $newHotels = count(array_diff_key($current['hotel_ids'], $before['hotel_ids']));
    $newTours = count(array_diff_key($current['tour_keys'], $before['tour_keys']));
    echo "STABLE_CONTINUE_SAMPLE search={$searchId} sample={$sample} seconds={$elapsedContinue} hotels={$current['hotels']} tours={$current['tours']} unique_tours={$current['unique_tours']} new_unique_hotels={$newHotels} new_unique_tours={$newTours}\n";
    if ($current['unique_tours'] > $best['unique_tours'] || $current['hotels'] > $best['hotels']) $best = $current;
}

$newHotelIds = array_diff_key($best['hotel_ids'], $before['hotel_ids']);
$newTourKeys = array_diff_key($best['tour_keys'], $before['tour_keys']);
$elapsed = round(microtime(true) - $startedAt, 2);
echo "STABLE_CONTINUE_RESULT search={$searchId} request_count={$requestCount} before_hotels={$before['hotels']} before_tours={$before['tours']} best_hotels={$best['hotels']} best_tours={$best['tours']} new_unique_hotels=" . count($newHotelIds) . " new_unique_tours=" . count($newTourKeys) . " elapsed_seconds={$elapsed}\n";
