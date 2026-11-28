<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
umask(0077);
date_default_timezone_set('UTC');

$config = $argv[1] ?? '';
$day = $argv[2] ?? '';
$directory = $argv[3] ?? null;
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $day);
if (!$date || $date->format('Y-m-d') !== $day || !is_file($config)) exit(2);

$exact = [
    'segment_fingerprint', 'departure_id', 'country_id', 'region_id', 'subregion_id',
    'hotel_id', 'departure_date', 'nights', 'adults', 'children_count', 'child_ages_signature',
    'meal_id', 'room_id', 'room_type', 'operator_id', 'currency', 'min_price', 'median_price',
    'max_price', 'observation_count', 'independent_search_count',
];
$coarse = [
    'departure_id', 'hotel_id', 'departure_year', 'departure_month', 'nights',
    'adults', 'children_count', 'child_ages_signature', 'meal_id', 'currency',
    'country_id', 'region_id', 'min_price', 'median_price', 'max_price', 'observation_count',
];

function priceDigest(
    PDO $db,
    string $table,
    array $columns,
    string $order,
    string $day,
    ?string $directory
): array {
    $sql = 'SELECT ' . implode(',', $columns) . " FROM $table WHERE price_date=? ORDER BY $order";
    $query = $db->prepare($sql);
    $query->execute([$day]);
    $temp = null;
    $output = null;
    try {
        if ($directory !== null) {
            $temp = tempnam($directory, '.payload-');
            if ($temp === false || dirname($temp) !== $directory) throw new RuntimeException('ARCHIVE_TEMP');
            $output = gzopen($temp, 'wb4');
            if ($output === false) throw new RuntimeException('ARCHIVE_OPEN');
        }
        $hash = hash_init('sha256');
        $count = $observations = 0;
        $observationIndex = array_search('observation_count', $columns, true);
        while (($row = $query->fetch(PDO::FETCH_NUM)) !== false) {
            $line = json_encode(array_map(
                static fn($value) => $value === null ? null : (string)$value,
                $row
            ), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            hash_update($hash, $line);
            if ($output !== null && gzwrite($output, $line) !== strlen($line)) {
                throw new RuntimeException('ARCHIVE_WRITE');
            }
            ++$count;
            $observations += (int)$row[$observationIndex];
        }
        $digest = ['groups'=>$count, 'observations'=>$observations, 'sha256'=>hash_final($hash)];
        if ($output !== null) {
            if (!gzclose($output)) throw new RuntimeException('ARCHIVE_CLOSE');
            $output = null;
            $size = filesize($temp);
            $compressedHash = hash_file('sha256', $temp);
            if ($size === false || $compressedHash === false) throw new RuntimeException('ARCHIVE_STAT');
            $path = $directory . '/' . $digest['sha256'] . '.jsonl.gz';
            // The exact and coarse empty digests share the same byte stream.
            // Reuse the same encoded archive so any active manifest stays valid.
            if (is_file($path)) {
                if (is_link($path) || hash_file('sha256', 'compress.zlib://' . $path) !== $digest['sha256']) {
                    throw new RuntimeException('ARCHIVE_CONTENT_CONFLICT');
                }
                $size = filesize($path);
                $compressedHash = hash_file('sha256', $path);
                if ($size === false || $compressedHash === false) throw new RuntimeException('ARCHIVE_STAT');
            } elseif (!rename($temp, $path)) {
                throw new RuntimeException('ARCHIVE_PUBLISH');
            }
            $digest += ['gzip_sha256'=>$compressedHash, 'bytes'=>$size];
        }
        return $digest;
    } finally {
        $query->closeCursor();
        if (is_resource($output)) gzclose($output);
        if (is_string($temp) && is_file($temp)) unlink($temp);
    }
}

try {
    require $config;
    $db = v2_data_db();
    if ($directory !== null) {
        $root = __DIR__ . '/price-return-ready';
        if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'anytour_price_pilot'
            || $directory !== "$root/$day" || is_link($root) || is_link($directory)) {
            throw new RuntimeException('PUBLISH_SCOPE');
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
            throw new RuntimeException('PUBLISH_DIRECTORY');
        }
    }
    $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    $db->exec('START TRANSACTION READ ONLY');
    $manifest = ['contract'=>1, 'date'=>$day] + priceDigest(
        $db, 'tour_price_daily_exact', $exact, 'segment_fingerprint', $day, $directory
    );
    $manifest['coarse'] = priceDigest(
        $db, 'tour_price_daily', $coarse, implode(',', $coarse), $day, $directory
    );
    $db->exec('ROLLBACK');
    echo json_encode($manifest, JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    $reason = $error instanceof PDOException ? 'DATABASE' : $error->getMessage();
    fwrite(STDERR, 'PRICE_ROLLING_DIGEST_FAILED ' . preg_replace('/[^A-Z0-9_]/', '', $reason) . PHP_EOL);
    exit(5);
}
