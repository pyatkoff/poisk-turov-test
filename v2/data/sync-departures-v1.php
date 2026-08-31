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

/**
 * Tourvisor /departures is a directory and can contain cities with no current
 * package-tour destinations. /countries?departureId=... is also a directory
 * request (not a paid search-start request) and reflects whether a departure
 * currently has at least one available destination.
 */
function departure_has_available_countries(int $departureId): bool
{
    if ($departureId <= 0) return false;
    $countries = v2_data_tv_get('/countries', [
        'departureId' => $departureId,
        'onlyCharter' => false,
        'onlyDirect' => false,
    ]);

    foreach ($countries as $country) {
        if (is_array($country) && (int)($country['id'] ?? 0) > 0) return true;
    }
    return false;
}

$pdo = v2_data_db();
$now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

try {
    $sourceRows = v2_data_tv_get('/departures', ['departureCountryId' => 1]);
    $departures = [];
    foreach ($sourceRows as $row) {
        if (!is_array($row)) continue;
        $id = (int)($row['id'] ?? 0);
        $name = departure_name($row);
        if ($id <= 0 || $name === '') continue;
        $departures[] = [
            'id' => $id,
            'name' => $name,
            'name_genitive' => departure_genitive($row),
            'slug' => v2_data_slug($name),
            'is_active' => departure_has_available_countries($id) ? 1 : 0,
        ];
    }

    if (!$departures) {
        throw new RuntimeException('Tourvisor returned no valid Russian departure cities');
    }

    $activeCount = count(array_filter($departures, static fn(array $row): bool => (int)$row['is_active'] === 1));
    if ($activeCount <= 0) {
        throw new RuntimeException('Tourvisor availability check returned zero active departure cities');
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO catalog_departures (id,name,name_genitive,slug,is_active,synced_at)
        VALUES (:id,:name,:name_genitive,:slug,:is_active,:synced)
        ON DUPLICATE KEY UPDATE
            name=VALUES(name),
            name_genitive=COALESCE(VALUES(name_genitive),name_genitive),
            slug=VALUES(slug),
            is_active=VALUES(is_active),
            synced_at=VALUES(synced_at)");

    foreach ($departures as $departure) {
        $stmt->execute([
            'id' => $departure['id'],
            'name' => $departure['name'],
            'name_genitive' => $departure['name_genitive'],
            'slug' => $departure['slug'],
            'is_active' => $departure['is_active'],
            'synced' => $now,
        ]);
    }
    $pdo->commit();

    $inactiveCount = count($departures) - $activeCount;
    fwrite(STDOUT, "DEPARTURES_OK total=" . count($departures) . " active={$activeCount} inactive={$inactiveCount}\n");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'DEPARTURES_FAILED ' . mb_substr($e->getMessage(), 0, 1000) . "\n");
    exit(1);
}
