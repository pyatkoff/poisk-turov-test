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

    echo "ANYTOUR_DATA_DB_OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ANYTOUR_DATA_DB_FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
