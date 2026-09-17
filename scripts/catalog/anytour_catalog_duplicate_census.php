<?php
/**
 * Aggregate, read-only duplicate/identity census for the independent AnyTour hotel catalog.
 * Evidence only: never merges, accepts, rejects or rewrites identity.
 */
declare(strict_types=1);

final class AnyTourCatalogDuplicateCensus
{
    public const MAX_PROFILES = 250000;
    public const MAX_SOURCES = 500000;
    public const TOP_GROUPS = 100;

    public function __construct(private PDO $pdo) {}

    private static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function text(mixed $value): ?string
    {
        if (!is_string($value)) return null;
        $value = trim($value);
        return $value !== '' && preg_match('//u', $value) === 1 ? $value : null;
    }

    private static function normalize(mixed $value): string
    {
        $value = self::text($value);
        if ($value === null) return '';
        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace('ё', 'е', $value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    private static function coordinates(mixed $value): ?array
    {
        if (!is_array($value)) return null;
        $lat = $value['latitude'] ?? null;
        $lon = $value['longitude'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lon)) return null;
        $lat = (float)$lat; $lon = (float)$lon;
        if (!is_finite($lat) || !is_finite($lon) || $lat < -90.0 || $lat > 90.0 || $lon < -180.0 || $lon > 180.0) return null;
        return [$lat, $lon];
    }

    private static function exactSchema(PDO $pdo): void
    {
        $rows = $pdo->query("SELECT TABLE_NAME,ENGINE,TABLE_TYPE FROM information_schema.TABLES
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN
            ('anytour_catalog_control','anytour_hotels','anytour_hotel_sources')")
            ->fetchAll(PDO::FETCH_ASSOC);
        $byName = [];
        foreach ($rows as $row) $byName[$row['TABLE_NAME']] = $row;
        foreach (['anytour_catalog_control','anytour_hotels','anytour_hotel_sources'] as $name) {
            if (($byName[$name]['ENGINE'] ?? null) !== 'InnoDB' || ($byName[$name]['TABLE_TYPE'] ?? null) !== 'BASE TABLE') {
                throw new RuntimeException('Independent AnyTour catalog schema is unavailable');
            }
        }
        if ((int)$pdo->query('SELECT schema_version FROM anytour_catalog_control WHERE singleton_id=1')->fetchColumn() !== 1) {
            throw new RuntimeException('Unsupported AnyTour catalog schema version');
        }
    }

    private static function summarizeGroups(array $groups, array $legacyByHotel): array
    {
        $dupes = [];
        foreach ($groups as $key => $ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if (count($ids) < 2) continue;
            sort($ids, SORT_NUMERIC);
            $dupes[] = ['key'=>$key, 'hotelIds'=>$ids];
        }
        usort($dupes, static fn(array $a, array $b): int => count($b['hotelIds']) <=> count($a['hotelIds']) ?: strcmp($a['key'], $b['key']));
        $hotelSet = [];
        foreach ($dupes as &$group) {
            $legacy = [];
            foreach ($group['hotelIds'] as $id) {
                $hotelSet[$id] = true;
                foreach ($legacyByHotel[$id] ?? [] as $legacyId) $legacy[] = $legacyId;
            }
            $legacy = array_values(array_unique($legacy));
            sort($legacy, SORT_NATURAL);
            $group['legacyCatalogIds'] = $legacy;
        }
        unset($group);
        return [
            'groups'=>count($dupes),
            'hotels'=>count($hotelSet),
            'top'=>array_slice($dupes, 0, self::TOP_GROUPS),
        ];
    }

    public function run(string $operation, string $baseReleaseSha): array
    {
        if (!preg_match('/\A[a-z0-9][a-z0-9_-]{0,127}\z/D', $operation)) throw new InvalidArgumentException('Invalid operation id');
        if (!preg_match('/\A[a-f0-9]{40}\z/D', $baseReleaseSha)) throw new InvalidArgumentException('Exact base release SHA required');
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql' || $this->pdo->inTransaction()) {
            throw new RuntimeException('Dedicated MySQL connection required');
        }
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->pdo->exec('SET TRANSACTION READ ONLY');
        $this->pdo->beginTransaction();
        try {
            self::exactSchema($this->pdo);
            $identity = $this->pdo->query('SELECT DATABASE() AS database_name,@@hostname AS server_host,@@port AS server_port')->fetch(PDO::FETCH_ASSOC);
            if (!$identity || self::text($identity['database_name'] ?? null) === null) throw new RuntimeException('Explicit database identity required');
            $databaseIdentity = hash('sha256', self::json([
                'database'=>(string)$identity['database_name'],
                'host'=>(string)$identity['server_host'],
                'port'=>(string)$identity['server_port'],
            ]));

            $sources = $this->pdo->query("SELECT id,namespace,external_key,anytour_hotel_id FROM anytour_hotel_sources ORDER BY id")
                ->fetchAll(PDO::FETCH_ASSOC);
            if (count($sources) > self::MAX_SOURCES) throw new RuntimeException('Source census bound exceeded');
            $legacyByHotel = [];
            $sourceTargets = [];
            $legacyRows = 0;
            foreach ($sources as $row) {
                $hotelId = (int)$row['anytour_hotel_id'];
                $namespace = (string)$row['namespace'];
                $external = (string)$row['external_key'];
                $sourceKey = $namespace . "\0" . $external;
                $sourceTargets[$sourceKey][$hotelId] = true;
                if ($namespace === 'legacy_catalog') {
                    ++$legacyRows;
                    $legacyByHotel[$hotelId][] = $external;
                }
            }
            $sourceConflictGroups = $sourceConflictHotels = 0;
            foreach ($sourceTargets as $targets) {
                if (count($targets) > 1) {
                    ++$sourceConflictGroups;
                    $sourceConflictHotels += count($targets);
                }
            }
            $hotelsWithMultipleLegacy = $maxLegacyPerHotel = 0;
            foreach ($legacyByHotel as &$legacyIds) {
                $legacyIds = array_values(array_unique($legacyIds));
                sort($legacyIds, SORT_NATURAL);
                $count = count($legacyIds);
                if ($count > 1) ++$hotelsWithMultipleLegacy;
                $maxLegacyPerHotel = max($maxLegacyPerHotel, $count);
            }
            unset($legacyIds);

            $profiles = $this->pdo->query('SELECT id,profile_json,profile_sha256,is_active FROM anytour_hotels ORDER BY id')
                ->fetchAll(PDO::FETCH_ASSOC);
            if (count($profiles) > self::MAX_PROFILES) throw new RuntimeException('Profile census bound exceeded');
            $active = 0;
            $invalidProfileJson = 0;
            $activeIds = [];
            $profileHashGroups = $nameGeoGroups = $nameGeoCoordGroups = $coordinateGroups = [];
            foreach ($profiles as $row) {
                if ((int)$row['is_active'] !== 1) continue;
                ++$active;
                $id = (int)$row['id'];
                $activeIds[$id] = true;
                try { $profile = json_decode((string)$row['profile_json'], true, 512, JSON_THROW_ON_ERROR); }
                catch (JsonException) { $profile = null; }
                if (!is_array($profile)) { ++$invalidProfileJson; continue; }

                $profileSha = (string)$row['profile_sha256'];
                if ($profileSha !== '') $profileHashGroups[$profileSha][] = $id;
                $name = self::normalize($profile['name'] ?? null);
                $country = self::normalize($profile['country']['name'] ?? null);
                $region = self::normalize($profile['region']['name'] ?? null);
                if ($name !== '' && $country !== '') {
                    $nameGeoKey = $name . '|' . $country . '|' . $region;
                    $nameGeoGroups[$nameGeoKey][] = $id;
                    $coords = self::coordinates($profile['coordinates'] ?? null);
                    if ($coords !== null) {
                        $coordKey = sprintf('%.5f,%.5f', $coords[0], $coords[1]);
                        $nameGeoCoordGroups[$nameGeoKey . '|' . $coordKey][] = $id;
                        $coordinateGroups[$country . '|' . $coordKey][] = $id;
                    }
                }
            }

            $canonicalWithLegacy = $canonicalWithoutLegacy = 0;
            foreach ($activeIds as $id => $_) {
                if (($legacyByHotel[$id] ?? []) !== []) ++$canonicalWithLegacy;
                else ++$canonicalWithoutLegacy;
            }
            $legacyKeys = [];
            foreach ($legacyByHotel as $ids) foreach ($ids as $legacyId) $legacyKeys[$legacyId] = true;

            $result = [
                'schema_version'=>1,
                'operation'=>$operation,
                'status'=>'completed_read_only',
                'base_release_sha'=>$baseReleaseSha,
                'captured_at'=>gmdate('Y-m-d\\TH:i:s\\Z'),
                'database_identity_sha256'=>$databaseIdentity,
                'profiles'=>[
                    'active'=>$active,
                    'invalidProfileJson'=>$invalidProfileJson,
                ],
                'legacy_catalog'=>[
                    'rows'=>$legacyRows,
                    'uniqueExternalKeys'=>count($legacyKeys),
                    'canonicalWithLegacy'=>$canonicalWithLegacy,
                    'canonicalWithoutLegacy'=>$canonicalWithoutLegacy,
                    'hotelsWithMultipleLegacyIds'=>$hotelsWithMultipleLegacy,
                    'maxLegacyIdsPerHotel'=>$maxLegacyPerHotel,
                ],
                'hard_identity_conflicts'=>[
                    'sameNamespaceExternalKeyToMultipleCanonicalHotels'=>$sourceConflictGroups,
                    'affectedCanonicalHotels'=>$sourceConflictHotels,
                    'contract'=>'namespace + external_key must resolve to at most one canonical hotel',
                ],
                'duplicate_suspects'=>[
                    'exactProfileSha256'=>self::summarizeGroups($profileHashGroups, $legacyByHotel),
                    'sameNormalizedNameCountryRegion'=>self::summarizeGroups($nameGeoGroups, $legacyByHotel),
                    'sameNormalizedNameCountryRegionCoordinates5dp'=>self::summarizeGroups($nameGeoCoordGroups, $legacyByHotel),
                    'sameCountryCoordinates5dp'=>self::summarizeGroups($coordinateGroups, $legacyByHotel),
                    'interpretation'=>'suspects only; coordinates/profile/name similarities do not authorize auto-merge',
                ],
                'writes'=>0,
                'supplier_calls'=>0,
                'mapping_writes'=>0,
                'legacy_writes'=>0,
                'site_file_writes'=>0,
            ];
            $this->pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }
}

function anytour_catalog_duplicate_live_db(): PDO
{
    $siteRoot = rtrim((string)getenv('ANYTOUR_SITE_ROOT'), "/\\");
    if ($siteRoot === '') throw new RuntimeException('ANYTOUR_SITE_ROOT required');
    $helper = $siteRoot . '/data/db-v1.php';
    if (!is_file($helper)) throw new RuntimeException('AnyTour DB helper unavailable');
    require_once $helper;
    if (!function_exists('v2_data_db')) throw new RuntimeException('AnyTour DB helper contract unavailable');
    return v2_data_db();
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        $operation = $base = null;
        foreach (array_slice($argv, 1) as $arg) {
            if (str_starts_with($arg, '--operation=')) $operation = substr($arg, 12);
            elseif (str_starts_with($arg, '--base-release-sha=')) $base = substr($arg, 19);
            else throw new InvalidArgumentException('Unknown argument');
        }
        if ($operation === null || $base === null) throw new InvalidArgumentException('Operation and base SHA are required');
        $result = (new AnyTourCatalogDuplicateCensus(anytour_catalog_duplicate_live_db()))->run($operation, $base);
        echo self_or_json($result) . "\n";
    } catch (Throwable $error) {
        fwrite(STDERR, 'ANYTOUR_DUPLICATE_CENSUS_FAILED class=' . get_class($error) . "\n");
        exit(1);
    }
}

function self_or_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
