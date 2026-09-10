<?php
// Actual existing writer on isolated loopback MySQL, never an application database.
$dsn = getenv('ANEX_LINK_TEST_DSN');
if ($dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=anex_link_test;charset=utf8mb4') {
    throw new RuntimeException('isolated_database_required');
}
$db = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$protocol = file_get_contents(getenv('ANEX_LINK_TEST_PROTOCOL'));
if (!is_string($protocol) || strlen($protocol) > 16384) throw new RuntimeException('test_protocol_required');
$checks = 0;
function ensure($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
$directory = sys_get_temp_dir() . '/anex-link-test-' . bin2hex(random_bytes(8));
$root = $directory . '/anytoour.ru';
mkdir($root . '/data', 0700, true);
file_put_contents($root . '/data/db-v1.php', <<<'PHP'
<?php
function v2_data_db() {
    $dsn = getenv('ANEX_LINK_TEST_DSN');
    if ($dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=anex_link_test;charset=utf8mb4') throw new RuntimeException();
    return new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
}
PHP
);
function invoke_writer($root, $protocol, $expectSuccess=true) {
    $pipes = [];
    $process = proc_open([PHP_BINARY, __DIR__ . '/../scripts/diagnostics/anex_search_mapping_writer.php'],
        [['pipe','r'],['pipe','w'],['pipe','w']], $pipes, $root);
    if (!is_resource($process)) throw new RuntimeException('test_child_failed');
    fwrite($pipes[0], $protocol); fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
    ensure($err === '', 'no diagnostics leakage');
    ensure($code === ($expectSuccess ? 0 : 1), 'expected real writer exit');
    return json_decode($out, true, 512, JSON_THROW_ON_ERROR);
}
function invoke_local_cli($root, $receipt) {
    $checkpoint = dirname(getenv('ANEX_LINK_TEST_PROTOCOL')) . '/review.json';
    $pipes = [];
    $process = proc_open(['python3', __DIR__ . '/../scripts/diagnostics/anex_tourvisor_link_import.py',
        '--local-root', $root, '--checkpoint', $checkpoint, '--receipt', $receipt, '--apply'],
        [['pipe','r'],['pipe','w'],['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('local_cli_child_failed');
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
    ensure($code === 0 && $err === '', 'real local CLI succeeds without diagnostics');
    return json_decode($out, true, 512, JSON_THROW_ON_ERROR);
}
function reset_case($db) {
    $db->exec('DELETE FROM anex_hotel_search_mappings WHERE anex_hotel_id<>70000');
    $db->exec('DELETE FROM anex_hotel_decisions WHERE anex_hotel_id<>7777');
    $db->exec('DELETE FROM anex_review_pair_exclusions');
    $db->exec('UPDATE catalog_hotels SET country_id=4,is_active=1');
}
$tables = ['anex_review_pair_exclusions','anex_search_hotel_observations','anex_hotel_decisions',
           'anex_hotel_search_mappings','anex_hotels','catalog_hotels'];
try {
    foreach ($tables as $table) $db->exec('DROP TABLE IF EXISTS ' . $table);
    $db->exec('CREATE TABLE catalog_hotels (id INT PRIMARY KEY,country_id INT,is_active INT) ENGINE=InnoDB');
    $db->exec('INSERT INTO catalog_hotels VALUES (6319,4,1),(1326,4,1),(17568,4,1),(55648,4,1),(999,4,1)');
    $db->exec('CREATE TABLE anex_hotels (id INT PRIMARY KEY) ENGINE=InnoDB');
    $db->beginTransaction();
    $insert = $db->prepare('INSERT INTO anex_hotels VALUES (?)');
    for ($i=1; $i<=8362; $i++) $insert->execute([$i]);
    $db->commit();
    $db->exec('CREATE TABLE anex_hotel_decisions (anex_hotel_id INT PRIMARY KEY,decision_status VARCHAR(32),catalog_hotel_id INT) ENGINE=InnoDB');
    $db->exec("INSERT INTO anex_hotel_decisions VALUES (7777,'accepted',999)");
    $db->exec('CREATE TABLE anex_search_hotel_observations (anex_hotel_id INT PRIMARY KEY) ENGINE=InnoDB');
    $db->exec('INSERT INTO anex_search_hotel_observations VALUES (8121),(16275),(23775),(26688)');
    $db->exec('CREATE TABLE anex_review_pair_exclusions (anex_hotel_id INT,catalog_hotel_id INT,PRIMARY KEY(anex_hotel_id,catalog_hotel_id)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE anex_hotel_search_mappings (anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,match_class VARCHAR(32),scope VARCHAR(16),approval_policy VARCHAR(64),source_row_digest CHAR(64),mapping_digest CHAR(64),enabled INT) ENGINE=InnoDB');
    $db->exec("INSERT INTO anex_hotel_search_mappings VALUES (70000,999,'exact','preview','owner_exact_and_strong_20260908',REPEAT('a',64),REPEAT('b',64),1)");
    $preserved = $db->query('SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id=70000')->fetch(PDO::FETCH_ASSOC);

    $report = invoke_writer($root, $protocol);
    ensure($report['inserted']===4 && $report['updated']===0, 'four real synthetic inserts');
    ensure($report['readback_verified']===true && count($report['link_readback'])===4, 'post-commit readback');
    ensure($report['catalog_country_guard']===4, 'Turkey active-target guard');
    $repeat = invoke_writer($root, $protocol);
    ensure($repeat['inserted']===0 && $repeat['unchanged']===4, 'low-level idempotence');
    ensure($preserved===$db->query('SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id=70000')->fetch(PDO::FETCH_ASSOC), 'prior mapping byte-preserved');

    reset_case($db);
    $db->exec("INSERT INTO anex_hotel_decisions VALUES (8121,'accepted',6319)");
    $db->exec('INSERT INTO anex_review_pair_exclusions VALUES (16275,1326)');
    $report = invoke_writer($root, $protocol);
    ensure($report['inserted']===2 && $report['skipped_manual']===1 && $report['skipped_pair_excluded']===1, 'manual and pair guards retained');
    ensure($report['link_readback'][0]['status']==='skipped_manual' && $report['link_readback'][1]['status']==='skipped_pair_excluded', 'skips not claimed as new mappings');

    reset_case($db);
    $receipt = $directory . '/receipt.json';
    $local = invoke_local_cli($root, $receipt);
    ensure($local['inserted']===4 && $local['readback_verified']===true, 'full local CLI performs four synthetic inserts');
    $saved = file_get_contents($receipt);
    $receiptData = json_decode($saved, true, 512, JSON_THROW_ON_ERROR);
    ensure($receiptData['state']==='finalized' && $receiptData['local_root']===$root, 'durable receipt binds this root');
    $repeatLocal = invoke_local_cli($root, $receipt);
    ensure($repeatLocal['status']==='already_finalized' && $repeatLocal['new_database_writes']===0, 'repeat reads finalized local receipt');
    ensure(file_get_contents($receipt)===$saved, 'repeated invocation leaves receipt byte-identical');
    ensure((int)$db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings')->fetchColumn()===5, 'no duplicate rows after local invocation');
    ensure($preserved===$db->query('SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id=70000')->fetch(PDO::FETCH_ASSOC), 'local CLI preserves existing mapping');

    foreach (['is_active=0','country_id=1'] as $change) {
        reset_case($db);
        $db->exec('UPDATE catalog_hotels SET ' . $change . ' WHERE id=6319');
        invoke_writer($root, $protocol, false);
        ensure((int)$db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings')->fetchColumn()===1, 'invalid target causes zero inserts');
    }
    reset_case($db);
    $db->exec("INSERT INTO anex_hotel_search_mappings VALUES (23775,999,'strong_candidate','preview','owner_exact_and_strong_20260908',REPEAT('a',64),REPEAT('b',64),1)");
    invoke_writer($root, $protocol, false);
    ensure((int)$db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings')->fetchColumn()===2, 'conflict rolls back earlier inserts');
    ensure((int)$db->query('SELECT catalog_hotel_id FROM anex_hotel_search_mappings WHERE anex_hotel_id=23775')->fetchColumn()===999, 'existing conflict never overwritten');

    reset_case($db);
    $db->exec('DROP TABLE anex_review_pair_exclusions');
    invoke_writer($root, $protocol, false);
    ensure((int)$db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings')->fetchColumn()===1, 'missing review schema fails closed');
    ensure((int)$db->query('SELECT COUNT(*) FROM anex_hotel_decisions')->fetchColumn()===1, 'original manual decision retained');
    echo json_encode(['status'=>'passed','checks'=>$checks,'application_database_writes'=>0,'supplier_calls'=>0]), "\n";
} finally {
    if ($db->inTransaction()) $db->rollBack();
    foreach ($tables as $table) $db->exec('DROP TABLE IF EXISTS ' . $table);
    if (is_file($directory . '/receipt.json')) unlink($directory . '/receipt.json');
    unlink($root . '/data/db-v1.php'); rmdir($root . '/data'); rmdir($root); rmdir($directory);
}
