<?php
declare(strict_types=1);
date_default_timezone_set('UTC');
$config = $argv[1] ?? '';
$requested = $argv[2] ?? '';
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $requested);
if (!$date || $date->format('Y-m-d') !== $requested) exit(2);
try {
    require $config;
    $db = v2_data_db();
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
            hash_update($hash, json_encode(array_map(static fn($value) => $value === null ? null : (string)$value, $row),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }
        $query->closeCursor();
    } while ($pageRows === 20000);
    $db->exec('ROLLBACK');
    if ($groups === 0) exit(3);
    echo 'PRICE_DAILY_DIGEST date=' . $requested . ' groups=' . $groups . ' observations=' . $observations . ' sha256=' . hash_final($hash) . PHP_EOL;
} catch (Throwable $error) {
    $driverCode = $error instanceof PDOException ? (int)($error->errorInfo[1] ?? 0) : 0;
    fwrite(STDERR, "PRICE_DAILY_DIGEST_READ_FAILED driver_code=$driverCode\n");
    exit(4);
}
