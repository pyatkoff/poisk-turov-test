<?php
/** Synchronize Tourvisor departure cities into AnyTour DB. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/tourvisor-client-v1.php';

function departure_name(array $row): string
{
    foreach (['russianName', 'fullRussianName', 'name'] as $key) {
        $value = trim((string)($row[$key] ?? ''));
        if ($value !== '') return $value;
    }
    return '';
}

function departure_genitive(array $row): ?string
{
    foreach (['nameGenitive', 'russianNameGenitive', 'fullRussianNameGenitive'] as $key) {
        $value = trim((string)($row[$key] ?? ''));
        if ($value !== '') return $value;
    }
    return null;
}

$pdo = v2_data_db();
$now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

try {
    $rows = v2_data_tv_get('/departures', ['departureCountryId' => 1]);
    $stmt = $pdo->prepare("INSERT INTO catalog_departures (id,name,name_genitive,slug,is_active,synced_at)
        VALUES (:id,:name,:name_genitive,:slug,1,:synced)
        ON DUPLICATE KEY UPDATE
            name=VALUES(name),
            name_genitive=COALESCE(VALUES(name_genitive),name_genitive),
            slug=VALUES(slug),
            is_active=1,
            synced_at=VALUES(synced_at)");

    $count = 0;
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $id = (int)($row['id'] ?? 0);
        $name = departure_name($row);
        if ($id <= 0 || $name === '') continue;
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'name_genitive' => departure_genitive($row),
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
