<?php
/** Create the bounded scheduled-collection attempt ledger. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

try {
    $pdo = v2_data_db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS tour_collection_attempts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        departure_id INT UNSIGNED NOT NULL,
        country_id INT UNSIGNED NOT NULL,
        source ENUM('scheduled_monitor') NOT NULL DEFAULT 'scheduled_monitor',
        status ENUM('started','success','empty','timeout','failure') NOT NULL DEFAULT 'started',
        search_id BIGINT UNSIGNED DEFAULT NULL,
        rows_received INT UNSIGNED NOT NULL DEFAULT 0,
        observations_written INT UNSIGNED NOT NULL DEFAULT 0,
        date_from DATE NOT NULL,
        date_to DATE NOT NULL,
        nights_from TINYINT UNSIGNED NOT NULL,
        nights_to TINYINT UNSIGNED NOT NULL,
        adults TINYINT UNSIGNED NOT NULL DEFAULT 2,
        error_text VARCHAR(1000) DEFAULT NULL,
        started_at DATETIME NOT NULL,
        finished_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_collection_pair_time (departure_id, country_id, started_at),
        KEY idx_collection_status_time (status, started_at),
        KEY idx_collection_search (search_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "ANYTOUR_COLLECTION_ATTEMPTS_SCHEMA_OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ANYTOUR_COLLECTION_ATTEMPTS_SCHEMA_FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
