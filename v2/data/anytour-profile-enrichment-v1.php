<?php
/**
 * LOCAL-owned first-party hotel profile enrichment.
 *
 * Reads only already-saved/sanitized local hotel presentation and demand evidence.
 * It may fill missing canonical hotel-presentation fields, but never overwrites an
 * existing non-empty AnyTour value. Room/meal concepts and traits are intentionally
 * outside this contract: those are hotel-scoped product dictionaries handled by a
 * separate reviewed layer.
 */
declare(strict_types=1);
require_once __DIR__ . '/hotel-presentation-read-v1.php';

final class AnyTourProfileEnrichmentV1
{
    public const MAX_BATCH = 250;
    public const LOCAL_ALIAS_NAMESPACE = 'anytour_local_id';
    public const LOCAL_ALIAS_ACQUIRED_VIA = 'canonical_local_alias_v1';
    public const PROVENANCE_NAMESPACE = 'profile_enrichment:legacy_saved_v1';
    public const PROVENANCE_ACQUIRED_VIA = 'profile_fill_missing_v1';

    private const ALIAS_KEYS = [
        'accepted_local_hotel_id', 'canonical_hotel_id', 'derived_from_namespace',
        'derived_from_source_sha256', 'schema_version',
    ];
    private const TARGET_FIELDS = [
        'region','subRegion','category','rating','coordinates','description','primaryImage','images',
        'address','place','build','repair','square','hotelInformation.infrastructure','hotelInformation.services',
    ];

    public function __construct(private PDO $pdo) {}

    private static function exactKeys(array $value, array $expected): bool
    {
        return count($value) === count($expected)
            && array_diff($expected, array_keys($value)) === []
            && array_diff(array_keys($value), $expected) === [];
    }

    private static function stable(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (array_is_list($value)) return array_map([self::class, 'stable'], $value);
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) $value[$key] = self::stable($item);
        return $value;
    }

    public static function json(array $value): string
    {
        return json_encode(self::stable($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function positiveInt(mixed $value): int
    {
        if (is_int($value) && $value > 0) return $value;
        if (is_string($value) && preg_match('/\A[1-9][0-9]*\z/D', $value)
            && filter_var($value, FILTER_VALIDATE_INT) !== false) return (int)$value;
        throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_ID');
    }

    private static function utc(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') throw new InvalidArgumentException('ANYTOUR_PROFILE_ENRICH_THROUGH');
        try { $time = new DateTimeImmutable($value); }
        catch (Throwable) { throw new InvalidArgumentException('ANYTOUR_PROFILE_ENRICH_THROUGH'); }
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function limit(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/\A[1-9][0-9]*\z/D', (string)$value)) {
            throw new InvalidArgumentException('ANYTOUR_PROFILE_ENRICH_LIMIT');
        }
        $limit = (int)$value;
        if ($limit < 1 || $limit > self::MAX_BATCH) throw new InvalidArgumentException('ANYTOUR_PROFILE_ENRICH_LIMIT');
        return $limit;
    }

    private static function operation(string $value): string
    {
        if (!preg_match('/\A[a-z0-9][a-z0-9_-]{0,127}\z/D', $value)) {
            throw new InvalidArgumentException('ANYTOUR_PROFILE_ENRICH_OPERATION');
        }
        return $value;
    }

    private function assertSchema(): void
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_MYSQL');
        }
        $wanted = [
            'anytour_catalog_control','anytour_hotels','anytour_hotel_sources',
            'catalog_hotels','catalog_hotel_details','tour_price_observations',
        ];
        $quoted = "'" . implode("','", $wanted) . "'";
        $rows = $this->pdo->query("SELECT TABLE_NAME,ENGINE,TABLE_TYPE FROM information_schema.TABLES
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($quoted)")->fetchAll(PDO::FETCH_ASSOC);
        $byName = [];
        foreach ($rows as $row) $byName[(string)$row['TABLE_NAME']] = $row;
        foreach ($wanted as $table) {
            if (($byName[$table]['ENGINE'] ?? null) !== 'InnoDB'
                || ($byName[$table]['TABLE_TYPE'] ?? null) !== 'BASE TABLE') {
                throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_SCHEMA');
            }
        }
        $version = $this->pdo->query('SELECT schema_version FROM anytour_catalog_control WHERE singleton_id=1')->fetchColumn();
        if ((int)$version !== 1) throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_SCHEMA_VERSION');
    }

    private static function decodeProfile(array $row): array
    {
        $json = $row['profile_json'] ?? null;
        $sha = $row['profile_sha256'] ?? null;
        if (!is_string($json) || !is_string($sha) || !preg_match('/\A[a-f0-9]{64}\z/D', $sha)
            || !hash_equals($sha, hash('sha256', $json))) {
            throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_PROFILE_INTEGRITY');
        }
        try { $profile = json_decode($json, true, 512, JSON_THROW_ON_ERROR); }
        catch (Throwable) { throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_PROFILE_JSON'); }
        if (!is_array($profile)) throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_PROFILE_JSON');
        return $profile;
    }

    private static function decodeAlias(array $row): array
    {
        $localId = self::positiveInt($row['local_id'] ?? null);
        $ownId = self::positiveInt($row['anytour_hotel_id'] ?? null);
        if (($row['alias_acquired_via'] ?? null) !== self::LOCAL_ALIAS_ACQUIRED_VIA
            || !is_string($row['alias_source_json'] ?? null)
            || !is_string($row['alias_source_sha256'] ?? null)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $row['alias_source_sha256'])
            || !hash_equals($row['alias_source_sha256'], hash('sha256', $row['alias_source_json']))) {
            throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_ALIAS_INTEGRITY');
        }
        try { $source = json_decode($row['alias_source_json'], true, 32, JSON_THROW_ON_ERROR); }
        catch (Throwable) { throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_ALIAS_JSON'); }
        if (!is_array($source) || !self::exactKeys($source, self::ALIAS_KEYS)
            || ($source['schema_version'] ?? null) !== 1
            || ($source['accepted_local_hotel_id'] ?? null) !== $localId
            || ($source['canonical_hotel_id'] ?? null) !== $ownId
            || ($source['derived_from_namespace'] ?? null) !== 'legacy_catalog'
            || !is_string($source['derived_from_source_sha256'] ?? null)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $source['derived_from_source_sha256'])) {
            throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_ALIAS_SEMANTICS');
        }
        return ['localId'=>$localId,'ownId'=>$ownId];
    }

    private static function namedNode(mixed $value): ?array
    {
        if (!is_array($value) || !is_string($value['name'] ?? null) || trim($value['name']) === '') return null;
        return ['name'=>trim($value['name'])];
    }

    private static function text(mixed $value): ?string
    {
        if (!is_string($value)) return null;
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private static function positiveNumber(mixed $value): int|float|null
    {
        if (!is_int($value) && !is_float($value)) return null;
        return is_finite((float)$value) && (float)$value > 0 ? $value : null;
    }

    private static function coordinates(mixed $value): ?array
    {
        if (!is_array($value) || !is_numeric($value['latitude'] ?? null) || !is_numeric($value['longitude'] ?? null)) return null;
        $lat = (float)$value['latitude']; $lon = (float)$value['longitude'];
        if (!is_finite($lat) || !is_finite($lon) || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) return null;
        return ['latitude'=>$lat,'longitude'=>$lon];
    }

    private static function nonEmptyList(mixed $value): ?array
    {
        return is_array($value) && array_is_list($value) && $value !== [] ? $value : null;
    }

    /** Only fields LOCAL is allowed to fill automatically in this layer. */
    private static function sourceFacts(array $source): array
    {
        $images = self::nonEmptyList($source['images'] ?? null);
        $infrastructure = self::nonEmptyList($source['infrastructure'] ?? null);
        $services = self::nonEmptyList($source['services'] ?? null);
        return [
            'region'=>self::namedNode($source['region'] ?? null),
            'subRegion'=>self::namedNode($source['subRegion'] ?? null),
            'category'=>self::positiveNumber($source['category'] ?? null),
            'rating'=>self::positiveNumber($source['rating'] ?? null),
            'coordinates'=>self::coordinates($source['coordinates'] ?? null),
            'description'=>self::text($source['description'] ?? null),
            'primaryImage'=>self::text($source['primaryImage'] ?? null),
            'images'=>$images,
            'address'=>self::text($source['address'] ?? null),
            'place'=>self::text($source['place'] ?? null),
            'build'=>self::text($source['build'] ?? null),
            'repair'=>self::text($source['repair'] ?? null),
            'square'=>self::text($source['square'] ?? null),
            'hotelInformation.infrastructure'=>$infrastructure,
            'hotelInformation.services'=>$services,
            'detailsFetchedAt'=>self::text($source['detailsFetchedAt'] ?? null),
        ];
    }

    private static function ownValue(array $profile, string $path): mixed
    {
        if (str_starts_with($path, 'hotelInformation.')) {
            $key = substr($path, strlen('hotelInformation.'));
            return is_array($profile['hotelInformation'] ?? null) ? ($profile['hotelInformation'][$key] ?? null) : null;
        }
        return $profile[$path] ?? null;
    }

    private static function missing(mixed $value): bool
    {
        if ($value === null) return true;
        if (is_string($value)) return trim($value) === '';
        if (is_array($value)) return $value === [];
        return false;
    }

    private static function patch(array $profile, array $facts): array
    {
        $patch = [];
        foreach (self::TARGET_FIELDS as $field) {
            $source = $facts[$field] ?? null;
            if ($source === null || (is_array($source) && $source === [])) continue;
            if (self::missing(self::ownValue($profile, $field))) $patch[$field] = $source;
        }
        return $patch;
    }

    private static function preservedConflicts(array $profile, array $facts): array
    {
        $conflicts = [];
        foreach (self::TARGET_FIELDS as $field) {
            $source = $facts[$field] ?? null;
            if ($source === null || (is_array($source) && $source === [])) continue;
            $own = self::ownValue($profile, $field);
            if (!self::missing($own) && self::stable($own) !== self::stable($source)) $conflicts[] = $field;
        }
        return $conflicts;
    }

    private static function applyPatch(array $profile, array $patch): array
    {
        foreach ($patch as $field => $value) {
            if (!in_array($field, self::TARGET_FIELDS, true)) throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_PATCH_FIELD');
            if (!self::missing(self::ownValue($profile, $field))) throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_OVERWRITE_GUARD');
            if (str_starts_with($field, 'hotelInformation.')) {
                $key = substr($field, strlen('hotelInformation.'));
                if (!is_array($profile['hotelInformation'] ?? null)) $profile['hotelInformation'] = [];
                $profile['hotelInformation'][$key] = $value;
            } else {
                $profile[$field] = $value;
            }
        }
        return $profile;
    }

    /**
     * Stable demand order: user searches first, then all saved observations, then most
     * recent observation, then canonical ID. `through` freezes the demand boundary so
     * a live search arriving between plan and apply cannot silently change the batch.
     */
    private function rankedRows(string $through): array
    {
        $sql = "SELECT h.id AS anytour_hotel_id,h.profile_json,h.profile_sha256,h.revision,
                       CAST(s.external_key AS CHAR) AS local_id,s.acquired_via AS alias_acquired_via,
                       s.source_json AS alias_source_json,s.source_sha256 AS alias_source_sha256,
                       COALESCE(d.user_search_count,0) AS user_search_count,
                       COALESCE(d.observation_count,0) AS observation_count,d.last_seen_at AS demand_last_seen_at
                FROM anytour_hotels h
                JOIN anytour_hotel_sources s ON s.anytour_hotel_id=h.id AND s.namespace='anytour_local_id'
                LEFT JOIN (
                    SELECT hotel_id,SUM(source='user_search') AS user_search_count,
                           COUNT(*) AS observation_count,MAX(observed_at) AS last_seen_at
                    FROM tour_price_observations WHERE observed_at<=:through GROUP BY hotel_id
                ) d ON d.hotel_id=CAST(s.external_key AS UNSIGNED)
                WHERE h.is_active=1
                ORDER BY user_search_count DESC,observation_count DESC,
                         (d.last_seen_at IS NULL) ASC,d.last_seen_at DESC,h.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['through'=>$through]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) < 1 || count($rows) > 250000) throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_COHORT_BOUND');
        $seenOwn = [];
        foreach ($rows as $row) {
            $alias = self::decodeAlias($row);
            if (isset($seenOwn[$alias['ownId']])) throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_DUPLICATE_ALIAS_TARGET');
            $seenOwn[$alias['ownId']] = true;
            self::decodeProfile($row);
            if ((int)($row['revision'] ?? 0) < 1) throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_REVISION');
        }
        return $rows;
    }

    private function snapshot(int $limit, string $through): array
    {
        $rows = $this->rankedRows($through);
        $selected = [];
        $scanned = 0;
        $fillableProfiles = 0;
        $preservedConflictCounts = [];
        for ($offset = 0; $offset < count($rows) && count($selected) < $limit; $offset += HOTEL_PRESENTATION_READ_LIMIT) {
            $chunk = array_slice($rows, $offset, HOTEL_PRESENTATION_READ_LIMIT);
            $localIds = array_map(static fn(array $row): int => self::positiveInt($row['local_id']), $chunk);
            $saved = hotel_presentation_read_many($this->pdo, $localIds);
            $byLocal = [];
            foreach ($saved['items'] as $item) $byLocal[(int)$item['id']] = $item;
            foreach ($chunk as $row) {
                ++$scanned;
                $alias = self::decodeAlias($row);
                $source = $byLocal[$alias['localId']] ?? null;
                if (!is_array($source)) continue;
                $profile = self::decodeProfile($row);
                $facts = self::sourceFacts($source);
                $patch = self::patch($profile, $facts);
                $conflicts = self::preservedConflicts($profile, $facts);
                foreach ($conflicts as $field) $preservedConflictCounts[$field] = ($preservedConflictCounts[$field] ?? 0) + 1;
                if ($patch === []) continue;
                ++$fillableProfiles;
                $sourceFingerprint = hash('sha256', self::json([
                    'localId'=>$alias['localId'],
                    'facts'=>$facts,
                ]));
                $selected[] = [
                    'anytourHotelId'=>$alias['ownId'],
                    'localHotelId'=>$alias['localId'],
                    'expectedProfileSha256'=>(string)$row['profile_sha256'],
                    'expectedRevision'=>(int)$row['revision'],
                    'sourceFingerprint'=>$sourceFingerprint,
                    'detailsFetchedAt'=>$facts['detailsFetchedAt'],
                    'patch'=>$patch,
                    'preservedConflicts'=>$conflicts,
                    'demand'=>[
                        'userSearches'=>(int)$row['user_search_count'],
                        'observations'=>(int)$row['observation_count'],
                        'lastSeenAt'=>$row['demand_last_seen_at'] === null ? null : (string)$row['demand_last_seen_at'],
                    ],
                ];
                if (count($selected) >= $limit) break;
            }
        }
        ksort($preservedConflictCounts, SORT_STRING);
        $core = [
            'schemaVersion'=>1,
            'demandThrough'=>$through,
            'limit'=>$limit,
            'activeProfiles'=>count($rows),
            'scannedProfiles'=>$scanned,
            'selected'=>$selected,
            'preservedConflictCounts'=>$preservedConflictCounts,
            'roomMealFieldsExcluded'=>true,
            'traitsExcluded'=>true,
        ];
        $core['planSha256'] = hash('sha256', self::json($core));
        return $core;
    }

    public function plan(mixed $requestedLimit, mixed $demandThrough): array
    {
        $limit = self::limit($requestedLimit);
        $through = self::utc($demandThrough);
        $this->assertSchema();
        if ($this->pdo->inTransaction()) throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_CALLER_TRANSACTION');
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->pdo->exec('SET TRANSACTION READ ONLY');
        $this->pdo->beginTransaction();
        try {
            $snapshot = $this->snapshot($limit, $through);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
        return ['status'=>'prepared_read_only','writes'=>0,'supplierCalls'=>0] + $snapshot;
    }

    public function apply(string $operation, mixed $requestedLimit, mixed $demandThrough, string $expectedPlanSha256): array
    {
        $operation = self::operation($operation);
        $limit = self::limit($requestedLimit);
        $through = self::utc($demandThrough);
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $expectedPlanSha256)) {
            throw new InvalidArgumentException('ANYTOUR_PROFILE_ENRICH_PLAN_SHA');
        }
        $this->assertSchema();
        if ($this->pdo->inTransaction()) throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_CALLER_TRANSACTION');
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $this->pdo->exec('SET TRANSACTION READ WRITE');
        $this->pdo->beginTransaction();
        $expected = [];
        try {
            $snapshot = $this->snapshot($limit, $through);
            if (!hash_equals($expectedPlanSha256, $snapshot['planSha256'])) {
                throw new DomainException('ANYTOUR_PROFILE_ENRICH_PLAN_DRIFT');
            }
            if ($snapshot['selected'] === []) {
                $this->pdo->commit();
                return [
                    'status'=>'committed_verified_noop','operation'=>$operation,'planSha256'=>$expectedPlanSha256,
                    'profilesUpdated'=>0,'fieldsFilled'=>0,'fieldCounts'=>[],
                    'profileWrites'=>0,'provenanceWrites'=>0,'legacyWrites'=>0,'mappingWrites'=>0,'supplierCalls'=>0,
                ];
            }

            $lock = $this->pdo->prepare('SELECT profile_json,profile_sha256,revision,is_active FROM anytour_hotels WHERE id=? FOR UPDATE');
            $update = $this->pdo->prepare('UPDATE anytour_hotels SET profile_json=?,profile_sha256=?,revision=?,updated_at=UTC_TIMESTAMP()
                WHERE id=? AND profile_sha256=? AND revision=? AND is_active=1');
            $insertProvenance = $this->pdo->prepare("INSERT INTO anytour_hotel_sources
                (namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
                VALUES (?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");

            $profilesUpdated = 0; $fieldsFilled = 0; $fieldCounts = [];
            foreach ($snapshot['selected'] as $item) {
                $ownId = self::positiveInt($item['anytourHotelId']);
                $localId = self::positiveInt($item['localHotelId']);
                $lock->execute([$ownId]);
                $row = $lock->fetch(PDO::FETCH_ASSOC);
                if (!is_array($row) || (int)$row['is_active'] !== 1
                    || !hash_equals((string)$item['expectedProfileSha256'], (string)$row['profile_sha256'])
                    || (int)$item['expectedRevision'] !== (int)$row['revision']) {
                    throw new DomainException('ANYTOUR_PROFILE_ENRICH_PROFILE_DRIFT');
                }
                $profile = self::decodeProfile($row);
                $nextProfile = self::applyPatch($profile, $item['patch']);
                $nextJson = self::json($nextProfile);
                $nextSha = hash('sha256', $nextJson);
                $nextRevision = (int)$row['revision'] + 1;
                $update->execute([$nextJson,$nextSha,$nextRevision,$ownId,$row['profile_sha256'],$row['revision']]);
                if ($update->rowCount() !== 1) throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_UPDATE');

                $fields = array_keys($item['patch']);
                sort($fields, SORT_STRING);
                foreach ($fields as $field) {
                    $fieldCounts[$field] = ($fieldCounts[$field] ?? 0) + 1;
                    ++$fieldsFilled;
                }
                $provenance = [
                    'schema_version'=>1,
                    'operation'=>$operation,
                    'canonical_hotel_id'=>$ownId,
                    'accepted_local_hotel_id'=>$localId,
                    'previous_profile_sha256'=>$row['profile_sha256'],
                    'result_profile_sha256'=>$nextSha,
                    'result_revision'=>$nextRevision,
                    'source_kind'=>'saved_local_hotel_presentation',
                    'source_fingerprint'=>$item['sourceFingerprint'],
                    'details_fetched_at'=>$item['detailsFetchedAt'],
                    'fields_filled'=>$fields,
                    'preserved_conflicts'=>$item['preservedConflicts'],
                    'room_meal_fields_excluded'=>true,
                    'traits_excluded'=>true,
                ];
                $provenanceJson = self::json($provenance);
                $externalKey = $ownId . ':' . $nextRevision;
                $insertProvenance->execute([
                    self::PROVENANCE_NAMESPACE,$externalKey,$ownId,self::PROVENANCE_ACQUIRED_VIA,
                    $provenanceJson,hash('sha256',$provenanceJson),
                ]);
                if ($insertProvenance->rowCount() !== 1) throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_PROVENANCE');
                $expected[$ownId] = [
                    'profileSha256'=>$nextSha,'revision'=>$nextRevision,'externalKey'=>$externalKey,
                    'provenanceSha256'=>hash('sha256',$provenanceJson),
                ];
                ++$profilesUpdated;
            }
            ksort($fieldCounts, SORT_STRING);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        $hotelRead = $this->pdo->prepare('SELECT profile_json,profile_sha256,revision,is_active FROM anytour_hotels WHERE id=?');
        $sourceRead = $this->pdo->prepare('SELECT anytour_hotel_id,acquired_via,source_json,source_sha256 FROM anytour_hotel_sources
            WHERE namespace=? AND external_key=?');
        foreach ($expected as $ownId=>$want) {
            $hotelRead->execute([$ownId]); $hotel = $hotelRead->fetch(PDO::FETCH_ASSOC);
            if (!is_array($hotel) || (int)$hotel['is_active'] !== 1 || (int)$hotel['revision'] !== $want['revision']
                || !hash_equals($want['profileSha256'], (string)$hotel['profile_sha256'])
                || !hash_equals((string)$hotel['profile_sha256'], hash('sha256', (string)$hotel['profile_json']))) {
                throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_POSTCOMMIT_PROFILE');
            }
            $sourceRead->execute([self::PROVENANCE_NAMESPACE,$want['externalKey']]);
            $source = $sourceRead->fetch(PDO::FETCH_ASSOC);
            if (!is_array($source) || (int)$source['anytour_hotel_id'] !== $ownId
                || ($source['acquired_via'] ?? null) !== self::PROVENANCE_ACQUIRED_VIA
                || !hash_equals($want['provenanceSha256'], (string)$source['source_sha256'])
                || !hash_equals((string)$source['source_sha256'], hash('sha256', (string)$source['source_json']))) {
                throw new RuntimeException('ANYTOUR_PROFILE_ENRICH_POSTCOMMIT_PROVENANCE');
            }
        }
        return [
            'status'=>'committed_verified','operation'=>$operation,'planSha256'=>$expectedPlanSha256,
            'demandThrough'=>$through,'profilesUpdated'=>$profilesUpdated,'fieldsFilled'=>$fieldsFilled,'fieldCounts'=>$fieldCounts,
            'profileWrites'=>$profilesUpdated,'provenanceWrites'=>$profilesUpdated,
            'legacyWrites'=>0,'mappingWrites'=>0,'supplierCalls'=>0,
        ];
    }
}
