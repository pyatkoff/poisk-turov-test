<?php
/** Synchronize Tourvisor departure cities into AnyTour DB. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/tourvisor-client-v1.php';

$pdo = v2_data_db();
$now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

try {
    $rows = v2_data_tv_get('/departures', ['departureCountryId' => 1]);
    $stmt = $pdo->prepare("INSERT INTO catalog_departures (id,name,slug,is_active,synced_at)
        VALUES (:id,:name,:slug,1,:synced)
        ON DUPLICATE KEY UPDATE name=VALUES(name),slug=VALUES(slug),is_active=1,synced_at=VALUES(synced_at)");

    $count = 0;
    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        $name = trim((string)($row['russianName'] ?? $row['name'] ?? ''));
        if ($id <= 0 || $name === '') continue;
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'slug' => v2_data_slug($name),
            'synced' => $now,
        ]);
        $count++;
    }

    fwrite(STDOUT, "DEPARTURES_OK rows={$count}\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'DEPARTURES_FAILED ' . mb_substr($e->getMessage(), 0, 1000) . "\n");
    exit(1);
}
