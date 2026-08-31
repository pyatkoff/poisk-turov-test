<?php
/** Report anonymous observation coverage for guarded price-intelligence rollout. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

try {
    $pdo = v2_data_db();
    $summary = $pdo->query("SELECT
        COUNT(*) AS total,
        SUM(observed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) AS last_1h,
        SUM(observed_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR)) AS last_6h,
        SUM(observed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS last_24h,
        COUNT(DISTINCT CASE WHEN observed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN search_id END) AS searches_24h,
        COUNT(DISTINCT CASE WHEN observed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN departure_id END) AS departures_24h,
        COUNT(DISTINCT CASE WHEN observed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN country_id END) AS countries_24h,
        COUNT(DISTINCT CASE WHEN observed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN hotel_id END) AS hotels_24h
      FROM tour_price_observations")->fetch(PDO::FETCH_ASSOC) ?: [];

    $coverage = $pdo->query("SELECT
        COUNT(*) AS comparable_groups,
        SUM(sample_count >= 5) AS groups_5_plus,
        SUM(sample_count >= 15) AS groups_15_plus,
        SUM(sample_count >= 30) AS groups_30_plus,
        COALESCE(MAX(sample_count),0) AS max_sample
      FROM (
        SELECT departure_id,hotel_id,departure_year,departure_month,nights,adults,children_count,
               child_ages_signature,COALESCE(meal_id,0) AS meal_key,currency,COUNT(*) AS sample_count
          FROM tour_price_observations
         WHERE observed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND price > 0
         GROUP BY departure_id,hotel_id,departure_year,departure_month,nights,adults,children_count,
                  child_ages_signature,COALESCE(meal_id,0),currency
      ) grouped")->fetch(PDO::FETCH_ASSOC) ?: [];

    $daily = $pdo->query("SELECT COUNT(*) AS rows_count,COALESCE(MAX(observation_count),0) AS max_observations
                           FROM tour_price_daily")->fetch(PDO::FETCH_ASSOC) ?: [];
    $hot = $pdo->query("SELECT COUNT(*) AS rows_count,
                               SUM(expires_at > NOW()) AS fresh_count,
                               COUNT(DISTINCT departure_id) AS departures,
                               COUNT(DISTINCT country_id) AS countries,
                               COUNT(DISTINCT hotel_id) AS hotels
                          FROM hot_tours_current")->fetch(PDO::FETCH_ASSOC) ?: [];

    echo 'PRICE_COVERAGE_OK' . "\n";
    foreach ($summary as $key => $value) echo 'observations_' . $key . '=' . (int)$value . "\n";
    foreach ($coverage as $key => $value) echo 'coverage_' . $key . '=' . (int)$value . "\n";
    foreach ($daily as $key => $value) echo 'daily_' . $key . '=' . (int)$value . "\n";
    foreach ($hot as $key => $value) echo 'hot_' . $key . '=' . (int)$value . "\n";

    $ready5 = (int)($coverage['groups_5_plus'] ?? 0);
    $ready15 = (int)($coverage['groups_15_plus'] ?? 0);
    echo 'price_intelligence_stage=' . ($ready15 > 0 ? 'guarded_delta_ready' : ($ready5 > 0 ? 'good_price_only' : 'collect_more')) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'PRICE_COVERAGE_FAILED ' . mb_substr($e->getMessage(), 0, 1000) . "\n");
    exit(1);
}
