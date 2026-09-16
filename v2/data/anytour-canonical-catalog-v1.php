<?php
/** Independent AnyTour profiles. No endpoint, network, schema installation or legacy writes. */
declare(strict_types=1);
require_once __DIR__ . '/hotel-presentation-read-v1.php';

final class AnyTourCanonicalCatalog
{
    public const BATCH_LIMIT = 1000;
    public const LEGACY_NAMESPACE = 'legacy_catalog';

    public function __construct(private PDO $pdo) {}

    public static function ids(array $values): array
    {
        if (!array_is_list($values) || !$values || count($values) > self::BATCH_LIMIT) {
            throw new InvalidArgumentException('Expected 1..1000 explicit IDs');
        }
        $ids = [];
        foreach ($values as $value) {
            if ((!is_int($value) && !is_string($value))
                || !preg_match('/^[1-9][0-9]*$/D', (string)$value)
                || filter_var($value, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('Invalid ID');
            }
            $ids[(int)$value] = (int)$value;
        }
        sort($ids, SORT_NUMERIC);
        return array_values($ids);
    }

    public static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** Seed selected materialized content, not a foreign-ID-shaped canonical entity. */
    public static function initialProfile(array $source): array
    {
        if (!is_string($source['name'] ?? null) || trim($source['name']) === '') {
            throw new InvalidArgumentException('A named saved hotel profile is required');
        }
        $profile = $source;
        unset($profile['id'], $profile['detailsFetchedAt']);
        foreach (['country', 'region', 'subRegion'] as $field) {
            if (is_array($profile[$field] ?? null)) unset($profile[$field]['id']);
        }
        // Numeric hotel type belongs to the legacy dictionary, not to AnyTour.
        unset($profile['type']);
        $profile['traits'] = [];
        // These legacy descriptive blocks are NOT normalized offer dictionaries.
        $profile['hotelInformation'] = [
            'meals' => $profile['meals'] ?? [], 'roomTypes' => $profile['roomTypes'] ?? null,
            'services' => $profile['services'] ?? [], 'infrastructure' => $profile['infrastructure'] ?? [],
        ];
        unset($profile['meals'], $profile['roomTypes'], $profile['services'], $profile['infrastructure']);
        return $profile;
    }

    public function assertSchema(): void
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('MySQL-compatible InnoDB database required');
        }
        $rows = $this->pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN
            ('anytour_catalog_control','anytour_hotels','anytour_hotel_sources','catalog_hotels','catalog_hotel_details')")->fetchAll(PDO::FETCH_KEY_PAIR);
        if (count($rows) !== 5 || count(array_filter($rows, static fn($engine) => $engine === 'InnoDB')) !== 5
            || (int)$this->pdo->query('SELECT schema_version FROM anytour_catalog_control WHERE singleton_id=1')->fetchColumn() !== 1) {
            throw new RuntimeException('Independent catalogue schema v1 must be installed and checked separately');
        }
    }

    private function begin(bool $write): void
    {
        if ($this->pdo->inTransaction()) throw new RuntimeException('Caller transaction must not be nested');
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->pdo->exec('SET TRANSACTION ' . ($write ? 'READ WRITE' : 'READ ONLY'));
        $this->pdo->beginTransaction();
    }

    private function sourceBatch(array $ids): array
    {
        $items = $missing = [];
        foreach (array_chunk($ids, HOTEL_PRESENTATION_READ_LIMIT) as $chunk) {
            $part = hotel_presentation_read_many($this->pdo, $chunk);
            array_push($items, ...$part['items']);
            array_push($missing, ...$part['missingIds']);
        }
        $batch = ['namespace' => self::LEGACY_NAMESPACE, 'requestedIds' => $ids,
            'items' => $items, 'missingIds' => $missing];
        return [$batch, hash('sha256', self::json($batch))];
    }

    public function plan(array $legacyIds): array
    {
        $ids = self::ids($legacyIds);
        $this->assertSchema();
        $this->begin(false);
        try {
            [$batch, $hash] = $this->sourceBatch($ids);
            $targets = $this->legacyTargets($ids);
            $result = ['status' => 'prepared_read_only', 'requestedIds' => $ids,
                'source_sha256' => $hash, 'source_profiles' => count($batch['items']),
                'missingIds' => $batch['missingIds'], 'existing_bridges' => count($targets),
                'writes' => 0];
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Apply only the exact saved-source batch inspected by plan(). No supplier IDs guessed. */
    public function seed(array $legacyIds, string $expectedHash): array
    {
        $ids = self::ids($legacyIds);
        if (!preg_match('/^[0-9a-f]{64}$/D', $expectedHash)) throw new InvalidArgumentException('Exact source hash required');
        $this->assertSchema();
        $this->begin(true);
        try {
            // Serialize our seed writers only. No locks or mutations of MATCH/source registries.
            $version = $this->pdo->query('SELECT schema_version FROM anytour_catalog_control WHERE singleton_id=1 FOR UPDATE')->fetchColumn();
            if ((int)$version !== 1) throw new RuntimeException('Catalogue schema changed');
            [$batch, $hash] = $this->sourceBatch($ids);
            if (!hash_equals($expectedHash, $hash)) throw new RuntimeException('Source changed; obtain and inspect a new plan');
            $find = $this->pdo->prepare("SELECT s.anytour_hotel_id,s.source_sha256,h.profile_sha256,h.revision,h.is_active
                FROM anytour_hotel_sources s JOIN anytour_hotels h ON h.id=s.anytour_hotel_id
                WHERE s.namespace='legacy_catalog' AND s.external_key=? FOR UPDATE");
            $insertHotel = $this->pdo->prepare('INSERT INTO anytour_hotels
                (profile_json,profile_sha256,created_at,updated_at) VALUES (?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
            $insertSource = $this->pdo->prepare("INSERT INTO anytour_hotel_sources
                (namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
                VALUES ('legacy_catalog',?,?,'saved_catalog',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
            $refresh = $this->pdo->prepare("UPDATE anytour_hotel_sources SET source_json=?,source_sha256=?,last_seen_at=UTC_TIMESTAMP()
                WHERE namespace='legacy_catalog' AND external_key=? AND anytour_hotel_id=?");
            $created = $changed = $unchanged = 0;
            $expected = [];
            foreach ($batch['items'] as $source) {
                $key = (string)$source['id'];
                $sourceJson = self::json($source); $sourceHash = hash('sha256', $sourceJson);
                $find->execute([$key]); $existing = $find->fetch(PDO::FETCH_ASSOC);
                if ($existing === false) {
                    $json = self::json(self::initialProfile($source)); $profileHash = hash('sha256', $json);
                    $insertHotel->execute([$json, $profileHash]);
                    $id = (int)$this->pdo->lastInsertId();
                    if ($id <= 0) throw new RuntimeException('Independent ID allocation failed');
                    $insertSource->execute([$key, $id, $sourceJson, $sourceHash]);
                    $revision = 1; $active = 1; $created++;
                } else {
                    $id = (int)$existing['anytour_hotel_id'];
                    $profileHash = $existing['profile_sha256'];
                    $revision = (int)$existing['revision']; $active = (int)$existing['is_active'];
                    if ($existing['source_sha256'] !== $sourceHash) {
                        // Source refresh never modifies an existing canonical profile, even blank fields.
                        $refresh->execute([$sourceJson, $sourceHash, $key, $id]); $changed++;
                    } else $unchanged++;
                }
                $expected[$key] = ['id' => $id, 'source_sha256' => $sourceHash,
                    'profile_sha256' => $profileHash, 'revision' => $revision, 'is_active' => $active];
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        // Independent post-COMMIT readback; a lost connection here is NOT reported as rollback.
        foreach ($expected as $key => $want) {
            $check = $this->pdo->prepare("SELECT s.anytour_hotel_id AS id,s.source_sha256,s.source_json,h.profile_sha256,h.profile_json,h.revision,h.is_active
                FROM anytour_hotel_sources s JOIN anytour_hotels h ON h.id=s.anytour_hotel_id
                WHERE s.namespace='legacy_catalog' AND s.external_key=?");
            $check->execute([(string)$key]); $got = $check->fetch(PDO::FETCH_ASSOC);
            if (!$got || (int)$got['id'] !== $want['id'] || (int)$got['revision'] !== $want['revision']
                || (int)$got['is_active'] !== $want['is_active']
                || $got['source_sha256'] !== $want['source_sha256'] || $got['profile_sha256'] !== $want['profile_sha256']
                || hash('sha256', $got['source_json']) !== $got['source_sha256']
                || hash('sha256', $got['profile_json']) !== $got['profile_sha256']) {
                throw new RuntimeException('Committed catalogue readback differs; inspect before any next operation');
            }
        }
        return ['status' => 'committed_verified', 'source_sha256' => $hash, 'created' => $created,
            'source_snapshots_refreshed' => $changed, 'unchanged' => $unchanged,
            'missingIds' => $batch['missingIds'], 'verified_bridges' => count($expected),
            'canonical_profiles_overwritten' => 0, 'legacy_writes' => 0, 'supplier_calls' => 0];
    }

    /** Caller first resolves provider identity with the existing authoritative MATCH resolver. */
    public function legacyTargets(array $legacyIds): array
    {
        $ids = self::ids($legacyIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT external_key,anytour_hotel_id FROM anytour_hotel_sources
            WHERE namespace='legacy_catalog' AND external_key IN ($placeholders)");
        $stmt->execute(array_map('strval', $ids)); $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $result = [];
        foreach ($ids as $id) if (isset($rows[$id])) $result[$id] = (int)$rows[$id];
        return $result;
    }

    public function read(array $anytourIds): array
    {
        $ids = self::ids($anytourIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id,profile_json,profile_sha256,revision FROM anytour_hotels
            WHERE is_active=1 AND id IN ($placeholders)");
        $stmt->execute($ids); $byId = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!hash_equals($row['profile_sha256'], hash('sha256', $row['profile_json']))) {
                throw new RuntimeException('Canonical profile integrity mismatch');
            }
            $profile = json_decode($row['profile_json'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($profile)) throw new RuntimeException('Invalid canonical profile');
            $id = (int)$row['id'];
            $byId[$id] = array_merge($profile, ['id' => $id, 'catalog' => 'anytour', 'revision' => (int)$row['revision']]);
        }
        $items = $missing = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) $items[] = $byId[$id];
            else $missing[] = $id;
        }
        return ['source' => 'anytour-canonical-catalog', 'items' => $items, 'missingIds' => $missing];
    }
}
