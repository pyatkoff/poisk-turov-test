<?php
declare(strict_types=1);

// Offline policy/read-path regression only; no supplier access or live DB writes.
require_once dirname(__DIR__) . '/app/integrations/anex-search-mapping-registry.php';

$checks = 0;
function policyCheck(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
function policyRow(int $id, string $policy, string $class, array $extra = []): array {
    return array_replace([
        'anex_hotel_id' => $id, 'catalog_hotel_id' => 10000 + $id,
        'existing_catalog_hotel_id' => 10000 + $id,
        'approval_policy' => $policy, 'match_class' => $class,
        'enabled' => 1, 'scope' => 'preview',
    ], $extra);
}
$policies = [
    'owner_exact_and_strong_20260908' => ['exact', 'strong_candidate'],
    'owner_exact_operator_key_20260912' => ['exact_operator_key'],
    'owner_exact_operator_key_20260912_v2' => ['exact_operator_key'],
    'owner_coordinate_name_geo_rescue_20260912_v1' => ['coordinate_name_geo'],
    'owner_multi_evidence_consensus_20260912_v1' => ['multi_evidence_consensus'],
    'owner_current_exact_cross_provider_20260912' => ['exact_cross_provider'],
];
$rows = [];
$expected = [];
$id = 1;
foreach ($policies as $policy => $classes) {
    foreach (['exact', 'strong_candidate', 'exact_operator_key', 'coordinate_name_geo',
              'multi_evidence_consensus', 'exact_cross_provider', 'price_rank_only'] as $class) {
        $row = policyRow($id++, $policy, $class);
        $rows[] = $row;
        $target = in_array($class, $classes, true) ? $row['catalog_hotel_id'] : null;
        $registry = AnyTourAnexSearchMappingRegistry::fromRows([$row]);
        policyCheck($registry->resolve('anex_online', $row['anex_hotel_id'], 'preview') === $target, 'policy/class pair drift');
        policyCheck($registry->resolve('anex_xml', $row['anex_hotel_id'], 'preview') === $target, 'XML namespace drift');
        policyCheck($registry->resolve('anex_online', $row['anex_hotel_id']) === null, 'production must stay closed');
        policyCheck($registry->resolve('andromeda_catalog', $row['anex_hotel_id'], 'preview') === null, 'foreign namespace must stay closed');
        if ($target !== null) $expected[$row['anex_hotel_id']] = $target;
    }
}
$base = policyRow(32772, 'owner_exact_operator_key_20260912', 'exact_operator_key', [
    'catalog_hotel_id' => 17617, 'existing_catalog_hotel_id' => 17617,
]);
foreach ([
    ['approval_policy' => 'owner_exact_operator_key_20260912_v999'],
    ['approval_policy' => 'owner_unreviewed'], ['approval_policy' => null],
    ['approval_policy' => ['owner_exact_operator_key_20260912']],
    ['match_class' => 'strong_candidate'], ['match_class' => 'pending'],
    ['match_class' => 'price_rank_only'], ['enabled' => 0], ['scope' => 'production'],
    ['existing_catalog_hotel_id' => null], ['existing_catalog_hotel_id' => 999],
] as $change) {
    policyCheck(AnyTourAnexSearchMappingRegistry::fromRows([array_replace($base, $change)])->count() === 0, 'invalid row admitted');
}
foreach (['rejected', 'pending', 'conflict', 'unknown'] as $state) {
    $decision = ['anex_hotel_id' => 32772, 'decision_status' => $state,
        'catalog_hotel_id' => 17617, 'existing_catalog_hotel_id' => 17617];
    policyCheck(AnyTourAnexSearchMappingRegistry::fromRows([$base], [$decision])->count() === 0, 'manual block lost');
}
$manual = ['anex_hotel_id' => 32772, 'decision_status' => 'accepted',
    'catalog_hotel_id' => 70943, 'existing_catalog_hotel_id' => 70943];
$registry = AnyTourAnexSearchMappingRegistry::fromRows([$base], [$manual]);
policyCheck(($registry->previewResolver())('anex_online', '32772') === 70943, 'manual target must win');
$exclusion = ['anex_hotel_id' => 32772, 'catalog_hotel_id' => 70943];
policyCheck(AnyTourAnexSearchMappingRegistry::fromRows([$base], [$manual], [$exclusion])->count() === 0, 'manual pair exclusion lost');
$exclusion['catalog_hotel_id'] = 17617;
policyCheck(AnyTourAnexSearchMappingRegistry::fromRows([$base], [], [$exclusion])->count() === 0, 'policy pair exclusion lost');
$manual['existing_catalog_hotel_id'] = null;
policyCheck(AnyTourAnexSearchMappingRegistry::fromRows([$base], [$manual])->count() === 0, 'missing manual target must suppress fallback');
try {
    AnyTourAnexSearchMappingRegistry::fromRows([$base, $base]);
    throw new RuntimeException('duplicate ID accepted');
} catch (UnexpectedValueException $expectedError) { $checks++; }

// A PDO test double captures the real query and bind values without a server.
// --sql-fixture lets an offline SQLite runner execute this exact SQL as well.
final class PolicyStatement extends PDOStatement {
    private array $rows;
    private ?PolicyPdo $owner;
    public function __construct(array $rows, ?PolicyPdo $owner = null) { $this->rows = $rows; $this->owner = $owner; }
    public function execute(?array $params = null): bool {
        if ($this->owner !== null) $this->owner->parameters = $params ?? [];
        return true;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}
final class PolicyPdo extends PDO {
    public string $sql = '';
    public array $parameters = [];
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $this->sql = $query;
        return new PolicyStatement($this->rows, $this);
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false {
        if (!str_starts_with($query, 'SELECT ')) throw new RuntimeException('non-read query');
        return new PolicyStatement([]);
    }
}
$pdo = new PolicyPdo($rows);
$registry = AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
foreach ($rows as $row) {
    policyCheck($registry->resolve('anex_online', $row['anex_hotel_id'], 'preview') === ($expected[$row['anex_hotel_id']] ?? null), 'fromPdo/fromRows result drift');
}
policyCheck(substr_count($pdo->sql, '?') === count($pdo->parameters), 'placeholder count drift');
policyCheck(substr_count($pdo->sql, 'm.approval_policy=? AND m.match_class IN (') === count($policies), 'SQL must pair each policy with its classes');
policyCheck(str_contains($pdo->sql, "m.enabled=1 AND m.scope='preview'"), 'SQL scope/enable guards lost');
policyCheck(str_ends_with($pdo->sql, 'LIMIT 50001'), 'SQL bound lost');
$expectedParameters = [];
foreach ($policies as $policy => $classes) { $expectedParameters[] = $policy; array_push($expectedParameters, ...$classes); }
policyCheck($pdo->parameters === $expectedParameters, 'SQL policy/class bind values drift');
if (($argv[1] ?? '') === '--sql-fixture') {
    echo json_encode(['sql' => $pdo->sql, 'parameters' => $pdo->parameters,
        'rows' => $rows, 'expected' => $expected, 'checks' => $checks], JSON_THROW_ON_ERROR) . PHP_EOL;
} else {
    echo 'PASS: ' . $checks . " mapping policy assertions (offline; no live DB access)\n";
}
