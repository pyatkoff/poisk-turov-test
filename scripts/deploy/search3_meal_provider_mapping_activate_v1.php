<?php
declare(strict_types=1);

/**
 * Dormant HIGH-risk activation contract for the four reviewed Tourvisor meal mappings.
 *
 * This file has no HTTP entrypoint, scheduler, SSH, secret lookup, or automatic execution path.
 * Later trusted execution must provide an exact DB target through environment variables and invoke
 * one explicit CLI command: plan, install-schema, or seed.
 */

const MEAL_ACTIVATE_MIGRATION = 'v2/data/migrations/20260923-anytour-search-meal-provider-mappings-v1.sql';
const MEAL_ACTIVATE_MIGRATION_SHA256 = 'fded346b412df53640e64910fe92ff8bb6d0cf92c892170de89b185479b1653b';
const MEAL_ACTIVATE_EVIDENCE_REF = 'issue3419/comment5791719696:search3-meal-provider-mapping-plan-3353-20260923-v1:result=a0755910d8e46c4f85021de5cae1ec88f29a2a811d4cc4f5c02d6c8ea0dfa4fa';
const MEAL_ACTIVATE_EVIDENCE_SHA256 = 'a0755910d8e46c4f85021de5cae1ec88f29a2a811d4cc4f5c02d6c8ea0dfa4fa';
const MEAL_ACTIVATE_REVIEWED_BY = 'owner:pyatkoff:issue3419/comment5791719696';

function meal_activate_expected_plans(): array
{
    return [
        1 => ['code' => 'no-meals', 'name_ru' => 'Без питания'],
        2 => ['code' => 'breakfast', 'name_ru' => 'Завтраки'],
        3 => ['code' => 'half-board', 'name_ru' => 'Полупансион'],
        4 => ['code' => 'half-board-plus', 'name_ru' => 'Полупансион плюс'],
        5 => ['code' => 'full-board', 'name_ru' => 'Полный пансион'],
        6 => ['code' => 'full-board-plus', 'name_ru' => 'Полный пансион плюс'],
        7 => ['code' => 'all-inclusive', 'name_ru' => 'Всё включено'],
        8 => ['code' => 'ultra-all-inclusive', 'name_ru' => 'Ультра всё включено'],
        9 => ['code' => 'soft-all-inclusive', 'name_ru' => 'Мягкое всё включено'],
        10 => ['code' => 'alcohol-free-all-inclusive', 'name_ru' => 'Всё включено без алкоголя'],
    ];
}

function meal_activate_expected_mappings(): array
{
    return [
        '3' => ['meal_plan_id' => 2, 'code' => 'breakfast', 'name_ru' => 'Завтраки', 'accepted_rows' => 79, 'evidence_set_sha256' => '2ee13e62b21e62533096efc9632bba3808d4eaa6e41b36d64eff1c8ceac70c35'],
        '4' => ['meal_plan_id' => 3, 'code' => 'half-board', 'name_ru' => 'Полупансион', 'accepted_rows' => 19, 'evidence_set_sha256' => '61b1451ac6a8b25d1f60437af71be70826b6598934b7b973541a722b2e58baed'],
        '7' => ['meal_plan_id' => 7, 'code' => 'all-inclusive', 'name_ru' => 'Всё включено', 'accepted_rows' => 100, 'evidence_set_sha256' => '4df9de7e01970682eb8684f04548f35d47906dc1523a99f52a8b1079d1b2c91a'],
        '9' => ['meal_plan_id' => 8, 'code' => 'ultra-all-inclusive', 'name_ru' => 'Ультра всё включено', 'accepted_rows' => 49, 'evidence_set_sha256' => '7c47ac252d7b29d2022205e473b741adaacce7143f9548a0ed7ea2477f419380'],
    ];
}

function meal_activate_error(string $code): never
{
    throw new RuntimeException($code);
}

function meal_activate_json(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
}

function meal_activate_pdo(): PDO
{
    $dsn = trim((string)getenv('ANYTOUR_SEARCH3_MEAL_ACTIVATE_DSN'));
    $user = (string)getenv('ANYTOUR_SEARCH3_MEAL_ACTIVATE_USER');
    $password = (string)getenv('ANYTOUR_SEARCH3_MEAL_ACTIVATE_PASSWORD');
    $expectedDb = trim((string)getenv('ANYTOUR_SEARCH3_MEAL_ACTIVATE_EXPECTED_DB'));
    if ($dsn === '' || !str_starts_with($dsn, 'mysql:') || $expectedDb === '' || preg_match('/^[A-Za-z0-9_$-]{1,64}$/D', $expectedDb) !== 1) {
        meal_activate_error('MEAL_ACTIVATE_TARGET_REQUIRED');
    }
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    $actualDb = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($actualDb !== $expectedDb) {
        meal_activate_error('MEAL_ACTIVATE_TARGET_MISMATCH');
    }
    return $pdo;
}

function meal_activate_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() === 1;
}

function meal_activate_assert_plans(PDO $pdo): void
{
    if (!meal_activate_table_exists($pdo, 'anytour_meal_plans')) {
        meal_activate_error('MEAL_ACTIVATE_PLANS_ABSENT');
    }
    $rows = $pdo->query('SELECT id,code,name_ru FROM anytour_meal_plans WHERE is_active=1 ORDER BY id')->fetchAll();
    $actual = [];
    foreach ($rows as $row) {
        $actual[(int)$row['id']] = ['code' => (string)$row['code'], 'name_ru' => (string)$row['name_ru']];
    }
    if ($actual !== meal_activate_expected_plans()) {
        meal_activate_error('MEAL_ACTIVATE_PLAN_CATALOG_MISMATCH');
    }
}

function meal_activate_assert_schema(PDO $pdo): void
{
    $table = 'anytour_search_meal_provider_mappings_v1';
    if (!meal_activate_table_exists($pdo, $table)) {
        meal_activate_error('MEAL_ACTIVATE_SCHEMA_ABSENT');
    }

    $tableStmt = $pdo->prepare('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $tableStmt->execute([$table]);
    $meta = $tableStmt->fetch();
    if (($meta['ENGINE'] ?? null) !== 'InnoDB' || ($meta['TABLE_COLLATION'] ?? null) !== 'utf8mb4_bin') {
        meal_activate_error('MEAL_ACTIVATE_SCHEMA_TABLE_MISMATCH');
    }

    $cols = $pdo->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
    $cols->execute([$table]);
    $actualCols = [];
    foreach ($cols->fetchAll() as $row) {
        $actualCols[] = [
            (string)$row['COLUMN_NAME'],
            strtolower((string)$row['COLUMN_TYPE']),
            (string)$row['IS_NULLABLE'],
            $row['CHARACTER_SET_NAME'] === null ? null : (string)$row['CHARACTER_SET_NAME'],
            $row['COLLATION_NAME'] === null ? null : (string)$row['COLLATION_NAME'],
        ];
    }
    $expectedCols = [
        ['provider', 'varchar(16)', 'NO', 'ascii', 'ascii_bin'],
        ['scope_key', 'varbinary(128)', 'NO', null, null],
        ['external_id', 'varbinary(128)', 'NO', null, null],
        ['meal_plan_id', 'bigint unsigned', 'YES', null, null],
        ['state', "enum('pending','accepted','rejected','conflict')", 'NO', 'utf8mb4', 'utf8mb4_bin'],
        ['evidence_ref', 'varchar(255)', 'NO', 'utf8mb4', 'utf8mb4_bin'],
        ['evidence_sha256', 'char(64)', 'NO', 'ascii', 'ascii_bin'],
        ['reviewed_by', 'varchar(128)', 'NO', 'utf8mb4', 'utf8mb4_bin'],
        ['created_at', 'datetime', 'NO', null, null],
    ];
    if ($actualCols !== $expectedCols) {
        meal_activate_error('MEAL_ACTIVATE_SCHEMA_COLUMNS_MISMATCH');
    }

    $constraints = $pdo->prepare('SELECT CONSTRAINT_NAME,CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY CONSTRAINT_NAME');
    $constraints->execute([$table]);
    $actualConstraints = [];
    foreach ($constraints->fetchAll() as $row) {
        $actualConstraints[(string)$row['CONSTRAINT_NAME']] = (string)$row['CONSTRAINT_TYPE'];
    }
    $expectedConstraints = [
        'PRIMARY' => 'PRIMARY KEY',
        'ck_search_meal_keys' => 'CHECK',
        'ck_search_meal_provider' => 'CHECK',
        'ck_search_meal_state' => 'CHECK',
        'fk_search_meal_plan' => 'FOREIGN KEY',
    ];
    ksort($actualConstraints);
    ksort($expectedConstraints);
    if ($actualConstraints !== $expectedConstraints) {
        meal_activate_error('MEAL_ACTIVATE_SCHEMA_CONSTRAINTS_MISMATCH');
    }

    $pk = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME='PRIMARY' ORDER BY ORDINAL_POSITION");
    $pk->execute([$table]);
    if (array_column($pk->fetchAll(), 'COLUMN_NAME') !== ['provider', 'scope_key', 'external_id']) {
        meal_activate_error('MEAL_ACTIVATE_SCHEMA_PK_MISMATCH');
    }

    $idx = $pdo->prepare("SELECT COLUMN_NAME,NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME='ix_search_meal_plan' ORDER BY SEQ_IN_INDEX");
    $idx->execute([$table]);
    $idxRows = $idx->fetchAll();
    if (array_column($idxRows, 'COLUMN_NAME') !== ['provider', 'scope_key', 'meal_plan_id'] || array_unique(array_map(static fn(array $r): int => (int)$r['NON_UNIQUE'], $idxRows)) !== [1]) {
        meal_activate_error('MEAL_ACTIVATE_SCHEMA_INDEX_MISMATCH');
    }

    $fk = $pdo->prepare("SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME='fk_search_meal_plan'");
    $fk->execute([$table]);
    $fkRows = $fk->fetchAll();
    if ($fkRows !== [['COLUMN_NAME' => 'meal_plan_id', 'REFERENCED_TABLE_NAME' => 'anytour_meal_plans', 'REFERENCED_COLUMN_NAME' => 'id']]) {
        meal_activate_error('MEAL_ACTIVATE_SCHEMA_FK_MISMATCH');
    }

    $checks = $pdo->prepare("SELECT CONSTRAINT_NAME,CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME IN ('ck_search_meal_provider','ck_search_meal_state','ck_search_meal_keys')");
    $checks->execute();
    $checkNames = [];
    foreach ($checks->fetchAll() as $row) {
        $checkNames[] = (string)$row['CONSTRAINT_NAME'];
        $clause = strtolower((string)$row['CHECK_CLAUSE']);
        if ((string)$row['CONSTRAINT_NAME'] === 'ck_search_meal_provider' && (!str_contains($clause, 'tourvisor') || !str_contains($clause, 'anex') || !str_contains($clause, 'andromeda'))) {
            meal_activate_error('MEAL_ACTIVATE_SCHEMA_CHECK_MISMATCH');
        }
        if ((string)$row['CONSTRAINT_NAME'] === 'ck_search_meal_state' && (!str_contains($clause, 'accepted') || !str_contains($clause, 'meal_plan_id'))) {
            meal_activate_error('MEAL_ACTIVATE_SCHEMA_CHECK_MISMATCH');
        }
        if ((string)$row['CONSTRAINT_NAME'] === 'ck_search_meal_keys' && (!str_contains($clause, 'scope_key') || !str_contains($clause, 'external_id') || !str_contains($clause, 'evidence_ref') || !str_contains($clause, 'reviewed_by'))) {
            meal_activate_error('MEAL_ACTIVATE_SCHEMA_CHECK_MISMATCH');
        }
    }
    sort($checkNames);
    if ($checkNames !== ['ck_search_meal_keys', 'ck_search_meal_provider', 'ck_search_meal_state']) {
        meal_activate_error('MEAL_ACTIVATE_SCHEMA_CHECK_MISMATCH');
    }
}

function meal_activate_rows(PDO $pdo, bool $lock = false): array
{
    $sql = 'SELECT provider,CONVERT(scope_key USING utf8mb4) AS scope_key,CONVERT(external_id USING utf8mb4) AS external_id,meal_plan_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at FROM anytour_search_meal_provider_mappings_v1 ORDER BY provider,scope_key,external_id';
    if ($lock) {
        $sql .= ' FOR UPDATE';
    }
    return $pdo->query($sql)->fetchAll();
}

function meal_activate_expected_row(string $externalId): array
{
    $mapping = meal_activate_expected_mappings()[$externalId] ?? null;
    if ($mapping === null) {
        meal_activate_error('MEAL_ACTIVATE_INTERNAL_MAPPING');
    }
    return [
        'provider' => 'tourvisor',
        'scope_key' => 'global',
        'external_id' => $externalId,
        'meal_plan_id' => $mapping['meal_plan_id'],
        'state' => 'accepted',
        'evidence_ref' => MEAL_ACTIVATE_EVIDENCE_REF,
        'evidence_sha256' => MEAL_ACTIVATE_EVIDENCE_SHA256,
        'reviewed_by' => MEAL_ACTIVATE_REVIEWED_BY,
    ];
}

function meal_activate_classify_rows(array $rows): array
{
    if ($rows === []) {
        return ['state' => 'empty', 'safe_to_seed' => true, 'count' => 0];
    }
    $expected = [];
    foreach (array_keys(meal_activate_expected_mappings()) as $externalId) {
        $row = meal_activate_expected_row($externalId);
        $expected[$row['provider'] . "\0" . $row['scope_key'] . "\0" . $row['external_id']] = $row;
    }

    $seen = [];
    $unexpected = [];
    foreach ($rows as $row) {
        $normalized = [
            'provider' => (string)$row['provider'],
            'scope_key' => (string)$row['scope_key'],
            'external_id' => (string)$row['external_id'],
            'meal_plan_id' => $row['meal_plan_id'] === null ? null : (int)$row['meal_plan_id'],
            'state' => (string)$row['state'],
            'evidence_ref' => (string)$row['evidence_ref'],
            'evidence_sha256' => (string)$row['evidence_sha256'],
            'reviewed_by' => (string)$row['reviewed_by'],
        ];
        $key = $normalized['provider'] . "\0" . $normalized['scope_key'] . "\0" . $normalized['external_id'];
        $seen[$key] = true;
        if (!isset($expected[$key]) || $normalized !== $expected[$key]) {
            $unexpected[] = [
                'provider' => $normalized['provider'],
                'scopeKey' => $normalized['scope_key'],
                'externalId' => $normalized['external_id'],
                'mealPlanId' => $normalized['meal_plan_id'],
                'state' => $normalized['state'],
            ];
        }
    }

    if ($unexpected !== []) {
        return ['state' => 'conflict_or_foreign', 'safe_to_seed' => false, 'count' => count($rows), 'unexpected' => $unexpected];
    }
    if (count($seen) !== count($expected) || array_diff_key($expected, $seen) !== []) {
        return ['state' => 'partial', 'safe_to_seed' => false, 'count' => count($rows)];
    }
    return ['state' => 'exact', 'safe_to_seed' => false, 'count' => count($rows)];
}

function meal_activate_plan(PDO $pdo): array
{
    meal_activate_assert_plans($pdo);
    $schemaPresent = meal_activate_table_exists($pdo, 'anytour_search_meal_provider_mappings_v1');
    $rowState = ['state' => 'schema_absent', 'safe_to_seed' => false, 'count' => 0];
    if ($schemaPresent) {
        meal_activate_assert_schema($pdo);
        $rowState = meal_activate_classify_rows(meal_activate_rows($pdo));
    }
    return [
        'state' => 'planned_read_only',
        'canonicalPlans' => count(meal_activate_expected_plans()),
        'schemaPresent' => $schemaPresent,
        'mappingState' => $rowState,
        'expectedMappings' => meal_activate_expected_mappings(),
        'evidenceRef' => MEAL_ACTIVATE_EVIDENCE_REF,
        'evidenceSha256' => MEAL_ACTIVATE_EVIDENCE_SHA256,
        'reviewedBy' => MEAL_ACTIVATE_REVIEWED_BY,
        'databaseWrites' => 0,
        'schemaWrites' => 0,
        'mappingWrites' => 0,
    ];
}

function meal_activate_install_schema(PDO $pdo): array
{
    meal_activate_assert_plans($pdo);
    if (meal_activate_table_exists($pdo, 'anytour_search_meal_provider_mappings_v1')) {
        meal_activate_assert_schema($pdo);
        $classification = meal_activate_classify_rows(meal_activate_rows($pdo));
        if (!in_array($classification['state'], ['empty', 'exact'], true)) {
            meal_activate_error('MEAL_ACTIVATE_EXISTING_STATE_UNSAFE');
        }
        return [
            'state' => 'schema_already_exact_read_only',
            'mappingState' => $classification,
            'databaseWrites' => 0,
            'schemaWrites' => 0,
            'mappingWrites' => 0,
        ];
    }

    $path = dirname(__DIR__, 2) . '/' . MEAL_ACTIVATE_MIGRATION;
    $sql = file_get_contents($path);
    if (!is_string($sql) || hash('sha256', $sql) !== MEAL_ACTIVATE_MIGRATION_SHA256) {
        meal_activate_error('MEAL_ACTIVATE_MIGRATION_MISMATCH');
    }
    if (substr_count($sql, 'CREATE TABLE anytour_search_meal_provider_mappings_v1') !== 1
        || stripos($sql, 'INSERT ') !== false
        || stripos($sql, 'UPDATE ') !== false
        || stripos($sql, 'DELETE ') !== false
        || str_contains($sql, 'anytour_search_meal_memberships_v1')
        || str_contains($sql, 'anytour_hotel_')) {
        meal_activate_error('MEAL_ACTIVATE_MIGRATION_SCOPE');
    }

    $pdo->exec($sql);
    meal_activate_assert_schema($pdo);
    $classification = meal_activate_classify_rows(meal_activate_rows($pdo));
    if ($classification['state'] !== 'empty') {
        meal_activate_error('MEAL_ACTIVATE_POST_SCHEMA_ROWS');
    }
    return [
        'state' => 'schema_installed',
        'mappingState' => $classification,
        'databaseWrites' => 1,
        'schemaWrites' => 1,
        'mappingWrites' => 0,
    ];
}

function meal_activate_seed(PDO $pdo): array
{
    meal_activate_assert_plans($pdo);
    meal_activate_assert_schema($pdo);

    $pdo->beginTransaction();
    try {
        $rows = meal_activate_rows($pdo, true);
        $classification = meal_activate_classify_rows($rows);
        if ($classification['state'] === 'exact') {
            $pdo->rollBack();
            return [
                'state' => 'mappings_already_exact_read_only',
                'mappingState' => $classification,
                'databaseWrites' => 0,
                'schemaWrites' => 0,
                'mappingWrites' => 0,
            ];
        }
        if ($classification['state'] !== 'empty') {
            meal_activate_error('MEAL_ACTIVATE_EXISTING_STATE_UNSAFE');
        }

        $insert = $pdo->prepare("INSERT INTO anytour_search_meal_provider_mappings_v1(provider,scope_key,external_id,meal_plan_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at) VALUES('tourvisor','global',?,?,'accepted',?,?,?,UTC_TIMESTAMP())");
        foreach (meal_activate_expected_mappings() as $externalId => $mapping) {
            $insert->execute([
                $externalId,
                $mapping['meal_plan_id'],
                MEAL_ACTIVATE_EVIDENCE_REF,
                MEAL_ACTIVATE_EVIDENCE_SHA256,
                MEAL_ACTIVATE_REVIEWED_BY,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $after = meal_activate_classify_rows(meal_activate_rows($pdo));
    if ($after['state'] !== 'exact') {
        meal_activate_error('MEAL_ACTIVATE_POST_SEED_MISMATCH');
    }
    return [
        'state' => 'mappings_seeded',
        'mappingState' => $after,
        'databaseWrites' => 1,
        'schemaWrites' => 0,
        'mappingWrites' => 4,
    ];
}

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

try {
    $command = $argv[1] ?? '';
    if (!in_array($command, ['plan', 'install-schema', 'seed'], true) || count($argv) !== 2) {
        meal_activate_error('MEAL_ACTIVATE_USAGE');
    }
    $pdo = meal_activate_pdo();
    $result = match ($command) {
        'plan' => meal_activate_plan($pdo),
        'install-schema' => meal_activate_install_schema($pdo),
        'seed' => meal_activate_seed($pdo),
    };
    meal_activate_json(['ok' => true, 'command' => $command] + $result);
} catch (Throwable $e) {
    fwrite(STDERR, json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'class' => get_class($e),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(2);
}
