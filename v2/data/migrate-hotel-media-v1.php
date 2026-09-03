<?php
/** Ensure catalog hotels can retain one trusted primary image URL from normal Tourvisor search results. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

try {
    $pdo = v2_data_db();
    $columns = array_map('strval', $pdo->query('SHOW COLUMNS FROM catalog_hotels')->fetchAll(PDO::FETCH_COLUMN));
    if (!in_array('primary_image_url', $columns, true)) {
        $pdo->exec('ALTER TABLE catalog_hotels ADD COLUMN primary_image_url VARCHAR(1024) DEFAULT NULL AFTER longitude');
    }
    if (!in_array('image_updated_at', $columns, true)) {
        $pdo->exec('ALTER TABLE catalog_hotels ADD COLUMN image_updated_at DATETIME DEFAULT NULL AFTER primary_image_url');
    }
    echo "ANYTOUR_HOTEL_MEDIA_SCHEMA_OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ANYTOUR_HOTEL_MEDIA_SCHEMA_FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
