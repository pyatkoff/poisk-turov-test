<?php
/** Fixed on-host READ ONLY inspection. No DDL, seed, supplier or public-file writes. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../../v2/data/anytour-canonical-catalog-v1.php';

const ANYTOUR_CURRENT_OPERATION = 'anytour-catalog-current-1646-20260916-v1';
const ANYTOUR_CURRENT_SOURCE = '0e9a87f8bf01070da6a33225dc0211795a59f4a3';

function anytour_current_database(string $dsn): string
{
    if (!str_starts_with($dsn, 'mysql:')) throw new RuntimeException('Configured MySQL target required');
    $names = [];
    foreach (explode(';', substr($dsn, 6)) as $part) {
        if (str_starts_with($part, 'dbname=')) $names[] = substr($part, 7);
    }
    if (count($names) !== 1 || !preg_match('/^[a-zA-Z0-9_.-]+$/D', $names[0])) throw new RuntimeException('One explicit configured database required');
    return $names[0];
}

function anytour_current_collect(PDO $pdo): array
{
    if ($pdo->inTransaction() || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') throw new RuntimeException('Dedicated MySQL connection required');
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    try {
        $targetNames = ['anytour_catalog_control','anytour_hotels','anytour_hotel_sources','anytour_meal_plans',
            'anytour_room_categories','anytour_hotel_rooms','anytour_stay_mappings'];
        $names = array_merge(['catalog_hotels','catalog_hotel_details'], $targetNames);
        $stmt = $pdo->prepare('SELECT TABLE_NAME,ENGINE,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,count($names),'?')).')');
        $stmt->execute($names); $meta = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $meta[$row['TABLE_NAME']] = $row;
        foreach (['catalog_hotels','catalog_hotel_details'] as $name) {
            if (($meta[$name]['ENGINE'] ?? null) !== 'InnoDB' || ($meta[$name]['TABLE_TYPE'] ?? null) !== 'BASE TABLE') throw new RuntimeException('Saved catalogue source is not an InnoDB base table');
        }
        $counts = [];
        foreach ($names as $name) {
            $m = $meta[$name] ?? null;
            $counts[$name] = ['present'=>$m !== null, 'engine'=>$m['ENGINE'] ?? null, 'type'=>$m['TABLE_TYPE'] ?? null,
                'rows'=>null, 'ddlSha256'=>null];
            if ($m && $m['ENGINE'] === 'InnoDB' && $m['TABLE_TYPE'] === 'BASE TABLE') {
                $counts[$name]['rows'] = (int)$pdo->query("SELECT COUNT(*) FROM `$name`")->fetchColumn();
                $ddl = $pdo->query("SHOW CREATE TABLE `$name`")->fetch(PDO::FETCH_NUM);
                $counts[$name]['ddlSha256'] = hash('sha256', (string)$ddl[1]);
            }
        }
        // Select actual named saved profiles, prioritizing already retained descriptions.
        // This is a bounded initial cohort, NOT every hotel or a provider-ID mapping.
        $ids = array_map('intval', $pdo->query("SELECT h.id FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id
            WHERE h.is_active=1 AND TRIM(h.name)<>''
            ORDER BY CASE WHEN d.status='success' AND TRIM(COALESCE(d.description,''))<>'' THEN 0 ELSE 1 END,h.id LIMIT 1000")->fetchAll(PDO::FETCH_COLUMN));
        $eligible = (int)$pdo->query("SELECT COUNT(*) FROM catalog_hotels WHERE is_active=1 AND TRIM(name)<>''")->fetchColumn();
        $identity = $pdo->query('SELECT DATABASE() AS db,@@hostname AS host,@@port AS port,VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    // Reuse the existing checked owner; its own second READ ONLY snapshot binds this cohort.
    $preflight = $ids ? (new AnyTourCanonicalCatalog($pdo))->preflight($ids) : null;
    return ['status'=>'current_read_only', 'observedAt'=>gmdate('c'), 'databaseNameSha256'=>hash('sha256',(string)$identity['db']),
        'targetIdentitySha256'=>hash('sha256',AnyTourCanonicalCatalog::json($identity)), 'serverVersion'=>$identity['version'],
        'tables'=>$counts, 'eligibleSavedProfiles'=>$eligible, 'cohortLimit'=>1000, 'preflight'=>$preflight,
        'metadataAndCohortAreSeparateReadOnlySnapshots'=>true, 'databaseWrites'=>0, 'supplierCalls'=>0,
        'migrationAuthorized'=>false, 'seedAuthorized'=>false];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $pdo = null; $journal = null;
    try {
        if ($argc !== 1 || basename(dirname(__DIR__,2)) !== 'payload') throw new RuntimeException('Fixed private operation invocation required');
        $directory = dirname(__DIR__,3);
        if (basename($directory) !== ANYTOUR_CURRENT_OPERATION || !is_file($directory.'/reservation.json')) throw new RuntimeException('Existing operation reservation required');
        $reservation = json_decode(file_get_contents($directory.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
        if (($reservation['operation'] ?? null) !== ANYTOUR_CURRENT_OPERATION || ($reservation['dataSource'] ?? null) !== ANYTOUR_CURRENT_SOURCE
            || ($reservation['attempt'] ?? null) !== 1) throw new RuntimeException('Operation provenance mismatch');
        $journal = fopen($directory.'/result.json','x+b');
        if (!$journal) throw new RuntimeException('Result already exists; no replay');
        $root = rtrim((string)getenv('HOME'),'/').'/www/anytoour.ru';
        if (!is_file($root.'/config.php') || !is_file($root.'/api-v2.php')) throw new RuntimeException('Existing project root required');
        foreach (['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT'] as $key) {
            if (getenv($key) !== false && getenv($key) !== '') throw new RuntimeException('Unexpected process DB override');
        }
        $configHash = hash_file('sha256',$root.'/config.php');
        require_once $root.'/config.php';
        require_once __DIR__.'/../../v2/data/db-v1.php';
        $config = v2_data_db_config(); $expectedName = anytour_current_database($config['dsn']);
        if ($config['user'] === '') throw new RuntimeException('Configured project DB user required');
        $pdo = new PDO($config['dsn'],$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>5]);
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $expectedName) throw new RuntimeException('Selected database differs from project configuration');
        $result = anytour_current_collect($pdo);
        if ($configHash !== hash_file('sha256',$root.'/config.php')) throw new RuntimeException('Project configuration changed during inspection');
        $result['operation'] = ANYTOUR_CURRENT_OPERATION; $result['dataSource'] = ANYTOUR_CURRENT_SOURCE;
        $result['executionSource'] = $reservation['executionSource']; $result['run'] = $reservation['run'];
        $result['projectConfigurationUnchanged'] = true; $result['publicFileWrites'] = 0; $result['noReplay'] = true;
        $bytes = AnyTourCanonicalCatalog::json($result)."\n";
        if (fwrite($journal,$bytes) !== strlen($bytes) || !fflush($journal) || !fsync($journal)) throw new RuntimeException('Durable result failed');
        fclose($journal); $journal = null;
        echo 'ANYTOUR_CATALOG_CURRENT_OK result_sha256='.hash('sha256',$bytes)." database_writes=0 supplier_calls=0\n";
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        if (is_resource($journal)) {
            $error = json_encode(['status'=>'inspection_failed','class'=>get_class($e),'databaseWrites'=>0,'noReplay'=>true])."\n";
            fwrite($journal,$error); fflush($journal); fsync($journal); fclose($journal);
        }
        fwrite(STDERR,'ANYTOUR_CATALOG_CURRENT_FAILED class='.get_class($e)." database_writes=0; inspect retained operation, no replay\n");
        exit(1);
    }
}
