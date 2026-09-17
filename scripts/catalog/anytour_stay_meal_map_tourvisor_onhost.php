<?php
/** One-shot reviewed Tourvisor meal mapping apply. No supplier I/O, rooms, or canonical/source overwrite. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const ANYTOUR_STAY_MEAL_OPERATION = 'local-stay-meal-map-tourvisor-2690-20260917-v1';
const ANYTOUR_STAY_MEAL_SOURCE = 'b90390c1c2008e07a91b053107d68822a45dd213';
const ANYTOUR_STAY_MEAL_CURRENT_RESULT = '766f6e7843af19730c1d8515898f072a46b797c33275af91755a70e2b3d2d5ae';
const ANYTOUR_STAY_MEAL_ROWS = 251;

function anytour_stay_meal_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function anytour_stay_meal_checkpoint($journal, string $event, array $state): void
{
    if (!is_resource($journal)) throw new RuntimeException('Journal unavailable');
    $line = anytour_stay_meal_json(['event'=>$event,'atUtc'=>gmdate('c'),'state'=>$state]) . "\n";
    if (fwrite($journal, $line) !== strlen($line) || !fflush($journal) || !fsync($journal)) {
        throw new RuntimeException('Durable journal checkpoint failed');
    }
}

function anytour_stay_meal_write_exclusive(string $path, array $value): string
{
    $bytes = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $out = fopen($path, 'x+b');
    if (!$out || fwrite($out, $bytes) !== strlen($bytes) || !fflush($out) || !fsync($out)) {
        if (is_resource($out)) fclose($out);
        throw new RuntimeException('Exclusive durable receipt write failed');
    }
    fclose($out);
    return hash('sha256', $bytes);
}

function anytour_stay_meal_validate_manifest(array $manifest): array
{
    if (($manifest['version'] ?? null) !== 1 || ($manifest['operation'] ?? null) !== ANYTOUR_STAY_MEAL_OPERATION
        || !isset($manifest['rows']) || !is_array($manifest['rows']) || !array_is_list($manifest['rows'])
        || count($manifest['rows']) !== ANYTOUR_STAY_MEAL_ROWS) {
        throw new RuntimeException('Wrong sealed meal manifest');
    }
    $targets = ['3'=>'breakfast','4'=>'half-board','7'=>'all-inclusive','9'=>'ultra-all-inclusive'];
    $expected = ['3'=>81,'4'=>12,'7'=>112,'9'=>46];
    $counts = array_fill_keys(array_keys($expected), 0);
    $seen = [];
    foreach ($manifest['rows'] as $row) {
        if (!is_array($row) || !is_array($row['scope'] ?? null) || !is_array($row['reference'] ?? null)
            || !is_array($row['target'] ?? null) || !is_array($row['evidence'] ?? null)) {
            throw new RuntimeException('Malformed sealed meal row');
        }
        $scope = $row['scope']; $ref = $row['reference']; $evidence = $row['evidence'];
        $id = (string)($ref['externalKey'] ?? '');
        if (($scope['namespace'] ?? null) !== 'legacy_catalog' || ($ref['kind'] ?? null) !== 'meal'
            || ($ref['keyKind'] ?? null) !== 'code' || !isset($targets[$id])
            || ($row['target']['code'] ?? null) !== $targets[$id]
            || ($evidence['reviewedBy'] ?? null) !== 'pyatkoff'
            || !str_contains((string)($evidence['ref'] ?? ''), ANYTOUR_STAY_MEAL_CURRENT_RESULT)
            || !str_contains((string)($evidence['ref'] ?? ''), 'tourvisor-tv-meal:' . $id . '->' . $targets[$id])) {
            throw new RuntimeException('Unreviewed meal mapping in sealed manifest');
        }
        if (!is_int($row['hotelId'] ?? null) || $row['hotelId'] < 1
            || !is_string($row['sourceSha256'] ?? null) || !preg_match('/^[0-9a-f]{64}$/D', $row['sourceSha256'])
            || !is_string($evidence['sha256'] ?? null) || !preg_match('/^[0-9a-f]{64}$/D', $evidence['sha256'])) {
            throw new RuntimeException('Invalid sealed meal identity/evidence');
        }
        $key = anytour_stay_meal_json([$scope, $ref]);
        if (isset($seen[$key])) throw new RuntimeException('Duplicate exact meal decision');
        $seen[$key] = true;
        $counts[$id]++;
    }
    if ($counts !== $expected) throw new RuntimeException('Sealed meal distribution changed');
    return $counts;
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) exit;

$operationDir = '';
$journal = null;
$phase = 'before_reservation';
$commitVerified = false;
try {
    if ($argc !== 1 || basename(dirname(__DIR__, 2)) !== 'payload') throw new RuntimeException('Fixed private invocation required');
    $operationDir = dirname(__DIR__, 3);
    if (basename($operationDir) !== ANYTOUR_STAY_MEAL_OPERATION) throw new RuntimeException('Wrong operation directory');

    $reservation = json_decode((string)file_get_contents($operationDir . '/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
    if (($reservation['operation'] ?? '') !== ANYTOUR_STAY_MEAL_OPERATION
        || ($reservation['dataSource'] ?? '') !== ANYTOUR_STAY_MEAL_SOURCE
        || ($reservation['attempt'] ?? 0) !== 1
        || ($reservation['expectedRows'] ?? 0) !== ANYTOUR_STAY_MEAL_ROWS
        || ($reservation['expectedNewMappings'] ?? 0) !== ANYTOUR_STAY_MEAL_ROWS
        || ($reservation['noReplay'] ?? null) !== true) {
        throw new RuntimeException('Wrong operation reservation');
    }

    $manifestPath = $operationDir . '/manifest.json';
    $manifestSha = hash_file('sha256', $manifestPath);
    if (!is_string($manifestSha) || !is_string($reservation['manifestSha256'] ?? null)
        || !hash_equals($reservation['manifestSha256'], $manifestSha)) {
        throw new RuntimeException('Sealed manifest digest mismatch');
    }
    $manifestRaw = file_get_contents($manifestPath);
    $manifest = json_decode((string)$manifestRaw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($manifest)) throw new RuntimeException('Sealed manifest object required');
    $counts = anytour_stay_meal_validate_manifest($manifest);

    if (is_file($operationDir . '/journal.jsonl') || is_file($operationDir . '/result.json')) {
        throw new RuntimeException('Existing operation state; no replay');
    }
    $journal = fopen($operationDir . '/journal.jsonl', 'x+b');
    if (!$journal) throw new RuntimeException('Cannot reserve operation journal');
    anytour_stay_meal_checkpoint($journal, 'reserved', [
        'operation'=>ANYTOUR_STAY_MEAL_OPERATION,'dataSource'=>ANYTOUR_STAY_MEAL_SOURCE,
        'manifestSha256'=>$manifestSha,'rows'=>ANYTOUR_STAY_MEAL_ROWS,'counts'=>$counts,'noReplay'=>true,
    ]);

    foreach (['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT'] as $key) {
        if (getenv($key) !== false && getenv($key) !== '') throw new RuntimeException('Unexpected DB override');
    }
    $root = rtrim((string)getenv('HOME'), '/') . '/www/anytoour.ru';
    $configFile = $root . '/config.php';
    $configHash = hash_file('sha256', $configFile);
    if ($configHash === false) throw new RuntimeException('Project configuration missing');
    require_once $configFile;
    require_once __DIR__ . '/../../v2/data/db-v1.php';
    require_once __DIR__ . '/anytour_stay_import.php';

    $manifest = AnyTourStayImport::manifest($manifest);
    anytour_stay_meal_validate_manifest($manifest);

    $config = v2_data_db_config();
    if (!str_starts_with((string)$config['dsn'], 'mysql:') || trim((string)$config['user']) === '') {
        throw new RuntimeException('Explicit project MySQL configuration required');
    }
    $pdo = new PDO($config['dsn'], $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::ATTR_TIMEOUT=>5,
    ]);
    $import = new AnyTourStayImport($pdo);

    $phase = 'plan';
    $plan = $import->plan($manifest);
    if (($plan['status'] ?? null) !== 'prepared_read_only'
        || ($plan['rows'] ?? null) !== ANYTOUR_STAY_MEAL_ROWS
        || ($plan['createRooms'] ?? null) !== 0
        || ($plan['createMappings'] ?? null) !== ANYTOUR_STAY_MEAL_ROWS
        || ($plan['unchangedMappings'] ?? null) !== 0
        || ($plan['writes'] ?? null) !== 0
        || !is_string($plan['planSha256'] ?? null) || !preg_match('/^[0-9a-f]{64}$/D', $plan['planSha256'])) {
        throw new RuntimeException('CURRENT plan is not the exact fresh 251-mapping delta');
    }
    $planSha = anytour_stay_meal_write_exclusive($operationDir . '/plan.json', $plan);
    anytour_stay_meal_checkpoint($journal, 'planned', [
        'planSha256'=>$plan['planSha256'],'planReceiptSha256'=>$planSha,
        'createMappings'=>$plan['createMappings'],'createRooms'=>$plan['createRooms'],'noReplay'=>true,
    ]);

    $phase = 'apply';
    $receipt = $import->apply($manifest, $plan['planSha256'], static function(array $state) use ($journal): void {
        anytour_stay_meal_checkpoint($journal, 'import', $state);
    });
    if (($receipt['status'] ?? null) !== 'committed_verified'
        || ($receipt['createdMappings'] ?? null) !== ANYTOUR_STAY_MEAL_ROWS
        || ($receipt['createdRooms'] ?? null) !== 0
        || ($receipt['unchangedMappings'] ?? null) !== 0
        || ($receipt['verifiedMappings'] ?? null) !== ANYTOUR_STAY_MEAL_ROWS
        || ($receipt['sourceWrites'] ?? null) !== 0
        || ($receipt['canonicalOverwrites'] ?? null) !== 0
        || ($receipt['supplierCalls'] ?? null) !== 0) {
        throw new RuntimeException('Importer terminal receipt mismatch');
    }
    $commitVerified = true;
    $phase = 'post_commit_readback';

    $after = $import->plan($manifest);
    if (($after['status'] ?? null) !== 'prepared_read_only'
        || ($after['rows'] ?? null) !== ANYTOUR_STAY_MEAL_ROWS
        || ($after['createRooms'] ?? null) !== 0
        || ($after['createMappings'] ?? null) !== 0
        || ($after['unchangedMappings'] ?? null) !== ANYTOUR_STAY_MEAL_ROWS
        || ($after['writes'] ?? null) !== 0) {
        throw new RuntimeException('Independent post-COMMIT readback mismatch');
    }
    if (!hash_equals($configHash, (string)hash_file('sha256', $configFile))) {
        throw new RuntimeException('Project configuration changed');
    }

    $phase = 'terminal_receipt';
    $result = [
        'status'=>'meal_mappings_committed_verified',
        'operation'=>ANYTOUR_STAY_MEAL_OPERATION,
        'dataSource'=>ANYTOUR_STAY_MEAL_SOURCE,
        'executionSource'=>(string)($reservation['executionSource'] ?? ''),
        'run'=>(int)($reservation['run'] ?? 0),
        'manifestSha256'=>$manifestSha,
        'currentEvidenceResultSha256'=>ANYTOUR_STAY_MEAL_CURRENT_RESULT,
        'mappingCounts'=>$counts,
        'createdMappings'=>ANYTOUR_STAY_MEAL_ROWS,
        'createdRooms'=>0,
        'unresolvedMealIds'=>['2'],
        'roomsRemainUnresolved'=>true,
        'initialPlan'=>$plan,
        'importReceipt'=>$receipt,
        'postCommitPlan'=>$after,
        'supplierCalls'=>0,
        'sourceWrites'=>0,
        'canonicalOverwrites'=>0,
        'publicFileWrites'=>0,
        'projectConfigurationUnchanged'=>true,
        'noReplay'=>true,
    ];
    $resultSha = anytour_stay_meal_write_exclusive($operationDir . '/result.json', $result);
    anytour_stay_meal_checkpoint($journal, 'terminal', ['status'=>$result['status'],'resultSha256'=>$resultSha,'noReplay'=>true]);
    fclose($journal); $journal = null;
    echo 'ANYTOUR_STAY_MEAL_MAP_VERIFIED result_sha256=' . $resultSha . ' mappings=' . ANYTOUR_STAY_MEAL_ROWS . " rooms=0 supplier_calls=0\n";
} catch (Throwable $e) {
    $state = $e instanceof AnyTourStayCommitUncertain
        ? 'commit_unknown'
        : ($e instanceof AnyTourStayReadbackFailed
            ? 'committed_unverified'
            : ($commitVerified ? 'committed_verified_terminal_incomplete' : 'failed_before_commit'));
    if (is_resource($journal)) {
        try { anytour_stay_meal_checkpoint($journal, 'stopped', ['status'=>$state,'phase'=>$phase,'class'=>get_class($e),'noReplay'=>true]); } catch (Throwable) {}
        fclose($journal); $journal = null;
    }
    if ($operationDir !== '' && is_dir($operationDir) && !is_file($operationDir . '/terminal.json')) {
        try { anytour_stay_meal_write_exclusive($operationDir . '/terminal.json', ['status'=>$state,'phase'=>$phase,'class'=>get_class($e),'noReplay'=>true]); } catch (Throwable) {}
    }
    fwrite(STDERR, 'ANYTOUR_STAY_MEAL_MAP_STOPPED state=' . $state . ' phase=' . $phase . ' class=' . get_class($e) . "; NO REPLAY\n");
    exit(1);
}
