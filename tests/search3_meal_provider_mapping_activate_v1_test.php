<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/deploy/search3_meal_provider_mapping_activate_v1.php';

$checks = 0;
function acheck(bool $ok, string $name): void {
    global $checks;
    $checks++;
    if (!$ok) {
        throw new RuntimeException('MEAL_ACTIVATION_TEST:'.$name);
    }
}
function athrows(callable $fn, string $prefix): void {
    try {
        $fn();
    } catch (Throwable $e) {
        acheck(str_starts_with($e->getMessage(), $prefix), 'throws '.$prefix.' got '.$e->getMessage());
        return;
    }
    throw new RuntimeException('MEAL_ACTIVATION_TEST:expected '.$prefix);
}

$dsn = (string)getenv('SEARCH3_MEAL_ACTIVATION_TEST_DSN');
$user = (string)getenv('SEARCH3_MEAL_ACTIVATION_TEST_USER');
$password = (string)getenv('SEARCH3_MEAL_ACTIVATION_TEST_PASSWORD');
if ($dsn === '') {
    throw new RuntimeException('SEARCH3_MEAL_ACTIVATION_TEST_DSN required');
}
acheck(str_contains($dsn, 'search3_meal_activation_fixture'), 'fixture DSN only');
$db = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$db->exec('DROP TABLE IF EXISTS anytour_search_meal_provider_mappings_v1');
$db->exec('DROP TABLE IF EXISTS anytour_meal_plans');
$db->exec("CREATE TABLE anytour_meal_plans (
    id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    name_ru VARCHAR(255) NOT NULL,
    family_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    qualifiers_json LONGTEXT NOT NULL,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
$plans = [
    [2,'breakfast','Завтраки','breakfast','{}'],
    [3,'half-board','Полупансион','half-board','{}'],
    [7,'all-inclusive','Всё включено','all-inclusive','{}'],
    [8,'ultra-all-inclusive','Ультра всё включено','all-inclusive','{"variant":"ultra"}'],
];
$ins = $db->prepare('INSERT INTO anytour_meal_plans(id,code,name_ru,family_code,qualifiers_json,is_active) VALUES(?,?,?,?,?,1)');
foreach ($plans as $plan) {
    $ins->execute($plan);
}

$activation = new Search3MealProviderMappingActivateV1($db);
$plan = $activation->plan();
acheck($plan['state'] === 'schema_absent' && $plan['writes'] === 0, 'read-only plan sees absent schema');
athrows(fn() => $activation->installSchema(false), 'SEARCH3_MEAL_WRITE_NOT_AUTHORIZED');
acheck(!$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anytour_search_meal_provider_mappings_v1'")->fetchColumn(), 'denied install writes nothing');

$installed = $activation->installSchema(true);
acheck($installed['state'] === 'schema_installed' && $installed['writes'] === 1, 'schema installs once');
acheck((int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anytour_search_meal_provider_mappings_v1'")->fetchColumn() === 1, 'provider table installed');
acheck((int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anytour_search_meal_memberships_v1'")->fetchColumn() === 0, 'membership table remains absent');
$again = $activation->installSchema(true);
acheck($again['state'] === 'schema_already_exact' && $again['writes'] === 0, 'schema rerun is read-only');
acheck($activation->plan()['state'] === 'empty', 'plan sees exact empty schema');

athrows(fn() => $activation->seed(false), 'SEARCH3_MEAL_WRITE_NOT_AUTHORIZED');
acheck((int)$db->query('SELECT COUNT(*) FROM anytour_search_meal_provider_mappings_v1')->fetchColumn() === 0, 'denied seed rolls back');
$seeded = $activation->seed(true);
acheck($seeded['state'] === 'seeded' && $seeded['writes'] === 4 && $seeded['rows'] === 4, 'four mappings seeded');
acheck($activation->plan()['state'] === 'exact', 'plan recognizes exact seed');
$idempotent = $activation->seed(true);
acheck($idempotent['state'] === 'seed_already_exact' && $idempotent['writes'] === 0, 'identical seed rerun is read-only');

$rows = $db->query("SELECT external_id,meal_plan_id,state,evidence_ref,evidence_sha256,reviewed_by FROM anytour_search_meal_provider_mappings_v1 ORDER BY external_id")->fetchAll();
acheck(array_column($rows, 'external_id') === ['3','4','7','9'], 'native IDs exact');
acheck(array_map('intval', array_column($rows, 'meal_plan_id')) === [2,3,7,8], 'canonical plan IDs exact');
foreach ($rows as $row) {
    acheck($row['state'] === 'accepted', 'accepted only');
    acheck($row['evidence_ref'] === Search3MealProviderMappingActivateV1::EVIDENCE_REF, 'evidence ref exact');
    acheck($row['evidence_sha256'] === Search3MealProviderMappingActivateV1::EVIDENCE_RESULT_SHA256, 'evidence hash exact');
    acheck($row['reviewed_by'] === Search3MealProviderMappingActivateV1::REVIEWED_BY, 'reviewer provenance exact');
}

$db->exec("DELETE FROM anytour_search_meal_provider_mappings_v1 WHERE external_id='9'");
athrows(fn() => $activation->seed(true), 'SEARCH3_MEAL_MAPPING_STATE_NOT_EMPTY:partial_or_foreign');
acheck((int)$db->query('SELECT COUNT(*) FROM anytour_search_meal_provider_mappings_v1')->fetchColumn() === 3, 'partial failure never fills rows');
$db->exec('DELETE FROM anytour_search_meal_provider_mappings_v1');
$activation->seed(true);

$db->exec("UPDATE anytour_search_meal_provider_mappings_v1 SET meal_plan_id=7 WHERE external_id='3'");
athrows(fn() => $activation->seed(true), 'SEARCH3_MEAL_MAPPING_STATE_NOT_EMPTY:conflict');
acheck((int)$db->query("SELECT meal_plan_id FROM anytour_search_meal_provider_mappings_v1 WHERE external_id='3'")->fetchColumn() === 7, 'conflict is never overwritten');
$db->exec('DELETE FROM anytour_search_meal_provider_mappings_v1');
$activation->seed(true);

$stmt = $db->prepare("INSERT INTO anytour_search_meal_provider_mappings_v1(provider,scope_key,external_id,meal_plan_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at) VALUES('tourvisor','global','99',2,'accepted',?,?,?,UTC_TIMESTAMP())");
$stmt->execute([Search3MealProviderMappingActivateV1::EVIDENCE_REF, Search3MealProviderMappingActivateV1::EVIDENCE_RESULT_SHA256, Search3MealProviderMappingActivateV1::REVIEWED_BY]);
athrows(fn() => $activation->seed(true), 'SEARCH3_MEAL_MAPPING_STATE_NOT_EMPTY:partial_or_foreign');
acheck((int)$db->query('SELECT COUNT(*) FROM anytour_search_meal_provider_mappings_v1')->fetchColumn() === 5, 'foreign row causes no cleanup or overwrite');
$db->exec("DELETE FROM anytour_search_meal_provider_mappings_v1 WHERE external_id='99'");

$db->exec("UPDATE anytour_meal_plans SET name_ru='drift' WHERE id=2");
athrows(fn() => $activation->plan(), 'SEARCH3_MEAL_CANONICAL_PLAN_DRIFT:2');
$db->exec("UPDATE anytour_meal_plans SET name_ru='Завтраки' WHERE id=2");
acheck($activation->plan()['state'] === 'exact', 'canonical repair restores exact readback');

echo "SEARCH3_MEAL_PROVIDER_ACTIVATION_CONTRACT_OK checks={$checks} live_writes=0 fixture_only=1\n";
