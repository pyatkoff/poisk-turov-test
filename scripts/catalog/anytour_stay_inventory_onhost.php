<?php
/** One-shot READ ONLY CURRENT schema probe for AnyTour room/meal evidence. No supplier/network calls. */
declare(strict_types=1);

const ANYTOUR_STAY_PROBE_OPERATION = 'local-stay-schema-probe-onhost-20260917-v2';
const ANYTOUR_STAY_PROBE_SOURCE = '7e52b07e872479cc853661854ff5d082d5f3a67d';
const ANYTOUR_STAY_PROBE_TABLES = [
    'anytour_catalog_control',
    'anytour_hotels',
    'anytour_hotel_sources',
    'anytour_meal_plans',
    'anytour_hotel_rooms',
    'anytour_stay_mappings',
    'hot_tours_current',
    'tour_price_observations',
];

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        if ($argc !== 1 || basename(dirname(__DIR__, 2)) !== 'payload') {
            throw new RuntimeException('Fixed private invocation required');
        }
        $dir = dirname(__DIR__, 3);
        if (basename($dir) !== ANYTOUR_STAY_PROBE_OPERATION) throw new RuntimeException('Wrong operation directory');
        $reservation = json_decode((string)file_get_contents($dir . '/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
        if (($reservation['operation'] ?? '') !== ANYTOUR_STAY_PROBE_OPERATION
            || ($reservation['dataSource'] ?? '') !== ANYTOUR_STAY_PROBE_SOURCE
            || ($reservation['attempt'] ?? 0) !== 1
            || ($reservation['noReplay'] ?? null) !== true) {
            throw new RuntimeException('Wrong reservation');
        }
        if (is_file($dir . '/result.json') || is_file($dir . '/result.sha256')) throw new RuntimeException('Existing result; no replay');

        $root = rtrim((string)getenv('HOME'), '/') . '/www/anytoour.ru';
        foreach (['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT'] as $key) {
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
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql' || $pdo->inTransaction()) {
            throw new RuntimeException('Dedicated MySQL connection required');
        }

        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('SET TRANSACTION READ ONLY');
        $pdo->beginTransaction();
        try {
            $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($database === '') throw new RuntimeException('Explicit target database required');
            $wanted = array_fill_keys(ANYTOUR_STAY_PROBE_TABLES, true);
            $tableStmt = $pdo->query("SELECT TABLE_NAME,ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME");
            $tables = [];
            foreach ($tableStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $name = (string)$row['TABLE_NAME'];
                if (!isset($wanted[$name])) continue;
                $tables[$name] = ['engine'=>(string)$row['ENGINE'], 'collation'=>(string)$row['TABLE_COLLATION'], 'columns'=>[], 'indexes'=>[], 'rowCount'=>null];
            }
            $columnStmt = $pdo->query("SELECT TABLE_NAME,COLUMN_NAME,ORDINAL_POSITION,COLUMN_TYPE,IS_NULLABLE,COLUMN_KEY,EXTRA,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION");
            foreach ($columnStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $name = (string)$row['TABLE_NAME'];
                if (!isset($tables[$name])) continue;
                $tables[$name]['columns'][] = [
                    'name'=>(string)$row['COLUMN_NAME'], 'position'=>(int)$row['ORDINAL_POSITION'],
                    'type'=>(string)$row['COLUMN_TYPE'], 'nullable'=>(string)$row['IS_NULLABLE'],
                    'key'=>(string)$row['COLUMN_KEY'], 'extra'=>(string)$row['EXTRA'],
                    'collation'=>$row['COLLATION_NAME'] === null ? null : (string)$row['COLLATION_NAME'],
                ];
            }
            $indexStmt = $pdo->query("SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX");
            foreach ($indexStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $name = (string)$row['TABLE_NAME'];
                if (!isset($tables[$name])) continue;
                $tables[$name]['indexes'][] = [
                    'name'=>(string)$row['INDEX_NAME'], 'nonUnique'=>(int)$row['NON_UNIQUE'],
                    'sequence'=>(int)$row['SEQ_IN_INDEX'], 'column'=>(string)$row['COLUMN_NAME'],
                    'subPart'=>$row['SUB_PART'] === null ? null : (int)$row['SUB_PART'],
                ];
            }
            foreach (ANYTOUR_STAY_PROBE_TABLES as $name) {
                if (isset($tables[$name])) $tables[$name]['rowCount'] = (int)$pdo->query('SELECT COUNT(*) FROM `' . $name . '`')->fetchColumn();
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        if (!hash_equals($configHash, (string)hash_file('sha256', $configFile))) throw new RuntimeException('Project configuration changed');
        $missing = array_values(array_diff(ANYTOUR_STAY_PROBE_TABLES, array_keys($tables)));
        $result = [
            'status'=>'read_only_schema_probe', 'operation'=>ANYTOUR_STAY_PROBE_OPERATION,
            'dataSource'=>ANYTOUR_STAY_PROBE_SOURCE, 'executionSource'=>(string)($reservation['executionSource'] ?? ''),
            'run'=>(int)($reservation['run'] ?? 0), 'databaseIdentitySha256'=>hash('sha256', $database),
            'tables'=>$tables, 'missingTables'=>$missing, 'tableCount'=>count($tables),
            'dbWrites'=>0, 'supplierCalls'=>0, 'automaticAccepts'=>0, 'publicFileWrites'=>0,
            'projectConfigurationUnchanged'=>true, 'noReplay'=>true,
        ];
        $bytes = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        $out = fopen($dir . '/result.json', 'x+b');
        if (!$out || fwrite($out, $bytes) !== strlen($bytes) || !fflush($out) || !fsync($out)) throw new RuntimeException('Final receipt incomplete');
        fclose($out);
        if (file_put_contents($dir . '/result.sha256', hash('sha256', $bytes) . "  result.json\n", LOCK_EX) === false) throw new RuntimeException('Result digest incomplete');
        echo 'ANYTOUR_STAY_SCHEMA_PROBE_VERIFIED result_sha256=' . hash('sha256', $bytes)
            . ' tables=' . count($tables) . ' missing=' . count($missing) . ' writes=0 supplier_calls=0' . "\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'ANYTOUR_STAY_SCHEMA_PROBE_STOPPED class=' . get_class($e) . '; no DB writes, inspect before any new operation' . "\n");
        exit(1);
    }
}
