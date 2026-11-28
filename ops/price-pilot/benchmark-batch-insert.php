<?php
declare(strict_types=1);

function insertPriceObservationBatch(PDO $db, array $columns, array &$statements, array $rows): void
{
    $rowCount = count($rows);
    if (!isset($statements[$rowCount])) {
        $placeholders = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $statements[$rowCount] = $db->prepare(
            'INSERT INTO tour_price_observations (' . implode(',', $columns) . ') VALUES ' .
            implode(',', array_fill(0, $rowCount, $placeholders)) .
            ' ON DUPLICATE KEY UPDATE fingerprint=VALUES(fingerprint)'
        );
    }
    $statements[$rowCount]->execute(array_merge(...$rows));
}

function runPriceBenchmarkScript(string $path, array $argv): void
{
    require $path;
}

function createPriceBenchmarkTable(PDO $db, string $table): void
{
    $db->exec("CREATE TEMPORARY TABLE price_bench_template LIKE $table");
    $db->exec("CREATE TEMPORARY TABLE $table LIKE price_bench_template");
    $db->exec('DROP TEMPORARY TABLE price_bench_template');
    if ((int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn() !== 0) {
        throw new RuntimeException('TEMPORARY_TABLE_NOT_EMPTY');
    }
}

function replayPriceBenchmarkSnapshot(PDO $db, $snapshot, array $columns, int $batchSize): array
{
    $started = hrtime(true);
    rewind($snapshot);
    $statements = [];
    $single = $batchSize === 1
        ? $db->prepare('INSERT INTO tour_price_observations (' . implode(',', $columns) .
            ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) .
            ') ON DUPLICATE KEY UPDATE fingerprint=VALUES(fingerprint)')
        : null;
    $rows = 0;
    $writes = 0;
    $batch = [];
    $db->beginTransaction();
    while (($line = fgets($snapshot)) !== false) {
        $row = json_decode($line, true, 8, JSON_THROW_ON_ERROR);
        if ($single !== null) {
            $single->execute($row);
            ++$writes;
        } else {
            $batch[] = $row;
            if (count($batch) === $batchSize) {
                insertPriceObservationBatch($db, $columns, $statements, $batch);
                ++$writes;
                $batch = [];
            }
        }
        if (++$rows % 20000 === 0) {
            $db->commit();
            $db->beginTransaction();
        }
    }
    if ($batch !== []) {
        insertPriceObservationBatch($db, $columns, $statements, $batch);
        ++$writes;
    }
    $db->commit();
    $seconds = (hrtime(true) - $started) / 1e9;
    $count = (int)$db->query('SELECT COUNT(*) FROM tour_price_observations')->fetchColumn();
    if ($count !== $rows) throw new RuntimeException('REPLAY_ROW_COUNT');
    $check = $db->query('SELECT ' . implode(',', $columns) .
        ' FROM tour_price_observations ORDER BY observed_at,id');
    $hash = hash_init('sha256');
    while (($row = $check->fetch(PDO::FETCH_NUM)) !== false) {
        hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR) . PHP_EOL);
    }
    $check->closeCursor();
    return ['rows'=>$rows, 'writes'=>$writes, 'seconds'=>round($seconds, 3), 'sha256'=>hash_final($hash)];
}

try {
    if (PHP_SAPI !== 'cli') throw new RuntimeException('CLI_ONLY');
    $root = realpath($argv[1] ?? '');
    $day = '2026-11-26';
    if ($root !== '/var/www/fastuser/data/anytour-price-pilot') {
        throw new RuntimeException('BENCHMARK_SCOPE');
    }
    $reference = json_decode(file_get_contents("$root/price-return-ready/$day/manifest.json"), true, 16, JSON_THROW_ON_ERROR);
    $expected = $reference['source']['observations'];
    if ($reference['date'] !== $day || $expected !== 699238 || !isset($reference['coarse']['sha256'])) {
        throw new RuntimeException('REFERENCE_SNAPSHOT');
    }
    putenv('ANYTOUR_DATA_DSN=mysql:host=localhost;dbname=anytour_price_pilot;charset=utf8mb4');
    putenv('ANYTOUR_DATA_DB_USER=price_pilot');
    putenv('ANYTOUR_DATA_DB_PASSWORD=' . trim(file_get_contents("$root/db-password.txt")));
    require_once "$root/db-v1.php";
    $db = v2_data_db();
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'anytour_price_pilot') {
        throw new RuntimeException('DATABASE_SCOPE');
    }
    date_default_timezone_set('UTC');
    $columns = explode(',', 'id,fingerprint,observed_at,source,search_id,departure_id,country_id,region_id,subregion_id,hotel_id,departure_date,departure_year,departure_month,nights,adults,children_count,child_ages_signature,meal_id,room_id,room_type,operator_id,price,fuel_charge,currency');
    $snapshot = tmpfile();
    if ($snapshot === false) throw new RuntimeException('SNAPSHOT_OPEN');
    $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    $select = $db->prepare('SELECT ' . implode(',', $columns) . ' FROM tour_price_observations
        WHERE observed_at >= ? AND observed_at < DATE_ADD(?, INTERVAL 1 DAY) AND id <= ?
        ORDER BY observed_at,id');
    $select->execute([$day, $day, $reference['source']['upper_id']]);
    $sourceHash = hash_init('sha256');
    $snapshotRows = 0;
    while (($row = $select->fetch(PDO::FETCH_NUM)) !== false) {
        $line = json_encode($row, JSON_THROW_ON_ERROR) . PHP_EOL;
        if (fwrite($snapshot, $line) !== strlen($line)) throw new RuntimeException('SNAPSHOT_WRITE');
        hash_update($sourceHash, $line);
        ++$snapshotRows;
    }
    $select->closeCursor();
    $originalHash = hash_final($sourceHash);
    if ($snapshotRows !== $expected) throw new RuntimeException('SNAPSHOT_ROW_COUNT');
    echo "PRICE_BATCH_BENCHMARK_SNAPSHOT day=$day rows=$snapshotRows\n";

    createPriceBenchmarkTable($db, 'tour_price_observations');
    $single = replayPriceBenchmarkSnapshot($db, $snapshot, $columns, 1);
    echo 'PRICE_BATCH_BENCHMARK_SINGLE ' . json_encode($single, JSON_THROW_ON_ERROR) . PHP_EOL;
    $db->exec('DROP TEMPORARY TABLE tour_price_observations');

    createPriceBenchmarkTable($db, 'tour_price_observations');
    $batch = replayPriceBenchmarkSnapshot($db, $snapshot, $columns, 500);
    echo 'PRICE_BATCH_BENCHMARK_BATCH ' . json_encode($batch, JSON_THROW_ON_ERROR) . PHP_EOL;
    fclose($snapshot);
    if ($single['rows'] !== $expected || $batch['rows'] !== $expected
        || $single['sha256'] !== $originalHash || $batch['sha256'] !== $originalHash) {
        throw new RuntimeException('OBSERVATION_PARITY');
    }

    createPriceBenchmarkTable($db, 'tour_price_daily_exact');
    createPriceBenchmarkTable($db, 'tour_price_daily');
    $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    foreach (['rollup-price-daily-exact-v1.php', 'rollup-price-daily-v1.php'] as $script) {
        runPriceBenchmarkScript("$root/$script", [$script, "--date=$day"]);
    }
    // The canonical digest requires a bootstrap path. The connection is already open.
    $bootstrap = tmpfile();
    if ($bootstrap === false) throw new RuntimeException('DIGEST_BOOTSTRAP');
    $bootstrapPath = stream_get_meta_data($bootstrap)['uri'];
    ob_start();
    runPriceBenchmarkScript("$root/rolling-digest.php", ['rolling-digest.php', $bootstrapPath, $day]);
    $actual = json_decode(ob_get_clean(), true, 16, JSON_THROW_ON_ERROR);
    fclose($bootstrap);
    foreach ([[$actual, $reference], [$actual['coarse'], $reference['coarse']]] as [$set, $expectedSet]) {
        foreach (['sha256', 'groups', 'observations'] as $key) {
            if ($set[$key] !== $expectedSet[$key]) throw new RuntimeException('AGGREGATE_PARITY');
        }
    }
    echo 'PRICE_BATCH_BENCHMARK_OK ' . json_encode([
        'rows'=>$snapshotRows, 'single_seconds'=>$single['seconds'], 'batch_seconds'=>$batch['seconds'],
        'exact_groups'=>$actual['groups'], 'coarse_groups'=>$actual['coarse']['groups'],
        'exact_sha256'=>$actual['sha256'], 'coarse_sha256'=>$actual['coarse']['sha256'],
    ], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    $reason = $error instanceof PDOException || $error instanceof Error
        ? get_class($error)
        : $error->getMessage();
    fwrite(STDERR, 'PRICE_BATCH_BENCHMARK_FAILED reason=' . preg_replace('/[^A-Z_0-9]/i', '', $reason) . PHP_EOL);
    exit(5);
}
