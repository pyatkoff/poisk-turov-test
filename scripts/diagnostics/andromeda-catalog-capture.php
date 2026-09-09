<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/integrations/andromeda-client.php';
require_once __DIR__ . '/../../app/integrations/andromeda-transport.php';

function andromeda_capture_save(string $dir, string $name, array $data): string {
    $raw = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    $path = $dir . '/' . $name . '.json';
    $f = @fopen($path, 'x+b');
    if (!$f) throw new RuntimeException('CAPTURE_EXISTS_OR_UNWRITABLE');
    try {
        if (fwrite($f, $raw) !== strlen($raw) || !fflush($f)) throw new RuntimeException('CAPTURE_WRITE_FAILED');
        rewind($f);
        if (stream_get_contents($f) !== $raw) throw new RuntimeException('CAPTURE_READBACK_FAILED');
    } finally { fclose($f); }
    return hash('sha256', $raw);
}
function andromeda_capture_id(array $rows, string $name): int {
    $found = array_values(array_filter($rows, static function ($row) use ($name) {
        return trim($row['name']) === $name;
    }));
    if (count($found) !== 1) throw new RuntimeException('DIRECTION_MISSING_OR_AMBIGUOUS');
    $id = filter_var($found[0]['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) throw new RuntimeException('DIRECTION_ID_INVALID');
    return $id;
}
function andromeda_capture(AnyTourAndromedaClient $client, string $dir, string $username, string $password): array {
    $report = ['state' => 'reserved', 'stage' => 'catalog_moscow_egypt_v1', 'actions' => [], 'digests' => []];
    andromeda_capture_save($dir, 'checkpoint', $report);
    $phase = 'login';
    try {
        $client->login($username, $password);
        $report['actions'][] = 'login';
        $requests = [['townfrom', []], ['state', null], ['all', null]];
        foreach ($requests as [$action, $params]) {
            $phase = $action;
            if ($action === 'state') $params = ['TOWNFROMINC' => $report['departure_id']];
            if ($action === 'all') $params = ['TOWNFROMINC' => $report['departure_id'], 'STATEINC' => $report['country_id']];
            $payload = $client->catalog($action, $params);
            // Fail closed before persistence if the returned text echoes account secrets.
            $raw = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            foreach ([$username, $password, rawurlencode($username), rawurlencode($password)] as $secret) {
                if ($secret !== '' && strpos($raw, $secret) !== false) throw new RuntimeException('CREDENTIAL_ECHO');
            }
            $report['actions'][] = $action;
            $report['digests'][$action] = andromeda_capture_save($dir, $action, ['action' => $action, 'params' => $params, 'payload' => $payload]);
            if ($action === 'townfrom') {
                $report['departures'] = count($payload['TOWNFROM']);
                $report['departure_id'] = andromeda_capture_id($payload['TOWNFROM'], 'Москва');
            } elseif ($action === 'state') {
                $report['countries'] = count($payload['STATE']);
                $report['country_id'] = andromeda_capture_id($payload['STATE'], 'Египет');
            } else {
                foreach (['OPERATORS', 'HOTELS', 'TOWNTO', 'STARS', 'MEAL', 'CURRENCY'] as $key) $report['counts'][$key] = count($payload[$key]);
            }
        }
        $report['state'] = 'completed';
    } catch (Throwable $e) {
        $report['state'] = 'source_error';
        $report['phase'] = $phase;
        $report['error'] = preg_match('/^[A-Z_]{1,80}$/D', $e->getMessage()) ? $e->getMessage() : 'CAPTURE_FAILED';
        $report['retry'] = false;
    }
    $report['finished_at'] = gmdate('c');
    andromeda_capture_save($dir, 'result', $report);
    return $report;
}
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    ini_set('zend.exception_ignore_args', '1');
    ini_set('display_errors', '0');
    if ($argc !== 3 || $argv[1] !== '--execute') { echo "CAPTURE_DISABLED\n"; exit(2); }
    $username = getenv('ANDROMEDA_USERNAME');
    $password = getenv('ANDROMEDA_PASSWORD');
    if (!$username || !$password) { echo "CREDENTIALS_REQUIRED\n"; exit(2); }
    umask(0077);
    if (!is_dir($argv[2]) || is_link($argv[2])) { echo "CAPTURE_DIRECTORY_REQUIRED\n"; exit(2); }
    try {
        $r = andromeda_capture(new AnyTourAndromedaClient(new AnyTourAndromedaTransport(), true), $argv[2], $username, $password);
        echo json_encode($r, JSON_THROW_ON_ERROR) . "\n";
        exit($r['state'] === 'completed' ? 0 : 1);
    } catch (Throwable $ignored) { echo "CAPTURE_STOPPED_NO_REPLAY\n"; exit(1); }
}
