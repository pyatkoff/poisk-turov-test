<?php
/** Ensure the departures catalog exists and supports grammatical city names. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

try {
    $pdo = v2_data_db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS catalog_departures (
        id INT UNSIGNED NOT NULL,
        name VARCHAR(180) NOT NULL,
        name_genitive VARCHAR(180) DEFAULT NULL,
        slug VARCHAR(200) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        synced_at DATETIME NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_catalog_departures_slug (slug),
        KEY idx_catalog_departures_active_name (is_active, name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columns = array_map('strval', $pdo->query('SHOW COLUMNS FROM catalog_departures')->fetchAll(PDO::FETCH_COLUMN));
    if (!in_array('name_genitive', $columns, true)) {
        $pdo->exec('ALTER TABLE catalog_departures ADD COLUMN name_genitive VARCHAR(180) DEFAULT NULL AFTER name');
    }

    echo "ANYTOUR_DEPARTURES_SCHEMA_OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ANYTOUR_DEPARTURES_SCHEMA_FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
