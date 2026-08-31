<?php
/**
 * Bounded scheduled collector for the AnyTour first-party tour knowledge layer.
 *
 * One broad Tourvisor search per selected departure/country pair. Search results
 * are persisted through the same trusted price observer as user searches, but
 * with source=scheduled_monitor. Failures are isolated and recorded.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/tourvisor-client-v1.php';
require_once __DIR__ . '/price-observer-v1.php';

function scheduled_collector_arg(array $argv, string $name, ?string $default = null): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) return substr($arg, strlen($name) + 3);
    }
    return $default;
}

function scheduled_collector_int(array $argv, string $name, int $min, int $max, int $default): int
{
    $raw = scheduled_collector_arg($argv, $name, null);
    if ($raw === null || $raw === '') $raw = getenv('ANYTOUR_' . strtoupper(str_replace('-', '_', $name))) ?: (string)$default;
    $value = filter_var($raw, FILTER_VALIDATE_INT);
    if ($value === false) return $default;
    return max($min, min($max, (int)$value));
}

function scheduled_collector_search_id(array $start): ?int
{
    foreach (['searchId', 'id'] as $key) {
        $value = filter_var($start[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value !== false && (int)$value > 0) return (int)$value;
    }
    return null;
}

function scheduled_collector_complete(array $status): bool
{
    if ((int)($status['progress'] ?? 0) >= 100) return true;
    return strtolower(trim((string)($status['status'] ?? ''))) === 'complete';
}

function scheduled_collector_rows(array $payload): array
{
    if (array_is_list($payload)) return $payload;
    foreach (['hotels', 'items', 'results'] as $key) {
        if (is_array($payload[$key] ?? null)) return $payload[$key];
    }
    return [];
}

function scheduled_collector_candidates(PDO $pdo): array
{
    $sql = "SELECT
        dc.departure_id,
        d.name AS departure_name,
        dc.country_id,
        c.name AS country_name,
        COUNT(o.id) AS observation_count,
        COUNT(DISTINCT NULLIF(o.search_id, '')) AS distinct_search_count,
        COUNT(DISTINCT DATE(o.observed_at)) AS distinct_observation_days,
        MAX(o.observed_at) AS last_observed_at,
        a.attempt_count,
        a.last_attempt_at
      FROM catalog_departure_countries dc
      JOIN catalog_departures d ON d.id=dc.departure_id AND d.is_active=1
      JOIN catalog_countries c ON c.id=dc.country_id AND c.is_active=1
      LEFT JOIN tour_price_observations o
        ON o.departure_id=dc.departure_id AND o.country_id=dc.country_id
      LEFT JOIN (
        SELECT departure_id,country_id,COUNT(*) AS attempt_count,MAX(started_at) AS last_attempt_at
        FROM tour_collection_attempts
        GROUP BY departure_id,country_id
      ) a ON a.departure_id=dc.departure_id AND a.country_id=dc.country_id
      WHERE dc.is_active=1
      GROUP BY dc.departure_id,d.name,dc.country_id,c.name,a.attempt_count,a.last_attempt_at";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function scheduled_collector_priority(array $row): array
{
    $observations = (int)($row['observation_count'] ?? 0);
    $searches = (int)($row['distinct_search_count'] ?? 0);
    $days = (int)($row['distinct_observation_days'] ?? 0);
    $attempts = (int)($row['attempt_count'] ?? 0);
    $lastAttempt = trim((string)($row['last_attempt_at'] ?? ''));
    $lastObserved = trim((string)($row['last_observed_at'] ?? ''));

    $coverageBand = $observations === 0 ? 0 : ($searches < 5 || $days < 2 ? 1 : ($searches < 15 || $days < 3 ? 2 : ($searches < 30 || $days < 7 ? 3 : 4)));
    $time = $lastAttempt !== '' ? $lastAttempt : ($lastObserved !== '' ? $lastObserved : '0000-00-00 00:00:00');
    return [$coverageBand, $attempts, $time, (int)$row['country_id']];
}

function scheduled_collector_depth_priority(array $row): array
{
    $searches = (int)($row['distinct_search_count'] ?? 0);
    $days = (int)($row['distinct_observation_days'] ?? 0);
    $lastAttempt = trim((string)($row['last_attempt_at'] ?? ''));
    $lastObserved = trim((string)($row['last_observed_at'] ?? ''));
    $time = $lastAttempt !== '' ? $lastAttempt : ($lastObserved !== '' ? $lastObserved : '0000-00-00 00:00:00');

    // Confidence gates are 5 searches/2 days, 15/3 and 30/7. Revisit observed
    // markets before exhausting the entire breadth matrix so price history can
    // actually become useful within days instead of months.
    $gate = $searches < 5 || $days < 2 ? 0 : ($searches < 15 || $days < 3 ? 1 : ($searches < 30 || $days < 7 ? 2 : 3));
    return [$gate, $searches, $days, $time, (int)$row['departure_id'], (int)$row['country_id']];
}

function scheduled_collector_targets(array $rows, int $budget): array
{
    if ($budget <= 0) return [];

    $breadth = array_values(array_filter($rows, static fn(array $row): bool => (int)($row['observation_count'] ?? 0) === 0));
    $depth = array_values(array_filter($rows, static function (array $row): bool {
        $observations = (int)($row['observation_count'] ?? 0);
        $searches = (int)($row['distinct_search_count'] ?? 0);
        $days = (int)($row['distinct_observation_days'] ?? 0);
        return $observations > 0 && ($searches < 30 || $days < 7);
    }));

    usort($breadth, static function (array $a, array $b): int {
        return scheduled_collector_priority($a) <=> scheduled_collector_priority($b);
    });
    usort($depth, static function (array $a, array $b): int {
        return scheduled_collector_depth_priority($a) <=> scheduled_collector_depth_priority($b);
    });

    $targets = [];
    $usedPairs = [];
    $usedDepartures = [];
    $add = static function (array $row) use (&$targets, &$usedPairs, &$usedDepartures): bool {
        $departure = (int)$row['departure_id'];
        $pair = $departure . ':' . (int)$row['country_id'];
        if (isset($usedPairs[$pair])) return false;
        $targets[] = $row;
        $usedPairs[$pair] = true;
        $usedDepartures[$departure] = true;
        return true;
    };

    // With the production budget of 2 this means one repeated market for
    // history/confidence and one new market for breadth. Larger budgets retain
    // the same roughly 50/50 split.
    $depthSlots = $depth === [] ? 0 : max(1, intdiv($budget, 2));
    $breadthSlots = $budget - $depthSlots;
    if ($breadth === [] && $depth !== []) {
        $depthSlots = $budget;
        $breadthSlots = 0;
    }

    foreach ($depth as $row) {
        if (count($targets) >= $depthSlots) break;
        $add($row);
    }

    // Breadth pass prefers a departure not already used in this run.
    foreach ($breadth as $row) {
        if (count($targets) >= $depthSlots + $breadthSlots) break;
        if (isset($usedDepartures[(int)$row['departure_id']])) continue;
        $add($row);
    }
    foreach ($breadth as $row) {
        if (count($targets) >= $depthSlots + $breadthSlots) break;
        $add($row);
    }

    // Fill any remaining budget from either pool without duplicates.
    foreach (array_merge($depth, $breadth) as $row) {
        if (count($targets) >= $budget) break;
        $add($row);
    }

    return $targets;
}

function scheduled_collector_attempt_start(PDO $pdo, array $target, array $search): int
{
    $stmt = $pdo->prepare("INSERT INTO tour_collection_attempts
      (departure_id,country_id,status,date_from,date_to,nights_from,nights_to,adults,started_at)
      VALUES (:departure,:country,'started',:date_from,:date_to,:nights_from,:nights_to,:adults,:started_at)");
    $stmt->execute([
        'departure'=>(int)$target['departure_id'],
        'country'=>(int)$target['country_id'],
        'date_from'=>$search['dateFrom'],
        'date_to'=>$search['dateTo'],
        'nights_from'=>$search['nightsFrom'],
        'nights_to'=>$search['nightsTo'],
        'adults'=>$search['adults'],
        'started_at'=>(new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
    ]);
    return (int)$pdo->lastInsertId();
}

function scheduled_collector_attempt_finish(PDO $pdo, int $id, string $status, ?int $searchId, int $rows, int $written, ?string $error = null): void
{
    $allowed = ['success','empty','timeout','failure'];
    if (!in_array($status, $allowed, true)) $status = 'failure';
    $stmt = $pdo->prepare("UPDATE tour_collection_attempts SET
      status=:status,search_id=:search_id,rows_received=:rows_received,observations_written=:written,
      error_text=:error,finished_at=:finished_at WHERE id=:id");
    $stmt->execute([
        'status'=>$status,
        'search_id'=>$searchId,
        'rows_received'=>max(0,$rows),
        'written'=>max(0,$written),
        'error'=>$error !== null ? mb_substr($error,0,1000) : null,
        'finished_at'=>(new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        'id'=>$id,
    ]);
}

$budget = scheduled_collector_int($argv, 'scheduled-search-budget', 0, 20, 0);
if ($budget <= 0) {
    echo "ANYTOUR_SCHEDULED_COLLECTOR_DISABLED budget=0\n";
    exit(0);
}

$pollAttempts = scheduled_collector_int($argv, 'poll-attempts', 1, 30, 20);
$pollSeconds = scheduled_collector_int($argv, 'poll-seconds', 1, 10, 2);
$dateFrom = (new DateTimeImmutable('today +7 days'))->format('Y-m-d');
$dateTo = (new DateTimeImmutable('today +20 days'))->format('Y-m-d');

$pdo = v2_data_db();
$candidates = scheduled_collector_candidates($pdo);
$targets = scheduled_collector_targets($candidates, $budget);
if ($targets === []) {
    echo "ANYTOUR_SCHEDULED_COLLECTOR_NO_TARGETS\n";
    exit(0);
}

$totalWritten = 0;
$completed = 0;
$failed = 0;
foreach ($targets as $target) {
    $search = [
        'departureId'=>(int)$target['departure_id'],
        'countryId'=>(int)$target['country_id'],
        'dateFrom'=>$dateFrom,
        'dateTo'=>$dateTo,
        'nightsFrom'=>7,
        'nightsTo'=>10,
        'adults'=>2,
        'currency'=>'RUB',
        'onlyCharter'=>false,
        'onlyDirect'=>false,
    ];
    $attemptId = scheduled_collector_attempt_start($pdo, $target, $search);
    $searchId = null;

    try {
        $start = v2_data_tv_get('/tours/search', $search);
        $searchId = scheduled_collector_search_id($start);
        if ($searchId === null) throw new RuntimeException('Tourvisor search response has no searchId');

        $complete = false;
        for ($poll = 1; $poll <= $pollAttempts; $poll++) {
            sleep($pollSeconds);
            $status = v2_data_tv_get('/tours/search/' . $searchId . '/status', ['operatorStatus'=>false]);
            if (scheduled_collector_complete($status)) { $complete = true; break; }
        }
        if (!$complete) {
            scheduled_collector_attempt_finish($pdo, $attemptId, 'timeout', $searchId, 0, 0, 'search did not complete within bounded polling window');
            $failed++;
            fwrite(STDERR, "COLLECT_TIMEOUT departure={$target['departure_id']} country={$target['country_id']} search={$searchId}\n");
            continue;
        }

        $raw = v2_data_tv_get('/tours/search/' . $searchId, ['limit'=>100]);
        $rows = scheduled_collector_rows($raw);
        $trusted = [];
        foreach ($rows as $hotel) {
            if (!is_array($hotel)) continue;
            $hotelCountry = is_array($hotel['country'] ?? null) ? (int)($hotel['country']['id'] ?? 0) : 0;
            if ($hotelCountry > 0 && $hotelCountry !== (int)$target['country_id']) continue;
            $trusted[] = $hotel;
        }

        if ($trusted === []) {
            scheduled_collector_attempt_finish($pdo, $attemptId, 'empty', $searchId, 0, 0);
            $completed++;
            echo "COLLECT_EMPTY departure={$target['departure_id']} country={$target['country_id']} search={$searchId}\n";
            continue;
        }

        $result = v2_data_observe_search_results($trusted, [
            'source'=>'scheduled_monitor',
            'searchId'=>$searchId,
            'departureId'=>(int)$target['departure_id'],
            'countryId'=>(int)$target['country_id'],
            'adults'=>2,
            'childs'=>[],
            'currency'=>'RUB',
        ]);
        $written = (int)($result['written'] ?? 0);
        $totalWritten += $written;
        $completed++;
        scheduled_collector_attempt_finish($pdo, $attemptId, 'success', $searchId, count($trusted), $written);
        echo "COLLECT_OK departure={$target['departure_id']}:{$target['departure_name']} country={$target['country_id']}:{$target['country_name']} search={$searchId} hotels=" . count($trusted) . " written={$written}\n";
    } catch (Throwable $e) {
        $failed++;
        scheduled_collector_attempt_finish($pdo, $attemptId, 'failure', $searchId, 0, 0, $e->getMessage());
        fwrite(STDERR, "COLLECT_FAILED departure={$target['departure_id']} country={$target['country_id']} " . mb_substr($e->getMessage(),0,500) . "\n");
    }
}

echo "ANYTOUR_SCHEDULED_COLLECTOR_OK budget={$budget} attempted=" . count($targets) . " completed={$completed} failed={$failed} written={$totalWritten}\n";
// Partial upstream failures are recorded but do not abort the whole data job.
exit(0);
