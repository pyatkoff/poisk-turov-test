<?php
/** Validate departures catalog shape without exposing DB credentials. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

try {
    $pdo = v2_data_db();
    $columns = array_map('strval', $pdo->query('SHOW COLUMNS FROM catalog_departures')->fetchAll(PDO::FETCH_COLUMN));
    foreach (['id','name','name_genitive','slug','is_active','synced_at','updated_at'] as $required) {
        if (!in_array($required, $columns, true)) {
            throw new RuntimeException('Missing column: ' . $required);
        }
    }
    $count = (int)$pdo->query('SELECT COUNT(*) FROM catalog_departures')->fetchColumn();
    $activeCount = (int)$pdo->query('SELECT COUNT(*) FROM catalog_departures WHERE is_active = 1')->fetchColumn();
    $inactiveCount = max(0, $count - $activeCount);
    $genitiveCount = (int)$pdo->query("SELECT COUNT(*) FROM catalog_departures WHERE name_genitive IS NOT NULL AND TRIM(name_genitive) <> ''")->fetchColumn();
    if ($count <= 0) throw new RuntimeException('Departure catalog is empty');
    if ($activeCount <= 0) throw new RuntimeException('Departure catalog has no active cities');
    if ($activeCount > $count) throw new RuntimeException('Active departure count exceeds total rows');
    echo "ANYTOUR_DEPARTURES_OK rows={$count} active={$activeCount} inactive={$inactiveCount} name_genitive_rows={$genitiveCount}\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ANYTOUR_DEPARTURES_INVALID: ' . $e->getMessage() . "\n");
    exit(1);
}
