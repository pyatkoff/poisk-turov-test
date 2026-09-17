<?php
/** One-shot READ ONLY CURRENT AnyTour room/meal evidence inventory. No supplier/network calls. */
declare(strict_types=1);

require_once __DIR__ . '/anytour_stay_candidates.php';

const ANYTOUR_STAY_INVENTORY_OPERATION = 'local-stay-inventory-onhost-20260917-v1';
const ANYTOUR_STAY_INVENTORY_SOURCE = '7e52b07e872479cc853661854ff5d082d5f3a67d';
const ANYTOUR_STAY_INVENTORY_LIMIT = 5000;

function anytour_stay_inventory_table_counts(PDO $pdo): array
{
    $out = [];
    foreach (['anytour_hotel_rooms', 'anytour_stay_mappings', 'anytour_meal_plans', 'anytour_hotel_sources'] as $table) {
        $out[$table] = (int)$pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }
    return $out;
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        if ($argc !== 1 || basename(dirname(__DIR__, 2)) !== 'payload') {
            throw new RuntimeException('Fixed private invocation required');
        }
        $dir = dirname(__DIR__, 3);
        if (basename($dir) !== ANYTOUR_STAY_INVENTORY_OPERATION) {
            throw new RuntimeException('Wrong operation directory');
        }
        $reservation = json_decode((string)file_get_contents($dir . '/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
        if (($reservation['operation'] ?? '') !== ANYTOUR_STAY_INVENTORY_OPERATION
            || ($reservation['dataSource'] ?? '') !== ANYTOUR_STAY_INVENTORY_SOURCE
            || ($reservation['attempt'] ?? 0) !== 1
            || ($reservation['noReplay'] ?? null) !== true) {
            throw new RuntimeException('Wrong reservation');
        }
        if (is_file($dir . '/result.json') || is_file($dir . '/result.sha256')) {
            throw new RuntimeException('Existing result; no replay');
        }

        $root = rtrim((string)getenv('HOME'), '/') . '/www/anytoour.ru';
        foreach (['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT',
                  'ANYTOUR_STAY_CANDIDATES_DSN','ANYTOUR_STAY_CANDIDATES_USER','ANYTOUR_STAY_CANDIDATES_PASSWORD'] as $key) {
            if (getenv($key) !== false && getenv($key) !== '') throw new RuntimeException('Unexpected DB override');
        }
        $configFile = $root . '/config.php';
        $configHash = hash_file('sha256', $configFile);
        if ($configHash === false) throw new RuntimeException('Project configuration missing');
        require_once $configFile;
        require_once __DIR__ . '/../../v2/data/db-v1.php';
        $config = v2_data_db_config();
        if (!str_starts_with((string)$config['dsn'], 'mysql:') || trim((string)$config['user']) === '') {
            throw new RuntimeException('Explicit project MySQL configuration required');
        }
        $pdo = new PDO($config['dsn'], $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        $before = anytour_stay_inventory_table_counts($pdo);
        $inventory = (new AnyTourStayCandidates($pdo))->collect(ANYTOUR_STAY_INVENTORY_LIMIT);
        $after = anytour_stay_inventory_table_counts($pdo);
        if (($inventory['status'] ?? '') !== 'read_only_evidence_inventory'
            || ($inventory['source'] ?? '') !== 'saved_db_only'
            || ($inventory['namespace'] ?? '') !== 'legacy_catalog'
            || ($inventory['writes'] ?? -1) !== 0
            || ($inventory['supplierCalls'] ?? -1) !== 0
            || ($inventory['automaticAccepts'] ?? -1) !== 0
            || !is_array($inventory['mealCandidates'] ?? null)
            || !is_array($inventory['roomCandidates'] ?? null)
            || count($inventory['mealCandidates']) > ANYTOUR_STAY_INVENTORY_LIMIT
            || count($inventory['roomCandidates']) > ANYTOUR_STAY_INVENTORY_LIMIT) {
            throw new RuntimeException('Evidence inventory contract mismatch');
        }
        if ($before !== $after) {
            throw new RuntimeException('Stay catalogue counts changed during read-only operation');
        }
        if (!hash_equals($configHash, (string)hash_file('sha256', $configFile))) {
            throw new RuntimeException('Project configuration changed');
        }

        $evidenceJson = json_encode($inventory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $result = $inventory + [
            'operation' => ANYTOUR_STAY_INVENTORY_OPERATION,
            'dataSource' => ANYTOUR_STAY_INVENTORY_SOURCE,
            'executionSource' => (string)($reservation['executionSource'] ?? ''),
            'run' => (int)($reservation['run'] ?? 0),
            'limitPerKind' => ANYTOUR_STAY_INVENTORY_LIMIT,
            'before' => $before,
            'after' => $after,
            'evidenceSha256' => hash('sha256', $evidenceJson),
            'projectConfigurationUnchanged' => true,
            'noReplay' => true,
            'publicFileWrites' => 0,
        ];
        $bytes = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        $out = fopen($dir . '/result.json', 'x+b');
        if (!$out || fwrite($out, $bytes) !== strlen($bytes) || !fflush($out) || !fsync($out)) {
            throw new RuntimeException('Final receipt incomplete');
        }
        fclose($out);
        file_put_contents($dir . '/result.sha256', hash('sha256', $bytes) . "  result.json\n", LOCK_EX);
        echo 'ANYTOUR_STAY_INVENTORY_VERIFIED result_sha256=' . hash('sha256', $bytes)
            . ' meal_candidates=' . count($inventory['mealCandidates'])
            . ' room_candidates=' . count($inventory['roomCandidates'])
            . ' writes=0 supplier_calls=0 automatic_accepts=0' . "\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'ANYTOUR_STAY_INVENTORY_STOPPED class=' . get_class($e) . '; no DB writes, inspect before any new operation' . "\n");
        exit(1);
    }
}
