<?php
declare(strict_types=1);

/**
 * SEARCH #1646 guarded additive destination identity schema + Tourvisor seed.
 * One-shot writer. It must reconstruct and hash-pin the CURRENT valid manifest
 * before any DDL, then seed only that exact manifest.
 */

const SEARCH3_DESTINATION_APPLY_OPERATION = 'search3-destination-valid-seed-apply-1646-20260924-v1';
const SEARCH3_DESTINATION_APPLY_LOCK = 'search3_destination_valid_seed_apply_v1';

function destination_select(PDO $db, string $sql, array $args = []): array
{
    if (preg_match('/^\s*SELECT\b/i', $sql) !== 1 || str_contains($sql, ';')) {
        throw new RuntimeException('SELECT_ONLY');
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function destination_scalar(PDO $db, string $sql, array $args = []): mixed
{
    $rows = destination_select($db, $sql, $args);
    if (count($rows) !== 1 || count($rows[0]) !== 1) {
        throw new RuntimeException('SCALAR_SHAPE');
    }
    return array_values($rows[0])[0];
}

function destination_count(PDO $db, string $sql, array $args = []): int
{
    $value = destination_scalar($db, $sql, $args);
    if (!is_numeric($value) || (int)$value < 0) {
        throw new RuntimeException('COUNT_VALUE');
    }
    return (int)$value;
}

function destination_table_exists(PDO $db, string $table): bool
{
    if (preg_match('/^[a-z0-9_]{1,64}$/D', $table) !== 1) {
        throw new InvalidArgumentException('TABLE_NAME');
    }
    return destination_count(
        $db,
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',
        [$table]
    ) === 1;
}

function destination_positive_id(mixed $value, string $label): int
{
    if ((!is_int($value) && !is_string($value))
        || preg_match('/^[1-9][0-9]*$/D', (string)$value) !== 1
        || filter_var($value, FILTER_VALIDATE_INT) === false
        || (int)$value > 9007199254740991) {
        throw new RuntimeException('INVALID_'.$label.'_ID');
    }
    return (int)$value;
}

function destination_name(mixed $value, string $label): string
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

function destination_manifest_add(
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
    $bridgeKey = 'tourvisor|'.$kind.'|'.$sourceId;
    if (isset($keys[$key])) {
        throw new RuntimeException('DUPLICATE_PLAN_KEY');
    }
    $slugKey = $kind.'|'.($parentSourceKey ?? 'root').'|'.$slug;
    if (isset($slugs[$slugKey])) {
        throw new RuntimeException('DUPLICATE_PLAN_SLUG');
    }
    if (isset($bridges[$bridgeKey])) {
        throw new RuntimeException('DUPLICATE_BRIDGE_KEY');
    }
    $keys[$key] = true;
    $slugs[$slugKey] = true;
    $bridges[$bridgeKey] = true;
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

function destination_build_manifest_current(PDO $db): array
{
    foreach (['catalog_countries', 'catalog_regions', 'catalog_subregions'] as $table) {
        if (!destination_table_exists($db, $table)) {
            throw new RuntimeException('SOURCE_TABLE_MISSING_'.$table);
        }
    }

    $countries = destination_select(
        $db,
        'SELECT id,name FROM catalog_countries WHERE is_active=1 ORDER BY id'
    );
    $regions = destination_select(
        $db,
        'SELECT r.id,r.country_id,r.name,c.id AS active_country_id FROM catalog_regions r LEFT JOIN catalog_countries c ON c.id=r.country_id AND c.is_active=1 WHERE r.is_active=1 ORDER BY r.id'
    );
    $subregions = destination_select(
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

    foreach ($countries as $row) {
        $id = destination_positive_id($row['id'] ?? null, 'COUNTRY');
        $name = destination_name($row['name'] ?? null, 'COUNTRY');
        if (isset($countryIds[$id])) {
            $errors[] = 'duplicate-country-id:'.$id;
            continue;
        }
        $countryIds[$id] = true;
        destination_manifest_add($manifest, $keys, $slugs, $bridges, 'country', $id, null, $name);
    }

    foreach ($regions as $row) {
        $id = destination_positive_id($row['id'] ?? null, 'REGION');
        $countryId = destination_positive_id($row['country_id'] ?? null, 'REGION_COUNTRY');
        $name = destination_name($row['name'] ?? null, 'REGION');
        if ($row['active_country_id'] === null || !isset($countryIds[$countryId])) {
            $excluded['orphanRegionsMissingOrInactiveCountry']++;
            continue;
        }
        if (isset($validRegionIds[$id])) {
            $errors[] = 'duplicate-region-id:'.$id;
            continue;
        }
        $validRegionIds[$id] = true;
        $validRegionCountry[$id] = $countryId;
        destination_manifest_add($manifest, $keys, $slugs, $bridges, 'region', $id, 'country:'.$countryId, $name);
    }

    $validSubregions = 0;
    foreach ($subregions as $row) {
        $id = destination_positive_id($row['id'] ?? null, 'SUBREGION');
        $regionId = destination_positive_id($row['region_id'] ?? null, 'SUBREGION_REGION');
        $name = destination_name($row['name'] ?? null, 'SUBREGION');
        if ($row['active_region_id'] === null) {
            $excluded['subregionsMissingOrInactiveRegion']++;
            continue;
        }
        if ($row['active_country_id'] === null || !isset($validRegionIds[$regionId])) {
            $excluded['subregionsUnderOrphanCountry']++;
            continue;
        }
        $validSubregions++;
        destination_manifest_add($manifest, $keys, $slugs, $bridges, 'subregion', $id, 'region:'.$regionId, $name);
    }

    $validCountries = count($countryIds);
    $validRegions = count($validRegionIds);
    $plannedRows = count($manifest);
    if ($plannedRows !== $validCountries + $validRegions + $validSubregions || $plannedRows !== count($bridges)) {
        $errors[] = 'manifest-count-mismatch';
    }

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
        $regionId = (int)substr((string)$row['parentSourceKey'], strlen('region:'));
        if (($validRegionCountry[$regionId] ?? null) === 4) {
            $turkeySubregions++;
        }
    }
    if (!isset($countryIds[4]) || $turkeyRegions < 1 || $turkeySubregions < 1) {
        $errors[] = 'turkey-valid-hierarchy-missing';
    }

    $json = json_encode(
        $manifest,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );

    return [
        'manifest' => $manifest,
        'manifestSha256' => hash('sha256', $json),
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
        'turkeyValid' => [
            'countryId' => 4,
            'countryPresent' => isset($countryIds[4]),
            'regions' => $turkeyRegions,
            'subregions' => $turkeySubregions,
        ],
        'plannedRows' => $plannedRows,
        'plannedBridges' => count($bridges),
        'errors' => $errors,
    ];
}

function destination_schema_statements(): array
{
    return [
        <<<'SQL'
CREATE TABLE anytour_destinations_v1 (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('country','region','subregion') NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  name_ru VARCHAR(255) NOT NULL,
  slug VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
  is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_anytour_destination_slug (kind,parent_id,slug),
  KEY ix_anytour_destination_parent (parent_id,kind,is_active),
  CONSTRAINT fk_anytour_destination_parent FOREIGN KEY (parent_id) REFERENCES anytour_destinations_v1(id),
  CONSTRAINT ck_anytour_destination_active CHECK (is_active IN (0,1)),
  CONSTRAINT ck_anytour_destination_revision CHECK (revision>0),
  CONSTRAINT ck_anytour_destination_parent CHECK ((kind='country' AND parent_id IS NULL) OR (kind<>'country' AND parent_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin
SQL,
        <<<'SQL'
CREATE TABLE anytour_destination_sources_v1 (
  provider VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  kind ENUM('country','region','subregion') NOT NULL,
  external_id VARBINARY(128) NOT NULL,
  anytour_destination_id BIGINT UNSIGNED NULL,
  state ENUM('pending','accepted','rejected','conflict') NOT NULL,
  evidence_ref VARCHAR(255) NOT NULL,
  evidence_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  reviewed_by VARCHAR(128) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (provider,kind,external_id),
  KEY ix_anytour_destination_reverse (anytour_destination_id,provider,kind,state),
  CONSTRAINT fk_anytour_destination_source_target FOREIGN KEY (anytour_destination_id) REFERENCES anytour_destinations_v1(id),
  CONSTRAINT ck_anytour_destination_provider CHECK (provider IN ('tourvisor','anex','samo','andromeda')),
  CONSTRAINT ck_anytour_destination_source_state CHECK ((state='accepted' AND anytour_destination_id IS NOT NULL) OR (state<>'accepted' AND anytour_destination_id IS NULL)),
  CONSTRAINT ck_anytour_destination_source_evidence CHECK (OCTET_LENGTH(external_id)>0 AND CHAR_LENGTH(evidence_ref)>0 AND CHAR_LENGTH(evidence_sha256)=64 AND CHAR_LENGTH(reviewed_by)>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin
SQL,
    ];
}

function destination_kind_counts(PDO $db): array
{
    $rows = destination_select(
        $db,
        'SELECT kind,COUNT(*) AS n FROM anytour_destinations_v1 WHERE is_active=1 GROUP BY kind ORDER BY kind'
    );
    $counts = ['country' => 0, 'region' => 0, 'subregion' => 0];
    foreach ($rows as $row) {
        $kind = (string)($row['kind'] ?? '');
        $n = (int)($row['n'] ?? -1);
        if (!array_key_exists($kind, $counts) || $n < 0) {
            throw new RuntimeException('KIND_COUNT_SHAPE');
        }
        $counts[$kind] = $n;
    }
    return $counts;
}

function destination_turkey_readback(PDO $db): array
{
    $rows = destination_select(
        $db,
        "SELECT anytour_destination_id FROM anytour_destination_sources_v1 WHERE provider='tourvisor' AND kind='country' AND external_id=? AND state='accepted' LIMIT 2",
        ['4']
    );
    if (count($rows) !== 1) {
        throw new RuntimeException('TURKEY_COUNTRY_BRIDGE');
    }
    $countryLocalId = destination_positive_id($rows[0]['anytour_destination_id'] ?? null, 'TURKEY_LOCAL');
    $regions = destination_count(
        $db,
        "SELECT COUNT(*) FROM anytour_destinations_v1 WHERE kind='region' AND parent_id=? AND is_active=1",
        [$countryLocalId]
    );
    $subregions = destination_count(
        $db,
        "SELECT COUNT(*) FROM anytour_destinations_v1 s JOIN anytour_destinations_v1 r ON r.id=s.parent_id AND r.kind='region' AND r.is_active=1 WHERE s.kind='subregion' AND s.is_active=1 AND r.parent_id=?",
        [$countryLocalId]
    );
    return [
        'countryLocalId' => $countryLocalId,
        'regions' => $regions,
        'subregions' => $subregions,
    ];
}

function destination_readback(PDO $db): array
{
    return [
        'destinationRows' => destination_count($db, 'SELECT COUNT(*) FROM anytour_destinations_v1'),
        'acceptedTourvisorBridges' => destination_count(
            $db,
            "SELECT COUNT(*) FROM anytour_destination_sources_v1 WHERE provider='tourvisor' AND state='accepted'"
        ),
        'linkedAcceptedRows' => destination_count(
            $db,
            "SELECT COUNT(*) FROM anytour_destination_sources_v1 s JOIN anytour_destinations_v1 d ON d.id=s.anytour_destination_id WHERE s.provider='tourvisor' AND s.state='accepted' AND d.is_active=1"
        ),
        'kindCounts' => destination_kind_counts($db),
        'turkey' => destination_turkey_readback($db),
    ];
}

function destination_assert_readback(array $readback, int $expectedRows, array $expectedKinds, array $expectedTurkey): void
{
    if ($readback['destinationRows'] !== $expectedRows
        || $readback['acceptedTourvisorBridges'] !== $expectedRows
        || $readback['linkedAcceptedRows'] !== $expectedRows) {
        throw new RuntimeException('READBACK_TOTAL_MISMATCH');
    }
    if ($readback['kindCounts'] !== $expectedKinds) {
        throw new RuntimeException('READBACK_KIND_MISMATCH');
    }
    if (($readback['turkey']['regions'] ?? null) !== $expectedTurkey['regions']
        || ($readback['turkey']['subregions'] ?? null) !== $expectedTurkey['subregions']) {
        throw new RuntimeException('READBACK_TURKEY_MISMATCH');
    }
}

function destination_acquire_lock(PDO $db): void
{
    $lock = destination_scalar($db, 'SELECT GET_LOCK(?,0)', [SEARCH3_DESTINATION_APPLY_LOCK]);
    if ((int)$lock !== 1) {
        throw new RuntimeException('APPLY_LOCK_BUSY');
    }
}

function destination_release_lock(PDO $db): void
{
    try {
        destination_scalar($db, 'SELECT RELEASE_LOCK(?)', [SEARCH3_DESTINATION_APPLY_LOCK]);
    } catch (Throwable) {
        // The DB connection closing also releases the advisory lock.
    }
}

function search3_destination_valid_seed_apply(
    PDO $db,
    string $expectedManifestSha256,
    int $expectedRows,
    string $evidenceRef,
    string $reviewedBy
): array {
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql' || $db->inTransaction()) {
        throw new RuntimeException('DEDICATED_MYSQL_REQUIRED');
    }
    if (preg_match('/^[a-f0-9]{64}$/D', $expectedManifestSha256) !== 1 || $expectedRows < 1) {
        throw new InvalidArgumentException('EXPECTED_PLAN');
    }
    if (trim($evidenceRef) === '' || strlen($evidenceRef) > 255
        || trim($reviewedBy) === '' || strlen($reviewedBy) > 128) {
        throw new InvalidArgumentException('EVIDENCE_METADATA');
    }

    $schemaCreated = 0;
    destination_acquire_lock($db);
    try {
        if (destination_table_exists($db, 'anytour_destinations_v1')
            || destination_table_exists($db, 'anytour_destination_sources_v1')) {
            throw new RuntimeException('DESTINATION_TABLES_ALREADY_PRESENT');
        }

        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('SET TRANSACTION READ ONLY');
        $db->beginTransaction();
        $preflight = destination_build_manifest_current($db);
        $db->rollBack();

        if ($preflight['manifestSha256'] !== $expectedManifestSha256
            || $preflight['plannedRows'] !== $expectedRows
            || $preflight['plannedBridges'] !== $expectedRows
            || $preflight['errors'] !== []) {
            throw new RuntimeException('PLAN_DRIFT_BEFORE_DDL');
        }

        if (destination_table_exists($db, 'anytour_destinations_v1')
            || destination_table_exists($db, 'anytour_destination_sources_v1')) {
            throw new RuntimeException('DESTINATION_TABLES_APPEARED');
        }

        foreach (destination_schema_statements() as $statement) {
            $db->exec($statement);
            $schemaCreated++;
        }
        if ($schemaCreated !== 2) {
            throw new RuntimeException('SCHEMA_CREATE_COUNT');
        }

        $db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $db->beginTransaction();
        try {
            $current = destination_build_manifest_current($db);
            if ($current['manifestSha256'] !== $expectedManifestSha256
                || $current['plannedRows'] !== $expectedRows
                || $current['plannedBridges'] !== $expectedRows
                || $current['errors'] !== []) {
                throw new RuntimeException('PLAN_DRIFT_AFTER_DDL');
            }

            $insertDestination = $db->prepare(
                'INSERT INTO anytour_destinations_v1(kind,parent_id,name_ru,slug,revision,is_active,created_at,updated_at) VALUES(?,?,?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
            );
            $insertBridge = $db->prepare(
                "INSERT INTO anytour_destination_sources_v1(provider,kind,external_id,anytour_destination_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at) VALUES('tourvisor',?,?,?,'accepted',?,?,?,UTC_TIMESTAMP())"
            );

            $localIds = [];
            $destinationWrites = 0;
            $mappingWrites = 0;

            foreach ($current['manifest'] as $row) {
                $parentId = null;
                if ($row['parentSourceKey'] !== null) {
                    $parentKey = (string)$row['parentSourceKey'];
                    if (!isset($localIds[$parentKey])) {
                        throw new RuntimeException('PARENT_LOCAL_ID_MISSING');
                    }
                    $parentId = $localIds[$parentKey];
                }

                $insertDestination->execute([
                    $row['kind'],
                    $parentId,
                    $row['nameRu'],
                    $row['slug'],
                ]);
                $localId = (int)$db->lastInsertId();
                if ($localId < 1 || isset($localIds[$row['key']])) {
                    throw new RuntimeException('LOCAL_ID_ALLOCATION');
                }
                $localIds[$row['key']] = $localId;
                $destinationWrites++;

                $externalId = (string)$row['bridge']['externalId'];
                $evidenceSha = hash(
                    'sha256',
                    $expectedManifestSha256.'|'.$row['key'].'|tourvisor|'.$row['kind'].'|'.$externalId
                );
                $insertBridge->execute([
                    $row['kind'],
                    $externalId,
                    $localId,
                    $evidenceRef,
                    $evidenceSha,
                    $reviewedBy,
                ]);
                $mappingWrites++;
            }

            if ($destinationWrites !== $expectedRows || $mappingWrites !== $expectedRows) {
                throw new RuntimeException('WRITE_COUNT_MISMATCH');
            }

            $expectedKinds = [
                'country' => $current['validHierarchyCounts']['countries'],
                'region' => $current['validHierarchyCounts']['regions'],
                'subregion' => $current['validHierarchyCounts']['subregions'],
            ];
            $insideReadback = destination_readback($db);
            destination_assert_readback(
                $insideReadback,
                $expectedRows,
                $expectedKinds,
                [
                    'regions' => $current['turkeyValid']['regions'],
                    'subregions' => $current['turkeyValid']['subregions'],
                ]
            );

            $db->commit();

            $postCommit = destination_readback($db);
            destination_assert_readback(
                $postCommit,
                $expectedRows,
                $expectedKinds,
                [
                    'regions' => $current['turkeyValid']['regions'],
                    'subregions' => $current['turkeyValid']['subregions'],
                ]
            );

            return [
                'operation' => SEARCH3_DESTINATION_APPLY_OPERATION,
                'state' => 'committed_verified',
                'manifestSha256' => $expectedManifestSha256,
                'schemaTablesCreated' => $schemaCreated,
                'destinationWrites' => $destinationWrites,
                'mappingWrites' => $mappingWrites,
                'databaseWrites' => $destinationWrites + $mappingWrites,
                'readbackVerified' => true,
                'readback' => $postCommit,
                'providerCalls' => 0,
                'siteFileWrites' => 0,
            ];
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    } finally {
        destination_release_lock($db);
    }
}

function destination_failure_snapshot(PDO $db): array
{
    try {
        $dest = destination_table_exists($db, 'anytour_destinations_v1');
        $source = destination_table_exists($db, 'anytour_destination_sources_v1');
        return [
            'destinationTablePresent' => $dest,
            'sourceTablePresent' => $source,
            'destinationRows' => $dest ? destination_count($db, 'SELECT COUNT(*) FROM anytour_destinations_v1') : 0,
            'sourceRows' => $source ? destination_count($db, 'SELECT COUNT(*) FROM anytour_destination_sources_v1') : 0,
        ];
    } catch (Throwable $error) {
        return ['snapshotError' => $error->getMessage()];
    }
}

function search3_destination_apply_main(): int
{
    if ((string)getenv('SEARCH3_DESTINATION_APPLY') !== '1') {
        fwrite(STDERR, "EXPLICIT_GUARDED_APPLY_REQUIRED\n");
        return 2;
    }

    $root = realpath((string)getenv('ANYTOUR_ROOT'));
    if ($root === false || !is_file($root.'/data/db-v1.php')) {
        fwrite(STDERR, "ANYTOUR_ROOT_INVALID\n");
        return 2;
    }

    $expectedHash = (string)getenv('SEARCH3_EXPECTED_MANIFEST_SHA256');
    $expectedRows = (int)getenv('SEARCH3_EXPECTED_ROWS');
    $evidenceRef = (string)getenv('SEARCH3_EVIDENCE_REF');
    $reviewedBy = (string)getenv('SEARCH3_REVIEWED_BY');

    require_once $root.'/data/db-v1.php';
    $db = v2_data_db();

    try {
        $result = search3_destination_valid_seed_apply(
            $db,
            $expectedHash,
            $expectedRows,
            $evidenceRef,
            $reviewedBy
        );
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        return 0;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $result = [
            'operation' => SEARCH3_DESTINATION_APPLY_OPERATION,
            'state' => 'failed_guarded_apply',
            'error' => $error->getMessage(),
            'failureSnapshot' => destination_failure_snapshot($db),
            'providerCalls' => 0,
            'siteFileWrites' => 0,
        ];
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        return 1;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (string)getenv('SEARCH3_DESTINATION_APPLY') === '1') {
    exit(search3_destination_apply_main());
}
