<?php
/** Ensure catalog hotels retain one trusted primary image URL from Tourvisor-backed data already collected by AnyTour. */
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

    // Reuse photos already captured by the existing hot-tour feed so the media
    // catalog is useful immediately, without making any new Tourvisor calls.
    $backfilled = 0;
    $tables = array_map('strval', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
    if (in_array('hot_tours_current', $tables, true)) {
        $sql = "UPDATE catalog_hotels h
                JOIN (
                    SELECT hotel_id, MAX(picture_url) AS picture_url, MAX(fetched_at) AS fetched_at
                    FROM hot_tours_current
                    WHERE picture_url IS NOT NULL AND TRIM(picture_url)<>''
                    GROUP BY hotel_id
                ) src ON src.hotel_id=h.id
                SET h.primary_image_url=src.picture_url,
                    h.image_updated_at=COALESCE(src.fetched_at,NOW())
                WHERE h.primary_image_url IS NULL OR TRIM(h.primary_image_url)=''";
        $backfilled = (int)$pdo->exec($sql);
    }

    $withImages = (int)$pdo->query("SELECT COUNT(*) FROM catalog_hotels WHERE primary_image_url IS NOT NULL AND TRIM(primary_image_url)<>''")->fetchColumn();
    echo "ANYTOUR_HOTEL_MEDIA_SCHEMA_OK backfilled={$backfilled} hotels_with_images={$withImages}\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ANYTOUR_HOTEL_MEDIA_SCHEMA_FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
