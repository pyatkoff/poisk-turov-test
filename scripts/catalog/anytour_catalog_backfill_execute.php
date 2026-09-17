<?php
/** Fixed one-shot live AnyTour canonical-profile backfill. Ops branch only; close without merge. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../v2/data/anytour-catalog-backfill-v1.php';
require_once __DIR__ . '/../../v2/data/db-v1.php';

const ANYTOUR_BACKFILL_OPERATION = 'anytour-catalog-backfill-2690-20260917-v1';
const ANYTOUR_BACKFILL_SOURCE = '7e52b07e872479cc853661854ff5d082d5f3a67d';
const ANYTOUR_BACKFILL_LIMIT = 1000;

function anytour_backfill_db_name(string $dsn): string
{
    if (!str_starts_with($dsn, 'mysql:')) throw new RuntimeException('Configured MySQL target required');
    $names = [];
    foreach (explode(';', substr($dsn, 6)) as $part) if (str_starts_with($part, 'dbname=')) $names[] = substr($part, 7);
    if (count($names) !== 1 || !preg_match('/^[A-Za-z0-9_.-]+$/D', $names[0])) throw new RuntimeException('One explicit configured database required');
    return $names[0];
}

function anytour_backfill_append($journal, array $entry): void
{
    $line = AnyTourCanonicalCatalog::json($entry) . "\n";
    if (fwrite($journal, $line) !== strlen($line) || !fflush($journal) || !fsync($journal)) {
        throw new RuntimeException('Durable operation journal failed');
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $pdo = null; $resultHandle = null; $journal = null;
    try {
        if ($argc !== 1 || basename(dirname(__DIR__, 2)) !== 'payload') throw new RuntimeException('Fixed private operation invocation required');
        $directory = dirname(__DIR__, 3);
        if (basename($directory) !== ANYTOUR_BACKFILL_OPERATION || !is_file($directory . '/reservation.json')) {
            throw new RuntimeException('Existing operation reservation required');
        }
        $reservation = json_decode(file_get_contents($directory . '/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
        if (($reservation['operation'] ?? null) !== ANYTOUR_BACKFILL_OPERATION
            || ($reservation['dataSource'] ?? null) !== ANYTOUR_BACKFILL_SOURCE
            || ($reservation['attempt'] ?? null) !== 1
            || ($reservation['limit'] ?? null) !== ANYTOUR_BACKFILL_LIMIT) {
            throw new RuntimeException('Operation provenance mismatch');
        }
        $resultHandle = fopen($directory . '/result.json', 'x+b');
        $journal = fopen($directory . '/journal.jsonl', 'x+b');
        if (!$resultHandle || !$journal) throw new RuntimeException('Operation already exists; no replay');

        $root = rtrim((string)getenv('HOME'), '/') . '/www/anytoour.ru';
        if (!is_file($root . '/config.php') || !is_file($root . '/api-v2.php')) throw new RuntimeException('Existing project root required');
        foreach (['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT'] as $key) {
            if (getenv($key) !== false && getenv($key) !== '') throw new RuntimeException('Unexpected process DB override');
        }
        $configHash = hash_file('sha256', $root . '/config.php');
        require_once $root . '/config.php';
        $config = v2_data_db_config();
        $expectedName = anytour_backfill_db_name($config['dsn']);
        if (($config['user'] ?? '') === '') throw new RuntimeException('Configured project DB user required');
        $pdo = new PDO($config['dsn'], $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($database !== $expectedName) throw new RuntimeException('Selected database differs from project configuration');

        $catalog = new AnyTourCanonicalCatalog($pdo);
        $catalog->assertSchema();
        $beforeHotels = (int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels')->fetchColumn();
        $beforeSources = (int)$pdo->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='legacy_catalog'")->fetchColumn();
        if ($beforeHotels < 1000 || $beforeSources < 1000) throw new RuntimeException('Expected initialized AnyTour catalogue not found');

        $planner = new AnyTourCatalogBackfillV1($pdo);
        $plan = $planner->planNext(ANYTOUR_BACKFILL_LIMIT);
        $ids = $plan['selectedIds'] ?? [];
        $canonicalPlan = $plan['canonical_plan'] ?? null;
        if (!is_array($ids) || count($ids) > ANYTOUR_BACKFILL_LIMIT) throw new RuntimeException('Planner returned invalid cohort');
        if ($ids === []) {
            anytour_backfill_append($journal, ['phase'=>'plan','selected'=>0,'contentReadyMissing'=>(int)($plan['content_ready_missing'] ?? 0),'databaseWrites'=>0]);
            $seed = null;
        } else {
            if (($plan['ready_for_seed_review'] ?? false) !== true || !is_array($canonicalPlan)
                || !preg_match('/^[0-9a-f]{64}$/D', (string)($canonicalPlan['source_sha256'] ?? ''))
                || (int)($canonicalPlan['source_profiles'] ?? 0) !== count($ids)
                || (int)($canonicalPlan['existing_bridges'] ?? -1) !== 0
                || ($canonicalPlan['missingIds'] ?? null) !== []) {
                throw new RuntimeException('Planner cohort is not exact-digest seed ready');
            }
            anytour_backfill_append($journal, [
                'phase'=>'plan','selected'=>count($ids),'selectedIdsSha256'=>hash('sha256', AnyTourCanonicalCatalog::json($ids)),
                'sourceSha256'=>$canonicalPlan['source_sha256'],'contentReadyMissing'=>(int)$plan['content_ready_missing'],
                'writes'=>0,'supplierCalls'=>0,
            ]);
            $seed = $catalog->seed($ids, $canonicalPlan['source_sha256']);
            if (($seed['status'] ?? null) !== 'committed_verified' || (int)($seed['created'] ?? -1) !== count($ids)
                || (int)($seed['verified_bridges'] ?? -1) !== count($ids) || (int)($seed['legacy_writes'] ?? -1) !== 0
                || (int)($seed['supplier_calls'] ?? -1) !== 0 || (int)($seed['canonical_profiles_overwritten'] ?? -1) !== 0) {
                throw new RuntimeException('Committed seed receipt differs from planned additive cohort');
            }
            anytour_backfill_append($journal, [
                'phase'=>'seed_committed_verified','created'=>(int)$seed['created'],'verifiedBridges'=>(int)$seed['verified_bridges'],
                'sourceSha256'=>$seed['source_sha256'],'legacyWrites'=>0,'supplierCalls'=>0,'canonicalProfilesOverwritten'=>0,
            ]);
        }

        $afterHotels = (int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels')->fetchColumn();
        $afterSources = (int)$pdo->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='legacy_catalog'")->fetchColumn();
        $created = is_array($seed) ? (int)$seed['created'] : 0;
        if ($afterHotels !== $beforeHotels + $created || $afterSources !== $beforeSources + $created) {
            throw new RuntimeException('Post-operation counts differ from verified additive seed');
        }
        $next = $planner->planNext(1);
        if ($configHash !== hash_file('sha256', $root . '/config.php')) throw new RuntimeException('Project configuration changed during operation');

        $result = [
            'status' => $ids === [] ? 'backfill_complete_no_missing' : 'backfill_seeded_verified',
            'operation' => ANYTOUR_BACKFILL_OPERATION,
            'dataSource' => ANYTOUR_BACKFILL_SOURCE,
            'executionSource' => $reservation['executionSource'],
            'run' => $reservation['run'],
            'limit' => ANYTOUR_BACKFILL_LIMIT,
            'contentReadyTotalBefore' => (int)($plan['content_ready_total'] ?? 0),
            'contentReadyBridgedBefore' => (int)($plan['content_ready_bridged'] ?? 0),
            'contentReadyMissingBefore' => (int)($plan['content_ready_missing'] ?? 0),
            'selected' => count($ids),
            'selectedIdsSha256' => hash('sha256', AnyTourCanonicalCatalog::json($ids)),
            'sourceSha256' => is_array($canonicalPlan) ? $canonicalPlan['source_sha256'] : null,
            'created' => $created,
            'verifiedBridges' => is_array($seed) ? (int)$seed['verified_bridges'] : 0,
            'anytourHotelsBefore' => $beforeHotels,
            'anytourHotelsAfter' => $afterHotels,
            'legacyBridgesBefore' => $beforeSources,
            'legacyBridgesAfter' => $afterSources,
            'nextMissingExists' => (int)($next['content_ready_missing'] ?? 0) > 0,
            'contentReadyMissingAfter' => (int)($next['content_ready_missing'] ?? 0),
            'legacyWrites' => 0,
            'supplierCalls' => 0,
            'canonicalProfilesOverwritten' => 0,
            'providerMappingsCreated' => 0,
            'publicFileWrites' => 0,
            'projectConfigurationUnchanged' => true,
            'noReplay' => true,
        ];
        $bytes = AnyTourCanonicalCatalog::json($result) . "\n";
        if (fwrite($resultHandle, $bytes) !== strlen($bytes) || !fflush($resultHandle) || !fsync($resultHandle)) {
            throw new RuntimeException('Durable result failed');
        }
        fclose($resultHandle); $resultHandle = null;
        fclose($journal); $journal = null;
        echo 'ANYTOUR_CATALOG_BACKFILL_OK status=' . $result['status'] . ' selected=' . $result['selected'] . ' created=' . $result['created']
            . ' before=' . $beforeHotels . ' after=' . $afterHotels . ' content_ready_missing_after=' . $result['contentReadyMissingAfter']
            . " legacy_writes=0 supplier_calls=0\n";
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        if (is_resource($journal)) {
            try { anytour_backfill_append($journal, ['phase'=>'failed','class'=>get_class($e),'noReplay'=>true]); } catch (Throwable) {}
            fclose($journal);
        }
        if (is_resource($resultHandle)) {
            $error = AnyTourCanonicalCatalog::json(['status'=>'backfill_failed','class'=>get_class($e),'noReplay'=>true]) . "\n";
            fwrite($resultHandle, $error); fflush($resultHandle); fsync($resultHandle); fclose($resultHandle);
        }
        fwrite(STDERR, 'ANYTOUR_CATALOG_BACKFILL_FAILED class=' . get_class($e) . "; inspect retained operation; no replay\n");
        exit(1);
    }
}
