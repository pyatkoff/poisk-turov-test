<?php
// Included by the guarded local-MySQL panel test, never a standalone/application entrypoint.
if (!isset($db, $service, $dsn) || $dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=anex_review_test;charset=utf8mb4') {
    throw new RuntimeException('test_database_required');
}

function pair_import_protocol(int $id, int $target): string {
    $row = ['anex_hotel_id'=>$id, 'catalog_hotel_id'=>$target, 'match_class'=>'exact',
        'reason'=>'synthetic_pair_exclusion_test', 'source_row_digest'=>str_repeat('a',64)];
    ksort($row);
    $encoded = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    $hash = hash('sha256', $encoded);
    $meta = ['type'=>'meta','protocol_version'=>1,'schema_version'=>1,'scope'=>'preview',
        'approval_policy'=>'owner_exact_and_strong_20260908','mapping_digest'=>$hash,'rows_digest'=>$hash,
        'sources'=>['catalog_sha256'=>str_repeat('b',64),'geo_sha256'=>str_repeat('c',64)],
        'counts'=>['exact'=>1,'strong'=>0,'total'=>1,'unique_catalog_hotels'=>1]];
    return json_encode($meta) . "\n" . json_encode(['type'=>'row','row'=>$row]) . "\n"
        . json_encode(['type'=>'commit','mapping_digest'=>$hash,'rows_digest'=>$hash]) . "\n";
}

function pair_import_start(string $root, int $id, int $target): array {
    $pipes = [];
    $process = proc_open([PHP_BINARY, __DIR__ . '/../scripts/diagnostics/anex_search_mapping_writer.php'],
        [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes, $root);
    if (!is_resource($process)) throw new RuntimeException('import_child_failed');
    fwrite($pipes[0], pair_import_protocol($id, $target));
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    return [$process, $pipes];
}

function pair_import_finish(array $child): array {
    [$process, $pipes] = $child;
    $stdout = ''; $stderr = ''; $deadline = microtime(true) + 10;
    do {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (!$status['running']) break;
        if (microtime(true) > $deadline) { proc_terminate($process); throw new RuntimeException('import_child_timeout'); }
        usleep(10000);
    } while (true);
    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
    check($status['exitcode'] === 0 && $stderr === '', 'actual writer child succeeds without diagnostics leakage');
    return json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
}

$testDirectory = sys_get_temp_dir() . '/anex-pair-import-' . bin2hex(random_bytes(8));
$testRoot = $testDirectory . '/anytoour.ru';
mkdir($testRoot . '/data', 0700, true);
file_put_contents($testRoot . '/data/db-v1.php', <<<'PHP'
<?php
function v2_data_db() {
    $dsn = getenv('ANEX_REVIEW_TEST_DSN');
    if ($dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=anex_review_test;charset=utf8mb4') throw new RuntimeException('test_database_required');
    $db = new PDO($dsn, 'root', getenv('ANEX_REVIEW_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec('SET SESSION innodb_lock_wait_timeout=5');
    return $db;
}
PHP
);
$child = null;
try {
    $preserved = $db->query('SELECT * FROM anex_hotel_decisions ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC);
    $service->decide(request($service,11,'reject_pair',101),'owner:import-test');
    foreach ([1,2] as $attempt) {
        $report = pair_import_finish(pair_import_start($testRoot,11,101));
        check($report['skipped_pair_excluded'] === 1 && $report['inserted'] === 0, 'committed owner rejection survives first and repeated import');
    }
    $report = pair_import_finish(pair_import_start($testRoot,11,102));
    check($report['inserted'] === 1 && $report['skipped_pair_excluded'] === 0, 'only the supplied allowed alternative is imported');
    check(AnyTourAnexSearchMappingRegistry::fromPdo($db)->resolve('anex_online',11,'preview') === 102, 'resolver allows a different candidate for the same hotel');

    // Hold the same observation mutex as the panel, with an uncommitted refusal.
    // The real importer must wait before locking catalog/mapping rows, then read
    // the refusal committed during its wait (not an earlier transaction snapshot).
    $db->beginTransaction();
    $db->query('SELECT anex_hotel_id FROM anex_search_hotel_observations WHERE anex_hotel_id=12 FOR UPDATE')->fetchAll();
    $db->exec("INSERT INTO anex_review_pair_exclusions VALUES (12,101,'owner:concurrent-test',UTC_TIMESTAMP(),REPEAT('d',64))");
    $child = pair_import_start($testRoot,12,101);
    $waiting = false;
    $deadline = microtime(true) + 3;
    do {
        $waiting = (int)$db->query("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID<>CONNECTION_ID()"
            . " AND INFO LIKE 'SELECT anex_hotel_id FROM anex_search_hotel_observations WHERE anex_hotel_id IN (%) ORDER BY anex_hotel_id FOR UPDATE'")->fetchColumn() > 0;
        if ($waiting || !proc_get_status($child[0])['running']) break;
        usleep(10000);
    } while (microtime(true) < $deadline);
    check($waiting, 'actual concurrent importer waits on the same observation mutex');
    $db->commit();
    $report = pair_import_finish($child); $child = null;
    check($report['skipped_pair_excluded'] === 1 && $report['inserted'] === 0, 'rejection committed during lock wait prevents insertion');
    check((int)$db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings WHERE anex_hotel_id=12')->fetchColumn() === 0, 'racing importer leaves the rejected pair absent');
    check($preserved === $db->query('SELECT * FROM anex_hotel_decisions ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC), 'imports preserve every earlier manual decision');
} finally {
    if ($db->inTransaction()) $db->rollBack();
    if ($child !== null && is_resource($child[0])) { proc_terminate($child[0]); proc_close($child[0]); }
    unlink($testRoot . '/data/db-v1.php');
    rmdir($testRoot . '/data'); rmdir($testRoot); rmdir($testDirectory);
}
