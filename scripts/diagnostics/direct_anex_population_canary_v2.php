<?php
declare(strict_types=1);

const CANARY_OPERATION = 'direct-anex-population-canary-2506-20260918-v2';
const CANARY_SOURCE = 'd8a11376535f6ea44ba0e83694a449fa54f84ab1';
const CANARY_RUNTIME_FILES = [
    'api-anex-search3-preview.php',
    'app/integrations/anex-program-observation-runtime.php',
    'app/integrations/anex-apd-cache-runtime.php',
    'app/integrations/anex-additional-prices-batch.php',
];

function canary_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}

function canary_write_new(string $path, array $value): void
{
    $handle = fopen($path, 'x+b');
    if (!$handle) throw new RuntimeException('CANARY_RECEIPT_EXISTS');
    $bytes = canary_json($value) . "\n";
    if (fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle) || !fsync($handle)) {
        fclose($handle);
        throw new RuntimeException('CANARY_RECEIPT_DURABILITY');
    }
    fclose($handle);
    chmod($path, 0600);
}

function canary_count(PDO $db, string $sql): int
{
    return (int)$db->query($sql)->fetchColumn();
}

function canary_db_state(PDO $db): array
{
    $classes = [];
    $query = $db->query(
        "SELECT flight_class,COUNT(*) AS n,COALESCE(SUM(observation_count),0) AS obs "
        . "FROM anytour_anex_program_contexts GROUP BY flight_class ORDER BY flight_class"
    );
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $classes[(string)$row['flight_class']] = [
            'rows' => (int)$row['n'],
            'observations' => (int)$row['obs'],
        ];
    }
    return [
        'programs' => canary_count($db, 'SELECT COUNT(*) FROM anytour_anex_programs'),
        'program_observations' => canary_count($db, 'SELECT COALESCE(SUM(observation_count),0) FROM anytour_anex_programs'),
        'contexts' => canary_count($db, 'SELECT COUNT(*) FROM anytour_anex_program_contexts'),
        'context_observations' => canary_count($db, 'SELECT COALESCE(SUM(observation_count),0) FROM anytour_anex_program_contexts'),
        'apd_rates' => canary_count($db, 'SELECT COUNT(*) FROM anytour_anex_apd_rates'),
        'apd_rate_rows' => canary_count($db, "SELECT COUNT(*) FROM anytour_anex_apd_rates WHERE apd_state='rate'"),
        'apd_empty_rows' => canary_count($db, "SELECT COUNT(*) FROM anytour_anex_apd_rates WHERE apd_state='empty'"),
        'apd_ambiguous_rows' => canary_count($db, "SELECT COUNT(*) FROM anytour_anex_apd_rates WHERE apd_state='ambiguous'"),
        'anex_offers' => canary_count($db, "SELECT COUNT(*) FROM anytour_offers WHERE provider='anex'"),
        'anex_active' => canary_count($db, "SELECT COUNT(*) FROM anytour_offers WHERE provider='anex' AND is_active=1"),
        'anex_ready' => canary_count($db, "SELECT COUNT(*) FROM anytour_offers WHERE provider='anex' AND final_price_ready=1"),
        'flight_classes' => (object)$classes,
    ];
}

function canary_scope(PDO $db): array
{
    $departure = $db->prepare(
        "SELECT id,name FROM catalog_departures WHERE is_active=1 AND (name=? OR name LIKE ?) "
        . "ORDER BY (name=?) DESC,id LIMIT 3"
    );
    $departure->execute(['Москва', 'Москва%', 'Москва']);
    $departures = $departure->fetchAll(PDO::FETCH_ASSOC);

    $country = $db->prepare(
        "SELECT id,name FROM catalog_countries WHERE is_active=1 AND (name=? OR name LIKE ?) "
        . "ORDER BY (name=?) DESC,id LIMIT 3"
    );
    $country->execute(['Турция', 'Турц%', 'Турция']);
    $countries = $country->fetchAll(PDO::FETCH_ASSOC);
    if ($departures === [] || $countries === []) throw new RuntimeException('CANARY_SCOPE_NOT_FOUND');

    return [
        'departure_id' => (int)$departures[0]['id'],
        'departure_name' => (string)$departures[0]['name'],
        'country_id' => (int)$countries[0]['id'],
        'country_name' => (string)$countries[0]['name'],
        'date' => '2026-10-12',
        'nights' => 7,
        'adults' => 2,
        'meal' => 'AI',
    ];
}

function canary_runtime_hashes(string $root): array
{
    $target = $root . '/_preview/search3-anex-candidate';
    if (!is_dir($target) || is_link($target)) throw new RuntimeException('CANARY_RUNTIME_TARGET');
    $out = [];
    foreach (CANARY_RUNTIME_FILES as $relative) {
        $path = $target . '/' . $relative;
        if (!is_file($path) || is_link($path)) throw new RuntimeException('CANARY_RUNTIME_FILE');
        $digest = hash_file('sha256', $path);
        if (!is_string($digest) || !preg_match('/\A[a-f0-9]{64}\z/D', $digest)) {
            throw new RuntimeException('CANARY_RUNTIME_HASH');
        }
        $out[$relative] = $digest;
    }
    return $out;
}

function canary_project_root(): string
{
    $home = rtrim((string)getenv('HOME'), '/');
    if ($home === '') throw new RuntimeException('CANARY_HOME');
    $root = $home . '/www/anytoour.ru';
    if (!is_dir($root) || is_link($root) || !is_file($root . '/config.php') || !is_file($root . '/v2/data/db-v1.php')) {
        throw new RuntimeException('CANARY_PROJECT_ROOT');
    }
    return $root;
}

function canary_db(string $root): PDO
{
    chdir($root);
    require_once $root . '/config.php';
    require_once $root . '/v2/data/db-v1.php';
    $db = v2_data_db();
    if (!$db instanceof PDO || $db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        throw new RuntimeException('CANARY_DB');
    }
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
}

function canary_operation_dir(): string
{
    $home = rtrim((string)getenv('HOME'), '/');
    $parent = $home . '/.anytoour-anex';
    if (!is_dir($parent) || is_link($parent)) throw new RuntimeException('CANARY_PRIVATE_ROOT');
    return $parent . '/' . CANARY_OPERATION;
}

function canary_pre(): array
{
    $control = (string)getenv('CONTROL_SHA');
    $run = (string)getenv('RUN_ID');
    if (!preg_match('/\A[a-f0-9]{40}\z/D', $control) || !preg_match('/\A[1-9][0-9]{5,20}\z/D', $run)) {
        throw new RuntimeException('CANARY_EXECUTION_IDENTITY');
    }
    $root = canary_project_root();
    $hashes = canary_runtime_hashes($root);
    $db = canary_db($root);
    $scope = canary_scope($db);
    if ($scope['departure_id'] < 1 || $scope['country_id'] < 1) throw new RuntimeException('CANARY_SCOPE_INVALID');
    $before = canary_db_state($db);
    $opDir = canary_operation_dir();
    if (file_exists($opDir) || is_link($opDir)) throw new RuntimeException('CANARY_OPERATION_EXISTS');
    if (!mkdir($opDir, 0700)) throw new RuntimeException('CANARY_RESERVATION_DIR');

    $reservation = [
        'state' => 'reserved',
        'operation_id' => CANARY_OPERATION,
        'source_sha' => CANARY_SOURCE,
        'control_sha' => $control,
        'run_id' => (int)$run,
        'scope' => $scope,
        'before' => $before,
        'supplier_budget_max' => 6,
        'booking_calls' => 0,
        'lead_calls' => 0,
        'replay_allowed' => false,
    ];
    canary_write_new($opDir . '/reservation.json', $reservation);

    return [
        'phase' => 'pre',
        'operation' => CANARY_OPERATION,
        'source_sha' => CANARY_SOURCE,
        'runtime_hashes' => $hashes,
        'scope' => $scope,
        'before' => $before,
        'reservation_created' => true,
        'supplier_calls' => 0,
    ];
}

function canary_post(): array
{
    $encoded = (string)getenv('HTTP_B64');
    if ($encoded === '' || strlen($encoded) > 32768) throw new RuntimeException('CANARY_HTTP_SUMMARY');
    $decoded = base64_decode($encoded, true);
    if (!is_string($decoded) || $decoded === '') throw new RuntimeException('CANARY_HTTP_SUMMARY');
    $http = json_decode($decoded, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($http)) throw new RuntimeException('CANARY_HTTP_SUMMARY');

    $opDir = canary_operation_dir();
    $reservation = json_decode((string)file_get_contents($opDir . '/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($reservation)
        || ($reservation['operation_id'] ?? null) !== CANARY_OPERATION
        || ($reservation['source_sha'] ?? null) !== CANARY_SOURCE
        || ($reservation['replay_allowed'] ?? null) !== false
        || !is_array($reservation['before'] ?? null)) {
        throw new RuntimeException('CANARY_RESERVATION_INVALID');
    }

    $root = canary_project_root();
    $db = canary_db($root);
    $db->exec('START TRANSACTION READ ONLY');
    try {
        $after = canary_db_state($db);
        $db->rollBack();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }

    $before = $reservation['before'];
    $progressed = ($after['program_observations'] ?? 0) > ($before['program_observations'] ?? 0)
        && ($after['context_observations'] ?? 0) > ($before['context_observations'] ?? 0);
    $budget = (int)($http['logical_supplier_reservations'] ?? 0);
    if ($budget < 0 || $budget > 6) throw new RuntimeException('CANARY_BUDGET_INVALID');

    $complete = ($http['status'] ?? null) === 'complete' && $progressed;
    $result = [
        'operation' => CANARY_OPERATION,
        'source_sha' => CANARY_SOURCE,
        'status' => $complete ? 'complete' : 'terminal_unknown',
        'outcome' => $complete ? 'program_context_population_verified' : 'population_not_verified',
        'scope' => $reservation['scope'],
        'before' => $before,
        'after' => $after,
        'http' => $http,
        'program_context_progressed' => $progressed,
        'supplier_budget_reservations' => $budget,
        'booking_calls' => 0,
        'lead_calls' => 0,
        'replay_allowed' => false,
    ];
    canary_write_new($opDir . '/result.json', $result);
    return $result;
}

$phase = (string)getenv('CANARY_PHASE');
try {
    $result = match ($phase) {
        'pre' => canary_pre(),
        'post' => canary_post(),
        default => throw new RuntimeException('CANARY_PHASE'),
    };
    echo canary_json($result), "\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'CANARY_STOP ' . preg_replace('/[^A-Z0-9_:-]+/i', '_', substr($error->getMessage(), 0, 120)) . "\n");
    exit(1);
}
