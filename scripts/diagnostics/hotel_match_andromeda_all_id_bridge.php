<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/integrations/andromeda-client.php';
require_once __DIR__ . '/../../app/integrations/andromeda-transport.php';

function match_all_write(string $dir, string $name, array $value): string {
    $raw = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $path = $dir . '/' . $name . '.json';
    $fh = @fopen($path, 'x+b');
    if ($fh === false) throw new RuntimeException('RESULT_EXISTS_OR_UNWRITABLE');
    try {
        if (fwrite($fh, $raw) !== strlen($raw) || !fflush($fh)) throw new RuntimeException('RESULT_WRITE_FAILED');
        rewind($fh);
        if (stream_get_contents($fh) !== $raw) throw new RuntimeException('RESULT_READBACK_FAILED');
    } finally { fclose($fh); }
    return hash('sha256', $raw);
}

function match_all_mapping_keys(array $hotel): array {
    $out = [];
    foreach (array_keys($hotel) as $key) {
        $s = (string)$key;
        if ($s === 'id') continue;
        if (preg_match('/(?:operator|provider|anex|source|external|hotel.*(?:id|inc|code)|(?:id|inc|code).*hotel|link|url|image|photo|media)/i', $s)) $out[] = $s;
    }
    sort($out, SORT_STRING);
    return $out;
}

function match_all_probe(AnyTourAndromedaClient $client, string $dir, string $username, string $password): array {
    $result = [
        'operation_id' => getenv('OPERATION_ID') ?: '',
        'state' => 'reserved',
        'action' => 'all',
        'params' => ['TOWNFROMINC' => 1, 'STATEINC' => 3],
        'supplier_calls_planned' => 2,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'tourvisor_calls' => 0,
        'no_replay' => true,
    ];
    $phase = 'login';
    try {
        $client->login($username, $password);
        $phase = 'all';
        $payload = $client->catalog('all', $result['params']);
        $hotels = $payload['HOTELS'];
        $schema = [];
        $mappingKeys = [];
        $mappingRows = [];
        foreach ($hotels as $hotel) {
            foreach (array_keys($hotel) as $key) $schema[(string)$key] = true;
            $keys = match_all_mapping_keys($hotel);
            foreach ($keys as $key) $mappingKeys[$key] = true;
            if ($keys && count($mappingRows) < 50) {
                $safe = ['id' => $hotel['id'] ?? null, 'name' => $hotel['name'] ?? null, 'town' => $hotel['town'] ?? null, 'state' => $hotel['state'] ?? null];
                foreach ($keys as $key) $safe[$key] = $hotel[$key] ?? null;
                $mappingRows[] = $safe;
            }
        }
        $schema = array_keys($schema); sort($schema, SORT_STRING);
        $mappingKeys = array_keys($mappingKeys); sort($mappingKeys, SORT_STRING);
        $result += [
            'state' => 'completed',
            'supplier_calls' => 2,
            'hotel_count' => count($hotels),
            'hotel_field_schema' => $schema,
            'mapping_like_fields' => $mappingKeys,
            'mapping_like_row_count' => count(array_filter($hotels, static fn(array $h): bool => match_all_mapping_keys($h) !== [])),
            'mapping_like_samples' => $mappingRows,
            'operator_count' => count($payload['OPERATORS'] ?? []),
            'operators' => array_values(array_map(static fn(array $r): array => ['id' => $r['id'] ?? null, 'name' => $r['name'] ?? null], $payload['OPERATORS'] ?? [])),
        ];
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $result += [
            'state' => 'source_error',
            'phase' => $phase,
            'supplier_calls' => $phase === 'login' ? 1 : 2,
            'error' => preg_match('/^[A-Z0-9_]{1,100}$/D', $message) ? $message : 'PROBE_FAILED',
        ];
    }
    $result['finished_at_utc'] = gmdate('c');
    $digest = match_all_write($dir, 'result', $result);
    match_all_write($dir, 'receipt', [
        'operation_id' => $result['operation_id'],
        'state' => $result['state'],
        'result_sha256' => $digest,
        'readback_verified' => true,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'tourvisor_calls' => 0,
        'no_replay' => true,
    ]);
    return $result;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    ini_set('zend.exception_ignore_args', '1');
    ini_set('display_errors', '0');
    if ($argc !== 3 || $argv[1] !== '--execute') { fwrite(STDERR, "PROBE_DISABLED\n"); exit(2); }
    $username = getenv('ANDROMEDA_USERNAME');
    $password = getenv('ANDROMEDA_PASSWORD');
    if (!is_string($username) || $username === '' || !is_string($password) || $password === '') { fwrite(STDERR, "CREDENTIALS_REQUIRED\n"); exit(2); }
    $op = getenv('OPERATION_ID');
    if (!is_string($op) || !preg_match('/^hotel-match-andromeda-all-id-bridge-1971-20260914-v[1-9][0-9]*$/D', $op)) { fwrite(STDERR, "OPERATION_ID_REQUIRED\n"); exit(2); }
    if (!is_dir($argv[2]) || is_link($argv[2])) { fwrite(STDERR, "OUTPUT_DIRECTORY_REQUIRED\n"); exit(2); }
    umask(0077);
    try {
        $result = match_all_probe(new AnyTourAndromedaClient(new AnyTourAndromedaTransport(), true), $argv[2], $username, $password);
        echo json_encode(['operation_id'=>$op,'state'=>$result['state'],'hotel_count'=>$result['hotel_count']??null,'mapping_like_fields'=>$result['mapping_like_fields']??null], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        exit($result['state'] === 'completed' ? 0 : 1);
    } catch (Throwable $e) {
        fwrite(STDERR, "PROBE_STOPPED_NO_REPLAY\n");
        exit(1);
    }
}
