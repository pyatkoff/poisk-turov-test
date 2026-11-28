<?php
declare(strict_types=1);

function runBenchmarkStage(string $script, array $argv): void
{
    require $script;
}

try {
    $root = realpath($argv[1] ?? '');
    $mode = $argv[2] ?? '';
    $day = '2026-11-26';
    if (PHP_SAPI !== 'cli' || $root !== '/var/www/fastuser/data/anytour-price-pilot'
        || !str_starts_with(__DIR__, $root . '/.price-rollup-bench.')
        || !in_array($mode, ['single', 'batch'], true)) {
        throw new RuntimeException('BENCHMARK_SCOPE');
    }
    $reference = json_decode(file_get_contents("$root/price-return-ready/$day/manifest.json"), true, 16, JSON_THROW_ON_ERROR);
    if ($reference['date'] !== $day || $reference['source']['observations'] !== 699238) {
        throw new RuntimeException('REFERENCE_SNAPSHOT');
    }
    date_default_timezone_set('UTC');
    putenv('ANYTOUR_DATA_DSN=mysql:host=localhost;dbname=anytour_price_pilot;charset=utf8mb4');
    putenv('ANYTOUR_DATA_DB_USER=price_pilot');
    putenv('ANYTOUR_DATA_DB_PASSWORD=' . trim(file_get_contents("$root/db-password.txt")));
    require_once __DIR__ . '/db-v1.php';
    $db = v2_data_db();
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'anytour_price_pilot') {
        throw new RuntimeException('DATABASE_SCOPE');
    }

    $source = $db->prepare('SELECT COUNT(*) AS rows_count, MAX(id) AS upper_id
        FROM tour_price_observations WHERE observed_at >= ? AND observed_at < DATE_ADD(?, INTERVAL 1 DAY)');
    $source->execute([$day, $day]);
    $snapshot = $source->fetch();
    $source->closeCursor();
    if ((int)$snapshot['rows_count'] !== $reference['source']['observations']
        || (string)$snapshot['upper_id'] !== $reference['source']['upper_id']) {
        throw new RuntimeException('SOURCE_SNAPSHOT');
    }

    foreach (['tour_price_daily_exact', 'tour_price_daily'] as $table) {
        $db->exec("CREATE TEMPORARY TABLE $table LIKE $table");
        if ((int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn() !== 0) {
            throw new RuntimeException('TEMPORARY_TABLE_NOT_EMPTY');
        }
    }

    $exactScript = $mode === 'single' ? 'rollup-price-daily-exact-v1.php' : 'rollup-price-daily-exact-batch.php';
    $start = hrtime(true);
    runBenchmarkStage(__DIR__ . "/$exactScript", [$exactScript, "--date=$day"]);
    $exactEnd = hrtime(true);
    runBenchmarkStage(__DIR__ . '/rollup-price-daily-v1.php', ['rollup-price-daily-v1.php', "--date=$day"]);
    $coarseEnd = hrtime(true);

    $emptyBootstrap = tmpfile();
    if ($emptyBootstrap === false) throw new RuntimeException('DIGEST_BOOTSTRAP');
    $bootstrapPath = stream_get_meta_data($emptyBootstrap)['uri'];
    $archiveDir = __DIR__ . "/price-return-ready/$day";
    ob_start();
    runBenchmarkStage(__DIR__ . '/rolling-digest.php', ['rolling-digest.php', $bootstrapPath, $day, $archiveDir]);
    $manifest = json_decode(ob_get_clean(), true, 16, JSON_THROW_ON_ERROR);
    fclose($emptyBootstrap);
    $end = hrtime(true);
    foreach ([[$manifest, $reference], [$manifest['coarse'], $reference['coarse']]] as [$actual, $expected]) {
        foreach (['sha256', 'groups', 'observations'] as $key) {
            if ($actual[$key] !== $expected[$key]) throw new RuntimeException('AGGREGATE_PARITY');
        }
        $path = "$archiveDir/" . $actual['sha256'] . '.jsonl.gz';
        if (filesize($path) !== $actual['bytes'] || hash_file('sha256', $path) !== $actual['gzip_sha256']) {
            throw new RuntimeException('ARCHIVE_PARITY');
        }
    }
    echo 'PRICE_ROLLUP_BENCHMARK_OK ' . json_encode([
        'mode'=>$mode, 'rows'=>(int)$snapshot['rows_count'],
        'exact_seconds'=>round(($exactEnd - $start) / 1e9, 3),
        'coarse_seconds'=>round(($coarseEnd - $exactEnd) / 1e9, 3),
        'archive_seconds'=>round(($end - $coarseEnd) / 1e9, 3),
        'total_seconds'=>round(($end - $start) / 1e9, 3),
        'exact_groups'=>$manifest['groups'], 'coarse_groups'=>$manifest['coarse']['groups'],
        'exact_sha256'=>$manifest['sha256'], 'coarse_sha256'=>$manifest['coarse']['sha256'],
        'exact_archive_sha256'=>$manifest['gzip_sha256'], 'coarse_archive_sha256'=>$manifest['coarse']['gzip_sha256'],
    ], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    $reason = $error instanceof PDOException
        ? 'DATABASE_' . $error->getCode()
        : ($error instanceof RuntimeException ? $error->getMessage() : get_class($error));
    fwrite(STDERR, 'PRICE_ROLLUP_BENCHMARK_FAILED reason=' . preg_replace('/[^A-Z_0-9]/i', '', $reason) . PHP_EOL);
    exit(5);
}
