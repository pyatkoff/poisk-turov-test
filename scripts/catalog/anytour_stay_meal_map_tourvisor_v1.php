<?php
/** One-shot reviewed Tourvisor meal mapping apply. No supplier calls or public runtime writes. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const ANYTOUR_STAY_MEAL_OPERATION = 'local-stay-meal-map-tourvisor-2690-20260917-v1';
const ANYTOUR_STAY_MEAL_SOURCE = '26e2de291f20bfca6aff365d3e9c77b48a12f3cf';
const ANYTOUR_STAY_MEAL_EVIDENCE_SHA = '766f6e7843af19730c1d8515898f072a46b797c33275af91755a70e2b3d2d5ae';
const ANYTOUR_STAY_MEAL_MANIFEST_SHA = '07ddbb389649561e357a7ff823bc8f115aef5346d3659cd168878d0dfb3f044c';
const ANYTOUR_STAY_MEAL_ROWS = 251;

function anytour_stay_meal_write_file_v1(string $path, array $value): void
{
    $bytes = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $out = fopen($path, 'x+b');
    if (!$out || fwrite($out, $bytes) !== strlen($bytes) || !fflush($out) || !fsync($out)) {
        throw new RuntimeException('Durable receipt write failed: ' . basename($path));
    }
    fclose($out);
}

function anytour_stay_meal_checkpoint_v1(string $operationDir, array $event): void
{
    $status = (string)($event['status'] ?? '');
    if (!in_array($status, ['before_commit', 'committed_unverified', 'committed_verified'], true)) {
        throw new RuntimeException('Unexpected importer checkpoint');
    }
    anytour_stay_meal_write_file_v1($operationDir . '/checkpoint-' . $status . '.json', $event);
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) exit;

$operationDir = '';
$phase = 'before_reservation';
$plan = null;
try {
    if ($argc !== 1 || basename(dirname(__DIR__, 2)) !== 'payload') throw new RuntimeException('Fixed private invocation required');
    $operationDir = dirname(__DIR__, 3);
    if (basename($operationDir) !== ANYTOUR_STAY_MEAL_OPERATION) throw new RuntimeException('Wrong operation directory');
    $reservation = json_decode((string)file_get_contents($operationDir . '/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
    if (($reservation['operation'] ?? '') !== ANYTOUR_STAY_MEAL_OPERATION
        || ($reservation['dataSource'] ?? '') !== ANYTOUR_STAY_MEAL_SOURCE
        || ($reservation['attempt'] ?? 0) !== 1 || ($reservation['writeOperation'] ?? null) !== true
        || ($reservation['manifestSha256'] ?? '') !== ANYTOUR_STAY_MEAL_MANIFEST_SHA
        || ($reservation['evidenceSha256'] ?? '') !== ANYTOUR_STAY_MEAL_EVIDENCE_SHA) {
        throw new RuntimeException('Wrong reservation');
    }
    foreach (['result.json', 'started.json', 'checkpoint-before_commit.json', 'checkpoint-committed_unverified.json', 'checkpoint-committed_verified.json'] as $name) {
        if (is_file($operationDir . '/' . $name)) throw new RuntimeException('Existing operation state; no replay');
    }
    anytour_stay_meal_write_file_v1($operationDir . '/started.json', [
        'status'=>'started','operation'=>ANYTOUR_STAY_MEAL_OPERATION,'noReplay'=>true,'startedAtUtc'=>gmdate('c')]);

    foreach (['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT',
        'ANYTOUR_STAY_CANDIDATES_DSN','ANYTOUR_STAY_CANDIDATES_USER','ANYTOUR_STAY_CANDIDATES_PASSWORD','ANYTOUR_STAY_IMPORT_TEST_DSN'] as $key) {
        if (getenv($key) !== false && getenv($key) !== '') throw new RuntimeException('Unexpected DB override');
    }
    $root = rtrim((string)getenv('HOME'), '/') . '/www/anytoour.ru';
    $configFile = $root . '/config.php';
    $configHash = hash_file('sha256', $configFile);
    if ($configHash === false) throw new RuntimeException('Project configuration missing');
    require_once $configFile;
    require_once __DIR__ . '/../../v2/data/db-v1.php';
    require_once __DIR__ . '/anytour_stay_candidates.php';
    require_once __DIR__ . '/anytour_stay_import.php';

    $manifestPath = __DIR__ . '/anytour_stay_meal_map_tourvisor_20260917_v1.json';
    $input = json_decode((string)file_get_contents($manifestPath), true, 64, JSON_THROW_ON_ERROR);
    $manifest = AnyTourStayImport::manifest($input);
    $manifestSha = hash('sha256', AnyTourStayImport::json($manifest));
    if (!hash_equals(ANYTOUR_STAY_MEAL_MANIFEST_SHA, $manifestSha) || count($manifest['rows']) !== ANYTOUR_STAY_MEAL_ROWS) {
        throw new RuntimeException('Sealed reviewed manifest differs');
    }
    $expectedTargets = ['3'=>'breakfast','4'=>'half-board','7'=>'all-inclusive','9'=>'ultra-all-inclusive'];
    $expectedCounts = ['3'=>81,'4'=>12,'7'=>112,'9'=>46];
    $actualCounts = array_fill_keys(array_keys($expectedCounts), 0);
    foreach ($manifest['rows'] as $row) {
        $key = $row['reference']['externalKey'];
        if ($row['scope']['namespace'] !== 'legacy_catalog' || $row['reference']['kind'] !== 'meal'
            || $row['reference']['keyKind'] !== 'code' || !isset($expectedTargets[$key])
            || $row['target']['code'] !== $expectedTargets[$key]
            || $row['evidence']['sha256'] !== ANYTOUR_STAY_MEAL_EVIDENCE_SHA) {
            throw new RuntimeException('Manifest contains an unreviewed meal decision');
        }
        $actualCounts[$key]++;
    }
    if ($actualCounts !== $expectedCounts) throw new RuntimeException('Reviewed meal distribution differs');

    $config = v2_data_db_config();
    if (!str_starts_with((string)$config['dsn'], 'mysql:') || trim((string)$config['user']) === '') throw new RuntimeException('Explicit project MySQL configuration required');
    $pdo = new PDO($config['dsn'], $config['user'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_TIMEOUT=>5]);

    // Revalidate every reviewed source/ref against CURRENT saved evidence before the importer obtains write locks.
    $phase = 'current_candidate_revalidation';
    $current = (new AnyTourStayCandidates($pdo))->collect(1000);
    $currentIndex = [];
    foreach ($current['mealCandidates'] as $candidate) {
        $key = AnyTourStayImport::json([$candidate['scope'], $candidate['reference']]);
        if (isset($currentIndex[$key])) throw new RuntimeException('Conflicting CURRENT meal candidate');
        $currentIndex[$key] = $candidate;
    }
    foreach ($manifest['rows'] as $row) {
        $key = AnyTourStayImport::json([$row['scope'], $row['reference']]);
        $candidate = $currentIndex[$key] ?? null;
        if (!$candidate || $candidate['decisionState'] !== 'unmapped'
            || $candidate['hotelId'] !== $row['hotelId']
            || !hash_equals($candidate['sourceSha256'], $row['sourceSha256'])) {
            throw new RuntimeException('CURRENT reviewed candidate missing, mapped, or identity-drifted');
        }
    }

    $phase = 'read_only_plan';
    $import = new AnyTourStayImport($pdo);
    $plan = $import->plan($manifest);
    if (($plan['rows'] ?? null) !== ANYTOUR_STAY_MEAL_ROWS || ($plan['createRooms'] ?? null) !== 0
        || ($plan['createMappings'] ?? null) !== ANYTOUR_STAY_MEAL_ROWS || ($plan['unchangedMappings'] ?? null) !== 0
        || ($plan['writes'] ?? null) !== 0) {
        throw new RuntimeException('CURRENT plan is not the exact fresh 251-mapping reviewed batch');
    }
    anytour_stay_meal_write_file_v1($operationDir . '/plan.json', $plan);

    $phase = 'apply';
    $receipt = $import->apply($manifest, (string)$plan['planSha256'],
        static fn(array $event) => anytour_stay_meal_checkpoint_v1($operationDir, $event));
    if (($receipt['status'] ?? '') !== 'committed_verified' || ($receipt['createdRooms'] ?? null) !== 0
        || ($receipt['createdMappings'] ?? null) !== ANYTOUR_STAY_MEAL_ROWS
        || ($receipt['verifiedMappings'] ?? null) !== ANYTOUR_STAY_MEAL_ROWS
        || ($receipt['sourceWrites'] ?? null) !== 0 || ($receipt['canonicalOverwrites'] ?? null) !== 0
        || ($receipt['supplierCalls'] ?? null) !== 0) {
        throw new RuntimeException('Committed receipt invariant failed');
    }
    if (!hash_equals($configHash, (string)hash_file('sha256', $configFile))) throw new RuntimeException('Project configuration changed');

    $phase = 'complete';
    $result = ['status'=>'committed_verified','operation'=>ANYTOUR_STAY_MEAL_OPERATION,'dataSource'=>ANYTOUR_STAY_MEAL_SOURCE,
        'executionSource'=>(string)($reservation['executionSource'] ?? ''),'run'=>(int)($reservation['run'] ?? 0),
        'completedAtUtc'=>gmdate('c'),'manifestSha256'=>$manifestSha,'evidenceSha256'=>ANYTOUR_STAY_MEAL_EVIDENCE_SHA,
        'officialTourvisorMealMap'=>$expectedTargets,'reviewedCounts'=>$expectedCounts,'plan'=>$plan,'receipt'=>$receipt,
        'unresolvedMealIds'=>['2'],'roomsMapped'=>0,'supplierCalls'=>0,'sourceWrites'=>0,'canonicalOverwrites'=>0,
        'publicFileWrites'=>0,'projectConfigurationUnchanged'=>true,'noReplay'=>true];
    anytour_stay_meal_write_file_v1($operationDir . '/result.json', $result);
    echo 'ANYTOUR_STAY_MEAL_MAP_OK mappings=' . ANYTOUR_STAY_MEAL_ROWS . ' plan_sha256=' . $plan['planSha256'] . "\n";
} catch (Throwable $e) {
    if ($operationDir !== '' && is_dir($operationDir) && !is_file($operationDir . '/result.json')) {
        try {
            anytour_stay_meal_write_file_v1($operationDir . '/result.json', [
                'status'=>($e instanceof AnyTourStayCommitUncertain || $e instanceof AnyTourStayReadbackFailed) ? 'commit_or_readback_uncertain_no_replay' : 'stopped_no_replay',
                'operation'=>ANYTOUR_STAY_MEAL_OPERATION,'dataSource'=>ANYTOUR_STAY_MEAL_SOURCE,'phase'=>$phase,
                'errorClass'=>get_class($e),'plan'=>$plan,'supplierCalls'=>0,'publicFileWrites'=>0,'noReplay'=>true]);
        } catch (Throwable) {}
    }
    fwrite(STDERR, 'ANYTOUR_STAY_MEAL_MAP_STOPPED phase=' . $phase . ' class=' . get_class($e) . '; NO REPLAY\n');
    exit(1);
}
