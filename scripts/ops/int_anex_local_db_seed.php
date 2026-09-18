<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}
if ($argc !== 2) {
    fwrite(STDERR, "Usage: int_anex_local_db_seed.php <site-root>\n");
    exit(2);
}

$site = realpath($argv[1]);
$runtime = realpath(dirname(__DIR__, 2));
if ($site === false || !is_dir($site) || basename($site) !== 'anytoour.ru'
    || $runtime === false || !is_dir($runtime)) {
    throw new RuntimeException('ANEX_SEED_ROOT');
}

$private = dirname($site, 2) . '/.anytoour-anex/search3-preview.php';
if (!is_file($private) || is_link($private)) {
    throw new RuntimeException('ANEX_SEED_PRIVATE_CONFIG');
}
require_once $private;
if (!defined('ANEX_API_TOKEN') || !defined('ANEX_B2B_TOKEN')) {
    throw new RuntimeException('ANEX_SEED_PRIVATE_CONFIG');
}

$config = $site . '/config.php';
if (!is_file($config) || is_link($config)) {
    throw new RuntimeException('ANEX_SEED_PROJECT_CONFIG');
}
require_once $config;
$dbFile = is_file($site . '/data/db-v1.php') ? $site . '/data/db-v1.php' : $site . '/v2/data/db-v1.php';
if (!is_file($dbFile) || is_link($dbFile)) {
    throw new RuntimeException('ANEX_SEED_DB_HELPER');
}
require_once $dbFile;

$db = v2_data_db();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$counts = static function (PDO $db): array {
    $one = static function (string $sql) use ($db): int {
        return (int)$db->query($sql)->fetchColumn();
    };
    return [
        'programs' => $one('SELECT COUNT(*) FROM anytour_anex_programs'),
        'contexts' => $one('SELECT COUNT(*) FROM anytour_anex_program_contexts'),
        'apd' => $one('SELECT COUNT(*) FROM anytour_anex_apd_rates'),
        'anex_offers' => $one("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex'"),
        'anex_active' => $one("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex' AND is_active=1"),
        'all_offers' => $one('SELECT COUNT(*) FROM anytour_offers'),
    ];
};

$before = $counts($db);

putenv('ANYTOUR_PROJECT_ROOT=' . $site);
putenv('ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE=' . $runtime . '/v2/data/anytour-offer-snapshot-ingest-v1.php');

$collectorScript = $runtime . '/scripts/ops/anex_local_offer_collect.php';
if (!is_file($collectorScript) || is_link($collectorScript)) {
    throw new RuntimeException('ANEX_SEED_COLLECTOR');
}

$originalArgv = $argv;
$originalArgc = $argc;
$argv = [
    $collectorScript,
    '--departure=1',
    '--country=4',
    '--date-from=2026-10-12',
    '--date-to=2026-10-12',
    '--nights=7',
    '--adults=2',
    '--meal=7',
    '--max-expands=1',
    '--max-apd=2',
    '--generation=25061821',
];
$argc = count($argv);

ob_start();
try {
    require $collectorScript;
    $raw = trim((string)ob_get_clean());
} catch (Throwable $error) {
    ob_end_clean();
    $argv = $originalArgv;
    $argc = $originalArgc;
    throw $error;
}
$argv = $originalArgv;
$argc = $originalArgc;

$collector = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
if (!is_array($collector) || ($collector['status'] ?? null) !== 'complete') {
    throw new RuntimeException('ANEX_SEED_COLLECTOR_RECEIPT');
}

$after = $counts($db);
$delta = [];
foreach ($before as $key => $value) $delta[$key] = $after[$key] - $value;

$completed = ($delta['programs'] > 0)
    && ($delta['contexts'] > 0)
    && ($delta['anex_offers'] > 0)
    && (($collector['final_price_ready_offers'] ?? 0) > 0);

$result = [
    'status' => $completed ? 'completed' : 'failed_terminal_no_replay',
    'provider' => 'anex',
    'scope' => [
        'departure_id' => 1,
        'country_id' => 4,
        'date_from' => '2026-10-12',
        'date_to' => '2026-10-12',
        'nights' => 7,
        'adults' => 2,
        'meal' => 'AI',
    ],
    'before' => $before,
    'after' => $after,
    'delta' => $delta,
    'collector' => $collector,
    'booking_calls' => 0,
    'lead_calls' => 0,
    'mapping_writes' => 0,
    'search3_publication' => 0,
    'production_webroot_writes' => 0,
    'replay_allowed' => false,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
exit($completed ? 0 : 3);
