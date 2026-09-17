<?php
declare(strict_types=1);

/** MATCH-only CURRENT Tourvisor invocation census. READ ONLY; never calls Tourvisor. */
const HMTV_OP = 'hotel-match-tourvisor-daily-attempt-current-1971-20260917-v1';

function hmtv_require(bool $ok, string $why): void {
    if (!$ok) {
        throw new RuntimeException($why);
    }
}
function hmtv_json(array $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
}
function hmtv_write(string $path, array $value): string {
    $raw = hmtv_json($value);
    $fh = @fopen($path, 'x+b');
    hmtv_require(is_resource($fh), 'exclusive_output');
    try {
        hmtv_require(fwrite($fh, $raw) === strlen($raw) && fflush($fh), 'output_write');
        if (function_exists('fsync')) {
            hmtv_require(fsync($fh), 'output_sync');
        }
        rewind($fh);
        hmtv_require(stream_get_contents($fh) === $raw, 'output_readback');
    } finally {
        fclose($fh);
    }
    return hash('sha256', $raw);
}
function hmtv_read(string $path, int $maxBytes): array {
    hmtv_require(!is_link($path) && realpath($path) === $path && is_file($path), 'input_path');
    $fh = fopen($path, 'rb');
    hmtv_require(is_resource($fh), 'input_open');
    try {
        $before = fstat($fh);
        hmtv_require(is_array($before) && $before['nlink'] === 1 && $before['size'] <= $maxBytes, 'input_size');
        $raw = stream_get_contents($fh, $maxBytes + 1);
        $after = fstat($fh);
        hmtv_require(is_string($raw) && strlen($raw) === $before['size'], 'input_read');
        hmtv_require($before['ino'] === $after['ino'] && $before['mtime'] === $after['mtime'] && $before['size'] === $after['size'], 'input_changed');
    } finally {
        fclose($fh);
    }
    $value = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    hmtv_require(is_array($value), 'input_shape');
    return $value;
}
function hmtv_query(PDO $db, string $sql, array $args = []): array {
    $stmt = $db->prepare($sql);
    $stmt->execute(array_values($args));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    hmtv_require(count($rows) <= 5000, 'query_row_budget');
    return $rows;
}
function hmtv_total(array $rows): int {
    $n = 0;
    foreach ($rows as $row) {
        $c = (int)($row['attempts'] ?? 0);
        hmtv_require($c >= 0, 'negative_count');
        $n += $c;
    }
    return $n;
}

function hmtv_main(): void {
    hmtv_require(PHP_SAPI === 'cli', 'cli_only');
    hmtv_require(getenv('MATCH_OPERATION_ID') === HMTV_OP, 'operation_guard');
    $sourceSha = (string)getenv('MATCH_SOURCE_SHA');
    hmtv_require(preg_match('/^[a-f0-9]{40}$/D', $sourceSha) === 1, 'source_guard');

    $dir = (string)getenv('HOME') . '/.anytoour-match/operations/' . HMTV_OP;
    $reservation = hmtv_read($dir . '/reservation.json', 32768);
    hmtv_require(($reservation['operation_id'] ?? '') === HMTV_OP, 'reservation_operation');
    hmtv_require(($reservation['source_sha'] ?? '') === $sourceSha, 'reservation_source');
    hmtv_require(($reservation['state'] ?? '') === 'reserved_before_db_access', 'reservation_state');

    $out = [
        'operation_id' => HMTV_OP,
        'source_sha' => $sourceSha,
        'state' => 'failed_no_replay',
        'no_replay' => true,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'supplier_calls' => 0,
        'tourvisor_calls' => 0,
        'booking_calls' => 0,
        'lead_calls' => 0,
        'daily_limit' => 300,
        'safe_to_spend_tourvisor_now' => false,
    ];
    $db = null;
    $phase = 'configuration';
    ob_start();
    try {
        $root = realpath(getcwd());
        hmtv_require(is_string($root) && basename($root) === 'anytoour.ru', 'project_guard');
        $bootstrap = $root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
        hmtv_require(realpath($bootstrap) === $bootstrap && !is_link($bootstrap), 'bootstrap_path');
        require_once $bootstrap;
        $db = v2_data_db();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $phase = 'current_read';
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');

        $table = hmtv_query($db, "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='api_invocation_audit'");
        hmtv_require(count($table) === 1 && strtoupper((string)$table[0]['ENGINE']) === 'INNODB', 'audit_table_contract');
        $columns = hmtv_query($db, "SELECT COLUMN_NAME,DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='api_invocation_audit' ORDER BY ORDINAL_POSITION");
        $columnMap = [];
        foreach ($columns as $column) {
            $columnMap[(string)$column['COLUMN_NAME']] = strtolower((string)$column['DATA_TYPE']);
        }
        foreach (['provider', 'method', 'created_at'] as $required) {
            hmtv_require(isset($columnMap[$required]), 'audit_column_contract');
        }
        hmtv_require(in_array($columnMap['created_at'], ['timestamp', 'datetime'], true), 'created_at_type');

        $clockRows = hmtv_query($db, "SELECT UTC_TIMESTAMP() AS utc_now, NOW() AS db_now, @@session.time_zone AS session_time_zone, @@global.time_zone AS global_time_zone, TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),NOW()) AS db_utc_offset_seconds");
        hmtv_require(count($clockRows) === 1, 'clock_contract');
        $clock = $clockRows[0];
        $offset = (int)$clock['db_utc_offset_seconds'];
        hmtv_require(abs($offset) <= 14 * 3600, 'clock_offset_range');

        // Express UTC/+02/+03 civil-day starts in the DB session timestamp coordinate.
        // created_at is compared to those translated boundaries; the earliest window is the conservative quota count.
        $boundaryRows = hmtv_query($db, "SELECT
            DATE(UTC_TIMESTAMP()) + INTERVAL ? SECOND AS utc_day_start_db,
            DATE(DATE_ADD(UTC_TIMESTAMP(), INTERVAL 2 HOUR)) - INTERVAL 2 HOUR + INTERVAL ? SECOND AS plus02_day_start_db,
            DATE(DATE_ADD(UTC_TIMESTAMP(), INTERVAL 3 HOUR)) - INTERVAL 3 HOUR + INTERVAL ? SECOND AS plus03_day_start_db,
            UTC_TIMESTAMP() - INTERVAL 18 HOUR + INTERVAL ? SECOND AS lookback_start_db", [$offset, $offset, $offset, $offset]);
        hmtv_require(count($boundaryRows) === 1, 'boundary_contract');
        $boundaries = $boundaryRows[0];

        $windows = [];
        foreach (['utc' => 'utc_day_start_db', 'plus02' => 'plus02_day_start_db', 'plus03' => 'plus03_day_start_db'] as $label => $key) {
            $start = (string)$boundaries[$key];
            hmtv_require(preg_match('/^2026-09-1[67] [0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $start) === 1, 'day_boundary_range');
            $rows = hmtv_query($db, "SELECT method,COUNT(*) AS attempts,MIN(created_at) AS first_at,MAX(created_at) AS last_at FROM api_invocation_audit WHERE LOWER(provider)='tourvisor' AND created_at>=? GROUP BY method ORDER BY method", [$start]);
            $windows[$label] = ['start_db' => $start, 'total' => hmtv_total($rows), 'by_method' => $rows];
        }
        $hourly = hmtv_query($db, "SELECT DATE_FORMAT(created_at,'%Y-%m-%d %H:00:00') AS hour_db,method,COUNT(*) AS attempts FROM api_invocation_audit WHERE LOWER(provider)='tourvisor' AND created_at>=? GROUP BY hour_db,method ORDER BY hour_db,method", [(string)$boundaries['lookback_start_db']]);
        $providerVariants = hmtv_query($db, "SELECT provider,COUNT(*) AS attempts FROM api_invocation_audit WHERE LOWER(provider) LIKE '%tourvisor%' AND created_at>=? GROUP BY provider ORDER BY provider", [(string)$boundaries['lookback_start_db']]);

        $conservativeUsed = max($windows['utc']['total'], $windows['plus02']['total'], $windows['plus03']['total']);
        hmtv_require($conservativeUsed >= 0, 'conservative_count');
        $remaining = max(0, 300 - $conservativeUsed);
        $out += [
            'clock' => $clock,
            'created_at_data_type' => $columnMap['created_at'],
            'boundaries' => $boundaries,
            'windows' => $windows,
            'lookback_hourly' => $hourly,
            'provider_variants_lookback' => $providerVariants,
            'conservative_used' => $conservativeUsed,
            'conservative_remaining' => $remaining,
            'safe_to_spend_tourvisor_now' => $conservativeUsed < 300,
            'read_at_utc' => gmdate('c'),
            'transaction' => 'REPEATABLE READ / READ ONLY',
        ];
        $db->exec('ROLLBACK');
        $out['state'] = 'completed_read_only';
    } catch (Throwable $e) {
        if ($db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }
        $out['error_phase'] = $phase;
        $msg = $e->getMessage();
        $out['error_code'] = preg_match('/^[a-z_]{3,100}$/D', $msg) ? $msg : 'sanitized_failure';
    }
    while (ob_get_level()) {
        ob_end_clean();
    }

    $resultSha = hmtv_write($dir . '/result.json', $out);
    hmtv_write($dir . '/receipt.json', [
        'operation_id' => HMTV_OP,
        'source_sha' => $sourceSha,
        'state' => $out['state'],
        'result_sha256' => $resultSha,
        'readback_verified' => true,
        'no_replay' => true,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'supplier_calls' => 0,
        'tourvisor_calls' => 0,
    ]);
    echo hmtv_json([
        'state' => $out['state'],
        'result_sha256' => $resultSha,
        'conservative_used' => $out['conservative_used'] ?? null,
        'conservative_remaining' => $out['conservative_remaining'] ?? null,
    ]);
    if ($out['state'] !== 'completed_read_only') {
        exit(2);
    }
}

if (in_array('--self-test', $argv ?? [], true)) {
    hmtv_require(hmtv_total([['attempts' => '2'], ['attempts' => 3]]) === 5, 'total_test');
    $tmp = tempnam(sys_get_temp_dir(), 'hmtv-');
    hmtv_require(is_string($tmp), 'tmp_test');
    unlink($tmp);
    $sha = hmtv_write($tmp, ['ok' => true]);
    hmtv_require($sha === hash_file('sha256', $tmp), 'write_test');
    unlink($tmp);
    echo "2 Tourvisor audit self-tests PASS\n";
    exit;
}

hmtv_main();
