<?php
declare(strict_types=1);

// Only a private pacing call is exercised: no request(), cURL or supplier access.
if (($argv[1] ?? '') === '--rate-lock-child') {
    require_once __DIR__ . '/../app/integrations/anex-package-quote-client.php';
    $client = new AnyTourAnexPackageQuoteClient('RATE_LOCK_FIXTURE');
    if (($argv[2] ?? 'none') !== 'none') {
        (new ReflectionProperty($client, 'deadlineNs'))->setValue($client,
            hrtime(true) + (int) $argv[2] * 1000000);
    }
    echo "READY\n"; flush();
    $started = hrtime(true);
    try {
        (new ReflectionMethod($client, 'rateSlot'))->invoke($client, ($argv[3] ?? '0') === '1');
        $outcome = 'reserved';
    } catch (RuntimeException $error) { $outcome = $error->getMessage(); }
    echo json_encode(['outcome' => $outcome, 'elapsed_ms' => (hrtime(true) - $started) / 1000000,
        'observed_at' => microtime(true)], JSON_THROW_ON_ERROR) . "\n";
    exit(0);
}

function qrl_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('ANEX quote lock regression: ' . $message);
}

/** Parent owns a real OS lock until releaseMs, or until after the child exits. */
function qrl_case(?int $releaseMs, ?int $sharedMs, array $state, bool $throttled = false): array
{
    $dir = sys_get_temp_dir() . '/anex-quote-lock-' . bin2hex(random_bytes(8));
    qrl_assert(mkdir($dir, 0700), 'isolated directory');
    $path = $dir . '/anytour-anex-b2b-rate-' . hash('sha256', 'RATE_LOCK_FIXTURE') . '.json';
    $initial = json_encode($state, JSON_THROW_ON_ERROR);
    $file = fopen($path, 'w+');
    qrl_assert(is_resource($file), 'fixture file');
    fwrite($file, $initial); fflush($file);
    qrl_assert(flock($file, LOCK_EX | LOCK_NB), 'parent acquires fixture lock');
    $process = null; $pipes = []; $stdout = ''; $stderr = ''; $held = true;
    try {
        $process = proc_open([PHP_BINARY, '-n', '-d', 'sys_temp_dir=' . $dir, __FILE__,
            '--rate-lock-child', $sharedMs === null ? 'none' : (string) $sharedMs, $throttled ? '1' : '0'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        qrl_assert(is_resource($process), 'child starts');
        stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
        $watchdog = hrtime(true) + 6000000000; $readyAt = null; $exitCode = null;
        while (true) {
            $stdout .= stream_get_contents($pipes[1]); $stderr .= stream_get_contents($pipes[2]);
            if ($readyAt === null && str_starts_with($stdout, "READY\n")) $readyAt = hrtime(true);
            if ($held && $releaseMs !== null && $readyAt !== null
                && hrtime(true) - $readyAt >= $releaseMs * 1000000) {
                flock($file, LOCK_UN); $held = false;
            }
            $status = proc_get_status($process);
            if (!$status['running']) { $exitCode = $status['exitcode']; break; }
            qrl_assert(hrtime(true) < $watchdog, 'watchdog: child remained blocked on parent lock');
            usleep(10000);
        }
        $stdout .= stream_get_contents($pipes[1]); $stderr .= stream_get_contents($pipes[2]);
        qrl_assert($exitCode === 0 && $stderr === '', 'clean child exit');
        qrl_assert($readyAt !== null, 'child reached private pacing method');
        $facts = json_decode(substr($stdout, strlen("READY\n")), true, 16, JSON_THROW_ON_ERROR);
        // Read before finally releases a deliberately held lock.
        $facts['parent_lock_still_held'] = $held;
        $facts['initial'] = $initial; $facts['raw'] = file_get_contents($path);
        $facts['state'] = json_decode($facts['raw'], true, 16, JSON_THROW_ON_ERROR);
        return $facts;
    } finally {
        if (is_resource($process)) {
            $status = proc_get_status($process);
            if ($status['running']) proc_terminate($process, 9);
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            proc_close($process);
        }
        flock($file, LOCK_UN); fclose($file); unlink($path); rmdir($dir);
    }
}

$qrlState = ['next_at' => 0.0, 'blocked_until' => 0.0];
$qrlHeld = qrl_case(null, null, $qrlState);
qrl_assert($qrlHeld['outcome'] === 'ANEX_QUOTE_RATE_LIMIT' && $qrlHeld['parent_lock_still_held'],
    '3s denial occurs while parent still holds the lock');
qrl_assert($qrlHeld['elapsed_ms'] >= 2800 && $qrlHeld['elapsed_ms'] < 5500
    && $qrlHeld['raw'] === $qrlHeld['initial'], 'bounded contention does not change the shared state');

$qrlShort = qrl_case(null, 150, $qrlState);
qrl_assert($qrlShort['outcome'] === 'ANEX_QUOTE_RATE_LIMIT' && $qrlShort['parent_lock_still_held']
    && $qrlShort['elapsed_ms'] < 2000 && $qrlShort['raw'] === $qrlShort['initial'],
    'remaining shared request budget bounds lock contention');

$qrlReleased = qrl_case(200, null, $qrlState);
qrl_assert($qrlReleased['outcome'] === 'reserved' && !$qrlReleased['parent_lock_still_held']
    && $qrlReleased['state']['next_at'] > $qrlReleased['observed_at'] + 0.7
    && $qrlReleased['state']['next_at'] <= $qrlReleased['observed_at'] + 1.1
    && (float) $qrlReleased['state']['blocked_until'] === 0.0,
    'release within budget reserves the existing 1.05s slot');

$qrlExpired = qrl_case(null, -1, $qrlState);
qrl_assert($qrlExpired['outcome'] === 'ANEX_QUOTE_RATE_LIMIT' && $qrlExpired['elapsed_ms'] < 1000
    && $qrlExpired['raw'] === $qrlExpired['initial'], 'expired request does not wait or rewrite state');

$qrlPacing = qrl_case(0, 150, ['next_at' => microtime(true) + 30, 'blocked_until' => 0.0]);
qrl_assert($qrlPacing['outcome'] === 'ANEX_QUOTE_RATE_LIMIT' && $qrlPacing['elapsed_ms'] < 750
    && $qrlPacing['raw'] === $qrlPacing['initial'], 'shared budget also bounds spacing sleep');

$qrlThrottle = qrl_case(0, -1, $qrlState, true);
qrl_assert($qrlThrottle['outcome'] === 'reserved'
    && $qrlThrottle['state']['blocked_until'] > $qrlThrottle['observed_at'] + 59
    && $qrlThrottle['state']['blocked_until'] <= $qrlThrottle['observed_at'] + 60.1
    && (float) $qrlThrottle['state']['next_at'] === 0.0,
    'already received 429 still persists its 60s cooldown after request expiry');

$qrlBlocked = qrl_case(0, null, ['next_at' => 0.0, 'blocked_until' => microtime(true) + 30]);
qrl_assert($qrlBlocked['outcome'] === 'ANEX_QUOTE_RATE_LIMIT' && $qrlBlocked['elapsed_ms'] < 2000
    && $qrlBlocked['raw'] === $qrlBlocked['initial'], 'active cooldown stays fail closed and unchanged');

echo "ANEX quote rate lock: 7 isolated process cases (private pacing only; supplier HTTP0)\n";
