<?php
/**
 * Diagnose the AnyTour first-party data database without exposing secrets.
 * CLI only.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

$required = [
    'catalog_departures',
    'catalog_countries',
    'catalog_regions',
    'catalog_subregions',
    'catalog_hotels',
    'hotel_aliases',
    'catalog_sync_state',
    'tour_price_observations',
    'tour_price_daily',
    'hot_tours_current',
    'seo_offer_snapshots',
];

try {
    $pdo = v2_data_db();
    $version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
    $tables = array_map('strval', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
    $missing = array_values(array_diff($required, $tables));

    echo "ANYTOUR_DATA_DB_CONNECTED\n";
    echo 'server=' . preg_replace('/[^A-Za-z0-9._+\-]/', '', $version) . "\n";
    echo 'required_tables=' . count($required) . "\n";
    echo 'present_tables=' . (count($required) - count($missing)) . "\n";

    foreach ($required as $table) {
        if (!in_array($table, $tables, true)) {
            echo $table . "=MISSING\n";
            continue;
        }
        $quoted = '`' . str_replace('`', '``', $table) . '`';
        $count = (int)$pdo->query('SELECT COUNT(*) FROM ' . $quoted)->fetchColumn();
        echo $table . '=' . $count . "\n";
    }

    if ($missing !== []) {
        fwrite(STDERR, 'ANYTOUR_DATA_DB_INCOMPLETE missing=' . implode(',', $missing) . "\n");
        exit(2);
    }

    // Review-only hotel cohort diagnostics. These counts expose no customer data or prices;
    // they only make fail-closed source eligibility observable when a cohort cannot be built.
    $diagnostics = [
        'hotel_cohort_catalog_target_active' => "SELECT COUNT(*) FROM catalog_hotels h JOIN catalog_countries c ON c.id=h.country_id AND c.is_active=1 WHERE h.is_active=1 AND h.country_id IN (1,4,8) AND h.slug IS NOT NULL AND h.slug<>'' AND c.slug IS NOT NULL AND c.slug<>''",
        'hotel_cohort_observations_recent_30d' => "SELECT COUNT(*) FROM tour_price_observations o WHERE o.observed_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)",
        'hotel_cohort_observations_recent_future' => "SELECT COUNT(*) FROM tour_price_observations o WHERE o.observed_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND o.departure_date>=CURDATE()",
        'hotel_cohort_observations_recent_future_rub' => "SELECT COUNT(*) FROM tour_price_observations o WHERE o.observed_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND o.departure_date>=CURDATE() AND o.price>0 AND o.currency='RUB'",
        'hotel_cohort_observations_target_countries' => "SELECT COUNT(*) FROM tour_price_observations o WHERE o.observed_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND o.departure_date>=CURDATE() AND o.price>0 AND o.currency='RUB' AND o.country_id IN (1,4,8)",
        'hotel_cohort_distinct_target_hotels' => "SELECT COUNT(DISTINCT o.hotel_id) FROM tour_price_observations o WHERE o.observed_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND o.departure_date>=CURDATE() AND o.price>0 AND o.currency='RUB' AND o.country_id IN (1,4,8)",
        'hotel_cohort_joined_eligible_hotels' => "SELECT COUNT(DISTINCT h.id) FROM catalog_hotels h JOIN catalog_countries c ON c.id=h.country_id AND c.is_active=1 JOIN tour_price_observations o ON o.hotel_id=h.id AND o.country_id=h.country_id WHERE h.is_active=1 AND h.country_id IN (1,4,8) AND h.slug IS NOT NULL AND h.slug<>'' AND c.slug IS NOT NULL AND c.slug<>'' AND o.observed_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND o.departure_date>=CURDATE() AND o.price>0 AND o.currency='RUB'",
    ];
    foreach ($diagnostics as $label => $sql) {
        echo $label . '=' . (int)$pdo->query($sql)->fetchColumn() . "\n";
    }

    echo "ANYTOUR_DATA_DB_OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ANYTOUR_DATA_DB_FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
