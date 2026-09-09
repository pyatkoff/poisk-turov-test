<?php
/** Synchronize Tourvisor departure cities and free country availability into AnyTour DB. */
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

/** Return normalized available country IDs from the free Tourvisor directory request. */
function departure_available_country_ids(int $departureId): array
{
    if ($departureId <= 0) return [];
    $rows = v2_data_tv_get('/countries', [
        'departureId' => $departureId,
        'onlyCharter' => false,
        'onlyDirect' => false,
    ]);
    $ids = [];
    foreach ($rows as $country) {
        if (!is_array($country)) continue;
        $id = (int)($country['id'] ?? 0);
        if ($id > 0) $ids[$id] = true;
    }
    return array_map('intval', array_keys($ids));
}

$pdo = v2_data_db();
$now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

try {
    $sourceRows = v2_data_tv_get('/departures', ['departureCountryId' => 1]);
    $departures = [];
    $pairs = [];
    foreach ($sourceRows as $row) {
        if (!is_array($row)) continue;
        $id = (int)($row['id'] ?? 0);
        $name = departure_name($row);
        if ($id <= 0 || $name === '') continue;
        $countryIds = departure_available_country_ids($id);
        $departures[] = [
            'id' => $id,
            'name' => $name,
            'name_genitive' => departure_genitive($row),
            'slug' => v2_data_slug($name),
            'is_active' => $countryIds !== [] ? 1 : 0,
        ];
        foreach ($countryIds as $countryId) {
            $pairs[] = ['departure_id' => $id, 'country_id' => $countryId];
        }
    }

    if (!$departures) {
        throw new RuntimeException('Tourvisor returned no valid Russian departure cities');
    }

    $activeCount = count(array_filter($departures, static fn(array $row): bool => (int)$row['is_active'] === 1));
    if ($activeCount <= 0 || !$pairs) {
        throw new RuntimeException('Tourvisor availability check returned zero active departure-country pairs');
    }

    $pdo->beginTransaction();
    $departureStmt = $pdo->prepare("INSERT INTO catalog_departures (id,name,name_genitive,slug,is_active,synced_at)
        VALUES (:id,:name,:name_genitive,:slug,:is_active,:synced)
        ON DUPLICATE KEY UPDATE
            name=VALUES(name),
            name_genitive=COALESCE(VALUES(name_genitive),name_genitive),
            slug=VALUES(slug),
            is_active=VALUES(is_active),
            synced_at=VALUES(synced_at)");

    foreach ($departures as $departure) {
        $departureStmt->execute([
            'id' => $departure['id'],
            'name' => $departure['name'],
            'name_genitive' => $departure['name_genitive'],
            'slug' => $departure['slug'],
            'is_active' => $departure['is_active'],
            'synced' => $now,
        ]);
    }

    $pdo->exec('UPDATE catalog_departure_countries SET is_active=0');
    $pairStmt = $pdo->prepare("INSERT INTO catalog_departure_countries (departure_id,country_id,is_active,synced_at)
        VALUES (:departure_id,:country_id,1,:synced)
        ON DUPLICATE KEY UPDATE is_active=1,synced_at=VALUES(synced_at)");
    foreach ($pairs as $pair) {
        $pairStmt->execute([
            'departure_id' => $pair['departure_id'],
            'country_id' => $pair['country_id'],
            'synced' => $now,
        ]);
    }
    $pdo->commit();

    $inactiveCount = count($departures) - $activeCount;
    fwrite(STDOUT, "DEPARTURES_OK total=" . count($departures) . " active={$activeCount} inactive={$inactiveCount} pairs=" . count($pairs) . "\n");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'DEPARTURES_FAILED ' . mb_substr($e->getMessage(), 0, 1000) . "\n");
    exit(1);
}
