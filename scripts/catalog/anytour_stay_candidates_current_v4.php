<?php
/** One-shot CURRENT read-only stay evidence capture v4. No supplier calls or DB writes. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const ANYTOUR_STAY_EVIDENCE_OPERATION_V4 = 'local-stay-evidence-current-2690-20260917-v4';
const ANYTOUR_STAY_EVIDENCE_SOURCE_V4 = '26e2de291f20bfca6aff365d3e9c77b48a12f3cf';
const ANYTOUR_STAY_EVIDENCE_LIMIT_V4 = 1000;

function anytour_stay_v4_write(string $path, array $value): void
{
    $bytes = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $out = fopen($path, 'x+b');
    if (!$out || fwrite($out, $bytes) !== strlen($bytes) || !fflush($out) || !fsync($out)) {
        throw new RuntimeException('Durable result write failed');
    }
    fclose($out);
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) exit;

$operationDir = '';
$phase = 'before_reservation';
try {
    if ($argc !== 1 || basename(dirname(__DIR__, 2)) !== 'payload') throw new RuntimeException('Fixed private invocation required');
    $operationDir = dirname(__DIR__, 3);
    if (basename($operationDir) !== ANYTOUR_STAY_EVIDENCE_OPERATION_V4) throw new RuntimeException('Wrong operation directory');
    $reservation = json_decode((string)file_get_contents($operationDir . '/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
    if (($reservation['operation'] ?? '') !== ANYTOUR_STAY_EVIDENCE_OPERATION_V4
        || ($reservation['dataSource'] ?? '') !== ANYTOUR_STAY_EVIDENCE_SOURCE_V4
        || ($reservation['attempt'] ?? 0) !== 1 || ($reservation['readOnly'] ?? null) !== true
        || ($reservation['dbWrites'] ?? null) !== 0 || ($reservation['supplierCalls'] ?? null) !== 0
        || ($reservation['noReplay'] ?? null) !== true) {
        throw new RuntimeException('Wrong reservation');
    }
    if (is_file($operationDir . '/result.json') || is_file($operationDir . '/started.json')) throw new RuntimeException('Existing operation state; no replay');
    anytour_stay_v4_write($operationDir . '/started.json', ['status'=>'started','operation'=>ANYTOUR_STAY_EVIDENCE_OPERATION_V4,'noReplay'=>true]);

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

    $phase = 'candidate_inventory';
    $inventory = (new AnyTourStayCandidates($pdo))->collect(ANYTOUR_STAY_EVIDENCE_LIMIT_V4);
    if (($inventory['writes'] ?? null) !== 0 || ($inventory['supplierCalls'] ?? null) !== 0 || ($inventory['automaticAccepts'] ?? null) !== 0) {
        throw new RuntimeException('Read-only evidence invariant failed');
    }
    if (!hash_equals($configHash, (string)hash_file('sha256', $configFile))) throw new RuntimeException('Project configuration changed');
    $phase = 'complete';
    anytour_stay_v4_write($operationDir . '/result.json', [
        'status'=>'current_read_only_evidence_verified','operation'=>ANYTOUR_STAY_EVIDENCE_OPERATION_V4,
        'dataSource'=>ANYTOUR_STAY_EVIDENCE_SOURCE_V4,'executionSource'=>(string)($reservation['executionSource'] ?? ''),
        'run'=>(int)($reservation['run'] ?? 0),'capturedAtUtc'=>gmdate('c'),'inventory'=>$inventory,
        'writes'=>0,'supplierCalls'=>0,'automaticAccepts'=>0,'publicFileWrites'=>0,'projectConfigurationUnchanged'=>true,'noReplay'=>true]);
    echo 'ANYTOUR_STAY_EVIDENCE_V4_OK meals=' . count($inventory['mealCandidates']) . ' rooms=' . count($inventory['roomCandidates']) . "\n";
} catch (Throwable $e) {
    if ($operationDir !== '' && is_dir($operationDir) && !is_file($operationDir . '/result.json')) {
        try { anytour_stay_v4_write($operationDir . '/result.json', [
            'status'=>'current_read_only_evidence_failed','operation'=>ANYTOUR_STAY_EVIDENCE_OPERATION_V4,'dataSource'=>ANYTOUR_STAY_EVIDENCE_SOURCE_V4,
            'phase'=>$phase,'errorClass'=>get_class($e),'writes'=>0,'supplierCalls'=>0,'automaticAccepts'=>0,'publicFileWrites'=>0,'noReplay'=>true]); }
        catch (Throwable) {}
    }
    fwrite(STDERR, 'ANYTOUR_STAY_EVIDENCE_V4_STOPPED phase=' . $phase . ' class=' . get_class($e) . '; NO REPLAY\n');
    exit(1);
}
