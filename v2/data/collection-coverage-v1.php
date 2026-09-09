<?php
/**
 * Read-only coverage/confidence planner for AnyTour accumulated tour observations.
 *
 * Ranks active departure→country pairs by package-tour flight usefulness and
 * independent price-history confidence. This script never starts Tourvisor
 * searches and never mutates the database.
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

function collection_coverage_confidence(array $row): string
{
    $searches = (int)($row['distinct_search_count'] ?? 0);
    $days = (int)($row['distinct_observation_days'] ?? 0);

    if ($searches >= 30 && $days >= 7) return 'history_ready';
    if ($searches >= 15 && $days >= 3) return 'guarded_delta_ready';
    if ($searches >= 5 && $days >= 2) return 'good_price_only';
    return 'collect_more';
}

function collection_coverage_flight_profile(array $row): string
{
    if ((int)($row['is_direct_charter'] ?? 0) === 1) return 'direct_charter';
    if ((int)($row['is_charter'] ?? 0) === 1) return 'charter';
    if ((int)($row['is_direct'] ?? 0) === 1) return 'direct';
    return 'general';
}

function collection_coverage_flight_rank(string $profile): int
{
    return match ($profile) {
        'direct_charter' => 0,
        'charter' => 1,
        'direct' => 2,
        default => 3,
    };
}

function collection_coverage_score(array $row, DateTimeImmutable $now): int
{
    $observations = (int)($row['observation_count'] ?? 0);
    $searches = (int)($row['distinct_search_count'] ?? 0);
    $days = (int)($row['distinct_observation_days'] ?? 0);
    $last = trim((string)($row['last_observed_at'] ?? ''));

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

    // Higher score means a larger confidence gap. Flight usefulness is sorted
    // separately before this score so history grows first in package-tour markets.
    if ($observations === 0) return 1000000;
    $searchGap = max(0, 30 - $searches);
    $dayGap = max(0, 7 - $days);
    return ($searchGap * 1000) + ($dayGap * 5000) + min(720, $ageHours);
}

function collection_coverage_require_tables(PDO $pdo): void
{
    $tables = [
        'catalog_departure_countries',
        'catalog_departure_countries_direct',
        'catalog_departure_countries_charter',
        'catalog_departure_countries_direct_charter',
    ];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute(['table_name' => $table]);
        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException($table . ' is missing; run the flight-matrix migration/sync first');
        }
    }
}

function collection_coverage_rows(PDO $pdo): array
{
    collection_coverage_require_tables($pdo);

    $sql = "SELECT
        dc.departure_id,
        d.name AS departure_name,
        dc.country_id,
        c.name AS country_name,
        CASE WHEN direct_pair.departure_id IS NULL THEN 0 ELSE 1 END AS is_direct,
        CASE WHEN charter_pair.departure_id IS NULL THEN 0 ELSE 1 END AS is_charter,
        CASE WHEN direct_charter_pair.departure_id IS NULL THEN 0 ELSE 1 END AS is_direct_charter,
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
      LEFT JOIN catalog_departure_countries_direct direct_pair
        ON direct_pair.departure_id = dc.departure_id
       AND direct_pair.country_id = dc.country_id
       AND direct_pair.is_active = 1
      LEFT JOIN catalog_departure_countries_charter charter_pair
        ON charter_pair.departure_id = dc.departure_id
       AND charter_pair.country_id = dc.country_id
       AND charter_pair.is_active = 1
      LEFT JOIN catalog_departure_countries_direct_charter direct_charter_pair
        ON direct_charter_pair.departure_id = dc.departure_id
       AND direct_charter_pair.country_id = dc.country_id
       AND direct_charter_pair.is_active = 1
      LEFT JOIN tour_price_observations o
        ON o.departure_id = dc.departure_id
       AND o.country_id = dc.country_id
      WHERE dc.is_active = 1
      GROUP BY dc.departure_id, d.name, dc.country_id, c.name,
        direct_pair.departure_id, charter_pair.departure_id, direct_charter_pair.departure_id";

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function collection_coverage_empty_stats(): array
{
    return [
        'pairs' => 0,
        'unobserved' => 0,
        'collect_more' => 0,
        'good_price_only' => 0,
        'guarded_delta_ready' => 0,
        'history_ready' => 0,
        'observations' => 0,
    ];
}

function collection_coverage_add_stats(array &$stats, array $row): void
{
    $confidence = (string)$row['confidence'];
    $stats['pairs']++;
    $stats['observations'] += (int)$row['observation_count'];
    if ((int)$row['observation_count'] === 0) $stats['unobserved']++;
    $stats[$confidence]++;
}

try {
    $limit = collection_coverage_limit($argv);
    $format = strtolower((string)collection_coverage_arg($argv, 'format', 'text'));
    if (!in_array($format, ['text', 'json'], true)) $format = 'text';

    $pdo = v2_data_db();
    $rows = collection_coverage_rows($pdo);
    $now = new DateTimeImmutable('now');

    $coverage = [
        'all' => collection_coverage_empty_stats(),
        'direct' => collection_coverage_empty_stats(),
        'charter' => collection_coverage_empty_stats(),
        'direct_charter' => collection_coverage_empty_stats(),
        'general_only' => collection_coverage_empty_stats(),
    ];

    foreach ($rows as &$row) {
        $row['flight_profile'] = collection_coverage_flight_profile($row);
        $row['flight_rank'] = collection_coverage_flight_rank((string)$row['flight_profile']);
        $row['confidence'] = collection_coverage_confidence($row);
        $row['priority_score'] = collection_coverage_score($row, $now);

        collection_coverage_add_stats($coverage['all'], $row);
        if ((int)$row['is_direct'] === 1) collection_coverage_add_stats($coverage['direct'], $row);
        if ((int)$row['is_charter'] === 1) collection_coverage_add_stats($coverage['charter'], $row);
        if ((int)$row['is_direct_charter'] === 1) collection_coverage_add_stats($coverage['direct_charter'], $row);
        if ((int)$row['is_direct'] === 0 && (int)$row['is_charter'] === 0) {
            collection_coverage_add_stats($coverage['general_only'], $row);
        }
    }
    unset($row);

    usort($rows, static function (array $a, array $b): int {
        $flight = ((int)$a['flight_rank']) <=> ((int)$b['flight_rank']);
        if ($flight !== 0) return $flight;
        $score = ((int)$b['priority_score']) <=> ((int)$a['priority_score']);
        if ($score !== 0) return $score;
        $searches = ((int)$a['distinct_search_count']) <=> ((int)$b['distinct_search_count']);
        if ($searches !== 0) return $searches;
        return strcmp((string)$a['departure_name'] . (string)$a['country_name'], (string)$b['departure_name'] . (string)$b['country_name']);
    });

    $targets = array_values(array_filter($rows, static fn(array $row): bool => $row['confidence'] !== 'history_ready'));
    $targets = array_slice($targets, 0, $limit);

    // Keep the original summary fields for consumers while adding matrix-aware detail.
    $summary = [
        'active_pairs' => $coverage['all']['pairs'],
        'unobserved_pairs' => $coverage['all']['unobserved'],
        'good_price_ready_pairs' => $coverage['all']['good_price_only'] + $coverage['all']['guarded_delta_ready'] + $coverage['all']['history_ready'],
        'guarded_delta_ready_pairs' => $coverage['all']['guarded_delta_ready'] + $coverage['all']['history_ready'],
        'history_ready_pairs' => $coverage['all']['history_ready'],
        'observations_across_pairs' => $coverage['all']['observations'],
        'target_count' => count($targets),
        'coverage' => $coverage,
    ];

    if ($format === 'json') {
        echo json_encode(['ok' => true, 'summary' => $summary, 'targets' => $targets], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }

    echo "ANYTOUR_COLLECTION_COVERAGE_OK\n";
    echo "ANYTOUR_COLLECTION_COVERAGE_V2_OK\n";
    foreach (['active_pairs','unobserved_pairs','good_price_ready_pairs','guarded_delta_ready_pairs','history_ready_pairs','observations_across_pairs','target_count'] as $key) {
        echo $key . '=' . $summary[$key] . "\n";
    }
    foreach ($coverage as $name => $stats) {
        printf(
            "COVERAGE profile=%s pairs=%d unobserved=%d collect_more=%d good_price_only=%d guarded_delta_ready=%d history_ready=%d observations=%d\n",
            $name,
            $stats['pairs'],
            $stats['unobserved'],
            $stats['collect_more'],
            $stats['good_price_only'],
            $stats['guarded_delta_ready'],
            $stats['history_ready'],
            $stats['observations']
        );
    }

    echo "TARGETS\n";
    foreach ($targets as $i => $row) {
        printf(
            "%d\tflight=%s\tconfidence=%s\tdeparture=%d:%s\tcountry=%d:%s\tobs=%d\tsearches=%d\tdays=%d\thotels=%d\tdates=%d\tlast=%s\tscore=%d\n",
            $i + 1,
            (string)$row['flight_profile'],
            (string)$row['confidence'],
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
