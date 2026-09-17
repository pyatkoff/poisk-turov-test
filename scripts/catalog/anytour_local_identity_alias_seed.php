<?php
declare(strict_types=1);

/**
 * LOCAL one-shot bridge migration: materialize an AnyTour-owned alias from the
 * historical accepted local/catalog id to the independent canonical hotel id.
 *
 * The historical legacy_catalog source is retained as provenance. This operation
 * never changes MATCH decisions or canonical hotel presentation.
 */
final class AnyTourLocalIdentityAliasSeed
{
    public const ALIAS_NAMESPACE = 'anytour_local_id';
    public const ACQUIRED_VIA = 'canonical_local_alias_v1';
    private const LEGACY_NAMESPACE = 'legacy_catalog';
    private const MAX_ROWS = 250000;

    public function __construct(private PDO $db) {}

    private static function positiveInt(mixed $value): int
    {
        if (is_int($value) && $value > 0) return $value;
        if (is_string($value) && preg_match('/\A[1-9][0-9]*\z/D', $value)
            && filter_var($value, FILTER_VALIDATE_INT) !== false) return (int)$value;
        throw new RuntimeException('ANYTOUR_LOCAL_ALIAS_ID');
    }

    private static function json(array $value): string
    {
        ksort($value, SORT_STRING);
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        return count($value) === count($expected)
            && array_diff($expected, array_keys($value)) === []
            && array_diff(array_keys($value), $expected) === [];
    }

    private static function aliasPayload(int $localId, int $canonicalId, string $sourceSha): array
    {
        return [
            'accepted_local_hotel_id' => $localId,
            'canonical_hotel_id' => $canonicalId,
            'derived_from_namespace' => self::LEGACY_NAMESPACE,
            'derived_from_source_sha256' => $sourceSha,
            'schema_version' => 1,
        ];
    }

    private static function verifyAliasRow(array $row, int $localId, int $canonicalId): bool
    {
        if (($row['acquired_via'] ?? null) !== self::ACQUIRED_VIA
            || (int)($row['anytour_hotel_id'] ?? 0) !== $canonicalId
            || !is_string($row['source_json'] ?? null)
            || !is_string($row['source_sha256'] ?? null)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $row['source_sha256'])
            || !hash_equals($row['source_sha256'], hash('sha256', $row['source_json']))) return false;
        try { $decoded = json_decode($row['source_json'], true, 32, JSON_THROW_ON_ERROR); }
        catch (Throwable) { return false; }
        if (!is_array($decoded) || !self::exactKeys($decoded, [
            'accepted_local_hotel_id','canonical_hotel_id','derived_from_namespace',
            'derived_from_source_sha256','schema_version'
        ])) return false;
        return ($decoded['schema_version'] ?? null) === 1
            && ($decoded['accepted_local_hotel_id'] ?? null) === $localId
            && ($decoded['canonical_hotel_id'] ?? null) === $canonicalId
            && ($decoded['derived_from_namespace'] ?? null) === self::LEGACY_NAMESPACE
            && is_string($decoded['derived_from_source_sha256'] ?? null)
            && preg_match('/\A[a-f0-9]{64}\z/D', $decoded['derived_from_source_sha256']) === 1;
    }

    private function assertSchema(): void
    {
        $tables = $this->db->query("SELECT TABLE_NAME,ENGINE,TABLE_TYPE FROM information_schema.TABLES
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('anytour_catalog_control','anytour_hotels','anytour_hotel_sources')")
            ->fetchAll(PDO::FETCH_ASSOC);
        $byName = [];
        foreach ($tables as $row) $byName[$row['TABLE_NAME']] = $row;
        foreach (['anytour_catalog_control','anytour_hotels','anytour_hotel_sources'] as $name) {
            if (($byName[$name]['ENGINE'] ?? null) !== 'InnoDB'
                || ($byName[$name]['TABLE_TYPE'] ?? null) !== 'BASE TABLE') {
                throw new RuntimeException('ANYTOUR_LOCAL_ALIAS_SCHEMA');
            }
        }
        $version = $this->db->query('SELECT schema_version FROM anytour_catalog_control WHERE singleton_id=1')->fetchColumn();
        if ((int)$version !== 1) throw new RuntimeException('ANYTOUR_LOCAL_ALIAS_SCHEMA_VERSION');
    }

    /** @return array<int,array{canonical:int,source_sha:string}> */
    private function currentLegacyTargets(bool $lock): array
    {
        $sql = "SELECT CAST(s.external_key AS CHAR) AS local_id,s.anytour_hotel_id,s.source_json,s.source_sha256
            FROM anytour_hotel_sources s
            JOIN anytour_hotels h ON h.id=s.anytour_hotel_id AND h.is_active=1
            WHERE s.namespace='legacy_catalog'
            ORDER BY CAST(s.external_key AS UNSIGNED),s.id" . ($lock ? ' FOR UPDATE' : '');
        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) < 1 || count($rows) > self::MAX_ROWS) throw new RuntimeException('ANYTOUR_LOCAL_ALIAS_SOURCE_BOUND');
        $targets = [];
        foreach ($rows as $row) {
            $local = self::positiveInt($row['local_id'] ?? null);
            $canonical = self::positiveInt($row['anytour_hotel_id'] ?? null);
            $sourceJson = (string)($row['source_json'] ?? '');
            $sourceSha = (string)($row['source_sha256'] ?? '');
            if (!preg_match('/\A[a-f0-9]{64}\z/D', $sourceSha)
                || !hash_equals($sourceSha, hash('sha256', $sourceJson))) {
                throw new RuntimeException('ANYTOUR_LOCAL_ALIAS_SOURCE_INTEGRITY');
            }
            if (isset($targets[$local]) && $targets[$local]['canonical'] !== $canonical) {
                throw new DomainException('ANYTOUR_LOCAL_ALIAS_SOURCE_CONFLICT');
            }
            $targets[$local] = ['canonical'=>$canonical,'source_sha'=>$sourceSha];
        }
        return $targets;
    }

    /** @return array<int,array<string,mixed>> */
    private function currentAliases(bool $lock): array
    {
        $sql = "SELECT CAST(external_key AS CHAR) AS local_id,anytour_hotel_id,acquired_via,source_json,source_sha256
            FROM anytour_hotel_sources WHERE namespace='" . self::ALIAS_NAMESPACE . "'
            ORDER BY CAST(external_key AS UNSIGNED),id" . ($lock ? ' FOR UPDATE' : '');
        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > self::MAX_ROWS) throw new RuntimeException('ANYTOUR_LOCAL_ALIAS_TARGET_BOUND');
        $aliases = [];
        foreach ($rows as $row) {
            $local = self::positiveInt($row['local_id'] ?? null);
            if (isset($aliases[$local])) throw new DomainException('ANYTOUR_LOCAL_ALIAS_DUPLICATE');
            $aliases[$local] = $row;
        }
        return $aliases;
    }

    private function analyze(array $targets, array $aliases): array
    {
        $missing = 0; $unchanged = 0; $conflicts = 0;
        foreach ($targets as $local=>$target) {
            if (!isset($aliases[$local])) { ++$missing; continue; }
            if (!self::verifyAliasRow($aliases[$local], $local, $target['canonical'])) { ++$conflicts; continue; }
            ++$unchanged;
        }
        foreach ($aliases as $local=>$row) if (!isset($targets[$local])) ++$conflicts;
        return ['source_profiles'=>count($targets),'existing_aliases'=>count($aliases),'missing'=>$missing,'unchanged'=>$unchanged,'conflicts'=>$conflicts];
    }

    public function plan(string $operation, string $baseReleaseSha): array
    {
        self::validateInvocation($operation, $baseReleaseSha);
        if ($this->db->inTransaction()) throw new LogicException('ANYTOUR_LOCAL_ALIAS_CALLER_TRANSACTION');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->db->exec('SET TRANSACTION READ ONLY');
        $this->db->beginTransaction();
        try {
            $this->assertSchema();
            $targets = $this->currentLegacyTargets(false);
            $aliases = $this->currentAliases(false);
            $analysis = $this->analyze($targets, $aliases);
            $sourceDigest = hash('sha256', self::json(array_map(
                static fn(array $v): array => ['canonical'=>$v['canonical'],'source_sha'=>$v['source_sha']],
                $targets
            )));
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
        return [
            'schema_version'=>1,'operation'=>$operation,'mode'=>'plan','status'=>'prepared_read_only',
            'base_release_sha'=>$baseReleaseSha,'alias_namespace'=>self::ALIAS_NAMESPACE,
            'source_digest'=>$sourceDigest,'analysis'=>$analysis,
            'writes'=>0,'mapping_writes'=>0,'profile_writes'=>0,'legacy_writes'=>0,'supplier_calls'=>0,
        ];
    }

    public function apply(string $operation, string $baseReleaseSha, string $expectedSourceDigest): array
    {
        self::validateInvocation($operation, $baseReleaseSha);
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $expectedSourceDigest)) throw new InvalidArgumentException('ANYTOUR_LOCAL_ALIAS_SOURCE_DIGEST');
        if ($this->db->inTransaction()) throw new LogicException('ANYTOUR_LOCAL_ALIAS_CALLER_TRANSACTION');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $this->db->beginTransaction();
        try {
            $this->assertSchema();
            $targets = $this->currentLegacyTargets(true);
            $aliases = $this->currentAliases(true);
            $analysis = $this->analyze($targets, $aliases);
            $sourceDigest = hash('sha256', self::json(array_map(
                static fn(array $v): array => ['canonical'=>$v['canonical'],'source_sha'=>$v['source_sha']],
                $targets
            )));
            if (!hash_equals($expectedSourceDigest, $sourceDigest)) throw new DomainException('ANYTOUR_LOCAL_ALIAS_SOURCE_DRIFT');
            if ($analysis['conflicts'] !== 0) throw new DomainException('ANYTOUR_LOCAL_ALIAS_CONFLICT');

            $insert = $this->db->prepare("INSERT INTO anytour_hotel_sources
                (namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
                VALUES (:namespace,:external_key,:canonical,:via,:source_json,:source_sha,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
            $created = 0;
            foreach ($targets as $local=>$target) {
                if (isset($aliases[$local])) continue;
                $payload = self::aliasPayload($local, $target['canonical'], $target['source_sha']);
                $json = self::json($payload);
                $insert->execute([
                    'namespace'=>self::ALIAS_NAMESPACE,'external_key'=>(string)$local,'canonical'=>$target['canonical'],
                    'via'=>self::ACQUIRED_VIA,'source_json'=>$json,'source_sha'=>hash('sha256',$json),
                ]);
                if ($insert->rowCount() !== 1) throw new RuntimeException('ANYTOUR_LOCAL_ALIAS_INSERT');
                ++$created;
            }
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }

        $this->db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->db->exec('SET TRANSACTION READ ONLY');
        $this->db->beginTransaction();
        try {
            $this->assertSchema();
            $targetsAfter = $this->currentLegacyTargets(false);
            $aliasesAfter = $this->currentAliases(false);
            $verified = $this->analyze($targetsAfter, $aliasesAfter);
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
        if ($verified['missing'] !== 0 || $verified['conflicts'] !== 0
            || $verified['unchanged'] !== $verified['source_profiles']) {
            throw new RuntimeException('ANYTOUR_LOCAL_ALIAS_POSTCOMMIT_VERIFY');
        }
        return [
            'schema_version'=>1,'operation'=>$operation,'mode'=>'apply','status'=>'committed_verified',
            'base_release_sha'=>$baseReleaseSha,'alias_namespace'=>self::ALIAS_NAMESPACE,
            'source_digest'=>$expectedSourceDigest,'created'=>$created,'verified_aliases'=>$verified['unchanged'],
            'source_profiles'=>$verified['source_profiles'],'conflicts'=>$verified['conflicts'],'missing'=>$verified['missing'],
            'mapping_writes'=>0,'profile_writes'=>0,'legacy_writes'=>0,'supplier_calls'=>0,
        ];
    }

    private static function validateInvocation(string $operation, string $baseReleaseSha): void
    {
        if (!preg_match('/\A[a-z0-9][a-z0-9_-]{0,127}\z/D', $operation)) throw new InvalidArgumentException('ANYTOUR_LOCAL_ALIAS_OPERATION');
        if (!preg_match('/\A[a-f0-9]{40}\z/D', $baseReleaseSha)) throw new InvalidArgumentException('ANYTOUR_LOCAL_ALIAS_BASE');
    }
}

function anytour_local_alias_live_db(): PDO
{
    $siteRoot = rtrim((string)getenv('ANYTOUR_SITE_ROOT'), "/\\");
    if ($siteRoot === '') throw new RuntimeException('ANYTOUR_SITE_ROOT required');
    $helper = $siteRoot . '/data/db-v1.php';
    if (!is_file($helper)) throw new RuntimeException('AnyTour DB helper unavailable');
    require_once $helper;
    if (!function_exists('v2_data_db')) throw new RuntimeException('AnyTour DB helper contract unavailable');
    $pdo = v2_data_db();
    if (!$pdo instanceof PDO || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') throw new RuntimeException('AnyTour MySQL unavailable');
    return $pdo;
}

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        $args = [];
        foreach (array_slice($argv,1) as $arg) {
            if (!preg_match('/\A--(mode|operation|base-release-sha|expected-source-digest)=(.+)\z/D',$arg,$m) || isset($args[$m[1]])) {
                throw new InvalidArgumentException('USAGE');
            }
            $args[$m[1]] = $m[2];
        }
        if (!isset($args['mode'],$args['operation'],$args['base-release-sha']) || !in_array($args['mode'],['plan','apply'],true)) {
            throw new InvalidArgumentException('USAGE');
        }
        $seed = new AnyTourLocalIdentityAliasSeed(anytour_local_alias_live_db());
        $result = $args['mode'] === 'plan'
            ? $seed->plan($args['operation'],$args['base-release-sha'])
            : $seed->apply($args['operation'],$args['base-release-sha'],(string)($args['expected-source-digest'] ?? ''));
        echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable $error) {
        fwrite(STDERR,'ANYTOUR_LOCAL_ALIAS_FAILED code=' . preg_replace('/[^A-Z0-9_]/','_',strtoupper($error->getMessage())) . "\n");
        exit(1);
    }
}
