<?php
declare(strict_types=1);

$work = __DIR__;
$day = $argv[1] ?? '';
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $day, new DateTimeZone('UTC'));
if (!$date || $date->format('Y-m-d') !== $day) {
    fwrite(STDERR, "PRICE_DAY_ARGUMENT_INVALID\n");
    exit(2);
}
umask(0077);
$lock = fopen("$work/full-day.lock", 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "PRICE_FULL_DAY_ALREADY_RUNNING\n");
    exit(3);
}
$endpoint = [
    '/usr/bin/timeout', '25', '/usr/bin/ssh', '-4', '-n', '-T', '-F', '/dev/null',
    '-i', "$work/source_ed25519", '-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes',
    '-o', 'ConnectTimeout=8', '-o', 'StrictHostKeyChecking=yes',
    '-o', "UserKnownHostsFile=$work/source-known-hosts", '-o', 'GlobalKnownHostsFile=/dev/null',
    '-o', 'HostKeyAlgorithms=ssh-ed25519', 'anytoour@anytoour.ru',
];
$sourcePage = "$work/full-day-$day.page.csv";
$sourceErrors = "$work/full-day-$day.source-errors.txt";
$checkpoint = "$work/full-day-$day.checkpoint.json";
$password = file_get_contents("$work/db-password.txt");
if ($password === false || rtrim($password, "\r\n") === '') {
    fwrite(STDERR, "PRICE_TARGET_DATABASE_PASSWORD_MISSING\n");
    exit(4);
}
putenv('ANYTOUR_DATA_DSN=mysql:host=localhost;dbname=anytour_price_pilot;charset=utf8mb4');
putenv('ANYTOUR_DATA_DB_USER=price_pilot');
putenv('ANYTOUR_DATA_DB_PASSWORD=' . rtrim($password, "\r\n"));
unset($password);
require "$work/db-v1.php";

function callSource(array $endpoint, string $input, string $output, string $errors): void
{
    $io = [0 => ['file','/dev/null','r'], 1 => ['file',$output,'w'], 2 => ['file',$errors,'w']];
    $child = proc_open([...$endpoint, $input], $io, $pipes, __DIR__, ['PATH'=>'/usr/bin:/bin']);
    if (!is_resource($child)) throw new RuntimeException('SOURCE_CLIENT_UNAVAILABLE');
    $code = proc_close($child);
    if ($code !== 0) {
        $reason = trim((string)file_get_contents($errors)) === 'PRICE_EXPORT_BUSY'
            ? 'SOURCE_BUSY'
            : "SOURCE_CLIENT_EXIT_$code";
        throw new RuntimeException($reason);
    }
}

function readSourceState(array $endpoint, string $day, string $output, string $errors): array
{
    callSource($endpoint, "state $day", $output, $errors);
    $state = json_decode(file_get_contents($output), true, 8, JSON_THROW_ON_ERROR);
    if (($state['date'] ?? '') !== $day || !is_int($state['observations'] ?? null)
        || $state['observations'] < 0 || !preg_match('/\A(?:0|[1-9][0-9]{0,17})\z/D', $state['upper_id'] ?? '')
        || (($state['observations'] === 0) !== ($state['upper_id'] === '0'))) {
        throw new RuntimeException('SOURCE_STATE_INVALID');
    }
    return $state;
}

function saveCheckpoint(string $path, array $state): void
{
    $encoded = json_encode($state, JSON_THROW_ON_ERROR);
    if (file_put_contents($path . '.tmp', $encoded . PHP_EOL) !== strlen($encoded) + 1
        || !rename($path . '.tmp', $path)) {
        throw new RuntimeException('CHECKPOINT_SAVE_FAILED');
    }
}

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

function assertAppendOnlyState(array $previous, array $latest): void
{
    if ($previous === $latest) return;
    if ($latest['date'] !== $previous['date'] || $latest['observations'] <= $previous['observations']
        || (int)$latest['upper_id'] <= (int)$previous['upper_id']) {
        throw new RuntimeException('SOURCE_SNAPSHOT_CONFLICT');
    }
}

try {
    $db = v2_data_db();
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'anytour_price_pilot') {
        throw new RuntimeException('TARGET_SCOPE_INVALID');
    }
    $latest = readSourceState($endpoint, $day, $sourcePage, $sourceErrors);
    $current = is_file($checkpoint)
        ? json_decode(file_get_contents($checkpoint), true, 8, JSON_THROW_ON_ERROR)
        : ['source'=>$latest, 'observed_at'=>"$day 00:00:00", 'id'=>0, 'rows'=>0];
    $previous = $current['source'];
    assertAppendOnlyState($previous, $latest);
    if ($current['rows'] === $previous['observations']) {
        $current['source'] = $latest;
    }
    $source = $current['source'];
    // Persist the upper boundary before writing the first page of this snapshot.
    saveCheckpoint($checkpoint, $current);

    echo 'PRICE_FULL_DAY_BEGIN expected=' . $source['observations'] . ' resumed=' . $current['rows'] . PHP_EOL;
    $columns = explode(',', 'id,fingerprint,observed_at,source,search_id,departure_id,country_id,region_id,subregion_id,hotel_id,departure_date,departure_year,departure_month,nights,adults,children_count,child_ages_signature,meal_id,room_id,room_type,operator_id,price,fuel_charge,currency');
    $nullable = ['search_id','region_id','subregion_id','meal_id','room_id','room_type','operator_id','fuel_charge'];
    $statements = [];
    $batchSize = 500;
    $pages = 0;
    while ($current['rows'] < $source['observations']) {
        $time = substr($current['observed_at'], 11);
        $limit = min(20000, $source['observations'] - $current['rows']);
        callSource($endpoint, "page $day $time " . $current['id'] . " " . $source['upper_id'] . " $limit", $sourcePage, $sourceErrors);
        $file = fopen($sourcePage, 'rb');
        if (!$file || fgetcsv($file, 0, ',', '"', '') !== $columns) throw new RuntimeException('SOURCE_HEADER_INVALID');
        $db->beginTransaction();
        $pageRows = 0;
        $batch = [];
        try {
            while (($row = fgetcsv($file, 0, ',', '"', '')) !== false) {
                if (count($row) !== count($columns) || ++$pageRows > $limit || !str_starts_with($row[2], "$day ")
                    || !preg_match('/\A[1-9][0-9]{0,17}\z/D', $row[0]) || !preg_match('/\A[0-9a-f]{64}\z/D', $row[1])) {
                    throw new RuntimeException('SOURCE_ROW_INVALID');
                }
                $id = (int)$row[0];
                $stamp = $row[2];
                if ($id > (int)$source['upper_id'] || $stamp < $current['observed_at']
                    || ($stamp === $current['observed_at'] && $id <= $current['id'])) {
                    throw new RuntimeException('SOURCE_CURSOR_NOT_MONOTONIC');
                }
                foreach ($columns as $index => $name) {
                    if ($row[$index] === '' && in_array($name, $nullable, true)) $row[$index] = null;
                }
                $batch[] = $row;
                if (count($batch) === $batchSize) {
                    insertPriceObservationBatch($db, $columns, $statements, $batch);
                    $batch = [];
                }
                $current['observed_at'] = $stamp;
                $current['id'] = $id;
                ++$current['rows'];
            }
            if ($pageRows !== $limit) throw new RuntimeException('SOURCE_PAGE_INCOMPLETE');
            if ($batch !== []) insertPriceObservationBatch($db, $columns, $statements, $batch);
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            if ($error instanceof RuntimeException && $error->getMessage() === 'SOURCE_PAGE_INCOMPLETE') {
                // A late observation may sort before an earlier cursor. Re-read this
                // frozen snapshot from the beginning on the next run.
                saveCheckpoint($checkpoint, ['source'=>$source, 'observed_at'=>"$day 00:00:00", 'id'=>0, 'rows'=>0]);
            }
            throw $error;
        } finally {
            fclose($file);
        }
        saveCheckpoint($checkpoint, $current);
        if (++$pages % 5 === 0 || $current['rows'] === $source['observations']) {
            echo 'PRICE_FULL_DAY_ROWS=' . $current['rows'] . PHP_EOL;
        }
        usleep(500000);
    }
    assertAppendOnlyState($source, readSourceState($endpoint, $day, $sourcePage, $sourceErrors));
    $count = $db->prepare('SELECT COUNT(*) FROM tour_price_observations
        WHERE observed_at >= ? AND observed_at < DATE_ADD(?, INTERVAL 1 DAY) AND id <= ?');
    $count->execute([$day, $day, $source['upper_id']]);
    if ((int)$count->fetchColumn() !== $source['observations']) throw new RuntimeException('TARGET_DAY_ROW_COUNT_MISMATCH');
    // The date-specific rollups do not have an ID filter. Detect rows beyond
    // the verified watermark before allowing them to read this day.
    $count = $db->prepare('SELECT COUNT(*) FROM tour_price_observations WHERE observed_at >= ? AND observed_at < DATE_ADD(?, INTERVAL 1 DAY)');
    $count->execute([$day, $day]);
    if ((int)$count->fetchColumn() !== $source['observations']) throw new RuntimeException('TARGET_DAY_WATERMARK_MISMATCH');
    echo 'PRICE_FULL_DAY_IMPORT_VERIFIED rows=' . $source['observations'] . PHP_EOL;
    echo 'PRICE_DAY_SOURCE ' . json_encode($source, JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (RuntimeException $error) {
    fwrite(STDERR, 'PRICE_FULL_DAY_FAILED reason=' . preg_replace('/[^A-Z_0-9]/', '', $error->getMessage()) . PHP_EOL);
    exit(5);
} catch (Throwable $error) {
    fwrite(STDERR, 'PRICE_FULL_DAY_FAILED class=' . get_class($error) . PHP_EOL);
    exit(6);
}
