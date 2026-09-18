<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(2); }
if ($argc !== 3) { fwrite(STDERR, "Usage: int_andromeda_local_db_seed.php <site-root> <source-sha>\n"); exit(2); }

$site = realpath($argv[1]);
$source = $argv[2];
$runtime = realpath(dirname(__DIR__, 2));
if ($site === false || basename($site) !== 'anytoour.ru' || $runtime === false
    || !preg_match('/\A[a-f0-9]{40}\z/D', $source)) {
    throw new RuntimeException('ANDROMEDA_SEED_ROOT');
}

$private = dirname($site, 2) . '/.anytoour-andromeda/search3-preview.php';
if (!is_file($private) || is_link($private)) throw new RuntimeException('ANDROMEDA_SEED_PRIVATE_CONFIG');

require_once $site . '/config.php';
$dbFile = is_file($site . '/data/db-v1.php') ? $site . '/data/db-v1.php' : $site . '/v2/data/db-v1.php';
require_once $dbFile;
$db = v2_data_db();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$count = static function (PDO $db): array {
    $one = static function (string $sql) use ($db): int { return (int)$db->query($sql)->fetchColumn(); };
    return [
        'andromeda_offers' => $one("SELECT COUNT(*) FROM anytour_offers WHERE provider='andromeda'"),
        'andromeda_active' => $one("SELECT COUNT(*) FROM anytour_offers WHERE provider='andromeda' AND is_active=1"),
        'andromeda_ready' => $one("SELECT COUNT(*) FROM anytour_offers WHERE provider='andromeda' AND final_price_ready=1"),
        'all_offers' => $one('SELECT COUNT(*) FROM anytour_offers'),
    ];
};

$readJson = static function (string $path, int $maxBytes = 3000000): ?array {
    if (is_link($path) || !is_file($path)) return null;
    $size = filesize($path);
    if (!is_int($size) || $size < 2 || $size > $maxBytes) return null;
    try {
        $value = json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        return is_array($value) ? $value : null;
    } catch (Throwable) {
        return null;
    }
};

$hist = static function (array &$target, int $value): void {
    $key = 'n' . $value;
    $target[$key] = ($target[$key] ?? 0) + 1;
};

$diagnose = static function (string $directory) use ($readJson, $hist): array {
    $result = [
        'status' => 'cohort_not_found',
        'surcharge_sidecars' => 0,
        'diagnostic_records' => 0,
        'fact_states' => [],
        'ttavia_option_count' => [],
        'transport_detail_count' => [],
        'markup_key_count' => [],
        'valid_markup_fact_count' => [],
        'distinct_markup_count' => [],
        'detail_currencies' => [],
        'markup_currencies' => [],
        'operator_rate_currencies' => [],
    ];
    if (!is_dir($directory) || is_link($directory)) return $result;

    $first = null;
    $inventory = 0;
    foreach (new DirectoryIterator($directory) as $entry) {
        if ($entry->isDot()) continue;
        if (++$inventory > 100000) return ['status' => 'inventory_limit'] + $result;
        if ($entry->isLink() || !$entry->isFile()
            || !preg_match('/\A([a-f0-9]{64})-1\.json\z/D', $entry->getFilename(), $m)) continue;
        $state = $readJson($entry->getPathname());
        $criteria = is_array($state) ? ($state['criteria'] ?? null) : null;
        $store = is_array($state) ? ($state['store'] ?? null) : null;
        if (!is_array($criteria) || !is_array($store)
            || ($state['generation'] ?? null) !== 17171831
            || !is_int($store['created_at'] ?? null)) continue;
        if ((string)($criteria['CHECKIN_BEG'] ?? '') !== '20261019'
            || (string)($criteria['CHECKIN_END'] ?? '') !== '20261019'
            || (string)($criteria['NIGHTS_FROM'] ?? '') !== '7'
            || (string)($criteria['NIGHTS_TILL'] ?? '') !== '7'
            || (string)($criteria['ADULT'] ?? '') !== '2'
            || (string)($criteria['CHILD'] ?? '') !== '0') continue;
        if ($first === null || $store['created_at'] > $first['created']) {
            $first = ['ref' => $m[1], 'created' => $store['created_at']];
        }
    }
    if ($first === null) return $result;

    $result['status'] = 'cohort_found';
    $prefix = $first['ref'] . '-' . $first['created'] . '-';
    $sets = ['detail_currencies' => [], 'markup_currencies' => [], 'operator_rate_currencies' => []];

    foreach (new DirectoryIterator($directory) as $entry) {
        if ($entry->isDot() || $entry->isLink() || !$entry->isFile()) continue;
        $name = $entry->getFilename();
        if (!str_starts_with($name, $prefix) || !str_ends_with($name, '-surcharge-v1.json')) continue;
        ++$result['surcharge_sidecars'];
        $record = $readJson($entry->getPathname(), 32768);
        if (!is_array($record)) continue;

        $fact = $record['fact'] ?? null;
        $state = is_array($fact) && is_string($fact['state'] ?? null)
            && preg_match('/\A[a-z_]{1,32}\z/D', $fact['state']) ? $fact['state'] : 'missing';
        $result['fact_states'][$state] = ($result['fact_states'][$state] ?? 0) + 1;

        $diag = $record['transport_money_diagnostic'] ?? null;
        if (!is_array($diag) || ($diag['schema_version'] ?? null) !== 1) continue;
        ++$result['diagnostic_records'];
        foreach ([
            'ttavia_option_count','transport_detail_count','markup_key_count',
            'valid_markup_fact_count','distinct_markup_count'
        ] as $field) {
            $value = $diag[$field] ?? null;
            if (is_int($value) && $value >= 0 && $value <= 10000) $hist($result[$field], $value);
        }
        foreach (array_keys($sets) as $field) {
            $values = $diag[$field] ?? null;
            if (!is_array($values) || !array_is_list($values) || count($values) > 32) continue;
            foreach ($values as $currency) {
                if (is_string($currency) && preg_match('/\A[A-Z0-9_]{2,8}\z/D', $currency)) {
                    $sets[$field][$currency] = true;
                }
            }
        }
    }

    foreach (['fact_states','ttavia_option_count','transport_detail_count','markup_key_count',
        'valid_markup_fact_count','distinct_markup_count'] as $field) {
        ksort($result[$field], SORT_STRING);
    }
    foreach ($sets as $field => $set) {
        $values = array_keys($set);
        sort($values, SORT_STRING);
        $result[$field] = $values;
    }
    return $result;
};

$before = $count($db);
$config = require $private;
$searchDirectory = is_array($config) && is_string($config['catalog_path'] ?? null)
    ? dirname($config['catalog_path']) . '/searches' : '';

$collector = $runtime . '/scripts/ops/andromeda_local_offer_collect.php';
if (!is_file($collector) || is_link($collector)) throw new RuntimeException('ANDROMEDA_SEED_COLLECTOR');

$originalArgv = $argv; $originalArgc = $argc;
$argv = [
    $collector,
    '--site-root=' . $site,
    '--private-config=' . $private,
    '--source-sha=' . $source,
    '--departure=1','--country=4',
    '--date-from=2026-10-19','--date-to=2026-10-19',
    '--nights=7','--adults=2','--meal=7',
    '--generation=17171831','--max-captures=6',
];
$argc = count($argv);

$collectorReceipt = null;
$failureClass = null;
ob_start();
try {
    require $collector;
    $raw = trim((string)ob_get_clean());
    $collectorReceipt = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($collectorReceipt) || ($collectorReceipt['status'] ?? null) !== 'complete') {
        $failureClass = 'ANDROMEDA_SEED_COLLECTOR_RECEIPT';
        $collectorReceipt = null;
    }
} catch (Throwable $error) {
    if (ob_get_level() > 0) ob_end_clean();
    $message = $error->getMessage();
    $failureClass = is_string($message) && preg_match('/\A[A-Z0-9_:-]{1,96}\z/D', $message)
        ? $message : get_class($error);
}
$argv = $originalArgv; $argc = $originalArgc;

$after = $count($db);
$delta = [];
foreach ($before as $key => $value) $delta[$key] = $after[$key] - $value;
$diagnostic = $diagnose($searchDirectory);

$completed = $collectorReceipt !== null
    && $delta['andromeda_offers'] > 0
    && $delta['andromeda_active'] > 0
    && $delta['andromeda_ready'] > 0
    && ($collectorReceipt['autosave_published'] ?? false) === true
    && ($collectorReceipt['ready_offer_count'] ?? 0) > 0;

$result = [
    'status' => $completed ? 'completed' : 'failed_terminal_no_replay',
    'provider' => 'andromeda',
    'scope' => [
        'departure_id' => 1, 'country_id' => 4,
        'date_from' => '2026-10-19', 'date_to' => '2026-10-19',
        'nights' => 7, 'adults' => 2, 'meal' => 'AI',
    ],
    'before' => $before,
    'after' => $after,
    'delta' => $delta,
    'collector' => $collectorReceipt,
    'failure_class' => $failureClass,
    'transport_money_diagnostic' => $diagnostic,
    'booking_calls' => 0,
    'lead_calls' => 0,
    'mapping_writes' => 0,
    'search3_publication' => 0,
    'production_webroot_writes' => 0,
    'replay_allowed' => false,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n";
exit($completed ? 0 : 3);
