<?php
/** One-shot next-missing AnyTour canonical profile backfill. No supplier/network calls. */
declare(strict_types=1);
require_once __DIR__ . '/../../v2/data/anytour-catalog-backfill-v1.php';

const ANYTOUR_BACKFILL_OPERATION = 'local-catalog-backfill-onhost-20260917-v5';
const ANYTOUR_BACKFILL_SOURCE = '7e52b07e872479cc853661854ff5d082d5f3a67d';
const ANYTOUR_BACKFILL_LIMIT = 1000;

function anytour_catalog_counts(PDO $pdo): array
{
    return [
        'hotels' => (int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels')->fetchColumn(),
        'sources' => (int)$pdo->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='legacy_catalog'")->fetchColumn(),
    ];
}

function anytour_catalog_backfill_apply(PDO $pdo, int $limit, callable $checkpoint): array
{
    if ($pdo->inTransaction() || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
        || $pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
        throw new RuntimeException('Dedicated MySQL connection required');
    }
    $limit = AnyTourCatalogBackfillV1::limit($limit);
    $catalog = new AnyTourCanonicalCatalog($pdo);
    $catalog->assertSchema();

    if ((int)$pdo->query("SELECT GET_LOCK('anytour-canonical-backfill-2690-v1',0)")->fetchColumn() !== 1) {
        throw new RuntimeException('Another canonical backfill owns the lock');
    }
    try {
        $before = anytour_catalog_counts($pdo);
        if ($before['hotels'] < 1000 || $before['sources'] < 1000 || $before['hotels'] !== $before['sources']) {
            throw new RuntimeException('Reviewed canonical baseline is not present');
        }

        $planner = new AnyTourCatalogBackfillV1($pdo);
        $plan = $planner->planNext($limit);
        $ids = AnyTourCanonicalCatalog::ids($plan['selectedIds'] ?? []);
        if (($plan['status'] ?? '') !== 'backfill_plan_read_only' || ($plan['writes'] ?? -1) !== 0
            || ($plan['supplier_calls'] ?? -1) !== 0 || !$plan['ready_for_seed_review'] || $ids === []) {
            throw new RuntimeException('Non-empty reviewed next-missing cohort required');
        }
        $sourceSha = (string)($plan['canonical_plan']['source_sha256'] ?? '');
        if (!preg_match('/^[0-9a-f]{64}$/D', $sourceSha)
            || (int)($plan['canonical_plan']['source_profiles'] ?? 0) !== count($ids)
            || (int)($plan['canonical_plan']['existing_bridges'] ?? -1) !== 0
            || ($plan['canonical_plan']['missingIds'] ?? null) !== []) {
            throw new RuntimeException('Planner canonical digest is not seed-ready');
        }
        $checkpoint([
            'status' => 'backfill_planned',
            'selected' => count($ids),
            'sourceSha256' => $sourceSha,
            'before' => $before,
            'remainingAfterSelected' => (int)$plan['remaining_after_selected'],
        ]);

        // Re-read the exact bounded source after the planner callback and immediately
        // before seed. seed() then revalidates the same digest again inside READ WRITE.
        $current = $catalog->plan($ids);
        if (($current['status'] ?? '') !== 'prepared_read_only'
            || !hash_equals($sourceSha, (string)($current['source_sha256'] ?? ''))
            || (int)($current['source_profiles'] ?? 0) !== count($ids)
            || (int)($current['existing_bridges'] ?? -1) !== 0
            || ($current['missingIds'] ?? null) !== []) {
            throw new RuntimeException('Reviewed source cohort changed before apply');
        }
        $checkpoint(['status' => 'seed_attempting', 'selected' => count($ids), 'sourceSha256' => $sourceSha]);
        $seed = $catalog->seed($ids, $sourceSha);
        if (($seed['status'] ?? '') !== 'committed_verified'
            || (int)($seed['created'] ?? -1) !== count($ids)
            || (int)($seed['verified_bridges'] ?? -1) !== count($ids)
            || (int)($seed['source_snapshots_refreshed'] ?? -1) !== 0
            || ($seed['missingIds'] ?? null) !== []
            || (int)($seed['canonical_profiles_overwritten'] ?? -1) !== 0
            || (int)($seed['legacy_writes'] ?? -1) !== 0
            || (int)($seed['supplier_calls'] ?? -1) !== 0) {
            throw new RuntimeException('Committed canonical seed result differs');
        }
        $checkpoint(['status' => 'seed_committed_verified', 'seed' => $seed]);

        $links = $catalog->legacyTargets($ids);
        $profiles = $catalog->read(array_values($links));
        if (count($links) !== count($ids) || count($profiles['items'] ?? []) !== count($ids)
            || ($profiles['missingIds'] ?? null) !== []) {
            throw new RuntimeException('Post-COMMIT canonical readback incomplete');
        }
        $withDescription = $withImages = 0;
        foreach ($profiles['items'] as $profile) {
            if (trim((string)($profile['description'] ?? '')) !== '') $withDescription++;
            if (($profile['images'] ?? []) !== []) $withImages++;
        }
        if ($withDescription !== count($ids) || $withImages !== count($ids)) {
            throw new RuntimeException('Post-COMMIT presentation content differs');
        }
        $after = anytour_catalog_counts($pdo);
        if ($after['hotels'] !== $before['hotels'] + count($ids)
            || $after['sources'] !== $before['sources'] + count($ids)) {
            throw new RuntimeException('Canonical count delta differs after COMMIT');
        }

        return [
            'status' => 'backfill_seeded_verified',
            'selected' => count($ids),
            'selectedIds' => $ids,
            'sourceSha256' => $sourceSha,
            'before' => $before,
            'after' => $after,
            'remainingAfterSelected' => (int)$plan['remaining_after_selected'],
            'seed' => $seed,
            'profilesWithDescription' => $withDescription,
            'profilesWithImages' => $withImages,
            'legacyWrites' => 0,
            'supplierCalls' => 0,
            'publicFileWrites' => 0,
            'noReplay' => true,
        ];
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('anytour-canonical-backfill-2690-v1')");
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $journal = null; $phase = 'before_apply';
    try {
        if ($argc !== 1 || basename(dirname(__DIR__, 2)) !== 'payload') {
            throw new RuntimeException('Fixed private invocation required');
        }
        $dir = dirname(__DIR__, 3);
        if (basename($dir) !== ANYTOUR_BACKFILL_OPERATION) throw new RuntimeException('Wrong operation directory');
        $reservation = json_decode((string)file_get_contents($dir . '/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
        if (($reservation['operation'] ?? '') !== ANYTOUR_BACKFILL_OPERATION
            || ($reservation['dataSource'] ?? '') !== ANYTOUR_BACKFILL_SOURCE
            || ($reservation['attempt'] ?? 0) !== 1) {
            throw new RuntimeException('Wrong reservation');
        }
        $journal = fopen($dir . '/journal.jsonl', 'x+b');
        if (!$journal) throw new RuntimeException('Existing journal; no replay');
        $checkpoint = static function (array $state) use (&$journal, &$phase): void {
            $phase = (string)($state['status'] ?? 'unknown');
            $line = AnyTourCanonicalCatalog::json($state + ['noReplay' => true]) . "\n";
            if (fwrite($journal, $line) !== strlen($line) || !fflush($journal) || !fsync($journal)) {
                throw new RuntimeException('Durable checkpoint failed');
            }
        };
        $checkpoint(['status' => 'reserved']);

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
        $result = anytour_catalog_backfill_apply($pdo, ANYTOUR_BACKFILL_LIMIT, $checkpoint);
        if (!hash_equals($configHash, (string)hash_file('sha256', $configFile))) {
            throw new RuntimeException('Project configuration changed');
        }
        $result += [
            'operation' => ANYTOUR_BACKFILL_OPERATION,
            'dataSource' => ANYTOUR_BACKFILL_SOURCE,
            'executionSource' => (string)($reservation['executionSource'] ?? ''),
            'run' => (int)($reservation['run'] ?? 0),
            'projectConfigurationUnchanged' => true,
        ];
        $checkpoint($result);
        $bytes = AnyTourCanonicalCatalog::json($result) . "\n";
        $out = fopen($dir . '/result.json', 'x+b');
        if (!$out || fwrite($out, $bytes) !== strlen($bytes) || !fflush($out) || !fsync($out)) {
            throw new RuntimeException('Final receipt incomplete');
        }
        fclose($out); fclose($journal); $journal = null;
        echo 'ANYTOUR_CATALOG_BACKFILL_VERIFIED result_sha256=' . hash('sha256', $bytes)
            . ' created=' . $result['selected'] . ' supplier_calls=0' . "\n";
    } catch (Throwable $e) {
        if (is_resource($journal)) {
            $line = json_encode(['status'=>'stopped_inspect_no_replay','lastPhase'=>$phase,'class'=>get_class($e)]) . "\n";
            fwrite($journal, $line); fflush($journal); fsync($journal); fclose($journal);
        }
        fwrite(STDERR, 'ANYTOUR_BACKFILL_STOPPED phase=' . $phase . ' class=' . get_class($e)
            . '; COMMIT may be complete, inspect journal/result before any next operation, NO REPLAY' . "\n");
        exit(1);
    }
}
