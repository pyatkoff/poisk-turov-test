<?php
/** Synchronize free Tourvisor direct/charter departure-country availability matrices. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/tourvisor-client-v1.php';

function flight_matrix_country_ids(int $departureId, bool $onlyCharter, bool $onlyDirect): array {
    $rows = v2_data_tv_get('/countries', [
        'departureId' => $departureId,
        'onlyCharter' => $onlyCharter,
        'onlyDirect' => $onlyDirect,
    ]);
    $ids = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) $ids[$id] = true;
    }
    return array_map('intval', array_keys($ids));
}

$pdo = v2_data_db();
$now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
$configs = [
    'direct' => [false, true],
    'charter' => [true, false],
    'direct_charter' => [true, true],
];

try {
    $departures = v2_data_tv_get('/departures', ['departureCountryId' => 1]);
    if (!$departures) throw new RuntimeException('Tourvisor returned no departures');
    $pairsByKind = array_fill_keys(array_keys($configs), []);
    $citiesByKind = array_fill_keys(array_keys($configs), []);

    foreach ($departures as $row) {
        if (!is_array($row)) continue;
        $departureId = (int)($row['id'] ?? 0);
        if ($departureId <= 0) continue;
        foreach ($configs as $kind => [$onlyCharter, $onlyDirect]) {
            $countryIds = flight_matrix_country_ids($departureId, $onlyCharter, $onlyDirect);
            if ($countryIds) $citiesByKind[$kind][$departureId] = true;
            foreach ($countryIds as $countryId) {
                $pairsByKind[$kind][] = [$departureId, $countryId];
            }
        }
    }

    if (!$pairsByKind['direct'] || !$pairsByKind['charter'] || !$pairsByKind['direct_charter']) {
        throw new RuntimeException('One or more flight availability matrices returned zero pairs');
    }

    $pdo->beginTransaction();
    foreach ($configs as $kind => $_) {
        $table = 'catalog_departure_countries_' . $kind;
        $pdo->exec("UPDATE {$table} SET is_active=0");
        $stmt = $pdo->prepare("INSERT INTO {$table} (departure_id,country_id,is_active,synced_at)
            VALUES (:departure_id,:country_id,1,:synced)
            ON DUPLICATE KEY UPDATE is_active=1,synced_at=VALUES(synced_at)");
        foreach ($pairsByKind[$kind] as [$departureId, $countryId]) {
            $stmt->execute(['departure_id'=>$departureId,'country_id'=>$countryId,'synced'=>$now]);
        }
    }
    $pdo->commit();

    printf("DEPARTURE_FLIGHT_MATRICES_OK total=%d direct_cities=%d direct_pairs=%d charter_cities=%d charter_pairs=%d direct_charter_cities=%d direct_charter_pairs=%d\n",
        count($departures), count($citiesByKind['direct']), count($pairsByKind['direct']),
        count($citiesByKind['charter']), count($pairsByKind['charter']),
        count($citiesByKind['direct_charter']), count($pairsByKind['direct_charter'])
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'DEPARTURE_FLIGHT_MATRICES_FAILED ' . mb_substr($e->getMessage(), 0, 1000) . "\n");
    exit(1);
}
