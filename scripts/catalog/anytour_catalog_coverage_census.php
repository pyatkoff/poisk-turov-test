<?php
/**
 * Aggregate, read-only completeness census for the independent AnyTour hotel catalog.
 * No HTTP endpoint, supplier calls, matching decisions, schema changes or data writes.
 */
declare(strict_types=1);

final class AnyTourCatalogCoverageCensus
{
    public const MAX_PROFILES = 250000;
    public const MAX_SOURCES = 500000;

    public function __construct(private PDO $pdo) {}

    private static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function text(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '' && preg_match('//u', $value) === 1;
    }

    private static function arrayNonEmpty(mixed $value): bool
    {
        return is_array($value) && count($value) > 0;
    }

    private static function httpsUrl(mixed $value): bool
    {
        if (!self::text($value) || strlen((string)$value) > 4096) return false;
        $parts = parse_url((string)$value);
        return is_array($parts) && strtolower((string)($parts['scheme'] ?? '')) === 'https'
            && self::text($parts['host'] ?? null);
    }

    private static function galleryCount(mixed $value): int
    {
        if (!is_array($value)) return 0;
        $seen = [];
        foreach ($value as $url) if (self::httpsUrl($url)) $seen[(string)$url] = true;
        return count($seen);
    }

    private static function coordinates(mixed $value): bool
    {
        if (!is_array($value)) return false;
        $lat = $value['latitude'] ?? null;
        $lon = $value['longitude'] ?? null;
        return is_numeric($lat) && is_numeric($lon)
            && is_finite((float)$lat) && is_finite((float)$lon)
            && (float)$lat >= -90.0 && (float)$lat <= 90.0
            && (float)$lon >= -180.0 && (float)$lon <= 180.0;
    }

    private static function percent(int $value, int $total): float
    {
        return $total > 0 ? round($value * 100.0 / $total, 1) : 0.0;
    }

    private static function add(array &$counts, string $key, bool $present): void
    {
        if ($present) $counts[$key] = ($counts[$key] ?? 0) + 1;
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
            if (($byName[$name]['ENGINE'] ?? null) !== 'InnoDB'
                || ($byName[$name]['TABLE_TYPE'] ?? null) !== 'BASE TABLE') {
                throw new RuntimeException('Independent AnyTour catalog schema is unavailable');
            }
        }
        $version = $pdo->query('SELECT schema_version FROM anytour_catalog_control WHERE singleton_id=1')->fetchColumn();
        if ((int)$version !== 1) throw new RuntimeException('Unsupported AnyTour catalog schema version');
    }

    public function run(string $operation, string $baseReleaseSha): array
    {
        if (!preg_match('/\A[a-z0-9][a-z0-9_-]{0,127}\z/D', $operation)) {
            throw new InvalidArgumentException('Invalid operation id');
        }
        if (!preg_match('/\A[a-f0-9]{40}\z/D', $baseReleaseSha)) {
            throw new InvalidArgumentException('Exact base release SHA required');
        }
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql' || $this->pdo->inTransaction()) {
            throw new RuntimeException('Dedicated MySQL connection required');
        }
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->pdo->exec('SET TRANSACTION READ ONLY');
        $this->pdo->beginTransaction();
        try {
            self::exactSchema($this->pdo);
            $identity = $this->pdo->query('SELECT DATABASE() AS database_name,@@hostname AS server_host,@@port AS server_port')
                ->fetch(PDO::FETCH_ASSOC);
            if (!$identity || !self::text($identity['database_name'] ?? null)) {
                throw new RuntimeException('Explicit database identity required');
            }
            $databaseIdentity = hash('sha256', self::json([
                'database'=>(string)$identity['database_name'],
                'host'=>(string)$identity['server_host'],
                'port'=>(string)$identity['server_port'],
            ]));

            $profiles = $this->pdo->query('SELECT id,profile_json,profile_sha256,revision,is_active FROM anytour_hotels ORDER BY id')
                ->fetchAll(PDO::FETCH_ASSOC);
            if (count($profiles) > self::MAX_PROFILES) throw new RuntimeException('Profile census bound exceeded');

            $profileById = [];
            $fields = array_fill_keys([
                'name','country','region','subRegion','coordinates','category','rating','description',
                'primaryImage','galleryAny','gallery3Plus','address','place','build','repair','square',
                'infrastructure','services','legacyMealsBlock','legacyRoomTypesBlock','traits'
            ], 0);
            $readiness = array_fill_keys([
                'identityReady','geoReady','cardReady','detailReady','identityOnly','partial'
            ], 0);
            $integrity = [
                'profileHashMismatch'=>0,'profileJsonInvalid'=>0,'profileRevisionInvalid'=>0,
                'sourceHashMismatch'=>0,'sourceJsonInvalid'=>0,
            ];
            $active = $inactive = 0;
            foreach ($profiles as $row) {
                $id = (int)$row['id'];
                $isActive = (int)$row['is_active'] === 1;
                $isActive ? ++$active : ++$inactive;
                $profileById[$id] = $isActive;
                if ((int)$row['revision'] < 1) ++$integrity['profileRevisionInvalid'];
                $json = (string)$row['profile_json'];
                $sha = (string)$row['profile_sha256'];
                if (!preg_match('/\A[a-f0-9]{64}\z/D', $sha) || !hash_equals($sha, hash('sha256', $json))) {
                    ++$integrity['profileHashMismatch'];
                }
                try {
                    $profile = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    $profile = null;
                }
                if (!is_array($profile)) {
                    ++$integrity['profileJsonInvalid'];
                    continue;
                }
                if (!$isActive) continue;

                $name = self::text($profile['name'] ?? null);
                $country = self::text($profile['country']['name'] ?? null);
                $region = self::text($profile['region']['name'] ?? null);
                $subRegion = self::text($profile['subRegion']['name'] ?? null);
                $coordinates = self::coordinates($profile['coordinates'] ?? null);
                $category = is_numeric($profile['category'] ?? null) && (float)$profile['category'] > 0;
                $rating = is_numeric($profile['rating'] ?? null) && is_finite((float)$profile['rating']) && (float)$profile['rating'] > 0;
                $description = self::text($profile['description'] ?? null);
                $primaryImage = self::httpsUrl($profile['primaryImage'] ?? null);
                $galleryCount = self::galleryCount($profile['images'] ?? null);
                $address = self::text($profile['address'] ?? null);
                $place = self::text($profile['place'] ?? null);
                $build = self::text($profile['build'] ?? null);
                $repair = self::text($profile['repair'] ?? null);
                $square = self::text($profile['square'] ?? null);
                $info = is_array($profile['hotelInformation'] ?? null) ? $profile['hotelInformation'] : [];
                $infrastructure = self::arrayNonEmpty($info['infrastructure'] ?? null);
                $services = self::arrayNonEmpty($info['services'] ?? null);
                $legacyMeals = self::arrayNonEmpty($info['meals'] ?? null);
                $legacyRoomTypes = self::text($info['roomTypes'] ?? null) || self::arrayNonEmpty($info['roomTypes'] ?? null);
                $traits = self::arrayNonEmpty($profile['traits'] ?? null);

                foreach ([
                    'name'=>$name,'country'=>$country,'region'=>$region,'subRegion'=>$subRegion,
                    'coordinates'=>$coordinates,'category'=>$category,'rating'=>$rating,'description'=>$description,
                    'primaryImage'=>$primaryImage,'galleryAny'=>$galleryCount > 0,'gallery3Plus'=>$galleryCount >= 3,
                    'address'=>$address,'place'=>$place,'build'=>$build,'repair'=>$repair,'square'=>$square,
                    'infrastructure'=>$infrastructure,'services'=>$services,'legacyMealsBlock'=>$legacyMeals,
                    'legacyRoomTypesBlock'=>$legacyRoomTypes,'traits'=>$traits,
                ] as $key=>$present) self::add($fields, $key, $present);

                $identityReady = $name && $country;
                $geoReady = $identityReady && $coordinates;
                $cardReady = $identityReady && $category && $primaryImage;
                $detailReady = $cardReady && $description && $galleryCount >= 3 && ($infrastructure || $services);
                $identityOnly = $identityReady && !$description && !$primaryImage && $galleryCount === 0
                    && !$infrastructure && !$services && !$traits;
                $partial = $identityReady && !$identityOnly && !$detailReady;
                foreach ([
                    'identityReady'=>$identityReady,'geoReady'=>$geoReady,'cardReady'=>$cardReady,
                    'detailReady'=>$detailReady,'identityOnly'=>$identityOnly,'partial'=>$partial,
                ] as $key=>$present) self::add($readiness, $key, $present);
            }

            $sources = $this->pdo->query('SELECT anytour_hotel_id,namespace,external_key,source_json,source_sha256,acquired_via FROM anytour_hotel_sources ORDER BY anytour_hotel_id,id')
                ->fetchAll(PDO::FETCH_ASSOC);
            if (count($sources) > self::MAX_SOURCES) throw new RuntimeException('Source census bound exceeded');
            $perHotel = [];
            $namespaceRows = [];
            $namespaceHotels = [];
            $acquiredRows = [];
            foreach ($sources as $row) {
                $hotelId = (int)$row['anytour_hotel_id'];
                $namespace = (string)$row['namespace'];
                $acquired = (string)$row['acquired_via'];
                $namespaceRows[$namespace] = ($namespaceRows[$namespace] ?? 0) + 1;
                $namespaceHotels[$namespace][$hotelId] = true;
                $acquiredRows[$acquired] = ($acquiredRows[$acquired] ?? 0) + 1;
                if ($profileById[$hotelId] ?? false) {
                    $perHotel[$hotelId]['legacy'] = ($perHotel[$hotelId]['legacy'] ?? false) || $namespace === 'legacy_catalog';
                    $perHotel[$hotelId]['direct'] = ($perHotel[$hotelId]['direct'] ?? false) || str_starts_with($namespace, 'provider_ref_digest:');
                    $perHotel[$hotelId]['other'] = ($perHotel[$hotelId]['other'] ?? false)
                        || ($namespace !== 'legacy_catalog' && !str_starts_with($namespace, 'provider_ref_digest:'));
                }
                $sourceJson = (string)$row['source_json'];
                $sourceSha = (string)$row['source_sha256'];
                if (!preg_match('/\A[a-f0-9]{64}\z/D', $sourceSha) || !hash_equals($sourceSha, hash('sha256', $sourceJson))) {
                    ++$integrity['sourceHashMismatch'];
                }
                try {
                    $decoded = json_decode($sourceJson, true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($decoded)) ++$integrity['sourceJsonInvalid'];
                } catch (JsonException) {
                    ++$integrity['sourceJsonInvalid'];
                }
            }

            $sourceModes = ['none'=>0,'legacyOnly'=>0,'directOnly'=>0,'legacyAndDirect'=>0,'otherOrMixed'=>0];
            foreach ($profileById as $hotelId=>$isActive) {
                if (!$isActive) continue;
                $flags = $perHotel[$hotelId] ?? ['legacy'=>false,'direct'=>false,'other'=>false];
                $legacy = (bool)($flags['legacy'] ?? false);
                $direct = (bool)($flags['direct'] ?? false);
                $other = (bool)($flags['other'] ?? false);
                if (!$legacy && !$direct && !$other) ++$sourceModes['none'];
                elseif ($legacy && !$direct && !$other) ++$sourceModes['legacyOnly'];
                elseif (!$legacy && $direct && !$other) ++$sourceModes['directOnly'];
                elseif ($legacy && $direct && !$other) ++$sourceModes['legacyAndDirect'];
                else ++$sourceModes['otherOrMixed'];
            }
            ksort($namespaceRows, SORT_STRING); ksort($namespaceHotels, SORT_STRING); ksort($acquiredRows, SORT_STRING);
            $namespaceSummary = [];
            foreach ($namespaceRows as $namespace=>$rows) {
                $namespaceSummary[$namespace] = ['rows'=>$rows,'hotels'=>count($namespaceHotels[$namespace] ?? [])];
            }
            $percent = [];
            foreach ($fields as $key=>$value) $percent[$key] = self::percent($value, $active);
            $readinessPercent = [];
            foreach ($readiness as $key=>$value) $readinessPercent[$key] = self::percent($value, $active);
            $integrityOk = array_sum($integrity) === 0;

            $result = [
                'schema_version'=>1,
                'operation'=>$operation,
                'status'=>'completed_read_only',
                'base_release_sha'=>$baseReleaseSha,
                'captured_at'=>gmdate('Y-m-d\\TH:i:s\\Z'),
                'database_identity_sha256'=>$databaseIdentity,
                'profiles'=>['total'=>count($profiles),'active'=>$active,'inactive'=>$inactive],
                'fields'=>$fields,
                'field_coverage_percent'=>$percent,
                'readiness'=>$readiness,
                'readiness_percent'=>$readinessPercent,
                'readiness_contract'=>[
                    'identityReady'=>'name + country',
                    'geoReady'=>'identityReady + valid coordinates',
                    'cardReady'=>'identityReady + category + HTTPS primaryImage',
                    'detailReady'=>'cardReady + description + >=3 HTTPS gallery images + infrastructure or services',
                    'identityOnly'=>'identityReady with no description/image/gallery/infrastructure/services/traits',
                    'partial'=>'identityReady, neither identityOnly nor detailReady',
                ],
                'sources'=>[
                    'rows'=>count($sources),
                    'active_hotel_modes'=>$sourceModes,
                    'namespaces'=>$namespaceSummary,
                    'acquired_via_rows'=>$acquiredRows,
                ],
                'integrity'=>$integrity + ['ok'=>$integrityOk],
                'writes'=>0,'supplier_calls'=>0,'mapping_writes'=>0,'legacy_writes'=>0,'site_file_writes'=>0,
            ];
            $this->pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }
}

function anytour_catalog_coverage_live_db(): PDO
{
    $siteRoot = rtrim((string)getenv('ANYTOUR_SITE_ROOT'), "/\\");
    if ($siteRoot === '') throw new RuntimeException('ANYTOUR_SITE_ROOT required');
    $helper = $siteRoot . '/data/db-v1.php';
    if (!is_file($helper)) throw new RuntimeException('AnyTour DB helper unavailable');
    require_once $helper;
    if (!function_exists('v2_data_db')) throw new RuntimeException('AnyTour DB helper contract unavailable');
    $pdo = v2_data_db();
    if (!$pdo instanceof PDO) throw new RuntimeException('AnyTour DB connection unavailable');
    return $pdo;
}

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        $args = [];
        foreach (array_slice($argv, 1) as $arg) {
            if (!preg_match('/\A--(operation|base-release-sha)=(.+)\z/D', $arg, $m) || isset($args[$m[1]])) {
                throw new InvalidArgumentException('USAGE');
            }
            $args[$m[1]] = $m[2];
        }
        if (!isset($args['operation'], $args['base-release-sha']) || count($args) !== 2) {
            throw new InvalidArgumentException('USAGE');
        }
        $census = new AnyTourCatalogCoverageCensus(anytour_catalog_coverage_live_db());
        echo json_encode($census->run($args['operation'], $args['base-release-sha']),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable $error) {
        fwrite(STDERR, 'ANYTOUR_CATALOG_COVERAGE_CENSUS_FAILED code=' . preg_replace('/[^A-Z0-9_]/', '_', strtoupper($error->getMessage())) . "\n");
        exit(1);
    }
}
