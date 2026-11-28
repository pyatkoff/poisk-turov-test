<?php
declare(strict_types=1);
date_default_timezone_set('UTC');
$config = $argv[1] ?? '';
$requested = $argv[2] ?? '';
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $requested);
if (!$date || $date->format('Y-m-d') !== $requested) exit(2);

$expected = null;
if ($argc !== 3) {
    if ($argc !== 7 || $argv[3] !== '--publish-ready' || $date >= new DateTimeImmutable('today')
        || !preg_match('/\A[1-9][0-9]{0,8}\z/D', $argv[4])
        || !preg_match('/\A[1-9][0-9]{0,8}\z/D', $argv[5])
        || !preg_match('/\A[a-f0-9]{64}\z/D', $argv[6])) exit(2);
    $expected = ['groups'=>(int)$argv[4], 'observations'=>(int)$argv[5], 'sha256'=>$argv[6]];
}

function publishDailySnapshot(string $archive, string $date, array $result, string $pointer): array
{
    $snapshot = dirname($archive).'/'.$result['sha256'].'.jsonl.gz';
    if (is_link($snapshot)) throw new RuntimeException('SNAPSHOT_UNREADABLE');
    if (file_exists($snapshot)) {
        if (!is_file($snapshot)
            || !hash_equals($result['sha256'], (string)hash_file('sha256', 'compress.zlib://'.$snapshot))) {
            throw new RuntimeException('SNAPSHOT_DIGEST_MISMATCH');
        }
    } elseif (!link($archive, $snapshot)) {
        throw new RuntimeException('SNAPSHOT_INSTALL_FAILED');
    }
    $bytes = filesize($snapshot);
    $gzipDigest = hash_file('sha256', $snapshot);
    if (!$bytes || $gzipDigest === false) throw new RuntimeException('SNAPSHOT_UNREADABLE');
    $manifest = ['contract'=>1, 'date'=>$date] + $result + ['gzip_sha256'=>$gzipDigest, 'bytes'=>$bytes];
    $payload = json_encode($manifest, JSON_THROW_ON_ERROR)."\n";
    if (file_put_contents($pointer, $payload) !== strlen($payload)
        || !rename($pointer, dirname($snapshot).'/manifest.json')) throw new RuntimeException('MANIFEST_INSTALL_FAILED');
    return $manifest;
}

$lock = $archive = $archivePath = $pointer = null;
try {
    require $config;
    $db = v2_data_db();
    if ($expected !== null) {
        if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'anytour_price_pilot') {
            throw new RuntimeException('TARGET_SCOPE_INVALID');
        }
        umask(0077);
        $root = dirname(realpath($config));
        $lock = fopen($root.'/price-result-return.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('PUBLISH_BUSY');
        $ready = $root.'/price-return-ready';
        $directory = $ready.'/'.$requested;
        if (is_link($ready) || is_link($directory) || (!is_dir($directory) && !mkdir($directory, 0700, true))) {
            throw new RuntimeException('PUBLISH_DIRECTORY_UNAVAILABLE');
        }
        $archivePath = $directory.'/.payload-'.bin2hex(random_bytes(8));
        $pointer = $archivePath.'.manifest';
        $archive = gzopen($archivePath, 'wb4');
        if (!$archive) throw new RuntimeException('SNAPSHOT_OPEN_FAILED');
    }
    $version = (string)$db->query('SELECT VERSION()')->fetchColumn();
    $db->exec(stripos($version, 'mariadb') === false
        ? 'SET SESSION MAX_EXECUTION_TIME=8000' : 'SET SESSION max_statement_time=8');
    $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    $db->exec('START TRANSACTION READ ONLY');
    $query = $db->prepare('SELECT segment_fingerprint,departure_id,country_id,region_id,subregion_id,hotel_id,
        departure_date,nights,adults,children_count,child_ages_signature,meal_id,room_id,room_type,operator_id,currency,
        min_price,median_price,max_price,observation_count,independent_search_count
        FROM tour_price_daily_exact FORCE INDEX (uq_price_daily_exact_segment)
        WHERE price_date=? AND segment_fingerprint>? ORDER BY segment_fingerprint LIMIT 20000');
    $hash = hash_init('sha256');
    $groups = $observations = 0;
    $cursor = '';
    do {
        $query->execute([$requested, $cursor]);
        $pageRows = 0;
        while (($row = $query->fetch(PDO::FETCH_NUM)) !== false) {
            $cursor = (string)$row[0];
            ++$pageRows;
            ++$groups;
            $observations += (int)$row[19];
            $line = json_encode(array_map(static fn($value) => $value === null ? null : (string)$value, $row),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
            hash_update($hash, $line);
            if ($archive && gzwrite($archive, $line) !== strlen($line)) throw new RuntimeException('SNAPSHOT_WRITE_FAILED');
        }
        $query->closeCursor();
    } while ($pageRows === 20000);
    $db->exec('ROLLBACK');
    $result = ['groups'=>$groups, 'observations'=>$observations, 'sha256'=>hash_final($hash)];
    if ($archive) {
        $closed = gzclose($archive);
        $archive = null;
        if (!$closed) throw new RuntimeException('SNAPSHOT_CLOSE_FAILED');
        if ($result !== $expected) throw new RuntimeException('SOURCE_DIGEST_MISMATCH');
        echo 'PRICE_RESULT_DAY_READY '.json_encode(publishDailySnapshot($archivePath, $requested, $result, $pointer), JSON_THROW_ON_ERROR).PHP_EOL;
    } else {
        if ($groups === 0) exit(3);
        echo 'PRICE_DAILY_DIGEST date=' . $requested . ' groups=' . $groups . ' observations=' . $observations . ' sha256=' . $result['sha256'] . PHP_EOL;
    }
} catch (Throwable $error) {
    $driverCode = $error instanceof PDOException ? (int)($error->errorInfo[1] ?? 0) : 0;
    $reason = $error instanceof RuntimeException && !$error instanceof PDOException ? $error->getMessage() : '';
    $failure = $expected === null ? 'PRICE_DAILY_DIGEST_READ_FAILED' : 'PRICE_RESULT_PUBLICATION_FAILED';
    if ($expected !== null && preg_match('/\A[A-Z_]+\z/D', $reason)) $failure .= " reason=$reason";
    fwrite(STDERR, "$failure driver_code=$driverCode\n");
    $exitCode = 4;
} finally {
    if (is_resource($archive)) gzclose($archive);
    if ($archivePath !== null && is_file($archivePath)) unlink($archivePath);
    if ($pointer !== null && is_file($pointer)) unlink($pointer);
    if (is_resource($lock)) fclose($lock);
}
exit($exitCode ?? 0);
