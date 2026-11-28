<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(2);
date_default_timezone_set('UTC');
umask(0077);

$sets = [
    'exact' => [
        'table'=>'tour_price_daily_exact',
        'key'=>['segment_fingerprint'],
        'columns'=>explode(' ', 'segment_fingerprint departure_id country_id region_id subregion_id hotel_id departure_date nights adults children_count child_ages_signature meal_id room_id room_type operator_id currency min_price median_price max_price observation_count independent_search_count'),
        'order'=>'segment_fingerprint',
    ],
    'coarse' => [
        'table'=>'tour_price_daily',
        'key'=>explode(' ', 'departure_id hotel_id departure_year departure_month nights adults children_count child_ages_signature meal_id currency'),
        'columns'=>explode(' ', 'departure_id hotel_id departure_year departure_month nights adults children_count child_ages_signature meal_id currency country_id region_id min_price median_price max_price observation_count'),
    ],
];
$sets['coarse']['order'] = implode(',', $sets['coarse']['columns']);

function snapshotMetadata(array $metadata, int $sourceCount): void
{
    foreach (['sha256', 'gzip_sha256'] as $field) {
        if (!is_string($metadata[$field] ?? null) || !preg_match('/\A[0-9a-f]{64}\z/D', $metadata[$field])) {
            throw new RuntimeException('SNAPSHOT_DIGEST');
        }
    }
    foreach (['bytes', 'groups', 'observations'] as $field) {
        if (!is_int($metadata[$field] ?? null) || $metadata[$field] < 0) {
            throw new RuntimeException('SNAPSHOT_TOTAL');
        }
    }
    if ($metadata['bytes'] < 1 || $metadata['bytes'] > 256 * 1024 * 1024
        || $metadata['groups'] > $metadata['observations'] || $metadata['observations'] > $sourceCount) {
        throw new RuntimeException('SNAPSHOT_LIMIT');
    }
}

function stageResult(PDO $db, array $set, array $metadata, string $archive, string $day, string $created): void
{
    if (!is_file($archive) || is_link($archive) || filesize($archive) !== $metadata['bytes']
        || hash_file('sha256', $archive) !== $metadata['gzip_sha256']) {
        throw new RuntimeException('ARCHIVE_INTEGRITY');
    }
    $stream = gzopen($archive, 'rb');
    if ($stream === false) throw new RuntimeException('ARCHIVE_READ');
    $columns = $set['columns'];
    $rowPattern = '(' . implode(',', array_fill(0, count($columns) + 2, '?')) . ')';
    $insert = 'INSERT INTO ' . $set['stage'] . ' (price_date,calculated_at,' . implode(',', $columns) . ') VALUES ';
    $hash = hash_init('sha256');
    $count = $observations = 0;
    $batch = [];
    $observationColumn = array_search('observation_count', $columns, true);
    try {
        while (($line = gzgets($stream, 8192)) !== false) {
            if (!str_ends_with($line, "\n") || ++$count > $metadata['groups']) throw new RuntimeException('ARCHIVE_ROW_BOUND');
            $values = json_decode($line, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($values) || !array_is_list($values) || count($values) !== count($columns)
                || !is_string($values[$observationColumn])
                || !preg_match('/\A[1-9][0-9]{0,9}\z/D', $values[$observationColumn])) {
                throw new RuntimeException('ARCHIVE_ROW_FORMAT');
            }
            foreach ($values as $value) {
                if ($value !== null && !is_string($value)) throw new RuntimeException('ARCHIVE_ROW_TYPE');
            }
            $observations += (int)$values[$observationColumn];
            if ($observations > $metadata['observations']) throw new RuntimeException('ARCHIVE_OBSERVATIONS');
            hash_update($hash, $line);
            $batch[] = [$day, $created, ...$values];
            if (count($batch) === 256) {
                $db->prepare($insert . implode(',', array_fill(0, count($batch), $rowPattern)))
                    ->execute(array_merge(...$batch));
                $batch = [];
            }
        }
        if (!gzeof($stream) || $count !== $metadata['groups'] || $observations !== $metadata['observations']
            || hash_final($hash) !== $metadata['sha256']) throw new RuntimeException('ARCHIVE_CONTENT');
        if ($batch !== []) {
            $db->prepare($insert . implode(',', array_fill(0, count($batch), $rowPattern)))
                ->execute(array_merge(...$batch));
        }
    } finally {
        gzclose($stream);
    }
}

function sameColumns(array $columns): string
{
    return implode(' AND ', array_map(static fn($name) => "old.$name <=> incoming.$name", $columns));
}

function compareResult(PDO $db, array $set, string $day): array
{
    $table = $set['table'];
    $stage = $set['stage'];
    $identity = sameColumns(['price_date', ...$set['key']]);
    $values = sameColumns($set['columns']);
    $extra = $db->prepare("SELECT COUNT(*) FROM $table old LEFT JOIN $stage incoming ON $identity
        WHERE old.price_date=? AND incoming.id IS NULL");
    $extra->execute([$day]);
    $changed = $db->query("SELECT COUNT(*) FROM $stage incoming
        WHERE NOT EXISTS (SELECT 1 FROM $table old WHERE $identity AND $values)");
    return ['old_only'=>(int)$extra->fetchColumn(), 'changed'=>(int)$changed->fetchColumn()];
}

function publishResult(PDO $db, array $set): void
{
    $table = $set['table'];
    $stage = $set['stage'];
    $identity = sameColumns(['price_date', ...$set['key']]);
    $values = sameColumns($set['columns']);
    $columns = ['price_date', 'calculated_at', ...$set['columns']];
    if ($table === 'tour_price_daily') {
        // MariaDB permits duplicates in the existing key when meal_id is NULL.
        // Replace only the matching null-meal entry when its values changed.
        $db->exec("DELETE old FROM $table old JOIN $stage incoming ON $identity
            WHERE incoming.meal_id IS NULL AND NOT ($values)");
    }
    $names = implode(',', $columns);
    $select = 'incoming.' . implode(',incoming.', $columns);
    $updated = implode(',', array_map(static fn($name) => "$name=VALUES($name)", array_diff(
        ['calculated_at', ...$set['columns']], $set['key']
    )));
    $db->exec("INSERT INTO $table ($names)
        SELECT $select FROM $stage incoming LEFT JOIN $table old ON $identity
        WHERE old.id IS NULL OR NOT ($values) ON DUPLICATE KEY UPDATE $updated");
}

function verifyStoredResult(PDO $db, array $set, string $table, string $day, array $metadata): void
{
    $sql = 'SELECT ' . implode(',', $set['columns']) . " FROM $table WHERE price_date=? ORDER BY " . $set['order'];
    $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    $query = $db->prepare($sql);
    $query->execute([$day]);
    $hash = hash_init('sha256');
    $groups = 0;
    while (($row = $query->fetch(PDO::FETCH_NUM)) !== false) {
        hash_update($hash, json_encode(array_map(
            static fn($value) => $value === null ? null : (string)$value,
            $row
        ), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        ++$groups;
    }
    $query->closeCursor();
    $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    if ($groups !== $metadata['groups'] || hash_final($hash) !== $metadata['sha256']) {
        throw new RuntimeException('STORED_RESULT_DIGEST');
    }
}

try {
    [$script, $config, $manifestPath, $exact, $coarse, $mode] = $argv + array_fill(0, 6, '');
    if (!in_array($mode, ['--rehearse', '--apply'], true) || !is_file($config)
        || !is_file($manifestPath) || filesize($manifestPath) > 16384) throw new RuntimeException('ARGUMENT');
    $manifest = json_decode((string)file_get_contents($manifestPath), true, 16, JSON_THROW_ON_ERROR);
    $day = $manifest['date'] ?? '';
    $parsedDay = DateTimeImmutable::createFromFormat('!Y-m-d', $day);
    if (($manifest['contract'] ?? null) !== 1 || !$parsedDay || $parsedDay->format('Y-m-d') !== $day
        || ($manifest['source']['date'] ?? null) !== $day
        || !is_int($manifest['source']['observations'] ?? null) || $manifest['source']['observations'] < 0
        || !is_string($manifest['source']['upper_id'] ?? null)
        || !preg_match('/\A(?:0|[1-9][0-9]{0,17})\z/D', $manifest['source']['upper_id'])) {
        throw new RuntimeException('MANIFEST');
    }
    $today = new DateTimeImmutable('today');
    if ($mode === '--apply' && ($parsedDay > $today || $parsedDay < $today->modify('-30 days'))) {
        throw new RuntimeException('PUBLICATION_WINDOW');
    }
    require $config;
    $db = v2_data_db();
    $sourceCount = $manifest['source']['observations'];
    $created = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $metadata = ['exact'=>$manifest, 'coarse'=>$manifest['coarse']];
    $archives = ['exact'=>$exact, 'coarse'=>$coarse];
    foreach ($sets as $kind => &$set) {
        snapshotMetadata($metadata[$kind], $sourceCount);
        $set['stage'] = 'price_candidate_' . $kind;
        $db->exec('CREATE TEMPORARY TABLE ' . $set['stage'] . ' LIKE ' . $set['table']);
        stageResult($db, $set, $metadata[$kind], $archives[$kind], $day, $created);
        verifyStoredResult($db, $set, $set['stage'], $day, $metadata[$kind]);
    }
    unset($set);
    $db->beginTransaction();
    $source = $db->prepare('SELECT COUNT(*) FROM tour_price_observations
        WHERE observed_at>=? AND observed_at<? AND id<=?');
    $source->execute([$day, $parsedDay->modify('+1 day')->format('Y-m-d'), $manifest['source']['upper_id']]);
    if ((int)$source->fetchColumn() !== $sourceCount) throw new RuntimeException('SOURCE_WATERMARK');
    $result = [];
    foreach ($sets as $kind => $set) {
        $result[$kind] = compareResult($db, $set, $day);
        if ($result[$kind]['old_only'] !== 0) throw new RuntimeException('WOULD_REMOVE_HISTORY');
        if ($mode === '--apply' && $result[$kind]['changed'] !== 0) publishResult($db, $set);
        if ($mode === '--apply') verifyStoredResult($db, $set, $set['table'], $day, $metadata[$kind]);
    }
    if ($mode === '--apply') {
        $db->commit();
    } else {
        $db->rollBack();
    }
    echo 'PRICE_ROLLING_' . ($mode === '--apply' ? 'APPLIED' : 'REHEARSED') . ' ' .
        json_encode(['date'=>$day, 'source'=>$manifest['source'], 'datasets'=>$result], JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    $reason = $error instanceof PDOException
        ? 'DATABASE_' . (string)($error->errorInfo[1] ?? 'UNKNOWN')
        : $error->getMessage();
    fwrite(STDERR, 'PRICE_ROLLING_APPLY_FAILED ' . preg_replace('/[^A-Z0-9_]/', '', $reason) . "\n");
    exit(4);
}
