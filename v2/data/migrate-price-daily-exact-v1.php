<?php
/** Create exact comparable-segment daily price history without changing the legacy monthly rollup. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

try {
    $pdo = v2_data_db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS tour_price_daily_exact (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        segment_fingerprint CHAR(64) NOT NULL,
        price_date DATE NOT NULL,
        departure_id INT UNSIGNED NOT NULL,
        country_id INT UNSIGNED NOT NULL,
        region_id INT UNSIGNED DEFAULT NULL,
        subregion_id INT UNSIGNED DEFAULT NULL,
        hotel_id INT UNSIGNED NOT NULL,
        departure_date DATE NOT NULL,
        nights TINYINT UNSIGNED NOT NULL,
        adults TINYINT UNSIGNED NOT NULL,
        children_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
        child_ages_signature VARCHAR(40) NOT NULL DEFAULT '',
        meal_id INT UNSIGNED NOT NULL DEFAULT 0,
        room_id INT UNSIGNED NOT NULL DEFAULT 0,
        room_type VARCHAR(255) NOT NULL DEFAULT '',
        operator_id INT UNSIGNED NOT NULL DEFAULT 0,
        currency CHAR(8) NOT NULL DEFAULT 'RUB',
        min_price DECIMAL(12,2) NOT NULL,
        median_price DECIMAL(12,2) NOT NULL,
        max_price DECIMAL(12,2) NOT NULL,
        observation_count INT UNSIGNED NOT NULL,
        independent_search_count INT UNSIGNED NOT NULL DEFAULT 0,
        calculated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_price_daily_exact_segment (price_date, segment_fingerprint),
        KEY idx_price_daily_exact_segment_time (segment_fingerprint, price_date),
        KEY idx_price_daily_exact_hotel_departure (hotel_id, departure_date, price_date),
        KEY idx_price_daily_exact_destination (country_id, region_id, departure_date, price_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Keep the migration idempotent if an earlier branch/rehearsal created the
    // table before independent-search confidence was added.
    $column = $pdo->query("SHOW COLUMNS FROM tour_price_daily_exact LIKE 'independent_search_count'")->fetch(PDO::FETCH_ASSOC);
    if (!$column) {
        $pdo->exec("ALTER TABLE tour_price_daily_exact
            ADD COLUMN independent_search_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER observation_count");
    }

    echo "ANYTOUR_PRICE_DAILY_EXACT_SCHEMA_OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ANYTOUR_PRICE_DAILY_EXACT_SCHEMA_FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
