<?php
/** One-shot CURRENT read-only stay evidence capture v2. No supplier calls or DB writes. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const ANYTOUR_STAY_EVIDENCE_OPERATION = 'local-stay-evidence-current-2690-20260917-v2';
const ANYTOUR_STAY_EVIDENCE_SOURCE = '7e52b07e872479cc853661854ff5d082d5f3a67d';
const ANYTOUR_STAY_EVIDENCE_LIMIT = 1000;

function anytour_stay_schema_metadata(PDO $pdo): array
{
    $tables = $pdo->query("SELECT TABLE_NAME,ENGINE,TABLE_ROWS FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN
        ('anytour_hotels','anytour_hotel_sources','anytour_meal_plans','anytour_room_categories','anytour_hotel_rooms','anytour_stay_mappings','hot_tours_current','tour_price_observations')
        ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
    $columns = $pdo->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,COLLATION_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND (
          (TABLE_NAME='hot_tours_current' AND COLUMN_NAME IN ('hotel_id','operator_id','meal_id','meal_name','fetched_at')) OR
          (TABLE_NAME='tour_price_observations' AND COLUMN_NAME IN ('hotel_id','operator_id','room_id','room_type','observed_at')) OR
          (TABLE_NAME='anytour_hotel_sources' AND COLUMN_NAME IN ('namespace','external_key','anytour_hotel_id','source_sha256')) OR
          (TABLE_NAME='anytour_stay_mappings' AND COLUMN_NAME IN ('namespace','external_hotel_key','operator_key','kind','key_kind','external_key','state'))
        ) ORDER BY TABLE_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
    return ['tables'=>$tables,'columns'=>$columns];
}

function anytour_stay_write_result(string $dir, array $result): string
{
    $bytes = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $out = fopen($dir . '/result.json', 'x+b');
    if (!$out || fwrite($out, $bytes) !== strlen($bytes) || !fflush($out) || !fsync($out)) {
        throw new RuntimeException('Result receipt incomplete');
    }
    fclose($out);
    return hash('sha256', $bytes);
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) exit;

$operationDir = '';
$metadata = [];
$phase = 'before_reservation';
try {
    if ($argc !== 1 || basename(dirname(__DIR__, 2)) !== 'payload') throw new RuntimeException('Fixed private invocation required');
    $operationDir = dirname(__DIR__, 3);
    if (basename($operationDir) !== ANYTOUR_STAY_EVIDENCE_OPERATION) throw new RuntimeException('Wrong operation directory');
    $reservation = json_decode((string)file_get_contents($operationDir . '/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
    if (($reservation['operation'] ?? '') !== ANYTOUR_STAY_EVIDENCE_OPERATION
        || ($reservation['dataSource'] ?? '') !== ANYTOUR_STAY_EVIDENCE_SOURCE
        || ($reservation['attempt'] ?? 0) !== 1 || ($reservation['readOnly'] ?? null) !== true) {
        throw new RuntimeException('Wrong reservation');
    }
    if (is_file($operationDir . '/result.json') || is_file($operationDir . '/started.json')) throw new RuntimeException('Existing operation state; no replay');
    $started = fopen($operationDir . '/started.json', 'x+b');
    if (!$started) throw new RuntimeException('Cannot reserve operation start');
    $startedBytes = json_encode(['status'=>'started','operation'=>ANYTOUR_STAY_EVIDENCE_OPERATION,'noReplay'=>true], JSON_THROW_ON_ERROR) . "\n";
    if (fwrite($started, $startedBytes) !== strlen($startedBytes) || !fflush($started) || !fsync($started)) throw new RuntimeException('Durable start checkpoint failed');
    fclose($started);

    foreach (['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT',
        'ANYTOUR_STAY_CANDIDATES_DSN','ANYTOUR_STAY_CANDIDATES_USER','ANYTOUR_STAY_CANDIDATES_PASSWORD'] as $key) {
        if (getenv($key) !== false && getenv($key) !== '') throw new RuntimeException('Unexpected DB override');
    }
    $root = rtrim((string)getenv('HOME'), '/') . '/www/anytoour.ru';
    $configFile = $root . '/config.php';
    $configHash = hash_file('sha256', $configFile);
    if ($configHash === false) throw new RuntimeException('Project configuration missing');
    require_once $configFile;
    require_once __DIR__ . '/../../v2/data/db-v1.php';
    require_once __DIR__ . '/anytour_stay_candidates.php';
    $config = v2_data_db_config();
    if (!str_starts_with((string)$config['dsn'], 'mysql:') || trim((string)$config['user']) === '') throw new RuntimeException('Explicit project MySQL configuration required');
    $pdo = new PDO($config['dsn'], $config['user'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_TIMEOUT=>5]);

    $phase = 'schema_metadata';
    $metadata = anytour_stay_schema_metadata($pdo);
    $phase = 'candidate_inventory';
    $inventory = (new AnyTourStayCandidates($pdo))->collect(ANYTOUR_STAY_EVIDENCE_LIMIT);
    if (($inventory['writes'] ?? null) !== 0 || ($inventory['supplierCalls'] ?? null) !== 0 || ($inventory['automaticAccepts'] ?? null) !== 0) {
        throw new RuntimeException('Read-only evidence invariant failed');
    }
    if (!hash_equals($configHash, (string)hash_file('sha256', $configFile))) throw new RuntimeException('Project configuration changed');
    $phase = 'complete';
    $result = ['status'=>'current_read_only_evidence_verified','operation'=>ANYTOUR_STAY_EVIDENCE_OPERATION,
        'dataSource'=>ANYTOUR_STAY_EVIDENCE_SOURCE,'executionSource'=>(string)($reservation['executionSource'] ?? ''),
        'run'=>(int)($reservation['run'] ?? 0),'capturedAtUtc'=>gmdate('c'),'schemaMetadata'=>$metadata,'inventory'=>$inventory,
        'writes'=>0,'supplierCalls'=>0,'automaticAccepts'=>0,'publicFileWrites'=>0,'projectConfigurationUnchanged'=>true,'noReplay'=>true];
    $sha = anytour_stay_write_result($operationDir, $result);
    echo 'ANYTOUR_STAY_EVIDENCE_CURRENT_OK result_sha256=' . $sha . ' meals=' . count($inventory['mealCandidates'])
        . ' rooms=' . count($inventory['roomCandidates']) . "\n";
} catch (Throwable $e) {
    if ($operationDir !== '' && is_dir($operationDir) && !is_file($operationDir . '/result.json')) {
        $sqlState = null; $driverCode = null;
        if ($e instanceof PDOException) {
            $sqlState = is_string($e->getCode()) ? $e->getCode() : (string)$e->getCode();
            $driverCode = isset($e->errorInfo[1]) && is_int($e->errorInfo[1]) ? $e->errorInfo[1] : null;
        }
        try {
            anytour_stay_write_result($operationDir, ['status'=>'current_read_only_evidence_failed','operation'=>ANYTOUR_STAY_EVIDENCE_OPERATION,
                'dataSource'=>ANYTOUR_STAY_EVIDENCE_SOURCE,'phase'=>$phase,'errorClass'=>get_class($e),'sqlState'=>$sqlState,
                'driverCode'=>$driverCode,'schemaMetadata'=>$metadata,'writes'=>0,'supplierCalls'=>0,'automaticAccepts'=>0,
                'publicFileWrites'=>0,'noReplay'=>true]);
        } catch (Throwable) {}
    }
    fwrite(STDERR, 'ANYTOUR_STAY_EVIDENCE_STOPPED phase=' . $phase . ' class=' . get_class($e) . '; NO REPLAY\n');
    exit(1);
}
