<?php
/**
 * Read-only coverage planner for AnyTour accumulated tour observations.
 *
 * Ranks active departure→country pairs that are unobserved, sparse or stale.
 * This script never starts Tourvisor searches and never mutates the database.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

function collection_coverage_arg(array $argv, string $name, ?string $default = null): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) {
            return substr($arg, strlen($name) + 3);
        }
    }
    return $default;
}

function collection_coverage_limit(array $argv): int
{
    $raw = collection_coverage_arg($argv, 'limit', '25');
    $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
    return $value === false ? 25 : (int)$value;
}

function collection_coverage_score(array $row, DateTimeImmutable $now): int
{
    $observations = (int)($row['observation_count'] ?? 0);
    $searches = (int)($row['distinct_search_count'] ?? 0);
    $days = (int)($row['distinct_observation_days'] ?? 0);
    $last = trim((string)($row['last_observed_at'] ?? ''));

    if ($observations === 0) return 1000000;

    $ageHours = 9999;
    if ($last !== '') {
        try {
            $lastAt = new DateTimeImmutable($last);
            $seconds = max(0, $now->getTimestamp() - $lastAt->getTimestamp());
            $ageHours = (int)floor($seconds / 3600);
        } catch (Throwable) {
            $ageHours = 9999;
        }
    }

    // Higher score = higher collection priority. Independent searches/days matter
    // more than raw offer rows because price intelligence must not be inflated by
    // many rows from a single search response.
    $searchGap = max(0, 30 - $searches);
    $dayGap = max(0, 7 - $days);
    $staleHours = min(720, $ageHours);
    return ($searchGap * 1000) + ($dayGap * 5000) + $staleHours;
}

function collection_coverage_rows(PDO $pdo): array
{
    $tableExists = $pdo->query("SHOW TABLES LIKE 'catalog_departure_countries'")->fetchColumn();
    if ($tableExists === false) {
        throw new RuntimeException('catalog_departure_countries is missing; run migrate-departure-countries-v1.php first');
    }

    $sql = "SELECT
        dc.departure_id,
        d.name AS departure_name,
        dc.country_id,
        c.name AS country_name,
        COUNT(o.id) AS observation_count,
        COUNT(DISTINCT NULLIF(o.search_id, '')) AS distinct_search_count,
        COUNT(DISTINCT DATE(o.observed_at)) AS distinct_observation_days,
        COUNT(DISTINCT o.hotel_id) AS observed_hotel_count,
        COUNT(DISTINCT o.departure_date) AS observed_departure_date_count,
        MIN(o.observed_at) AS first_observed_at,
        MAX(o.observed_at) AS last_observed_at
      FROM catalog_departure_countries dc
      JOIN catalog_departures d ON d.id = dc.departure_id AND d.is_active = 1
      JOIN catalog_countries c ON c.id = dc.country_id AND c.is_active = 1
      LEFT JOIN tour_price_observations o
        ON o.departure_id = dc.departure_id
       AND o.country_id = dc.country_id
      WHERE dc.is_active = 1
      GROUP BY dc.departure_id, d.name, dc.country_id, c.name";

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

try {
    $limit = collection_coverage_limit($argv);
    $format = strtolower((string)collection_coverage_arg($argv, 'format', 'text'));
    if (!in_array($format, ['text', 'json'], true)) $format = 'text';

    $pdo = v2_data_db();
    $rows = collection_coverage_rows($pdo);
    $now = new DateTimeImmutable('now');

    foreach ($rows as &$row) {
        $row['priority_score'] = collection_coverage_score($row, $now);
    }
    unset($row);

    usort($rows, static function (array $a, array $b): int {
        $score = ((int)$b['priority_score']) <=> ((int)$a['priority_score']);
        if ($score !== 0) return $score;
        $obs = ((int)$a['observation_count']) <=> ((int)$b['observation_count']);
        if ($obs !== 0) return $obs;
        return strcmp((string)$a['departure_name'] . (string)$a['country_name'], (string)$b['departure_name'] . (string)$b['country_name']);
    });

    $pairCount = count($rows);
    $unobserved = 0;
    $historyReady = 0;
    $guardedDeltaReady = 0;
    $goodPriceReady = 0;
    $totalObservations = 0;
    foreach ($rows as $row) {
        $observations = (int)$row['observation_count'];
        $searches = (int)$row['distinct_search_count'];
        $days = (int)$row['distinct_observation_days'];
        $totalObservations += $observations;
        if ($observations === 0) $unobserved++;
        if ($searches >= 30 && $days >= 7) $historyReady++;
        if ($searches >= 15 && $days >= 3) $guardedDeltaReady++;
        if ($searches >= 5 && $days >= 2) $goodPriceReady++;
    }

    $targets = array_slice($rows, 0, $limit);
    $summary = [
        'active_pairs' => $pairCount,
        'unobserved_pairs' => $unobserved,
        'good_price_ready_pairs' => $goodPriceReady,
        'guarded_delta_ready_pairs' => $guardedDeltaReady,
        'history_ready_pairs' => $historyReady,
        'observations_across_pairs' => $totalObservations,
        'target_count' => count($targets),
    ];

    if ($format === 'json') {
        echo json_encode(['ok' => true, 'summary' => $summary, 'targets' => $targets], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }

    echo "ANYTOUR_COLLECTION_COVERAGE_OK\n";
    foreach ($summary as $key => $value) echo $key . '=' . $value . "\n";
    echo "TARGETS\n";
    foreach ($targets as $i => $row) {
        printf(
            "%d\tdeparture=%d:%s\tcountry=%d:%s\tobs=%d\tsearches=%d\tdays=%d\thotels=%d\tdates=%d\tlast=%s\tscore=%d\n",
            $i + 1,
            (int)$row['departure_id'],
            (string)$row['departure_name'],
            (int)$row['country_id'],
            (string)$row['country_name'],
            (int)$row['observation_count'],
            (int)$row['distinct_search_count'],
            (int)$row['distinct_observation_days'],
            (int)$row['observed_hotel_count'],
            (int)$row['observed_departure_date_count'],
            (string)($row['last_observed_at'] ?? '-'),
            (int)$row['priority_score']
        );
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ANYTOUR_COLLECTION_COVERAGE_FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
