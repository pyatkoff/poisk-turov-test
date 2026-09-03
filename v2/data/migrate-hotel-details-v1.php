<?php
/** Create durable storage for paid Tourvisor hotel-description API responses. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

try {
    $pdo = v2_data_db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS catalog_hotel_details (
        hotel_id INT UNSIGNED NOT NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'success',
        source_hash CHAR(64) DEFAULT NULL,
        country_id INT UNSIGNED DEFAULT NULL,
        region_id INT UNSIGNED DEFAULT NULL,
        subregion_id INT UNSIGNED DEFAULT NULL,
        name VARCHAR(255) DEFAULT NULL,
        category TINYINT UNSIGNED DEFAULT NULL,
        rating DECIMAL(4,2) DEFAULT NULL,
        hotel_type INT UNSIGNED DEFAULT NULL,
        description MEDIUMTEXT DEFAULT NULL,
        address VARCHAR(1000) DEFAULT NULL,
        place VARCHAR(1000) DEFAULT NULL,
        phone VARCHAR(255) DEFAULT NULL,
        site VARCHAR(1000) DEFAULT NULL,
        build_info TEXT DEFAULT NULL,
        repair_info TEXT DEFAULT NULL,
        square_info VARCHAR(1000) DEFAULT NULL,
        latitude DECIMAL(10,7) DEFAULT NULL,
        longitude DECIMAL(10,7) DEFAULT NULL,
        primary_image_url VARCHAR(2048) DEFAULT NULL,
        images_json MEDIUMTEXT DEFAULT NULL,
        infrastructure_json MEDIUMTEXT DEFAULT NULL,
        meals_json MEDIUMTEXT DEFAULT NULL,
        services_json MEDIUMTEXT DEFAULT NULL,
        room_types MEDIUMTEXT DEFAULT NULL,
        raw_json LONGTEXT DEFAULT NULL,
        fetched_at DATETIME NOT NULL,
        last_error VARCHAR(1000) DEFAULT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (hotel_id),
        KEY idx_catalog_hotel_details_status_fetched (status, fetched_at),
        KEY idx_catalog_hotel_details_country_region (country_id, region_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columns = array_map('strval', $pdo->query('SHOW COLUMNS FROM catalog_hotels')->fetchAll(PDO::FETCH_COLUMN));
    if (!in_array('primary_image_url', $columns, true)) {
        $pdo->exec('ALTER TABLE catalog_hotels ADD COLUMN primary_image_url VARCHAR(1024) DEFAULT NULL AFTER longitude');
    }
    if (!in_array('image_updated_at', $columns, true)) {
        $pdo->exec('ALTER TABLE catalog_hotels ADD COLUMN image_updated_at DATETIME DEFAULT NULL AFTER primary_image_url');
    }

    echo "ANYTOUR_HOTEL_DETAILS_SCHEMA_OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ANYTOUR_HOTEL_DETAILS_SCHEMA_FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
