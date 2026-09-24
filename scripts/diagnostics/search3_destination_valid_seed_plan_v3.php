<?php
declare(strict_types=1);

/**
 * SEARCH #1646 valid destination seed plan.
 * CURRENT MySQL is read under REPEATABLE READ / READ ONLY.
 * This plan never allocates local IDs and never authorizes an apply.
 */
const SEARCH3_DESTINATION_SEED_PLAN_OPERATION = 'search3-destination-valid-seed-plan-1646-20260924-v3';

function seed_plan_rows(PDO $db, string $sql, array $args = []): array
{
    if (preg_match('/^\s*SELECT\b/i', $sql) !== 1 || str_contains($sql, ';')) {
        throw new RuntimeException('SELECT_ONLY');
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function seed_plan_scalar(PDO $db, string $sql, array $args = []): int
{
    $rows = seed_plan_rows($db, $sql, $args);
    if (count($rows) !== 1 || count($rows[0]) !== 1) {
        throw new RuntimeException('SCALAR_SHAPE');
    }
    $value = array_values($rows[0])[0];
    if (!is_numeric($value) || (int)$value < 0) {
        throw new RuntimeException('SCALAR_VALUE');
    }
    return (int)$value;
}

function seed_plan_table_exists(PDO $db, string $table): bool
{
    if (preg_match('/^[a-z0-9_]{1,64}$/D', $table) !== 1) {
        throw new InvalidArgumentException('TABLE_NAME');
    }
    return seed_plan_scalar(
        $db,
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',
        [$table]
    ) === 1;
}

function seed_plan_positive_id(mixed $value, string $label): int
{
    if ((!is_int($value) && !is_string($value))
        || preg_match('/^[1-9][0-9]*$/D', (string)$value) !== 1
        || filter_var($value, FILTER_VALIDATE_INT) === false
        || (int)$value > 9007199254740991) {
        throw new RuntimeException('INVALID_'.$label.'_ID');
    }
    return (int)$value;
}

function seed_plan_name(mixed $value, string $label): string
{
    if (!is_string($value)) {
        throw new RuntimeException('INVALID_'.$label.'_NAME');
    }
    $name = trim($value);
    if ($name === '' || strlen($name) > 255 || preg_match('//u', $name) !== 1
        || preg_match('/[\x00-\x1f\x7f]/', $name)) {
        throw new RuntimeException('INVALID_'.$label.'_NAME');
    }
    return $name;
}

function seed_plan_add(
    array &$manifest,
    array &$keys,
    array &$slugs,
    array &$bridges,
    string $kind,
    int $sourceId,
    ?string $parentSourceKey,
    string $name
): void {
    $key = $kind.':'.$sourceId;
    $slug = 'tourvisor-'.$kind.'-'.$sourceId;
    $bridge = 'tourvisor|'.$kind.'|'.$sourceId;
    if (isset($keys[$key])) {
        throw new RuntimeException('DUPLICATE_PLAN_KEY');
    }
    $slugKey = $kind.'|'.($parentSourceKey ?? 'root').'|'.$slug;
    if (isset($slugs[$slugKey])) {
        throw new RuntimeException('DUPLICATE_PLAN_SLUG');
    }
    if (isset($bridges[$bridge])) {
        throw new RuntimeException('DUPLICATE_BRIDGE_KEY');
    }
    $keys[$key] = true;
    $slugs[$slugKey] = true;
    $bridges[$bridge] = true;
    $manifest[] = [
        'key' => $key,
        'kind' => $kind,
        'parentSourceKey' => $parentSourceKey,
        'nameRu' => $name,
        'slug' => $slug,
        'bridge' => [
            'provider' => 'tourvisor',
            'kind' => $kind,
            'externalId' => (string)$sourceId,
        ],
    ];
}

function search3_destination_valid_seed_plan(PDO $db, string $sourceSha): array
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
        foreach ($sourceTables as $table) {
            if (!seed_plan_table_exists($db, $table)) {
                throw new RuntimeException('SOURCE_TABLE_MISSING_'.$table);
            }
        }

        $destinationTables = [
            'anytour_destinations_v1' => seed_plan_table_exists($db, 'anytour_destinations_v1'),
            'anytour_destination_sources_v1' => seed_plan_table_exists($db, 'anytour_destination_sources_v1'),
        ];
        $destinationTablesPresent = count(array_filter($destinationTables));

        $countries = seed_plan_rows(
            $db,
            'SELECT id,name FROM catalog_countries WHERE is_active=1 ORDER BY id'
        );
        $regions = seed_plan_rows(
            $db,
            'SELECT r.id,r.country_id,r.name,c.id AS active_country_id FROM catalog_regions r LEFT JOIN catalog_countries c ON c.id=r.country_id AND c.is_active=1 WHERE r.is_active=1 ORDER BY r.id'
        );
        $subregions = seed_plan_rows(
            $db,
            'SELECT s.id,s.region_id,s.name,r.id AS active_region_id,r.country_id,c.id AS active_country_id FROM catalog_subregions s LEFT JOIN catalog_regions r ON r.id=s.region_id AND r.is_active=1 LEFT JOIN catalog_countries c ON c.id=r.country_id AND c.is_active=1 WHERE s.is_active=1 ORDER BY s.id'
        );

        $manifest = [];
        $keys = [];
        $slugs = [];
        $bridges = [];
        $errors = [];
        $countryIds = [];
        $validRegionIds = [];
        $validRegionCountry = [];
        $excluded = [
            'orphanRegionsMissingOrInactiveCountry' => 0,
            'subregionsMissingOrInactiveRegion' => 0,
            'subregionsUnderOrphanCountry' => 0,
        ];
        $orphanCountryIds = [];

        foreach ($countries as $row) {
            $id = seed_plan_positive_id($row['id'] ?? null, 'COUNTRY');
            $name = seed_plan_name($row['name'] ?? null, 'COUNTRY');
            if (isset($countryIds[$id])) {
                $errors[] = 'duplicate-country-id:'.$id;
                continue;
            }
            $countryIds[$id] = true;
            seed_plan_add($manifest, $keys, $slugs, $bridges, 'country', $id, null, $name);
        }

        foreach ($regions as $row) {
            $id = seed_plan_positive_id($row['id'] ?? null, 'REGION');
            $countryId = seed_plan_positive_id($row['country_id'] ?? null, 'REGION_COUNTRY');
            $name = seed_plan_name($row['name'] ?? null, 'REGION');
            if ($row['active_country_id'] === null || !isset($countryIds[$countryId])) {
                $excluded['orphanRegionsMissingOrInactiveCountry']++;
                $orphanCountryIds[$countryId] = ($orphanCountryIds[$countryId] ?? 0) + 1;
                continue;
            }
            if (isset($validRegionIds[$id])) {
                $errors[] = 'duplicate-region-id:'.$id;
                continue;
            }
            $validRegionIds[$id] = true;
            $validRegionCountry[$id] = $countryId;
            seed_plan_add($manifest, $keys, $slugs, $bridges, 'region', $id, 'country:'.$countryId, $name);
        }

        $validSubregions = 0;
        foreach ($subregions as $row) {
            $id = seed_plan_positive_id($row['id'] ?? null, 'SUBREGION');
            $regionId = seed_plan_positive_id($row['region_id'] ?? null, 'SUBREGION_REGION');
            $name = seed_plan_name($row['name'] ?? null, 'SUBREGION');
            if ($row['active_region_id'] === null) {
                $excluded['subregionsMissingOrInactiveRegion']++;
                continue;
            }
            if ($row['active_country_id'] === null || !isset($validRegionIds[$regionId])) {
                $excluded['subregionsUnderOrphanCountry']++;
                continue;
            }
            $validSubregions++;
            seed_plan_add($manifest, $keys, $slugs, $bridges, 'subregion', $id, 'region:'.$regionId, $name);
        }

        $validCountries = count($countryIds);
        $validRegions = count($validRegionIds);
        $plannedRows = count($manifest);
        $plannedBridges = count($bridges);
        if ($plannedRows !== $validCountries + $validRegions + $validSubregions || $plannedRows !== $plannedBridges) {
            $errors[] = 'manifest-count-mismatch';
        }

        $turkeyCountryPresent = isset($countryIds[4]);
        $turkeyRegions = 0;
        foreach ($validRegionCountry as $countryId) {
            if ($countryId === 4) {
                $turkeyRegions++;
            }
        }
        $turkeySubregions = 0;
        foreach ($manifest as $row) {
            if ($row['kind'] !== 'subregion') {
                continue;
            }
            $parent = (string)$row['parentSourceKey'];
            $regionId = (int)substr($parent, strlen('region:'));
            if (($validRegionCountry[$regionId] ?? null) === 4) {
                $turkeySubregions++;
            }
        }
        if (!$turkeyCountryPresent || $turkeyRegions < 1 || $turkeySubregions < 1) {
            $errors[] = 'turkey-valid-hierarchy-missing';
        }

        ksort($orphanCountryIds, SORT_NUMERIC);
        $manifestJson = json_encode(
            $manifest,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $manifestSha256 = hash('sha256', $manifestJson);

        $db->rollBack();

        return [
            'operation' => SEARCH3_DESTINATION_SEED_PLAN_OPERATION,
            'state' => 'completed_read_only',
            'sourceSha' => $sourceSha,
            'sourceCounts' => [
                'countries' => count($countries),
                'regions' => count($regions),
                'subregions' => count($subregions),
            ],
            'validHierarchyCounts' => [
                'countries' => $validCountries,
                'regions' => $validRegions,
                'subregions' => $validSubregions,
            ],
            'excluded' => $excluded,
            'orphanRegionCountryIds' => $orphanCountryIds,
            'turkeyValid' => [
                'countryId' => 4,
                'countryPresent' => $turkeyCountryPresent,
                'regions' => $turkeyRegions,
                'subregions' => $turkeySubregions,
            ],
            'destinationTables' => $destinationTables,
            'destinationTablesPresent' => $destinationTablesPresent,
            'plannedRows' => $plannedRows,
            'plannedBridges' => $plannedBridges,
            'errors' => $errors,
            'manifestSha256' => $manifestSha256,
            'guardedApplyCandidate' => $destinationTablesPresent === 0 && $errors === [],
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

function search3_destination_seed_plan_main(): int
{
    if ((string)getenv('SEARCH3_DESTINATION_SEED_PLAN') !== '1') {
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
        $result = search3_destination_valid_seed_plan(v2_data_db(), $sourceSha);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        return 0;
    } catch (Throwable $error) {
        echo json_encode([
            'operation' => SEARCH3_DESTINATION_SEED_PLAN_OPERATION,
            'state' => 'failed_read_only',
            'sourceSha' => $sourceSha,
            'error' => $error->getMessage(),
            'databaseWrites' => 0,
            'mappingWrites' => 0,
            'providerCalls' => 0,
            'siteFileWrites' => 0,
            'safeToApply' => false,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        return 1;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (string)getenv('SEARCH3_DESTINATION_SEED_PLAN') === '1') {
    exit(search3_destination_seed_plan_main());
}
