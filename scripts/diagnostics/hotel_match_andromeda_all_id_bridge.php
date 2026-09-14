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

function match_all_interesting_key(string $key): bool {
    if ($key === 'id') return false;
    return (bool)preg_match('/(?:operator|provider|anex|source|external|hotel.*(?:id|inc|code|key)|(?:id|inc|code|key).*hotel|link|url|image|photo|media|mapping|correspond|supplier)/i', $key);
}

function match_all_scan($value, string $path, array &$paths, array &$arraySchemas, int $depth = 0): void {
    if ($depth > 8 || !is_array($value)) return;
    $assoc = array_keys($value) !== range(0, count($value) - 1);
    if (!$assoc) {
        if ($value && is_array($value[0])) {
            $schema = [];
            foreach (array_slice($value, 0, 100) as $row) if (is_array($row)) foreach (array_keys($row) as $k) $schema[(string)$k] = true;
            $keys = array_keys($schema); sort($keys, SORT_STRING);
            $arraySchemas[$path] = ['count'=>count($value),'fields'=>$keys];
        }
        foreach (array_slice($value, 0, 100) as $i => $item) match_all_scan($item, $path.'['.$i.']', $paths, $arraySchemas, $depth + 1);
        return;
    }
    foreach ($value as $key => $item) {
        $key = (string)$key;
        $child = $path === '' ? $key : $path.'.'.$key;
        if (match_all_interesting_key($key)) {
            $sample = is_scalar($item) || $item === null ? $item : (is_array($item) ? '[array count='.count($item).']' : '['.gettype($item).']');
            $paths[$child] = $sample;
        }
        match_all_scan($item, $child, $paths, $arraySchemas, $depth + 1);
    }
}

function match_all_probe(string $dir, string $username, string $password): array {
    $op = getenv('OPERATION_ID') ?: '';
    $result = [
        'operation_id' => $op,
        'state' => 'reserved',
        'probe' => 'raw_all_schema_before_client_projection',
        'params' => ['TOWNFROMINC'=>1,'STATEINC'=>3],
        'supplier_calls_planned' => 2,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'tourvisor_calls' => 0,
        'no_replay' => true,
    ];
    $phase = 'login';
    try {
        $loginTransport = new AnyTourAndromedaTransport();
        $client = new AnyTourAndromedaClient($loginTransport, true);
        $client->login($username, $password);
        $session = $client->privateSession();
        if (!isset($session['sid']) || !is_string($session['sid'])) throw new RuntimeException('ANDROMEDA_LOGIN_REQUIRED');
        $sid = $session['sid'];
        $phase = 'raw_all';
        $url = 'https://gateway.samo.ru/api/?' . http_build_query([
            'version'=>'1.01','action'=>'all','sid'=>$sid,'TOWNFROMINC'=>1,'STATEINC'=>3
        ], '', '&', PHP_QUERY_RFC3986);
        $rawTransport = new AnyTourAndromedaTransport();
        $response = $rawTransport($url);
        if (($response['status'] ?? 0) !== 200 || !is_string($response['body'] ?? null)) throw new RuntimeException('ANDROMEDA_HTTP_ERROR');
        if (strpos($response['body'], $sid) !== false) throw new RuntimeException('ANDROMEDA_SECRET_ECHO');
        $reply = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($reply)) throw new RuntimeException('ANDROMEDA_INVALID_RESPONSE');
        if (array_key_exists('error', $reply)) throw new RuntimeException('ANDROMEDA_SUPPLIER_ERROR');

        $top = array_keys($reply); sort($top, SORT_STRING);
        $paths = []; $schemas = [];
        match_all_scan($reply, '', $paths, $schemas);
        ksort($paths, SORT_STRING); ksort($schemas, SORT_STRING);
        $hotelRows = is_array($reply['HOTELS'] ?? null) ? $reply['HOTELS'] : [];
        $hotelSchema = [];
        foreach (array_slice($hotelRows, 0, 200) as $hotel) if (is_array($hotel)) foreach (array_keys($hotel) as $key) $hotelSchema[(string)$key] = true;
        $hotelSchema = array_keys($hotelSchema); sort($hotelSchema, SORT_STRING);

        $result['state'] = 'completed';
        $result['supplier_calls'] = 2;
        $result['top_level_keys'] = $top;
        $result['hotel_count'] = count($hotelRows);
        $result['hotel_field_schema'] = $hotelSchema;
        $result['interesting_paths'] = $paths;
        $result['array_schemas'] = $schemas;
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $result['state'] = 'source_error';
        $result['phase'] = $phase;
        $result['supplier_calls'] = $phase === 'login' ? 1 : 2;
        $result['error'] = preg_match('/^[A-Z0-9_]{1,100}$/D', $message) ? $message : 'PROBE_FAILED';
    }
    $result['finished_at_utc'] = gmdate('c');
    $digest = match_all_write($dir, 'result', $result);
    match_all_write($dir, 'receipt', [
        'operation_id'=>$op,'state'=>$result['state'],'result_sha256'=>$digest,'readback_verified'=>true,
        'database_writes'=>0,'mapping_writes'=>0,'tourvisor_calls'=>0,'no_replay'=>true,
    ]);
    return $result;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    ini_set('zend.exception_ignore_args', '1');
    ini_set('display_errors', '0');
    if ($argc !== 3 || $argv[1] !== '--execute') { fwrite(STDERR, "PROBE_DISABLED\n"); exit(2); }
    $username = getenv('ANDROMEDA_USERNAME'); $password = getenv('ANDROMEDA_PASSWORD');
    if (!is_string($username) || $username === '' || !is_string($password) || $password === '') { fwrite(STDERR, "CREDENTIALS_REQUIRED\n"); exit(2); }
    $op = getenv('OPERATION_ID');
    if (!is_string($op) || !preg_match('/^hotel-match-andromeda-all-id-bridge-1971-20260914-v[1-9][0-9]*$/D', $op)) { fwrite(STDERR, "OPERATION_ID_REQUIRED\n"); exit(2); }
    if (!is_dir($argv[2]) || is_link($argv[2])) { fwrite(STDERR, "OUTPUT_DIRECTORY_REQUIRED\n"); exit(2); }
    umask(0077);
    try {
        $r = match_all_probe($argv[2], $username, $password);
        echo json_encode(['operation_id'=>$op,'state'=>$r['state'],'hotel_count'=>$r['hotel_count']??null,'top_level_keys'=>$r['top_level_keys']??null,'interesting_path_count'=>count($r['interesting_paths']??[])], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        exit($r['state'] === 'completed' ? 0 : 1);
    } catch (Throwable $e) { fwrite(STDERR, "PROBE_STOPPED_NO_REPLAY\n"); exit(1); }
}
