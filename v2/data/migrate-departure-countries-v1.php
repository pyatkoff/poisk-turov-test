<?php
/** Ensure the free Tourvisor departure-to-country availability matrix exists. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

try {
    $pdo = v2_data_db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS catalog_departure_countries (
        departure_id INT UNSIGNED NOT NULL,
        country_id INT UNSIGNED NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        synced_at DATETIME NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (departure_id, country_id),
        KEY idx_departure_countries_country (country_id, is_active),
        KEY idx_departure_countries_active (departure_id, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "ANYTOUR_DEPARTURE_COUNTRIES_SCHEMA_OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ANYTOUR_DEPARTURE_COUNTRIES_SCHEMA_FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
