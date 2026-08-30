<?php
/**
 * AnyTour first-party data DB diagnostics.
 *
 * CLI only. Prints connectivity, server version, schema presence and row counts
 * without exposing credentials.
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
    $config = v2_data_db_config();
    if ($config['dsn'] === '' || $config['user'] === '') {
        throw new RuntimeException('database environment variables are not configured');
    }

    $pdo = v2_data_db();
    $version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $tables = array_map('strval', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));

    echo "ANYTOUR_DATA_DB_OK\n";
    echo 'database=' . $database . "\n";
    echo 'server=' . $version . "\n";

    $missing = [];
    foreach ($required as $table) {
        if (!in_array($table, $tables, true)) {
            $missing[] = $table;
            echo $table . "=MISSING\n";
            continue;
        }
        $quoted = '`' . str_replace('`', '``', $table) . '`';
        $count = (int)$pdo->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();
        echo $table . '=' . $count . "\n";
    }

    if ($missing !== []) {
        fwrite(STDERR, 'ANYTOUR_DATA_SCHEMA_INCOMPLETE: ' . implode(', ', $missing) . "\n");
        exit(2);
    }

    echo "ANYTOUR_DATA_SCHEMA_OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ANYTOUR_DATA_DB_FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
