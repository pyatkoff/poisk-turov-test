<?php
declare(strict_types=1);

/**
 * Dormant SEARCH meal-provider mapping activation contract.
 *
 * This file has no HTTP entrypoint, no SSH, no supplier calls and no automatic
 * execution path. Mutating CLI commands require an explicit --allow-write flag.
 * Live execution remains a separate HIGH operation requiring exact authorization.
 */
final class Search3MealProviderMappingActivateV1
{
    public const TABLE = 'anytour_search_meal_provider_mappings_v1';
    public const PROVIDER = 'tourvisor';
    public const SCOPE = 'global';
    public const EVIDENCE_RESULT_SHA256 = 'a0755910d8e46c4f85021de5cae1ec88f29a2a811d4cc4f5c02d6c8ea0dfa4fa';
    public const EVIDENCE_REF = 'search3-meal-provider-mapping-plan-3353-20260923-v1;result-sha256:a0755910d8e46c4f85021de5cae1ec88f29a2a811d4cc4f5c02d6c8ea0dfa4fa';
    public const REVIEWED_BY = 'owner:pyatkoff;reviewed-evidence:2026-09-23';

    /** @var array<string,array{id:int,code:string,name:string}> */
    private const MAPPINGS = [
        '3' => ['id' => 2, 'code' => 'breakfast', 'name' => 'Завтраки'],
        '4' => ['id' => 3, 'code' => 'half-board', 'name' => 'Полупансион'],
        '7' => ['id' => 7, 'code' => 'all-inclusive', 'name' => 'Всё включено'],
        '9' => ['id' => 8, 'code' => 'ultra-all-inclusive', 'name' => 'Ультра всё включено'],
    ];

    public function __construct(private PDO $db)
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    /** @return array<string,mixed> */
    public function plan(): array
    {
        $this->assertCanonicalPlans();
        if (!$this->tableExists(self::TABLE)) {
            return [
                'state' => 'schema_absent',
                'provider' => self::PROVIDER,
                'scope' => self::SCOPE,
                'expectedMappings' => count(self::MAPPINGS),
                'writes' => 0,
            ];
        }

        $this->assertExactSchema();
        $rows = $this->scopeRows(false);
        $classification = $this->classifyRows($rows);
        return [
            'state' => $classification,
            'provider' => self::PROVIDER,
            'scope' => self::SCOPE,
            'expectedMappings' => count(self::MAPPINGS),
            'currentRows' => count($rows),
            'externalIds' => array_values(array_map(static fn(array $r): string => (string)$r['external_id'], $rows)),
            'writes' => 0,
        ];
    }

    /** @return array<string,mixed> */
    public function installSchema(bool $allowWrite): array
    {
        $this->assertCanonicalPlans();
        if ($this->tableExists(self::TABLE)) {
            $this->assertExactSchema();
            return ['state' => 'schema_already_exact', 'writes' => 0];
        }
        if (!$allowWrite) {
            throw new RuntimeException('SEARCH3_MEAL_WRITE_NOT_AUTHORIZED');
        }

        $migration = dirname(__DIR__, 2).'/v2/data/migrations/20260923-anytour-search-meal-provider-mappings-v1.sql';
        $sql = @file_get_contents($migration);
        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException('SEARCH3_MEAL_MIGRATION_MISSING');
        }
        if (str_contains($sql, 'anytour_search_meal_memberships_v1') || str_contains($sql, 'anytour_hotel_')) {
            throw new RuntimeException('SEARCH3_MEAL_MIGRATION_SCOPE_DRIFT');
        }
        if (substr_count($sql, 'CREATE TABLE '.self::TABLE) !== 1) {
            throw new RuntimeException('SEARCH3_MEAL_MIGRATION_IDENTITY_DRIFT');
        }

        $this->db->exec($sql);
        $this->assertExactSchema();
        return ['state' => 'schema_installed', 'writes' => 1];
    }

    /** @return array<string,mixed> */
    public function seed(bool $allowWrite): array
    {
        $this->assertCanonicalPlans();
        if (!$this->tableExists(self::TABLE)) {
            throw new RuntimeException('SEARCH3_MEAL_SCHEMA_ABSENT');
        }
        $this->assertExactSchema();

        $this->db->beginTransaction();
        try {
            $rows = $this->scopeRows(true);
            $classification = $this->classifyRows($rows);
            if ($classification === 'exact') {
                $this->db->rollBack();
                return ['state' => 'seed_already_exact', 'writes' => 0, 'rows' => count($rows)];
            }
            if ($classification !== 'empty') {
                throw new RuntimeException('SEARCH3_MEAL_MAPPING_STATE_NOT_EMPTY:'.$classification);
            }
            if (!$allowWrite) {
                throw new RuntimeException('SEARCH3_MEAL_WRITE_NOT_AUTHORIZED');
            }

            $insert = $this->db->prepare(
                'INSERT INTO '.self::TABLE.' '
                .'(provider,scope_key,external_id,meal_plan_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at) '
                .'VALUES (:provider,:scope_key,:external_id,:meal_plan_id,\'accepted\',:evidence_ref,:evidence_sha256,:reviewed_by,UTC_TIMESTAMP())'
            );
            foreach (self::MAPPINGS as $externalId => $plan) {
                $insert->execute([
                    ':provider' => self::PROVIDER,
                    ':scope_key' => self::SCOPE,
                    ':external_id' => $externalId,
                    ':meal_plan_id' => $plan['id'],
                    ':evidence_ref' => self::EVIDENCE_REF,
                    ':evidence_sha256' => self::EVIDENCE_RESULT_SHA256,
                    ':reviewed_by' => self::REVIEWED_BY,
                ]);
            }

            $after = $this->scopeRows(false);
            if ($this->classifyRows($after) !== 'exact') {
                throw new RuntimeException('SEARCH3_MEAL_POSTINSERT_MISMATCH');
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $verified = $this->scopeRows(false);
        if ($this->classifyRows($verified) !== 'exact') {
            throw new RuntimeException('SEARCH3_MEAL_POSTCOMMIT_MISMATCH');
        }
        return ['state' => 'seeded', 'writes' => count(self::MAPPINGS), 'rows' => count($verified)];
    }

    private function assertCanonicalPlans(): void
    {
        if (!$this->tableExists('anytour_meal_plans')) {
            throw new RuntimeException('SEARCH3_MEAL_CANONICAL_PLANS_ABSENT');
        }
        $ids = array_values(array_map(static fn(array $m): int => $m['id'], self::MAPPINGS));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare('SELECT id,code,name_ru,is_active FROM anytour_meal_plans WHERE id IN ('.$placeholders.') ORDER BY id');
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int)$row['id']] = $row;
        }
        foreach (self::MAPPINGS as $mapping) {
            $row = $byId[$mapping['id']] ?? null;
            if (!is_array($row)
                || (string)$row['code'] !== $mapping['code']
                || (string)$row['name_ru'] !== $mapping['name']
                || (int)$row['is_active'] !== 1) {
                throw new RuntimeException('SEARCH3_MEAL_CANONICAL_PLAN_DRIFT:'.$mapping['id']);
            }
        }
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() === 1;
    }

    private function assertExactSchema(): void
    {
        $stmt = $this->db->prepare(
            'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME '
            .'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION'
        );
        $stmt->execute([self::TABLE]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $expected = [
            ['provider','varchar(16)','NO','ascii_bin'],
            ['scope_key','varbinary(128)','NO',null],
            ['external_id','varbinary(128)','NO',null],
            ['meal_plan_id','bigint unsigned','YES',null],
            ['state',"enum('pending','accepted','rejected','conflict')",'NO','utf8mb4_bin'],
            ['evidence_ref','varchar(255)','NO','utf8mb4_bin'],
            ['evidence_sha256','char(64)','NO','ascii_bin'],
            ['reviewed_by','varchar(128)','NO','utf8mb4_bin'],
            ['created_at','datetime','NO',null],
        ];
        if (count($rows) !== count($expected)) {
            throw new RuntimeException('SEARCH3_MEAL_SCHEMA_COLUMN_COUNT');
        }
        foreach ($expected as $i => $want) {
            $got = $rows[$i];
            if ((string)$got['COLUMN_NAME'] !== $want[0]
                || strtolower((string)$got['COLUMN_TYPE']) !== $want[1]
                || (string)$got['IS_NULLABLE'] !== $want[2]
                || (($got['COLLATION_NAME'] ?? null) !== $want[3])) {
                throw new RuntimeException('SEARCH3_MEAL_SCHEMA_COLUMN_DRIFT:'.$want[0]);
            }
        }

        $c = $this->db->prepare(
            'SELECT CONSTRAINT_NAME,CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS '
            .'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY CONSTRAINT_NAME'
        );
        $c->execute([self::TABLE]);
        $constraints = [];
        foreach ($c->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $constraints[(string)$row['CONSTRAINT_NAME']] = (string)$row['CONSTRAINT_TYPE'];
        }
        $required = [
            'PRIMARY' => 'PRIMARY KEY',
            'fk_search_meal_plan' => 'FOREIGN KEY',
            'ck_search_meal_provider' => 'CHECK',
            'ck_search_meal_state' => 'CHECK',
            'ck_search_meal_keys' => 'CHECK',
        ];
        foreach ($required as $name => $type) {
            if (($constraints[$name] ?? null) !== $type) {
                throw new RuntimeException('SEARCH3_MEAL_SCHEMA_CONSTRAINT_DRIFT:'.$name);
            }
        }
        if (count($constraints) !== count($required)) {
            throw new RuntimeException('SEARCH3_MEAL_SCHEMA_CONSTRAINT_COUNT');
        }

        $i = $this->db->prepare(
            'SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR \',\') AS cols '
            .'FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? '
            .'GROUP BY INDEX_NAME,NON_UNIQUE ORDER BY INDEX_NAME'
        );
        $i->execute([self::TABLE]);
        $indexes = [];
        foreach ($i->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $indexes[(string)$row['INDEX_NAME']] = [(int)$row['NON_UNIQUE'], (string)$row['cols']];
        }
        if (($indexes['PRIMARY'] ?? null) !== [0, 'provider,scope_key,external_id']
            || ($indexes['ix_search_meal_plan'] ?? null) !== [1, 'provider,scope_key,meal_plan_id']
            || count($indexes) !== 2) {
            throw new RuntimeException('SEARCH3_MEAL_SCHEMA_INDEX_DRIFT');
        }
    }

    /** @return list<array<string,mixed>> */
    private function scopeRows(bool $forUpdate): array
    {
        $sql = 'SELECT provider,scope_key,external_id,meal_plan_id,state,evidence_ref,evidence_sha256,reviewed_by '
            .'FROM '.self::TABLE.' WHERE provider=? AND scope_key=? ORDER BY external_id';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([self::PROVIDER, self::SCOPE]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param list<array<string,mixed>> $rows */
    private function classifyRows(array $rows): string
    {
        if ($rows === []) {
            return 'empty';
        }
        if (count($rows) !== count(self::MAPPINGS)) {
            return 'partial_or_foreign';
        }
        foreach ($rows as $row) {
            $externalId = (string)$row['external_id'];
            $expected = self::MAPPINGS[$externalId] ?? null;
            if ($expected === null) {
                return 'foreign';
            }
            if ((string)$row['provider'] !== self::PROVIDER
                || (string)$row['scope_key'] !== self::SCOPE
                || (int)$row['meal_plan_id'] !== $expected['id']
                || (string)$row['state'] !== 'accepted'
                || (string)$row['evidence_ref'] !== self::EVIDENCE_REF
                || (string)$row['evidence_sha256'] !== self::EVIDENCE_RESULT_SHA256
                || (string)$row['reviewed_by'] !== self::REVIEWED_BY) {
                return 'conflict';
            }
        }
        return 'exact';
    }
}

function search3MealActivationCli(array $argv): int
{
    $command = $argv[1] ?? '';
    if (!in_array($command, ['plan','install-schema','seed'], true)) {
        fwrite(STDERR, "usage: php search3_meal_provider_mapping_activate_v1.php plan|install-schema|seed [--allow-write]\n");
        return 64;
    }
    $allowWrite = in_array('--allow-write', $argv, true);
    $dsn = (string)getenv('SEARCH3_MEAL_ACTIVATION_DSN');
    $user = (string)getenv('SEARCH3_MEAL_ACTIVATION_USER');
    $password = (string)getenv('SEARCH3_MEAL_ACTIVATION_PASSWORD');
    if ($dsn === '') {
        fwrite(STDERR, "SEARCH3_MEAL_ACTIVATION_DSN is required\n");
        return 64;
    }
    try {
        $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $activation = new Search3MealProviderMappingActivateV1($pdo);
        $result = match ($command) {
            'plan' => $activation->plan(),
            'install-schema' => $activation->installSchema($allowWrite),
            'seed' => $activation->seed($allowWrite),
        };
        echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
        return 0;
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage()."\n");
        return 1;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(search3MealActivationCli($argv));
}
