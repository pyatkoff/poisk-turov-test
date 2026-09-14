<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/integrations/andromeda-client.php';
require_once __DIR__ . '/../../app/integrations/andromeda-transport.php';

const MATCH_ALL_OPERATION = 'hotel-match-andromeda-all-id-bridge-1971-20260914-v5';
const MATCH_ALL_TARGET = 416247;

function match_all_write(string $dir, string $name, array $value): string {
    $raw = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $fh = @fopen($dir . '/' . $name . '.json', 'x+b');
    if ($fh === false) throw new RuntimeException('RESULT_EXISTS_OR_UNWRITABLE');
    try {
        if (fwrite($fh, $raw) !== strlen($raw) || !fflush($fh)) throw new RuntimeException('RESULT_WRITE_FAILED');
        if (function_exists('fsync') && !fsync($fh)) throw new RuntimeException('RESULT_SYNC_FAILED');
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
    if ($depth > 30) throw new RuntimeException('SCHEMA_DEPTH_LIMIT');
    if (!is_array($value)) return;
    $list = $value === [] || array_keys($value) === range(0, count($value) - 1);
    if ($list) {
        $schema = [];
        foreach ($value as $row) if (is_array($row)) foreach (array_keys($row) as $key) $schema[(string)$key] = true;
        $keys = array_keys($schema); sort($keys, SORT_STRING);
        $arraySchemas[$path] = ['count'=>count($value), 'fields'=>$keys, 'rows_scanned'=>count($value)];
        foreach ($value as $i => $item) match_all_scan($item, $path.'['.$i.']', $paths, $arraySchemas, $depth + 1);
        return;
    }
    foreach ($value as $key => $item) {
        $key = (string)$key;
        $child = $path === '' ? $key : $path.'.'.$key;
        // Preserve the complete key coverage, not arbitrary supplier values/URLs.
        if (match_all_interesting_key($key)) $paths[$child] = gettype($item);
        match_all_scan($item, $child, $paths, $arraySchemas, $depth + 1);
    }
}

function match_all_inspect(array $reply, array $params): array {
    $hotels = is_array($reply['HOTELS'] ?? null) ? $reply['HOTELS'] : [];
    $schema = []; $targets = []; $others = 0;
    $safeFields = ['id','name','lName','star','starKey','starLName','state','stateKey','stateLName','town','townKey','townLName','selected','hotelCode','operatorHotelId','providerHotelId','externalHotelId','isOperatorHotelKey'];
    foreach ($hotels as $hotel) {
        if (!is_array($hotel)) throw new RuntimeException('INVALID_HOTEL_ROW');
        foreach (array_keys($hotel) as $key) $schema[(string)$key] = true;
        if ((string)($hotel['id'] ?? '') === (string)MATCH_ALL_TARGET) {
            $safe = [];
            foreach ($safeFields as $key) if (array_key_exists($key, $hotel) && (is_scalar($hotel[$key]) || $hotel[$key] === null)) $safe[$key] = $hotel[$key];
            $targets[] = $safe;
        } else { ++$others; }
    }
    $paths = []; $arrays = [];
    match_all_scan($reply, '', $paths, $arrays);
    $top = array_keys($reply); sort($top, SORT_STRING);
    $fields = array_keys($schema); sort($fields, SORT_STRING);
    ksort($paths, SORT_STRING); ksort($arrays, SORT_STRING);
    return ['params'=>$params,'hotel_count'=>count($hotels),'hotel_rows_scanned'=>count($hotels),
        'target_hotel_id'=>MATCH_ALL_TARGET,'target_count'=>count($targets),'target_rows'=>$targets,
        'non_target_count'=>$others,'hotel_field_schema'=>$fields,'top_level_keys'=>$top,
        'interesting_paths'=>$paths,'array_schemas'=>$arrays,'sampling_applied'=>false];
}

function match_all_probe(string $dir, string $username, string $password): array {
    $result = ['operation_id'=>MATCH_ALL_OPERATION,'source_sha'=>getenv('GITHUB_SHA') ?: '',
        'state'=>'reserved','probe'=>'raw_all_specific_hotel_and_operator_v5',
        'supplier_calls_max'=>3,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,
        'tourvisor_calls'=>0,'no_replay'=>true,'variants'=>[]];
    $phase = 'login';
    try {
        ++$result['supplier_calls'];
        $client = new AnyTourAndromedaClient(new AnyTourAndromedaTransport(), true);
        $client->login($username, $password);
        $session = $client->privateSession();
        $sid = $session['sid'] ?? null;
        if (!is_string($sid) || $sid === '') throw new RuntimeException('ANDROMEDA_LOGIN_REQUIRED');
        $variants = [
            'hotel_only'=>['TOWNFROMINC'=>1,'STATEINC'=>3,'HOTELS'=>MATCH_ALL_TARGET],
            'hotel_anex'=>['TOWNFROMINC'=>1,'STATEINC'=>3,'HOTELS'=>MATCH_ALL_TARGET,'OPERATORS'=>5],
        ];
        foreach ($variants as $name => $params) {
            $phase = $name;
            usleep(1100000);
            $url = 'https://gateway.samo.ru/api/?' . http_build_query(
                ['version'=>'1.01','action'=>'all','sid'=>$sid] + $params, '', '&', PHP_QUERY_RFC3986);
            ++$result['supplier_calls'];
            $transport = new AnyTourAndromedaTransport();
            $response = $transport($url);
            if (($response['status'] ?? 0) !== 200 || !is_string($response['body'] ?? null)) throw new RuntimeException('ANDROMEDA_HTTP_ERROR');
            if (strpos($response['body'], $sid) !== false || strpos($response['body'], $password) !== false) throw new RuntimeException('ANDROMEDA_SECRET_ECHO');
            $reply = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($reply) || array_key_exists('error', $reply)) throw new RuntimeException('ANDROMEDA_SUPPLIER_ERROR');
            $result['variants'][$name] = match_all_inspect($reply, $params);
            $result['variants'][$name]['response_sha256'] = hash('sha256', $response['body']);
        }
        $result['state'] = 'completed';
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $result['state'] = 'source_error'; $result['phase'] = $phase;
        $result['error'] = preg_match('/^[A-Z0-9_]{1,100}$/D', $message) ? $message : 'PROBE_FAILED';
    }
    $result['finished_at_utc'] = gmdate('c');
    $digest = match_all_write($dir, 'result', $result);
    match_all_write($dir, 'receipt', ['operation_id'=>MATCH_ALL_OPERATION,'source_sha'=>$result['source_sha'],
        'state'=>$result['state'],'result_sha256'=>$digest,'readback_verified'=>true,
        'database_writes'=>0,'mapping_writes'=>0,'tourvisor_calls'=>0,'supplier_calls'=>$result['supplier_calls'],'no_replay'=>true]);
    return $result;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    ini_set('zend.exception_ignore_args', '1'); ini_set('display_errors', '0'); umask(0077);
    if ($argc !== 3 || $argv[1] !== '--execute' || getenv('OPERATION_ID') !== MATCH_ALL_OPERATION || getenv('GITHUB_RUN_ATTEMPT') !== '1') { fwrite(STDERR, "PROBE_DISABLED_OR_REPLAY\n"); exit(2); }
    $username = getenv('ANDROMEDA_USERNAME'); $password = getenv('ANDROMEDA_PASSWORD');
    if (!is_string($username) || $username === '' || !is_string($password) || $password === '') { fwrite(STDERR, "CREDENTIALS_REQUIRED\n"); exit(2); }
    try {
        $dir = $argv[2];
        if (!is_dir($dir) || is_link($dir) || file_exists($dir.'/result.json') || file_exists($dir.'/receipt.json')) throw new RuntimeException('REPLAY_REFUSED');
        $reservation = json_decode((string)file_get_contents($dir.'/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
        if (($reservation['operation_id'] ?? '') !== MATCH_ALL_OPERATION || ($reservation['source_sha'] ?? '') !== getenv('GITHUB_SHA') || ($reservation['state'] ?? '') !== 'reserved_before_supplier_access' || ($reservation['supplier_calls_max'] ?? 0) !== 3) throw new RuntimeException('RESERVATION_REQUIRED');
        $r = match_all_probe($dir, $username, $password);
        echo json_encode(['operation_id'=>MATCH_ALL_OPERATION,'state'=>$r['state'],'supplier_calls'=>$r['supplier_calls']], JSON_THROW_ON_ERROR)."\n";
        exit($r['state'] === 'completed' ? 0 : 1);
    } catch (Throwable $e) { fwrite(STDERR, "PROBE_STOPPED_NO_REPLAY\n"); exit(1); }
}
