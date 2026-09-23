<?php
declare(strict_types=1);

$checks = 0;
function ma_check(bool $value, string $name): void
{
    global $checks;
    $checks++;
    if (!$value) {
        throw new RuntimeException('MEAL_ACTIVATE_TEST:' . $name);
    }
}

function ma_run(string $command, int $expectedExit = 0): array
{
    $script = __DIR__ . '/../scripts/deploy/search3_meal_provider_mapping_activate_v1.php';
    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([PHP_BINARY, $script, $command], $descriptors, $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('proc_open');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    ma_check($exit === $expectedExit, "exit:$command:$exit stderr=$stderr");
    $raw = trim($expectedExit === 0 ? $stdout : $stderr);
    $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    ma_check(is_array($decoded), "json:$command");
    return $decoded;
}

$dsn = (string)getenv('ANYTOUR_SEARCH3_MEAL_ACTIVATE_DSN');
$user = (string)getenv('ANYTOUR_SEARCH3_MEAL_ACTIVATE_USER');
$password = (string)getenv('ANYTOUR_SEARCH3_MEAL_ACTIVATE_PASSWORD');
$expectedDb = (string)getenv('ANYTOUR_SEARCH3_MEAL_ACTIVATE_EXPECTED_DB');
ma_check(str_contains($dsn, 'mysql:'), 'fixture DSN');
ma_check($expectedDb === 'anytour_search3_meal_activate_fixture', 'fixture DB name');
$db = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_STRINGIFY_FETCHES => false,
]);
$db->exec('SET FOREIGN_KEY_CHECKS=0');
$db->exec('DROP TABLE IF EXISTS anytour_search_meal_provider_mappings_v1');
$db->exec('DROP TABLE IF EXISTS anytour_meal_plans');
$db->exec('SET FOREIGN_KEY_CHECKS=1');

$db->exec("CREATE TABLE anytour_meal_plans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    name_ru VARCHAR(255) NOT NULL,
    family_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    qualifiers_json LONGTEXT NOT NULL,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT ck_anytour_meal_json CHECK (JSON_VALID(qualifiers_json)),
    CONSTRAINT ck_anytour_meal_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
$db->exec("INSERT INTO anytour_meal_plans (id,code,name_ru,family_code,qualifiers_json,is_active) VALUES
(1,'no-meals','Без питания','no-meals','{}',1),
(2,'breakfast','Завтраки','breakfast','{}',1),
(3,'half-board','Полупансион','half-board','{}',1),
(4,'half-board-plus','Полупансион плюс','half-board','{\"variant\":\"plus\"}',1),
(5,'full-board','Полный пансион','full-board','{}',1),
(6,'full-board-plus','Полный пансион плюс','full-board','{\"variant\":\"plus\"}',1),
(7,'all-inclusive','Всё включено','all-inclusive','{}',1),
(8,'ultra-all-inclusive','Ультра всё включено','all-inclusive','{\"variant\":\"ultra\"}',1),
(9,'soft-all-inclusive','Мягкое всё включено','all-inclusive','{\"variant\":\"soft\"}',1),
(10,'alcohol-free-all-inclusive','Всё включено без алкоголя','all-inclusive','{\"variant\":\"alcohol-free\"}',1)");

$plan = ma_run('plan');
ma_check(($plan['state'] ?? null) === 'planned_read_only', 'plan state');
ma_check(($plan['schemaPresent'] ?? null) === false, 'plan sees absent schema');
ma_check((int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anytour_search_meal_provider_mappings_v1'")->fetchColumn() === 0, 'plan writes no schema');

$install = ma_run('install-schema');
ma_check(($install['state'] ?? null) === 'schema_installed', 'install state');
ma_check((int)$db->query('SELECT COUNT(*) FROM anytour_search_meal_provider_mappings_v1')->fetchColumn() === 0, 'install seeds nothing');

$seed = ma_run('seed');
ma_check(($seed['state'] ?? null) === 'mappings_seeded', 'seed state');
$rows = $db->query("SELECT provider,CONVERT(scope_key USING utf8mb4) scope_key,CONVERT(external_id USING utf8mb4) external_id,meal_plan_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at FROM anytour_search_meal_provider_mappings_v1 ORDER BY external_id")->fetchAll();
ma_check(count($rows) === 4, 'four rows only');
ma_check(array_column($rows, 'external_id') === ['3','4','7','9'], 'exact native IDs');
ma_check(array_map('intval', array_column($rows, 'meal_plan_id')) === [2,3,7,8], 'exact plan IDs');
foreach ($rows as $row) {
    ma_check($row['provider'] === 'tourvisor' && $row['scope_key'] === 'global' && $row['state'] === 'accepted', 'exact provider scope state');
    ma_check($row['evidence_sha256'] === 'a0755910d8e46c4f85021de5cae1ec88f29a2a811d4cc4f5c02d6c8ea0dfa4fa', 'result SHA pinned');
    ma_check($row['reviewed_by'] === 'owner:pyatkoff:issue3419/comment5791719696', 'review provenance pinned');
}
$snapshot = json_encode($rows, JSON_THROW_ON_ERROR);
$installAgain = ma_run('install-schema');
$seedAgain = ma_run('seed');
ma_check(($installAgain['state'] ?? null) === 'schema_already_exact_read_only', 'schema rerun read only');
ma_check(($seedAgain['state'] ?? null) === 'mappings_already_exact_read_only', 'seed rerun read only');
$rowsAgain = $db->query("SELECT provider,CONVERT(scope_key USING utf8mb4) scope_key,CONVERT(external_id USING utf8mb4) external_id,meal_plan_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at FROM anytour_search_meal_provider_mappings_v1 ORDER BY external_id")->fetchAll();
ma_check(json_encode($rowsAgain, JSON_THROW_ON_ERROR) === $snapshot, 'idempotent rerun byte-equivalent rows');

$ref = 'issue3419/comment5791719696:search3-meal-provider-mapping-plan-3353-20260923-v1:result=a0755910d8e46c4f85021de5cae1ec88f29a2a811d4cc4f5c02d6c8ea0dfa4fa';
$sha = 'a0755910d8e46c4f85021de5cae1ec88f29a2a811d4cc4f5c02d6c8ea0dfa4fa';
$review = 'owner:pyatkoff:issue3419/comment5791719696';
$insert = $db->prepare("INSERT INTO anytour_search_meal_provider_mappings_v1(provider,scope_key,external_id,meal_plan_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at) VALUES(?,?,?,?,?,?,?,?,UTC_TIMESTAMP())");

$db->exec('DELETE FROM anytour_search_meal_provider_mappings_v1');
$insert->execute(['tourvisor','global','3',2,'accepted',$ref,$sha,$review]);
$partial = ma_run('seed', 2);
ma_check(($partial['error'] ?? null) === 'MEAL_ACTIVATE_EXISTING_STATE_UNSAFE', 'partial fails closed');
ma_check((int)$db->query('SELECT COUNT(*) FROM anytour_search_meal_provider_mappings_v1')->fetchColumn() === 1, 'partial untouched');

$db->exec('DELETE FROM anytour_search_meal_provider_mappings_v1');
$insert->execute(['tourvisor','global','99',2,'accepted',$ref,$sha,$review]);
$foreign = ma_run('seed', 2);
ma_check(($foreign['error'] ?? null) === 'MEAL_ACTIVATE_EXISTING_STATE_UNSAFE', 'foreign row fails closed');
ma_check((string)$db->query("SELECT CONVERT(external_id USING utf8mb4) FROM anytour_search_meal_provider_mappings_v1")->fetchColumn() === '99', 'foreign row untouched');

$db->exec('DELETE FROM anytour_search_meal_provider_mappings_v1');
$insert->execute(['tourvisor','global','3',3,'accepted',$ref,$sha,$review]);
$conflict = ma_run('seed', 2);
ma_check(($conflict['error'] ?? null) === 'MEAL_ACTIVATE_EXISTING_STATE_UNSAFE', 'conflict fails closed');
ma_check((int)$db->query('SELECT meal_plan_id FROM anytour_search_meal_provider_mappings_v1')->fetchColumn() === 3, 'conflict untouched');

$db->exec('DROP TABLE anytour_search_meal_provider_mappings_v1');
$db->exec('CREATE TABLE anytour_search_meal_provider_mappings_v1 (provider VARCHAR(16) NOT NULL PRIMARY KEY) ENGINE=InnoDB');
$badSchema = ma_run('install-schema', 2);
ma_check(str_starts_with((string)($badSchema['error'] ?? ''), 'MEAL_ACTIVATE_SCHEMA_'), 'partial schema fails closed');
$columnCount = (int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anytour_search_meal_provider_mappings_v1'")->fetchColumn();
ma_check($columnCount === 1, 'partial schema not replaced');

$db->exec('DROP TABLE anytour_search_meal_provider_mappings_v1');
$db->exec("UPDATE anytour_meal_plans SET name_ru='BROKEN' WHERE id=2");
$badPlan = ma_run('plan', 2);
ma_check(($badPlan['error'] ?? null) === 'MEAL_ACTIVATE_PLAN_CATALOG_MISMATCH', 'canonical catalogue mismatch fails');
$db->exec("UPDATE anytour_meal_plans SET name_ru='Завтраки' WHERE id=2");

echo "SEARCH3_MEAL_PROVIDER_ACTIVATION_CONTRACT_OK checks=$checks live_writes=0 fixture_only=1 mappings=4\n";
