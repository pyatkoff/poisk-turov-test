<?php
declare(strict_types=1);

/**
 * SEARCH #1646 CURRENT destination identity census.
 * Supplier-free and database READ ONLY. It never authorizes an apply.
 */

const SEARCH3_DESTINATION_CENSUS_OPERATION = 'search3-destination-identity-current-census-1646-20260924-v2';

function census_rows(PDO $db, string $sql, array $args = []): array
{
    if (preg_match('/^\s*SELECT\b/i', $sql) !== 1 || str_contains($sql, ';')) {
        throw new RuntimeException('SELECT_ONLY');
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function census_scalar(PDO $db, string $sql, array $args = []): int
{
    $rows = census_rows($db, $sql, $args);
    if (count($rows) !== 1 || count($rows[0]) !== 1) {
        throw new RuntimeException('SCALAR_SHAPE');
    }
    $value = array_values($rows[0])[0];
    if (!is_numeric($value) || (int)$value < 0) {
        throw new RuntimeException('SCALAR_VALUE');
    }
    return (int)$value;
}

function census_table_exists(PDO $db, string $table): bool
{
    if (preg_match('/^[a-z0-9_]{1,64}$/D', $table) !== 1) {
        throw new InvalidArgumentException('TABLE_NAME');
    }
    return census_scalar(
        $db,
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',
        [$table]
    ) === 1;
}

function census_group_counts(array $rows, string $first, string $second): array
{
    $out = [];
    foreach ($rows as $row) {
        $a = (string)($row[$first] ?? '');
        $b = (string)($row[$second] ?? '');
        $count = (int)($row['n'] ?? -1);
        if ($a === '' || $b === '' || $count < 0) {
            throw new RuntimeException('GROUP_SHAPE');
        }
        $out[$a][$b] = $count;
    }
    ksort($out);
    foreach ($out as &$nested) {
        ksort($nested);
    }
    unset($nested);
    return $out;
}

function search3_destination_identity_current_census(PDO $db, string $sourceSha): array
{
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql' || $db->inTransaction()) {
        throw new RuntimeException('DEDICATED_MYSQL_REQUIRED');
    }
    if (preg_match('/^[0-9a-f]{40}$/D', $sourceSha) !== 1) {
        throw new InvalidArgumentException('SOURCE_SHA');
    }

    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('SET TRANSACTION READ ONLY');
    $db->beginTransaction();

    try {
        $sourceTables = ['catalog_countries', 'catalog_regions', 'catalog_subregions'];
        $destinationTables = ['anytour_destinations_v1', 'anytour_destination_sources_v1'];

        $sourcePresence = [];
        foreach ($sourceTables as $table) {
            $sourcePresence[$table] = census_table_exists($db, $table);
        }
        $destinationPresence = [];
        foreach ($destinationTables as $table) {
            $destinationPresence[$table] = census_table_exists($db, $table);
        }

        $sourceCounts = ['countries' => null, 'regions' => null, 'subregions' => null];
        $orphans = ['regions' => null, 'subregions' => null];
        $turkey = ['countryId' => 4, 'regions' => null, 'subregions' => null, 'visibleLabels' => []];

        if (!in_array(false, $sourcePresence, true)) {
            $sourceCounts = [
                'countries' => census_scalar($db, 'SELECT COUNT(*) FROM catalog_countries WHERE is_active=1'),
                'regions' => census_scalar($db, 'SELECT COUNT(*) FROM catalog_regions WHERE is_active=1'),
                'subregions' => census_scalar($db, 'SELECT COUNT(*) FROM catalog_subregions WHERE is_active=1'),
            ];
            $orphans = [
                'regions' => census_scalar(
                    $db,
                    'SELECT COUNT(*) FROM catalog_regions r LEFT JOIN catalog_countries c ON c.id=r.country_id AND c.is_active=1 WHERE r.is_active=1 AND c.id IS NULL'
                ),
                'subregions' => census_scalar(
                    $db,
                    'SELECT COUNT(*) FROM catalog_subregions s LEFT JOIN catalog_regions r ON r.id=s.region_id AND r.is_active=1 WHERE s.is_active=1 AND r.id IS NULL'
                ),
            ];
            $turkey['regions'] = census_scalar(
                $db,
                'SELECT COUNT(*) FROM catalog_regions WHERE is_active=1 AND country_id=?',
                [4]
            );
            $turkey['subregions'] = census_scalar(
                $db,
                'SELECT COUNT(*) FROM catalog_subregions s JOIN catalog_regions r ON r.id=s.region_id AND r.is_active=1 WHERE s.is_active=1 AND r.country_id=?',
                [4]
            );

            $regions = census_rows(
                $db,
                "SELECT id,name,'region' AS kind FROM catalog_regions WHERE is_active=1 AND country_id=? AND name IN (?,?,?) ORDER BY id",
                [4, 'Белек', 'Кемер', 'Сиде']
            );
            $subs = census_rows(
                $db,
                "SELECT s.id,s.name,'subregion' AS kind FROM catalog_subregions s JOIN catalog_regions r ON r.id=s.region_id AND r.is_active=1 WHERE s.is_active=1 AND r.country_id=? AND s.name IN (?,?,?) ORDER BY s.id",
                [4, 'Белек', 'Кемер', 'Сиде']
            );
            $visible = [];
            foreach (array_merge($regions, $subs) as $row) {
                $id = (int)($row['id'] ?? 0);
                $name = trim((string)($row['name'] ?? ''));
                $kind = (string)($row['kind'] ?? '');
                if ($id < 1 || $name === '' || !in_array($kind, ['region', 'subregion'], true)) {
                    throw new RuntimeException('VISIBLE_LABEL_SHAPE');
                }
                $visible[] = ['kind' => $kind, 'sourceId' => $id, 'label' => $name];
            }
            $turkey['visibleLabels'] = $visible;
        }

        $destinationTablesPresent = count(array_filter($destinationPresence));
        $localCounts = [];
        $acceptedBridges = [];
        $acceptedTourvisor = 0;

        if ($destinationTablesPresent === 2) {
            $localRows = census_rows(
                $db,
                'SELECT kind,COUNT(*) AS n FROM anytour_destinations_v1 WHERE is_active=1 GROUP BY kind ORDER BY kind'
            );
            foreach ($localRows as $row) {
                $kind = (string)($row['kind'] ?? '');
                $n = (int)($row['n'] ?? -1);
                if (!in_array($kind, ['country', 'region', 'subregion'], true) || $n < 0) {
                    throw new RuntimeException('LOCAL_COUNT_SHAPE');
                }
                $localCounts[$kind] = $n;
            }

            $bridgeRows = census_rows(
                $db,
                "SELECT provider,kind,COUNT(*) AS n FROM anytour_destination_sources_v1 WHERE state='accepted' GROUP BY provider,kind ORDER BY provider,kind"
            );
            $acceptedBridges = census_group_counts($bridgeRows, 'provider', 'kind');
            $acceptedTourvisor = census_scalar(
                $db,
                "SELECT COUNT(*) FROM anytour_destination_sources_v1 WHERE state='accepted' AND provider='tourvisor'"
            );
        }

        $db->rollBack();

        return [
            'operation' => SEARCH3_DESTINATION_CENSUS_OPERATION,
            'state' => 'completed_read_only',
            'sourceSha' => $sourceSha,
            'sourceTables' => $sourcePresence,
            'sourceCounts' => $sourceCounts,
            'orphans' => $orphans,
            'turkey' => $turkey,
            'destinationTables' => $destinationPresence,
            'destinationTablesPresent' => $destinationTablesPresent,
            'localDestinationCounts' => $localCounts,
            'acceptedBridges' => $acceptedBridges,
            'acceptedTourvisorBridges' => $acceptedTourvisor,
            'databaseWrites' => 0,
            'mappingWrites' => 0,
            'providerCalls' => 0,
            'siteFileWrites' => 0,
            'safeToApply' => false,
        ];
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function search3_destination_census_main(): int
{
    if ((string)getenv('SEARCH3_DESTINATION_CENSUS') !== '1') {
        fwrite(STDERR, "EXPLICIT_READ_ONLY_EXECUTION_REQUIRED\n");
        return 2;
    }
    $root = realpath((string)getenv('ANYTOUR_ROOT'));
    $sourceSha = (string)getenv('SEARCH3_SOURCE_SHA');
    if ($root === false || !is_file($root.'/data/db-v1.php')) {
        fwrite(STDERR, "ANYTOUR_ROOT_INVALID\n");
        return 2;
    }
    require_once $root.'/data/db-v1.php';
    try {
        $result = search3_destination_identity_current_census(v2_data_db(), $sourceSha);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        return 0;
    } catch (Throwable $error) {
        $result = [
            'operation' => SEARCH3_DESTINATION_CENSUS_OPERATION,
            'state' => 'failed_read_only',
            'sourceSha' => $sourceSha,
            'error' => $error->getMessage(),
            'databaseWrites' => 0,
            'mappingWrites' => 0,
            'providerCalls' => 0,
            'siteFileWrites' => 0,
            'safeToApply' => false,
        ];
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        return 1;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (string)getenv('SEARCH3_DESTINATION_CENSUS') === '1') {
    exit(search3_destination_census_main());
}
