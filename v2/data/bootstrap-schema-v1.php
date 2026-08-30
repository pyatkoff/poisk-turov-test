<?php
/**
 * Apply the AnyTour first-party data schema to the configured database.
 *
 * CLI only. Credentials come from the same env contract as db-v1.php.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

$schemaFile = __DIR__ . '/schema-v1.sql';
if (!is_file($schemaFile)) {
    fwrite(STDERR, "Schema file not found: {$schemaFile}\n");
    exit(2);
}

$schema = file_get_contents($schemaFile);
if ($schema === false || trim($schema) === '') {
    fwrite(STDERR, "Schema file is empty or unreadable\n");
    exit(3);
}

try {
    $pdo = v2_data_db();
    $pdo->exec($schema);

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

    $stmt = $pdo->query('SHOW TABLES');
    $tables = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $missing = array_values(array_diff($required, $tables));

    if ($missing !== []) {
        fwrite(STDERR, 'Schema applied but required tables are missing: ' . implode(', ', $missing) . "\n");
        exit(4);
    }

    echo "ANYTOUR_DATA_SCHEMA_OK\n";
    echo 'tables=' . count($required) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ANYTOUR_DATA_SCHEMA_FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
