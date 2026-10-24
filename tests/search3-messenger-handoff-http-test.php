<?php
declare(strict_types=1);
define('ANYTOUR_HANDOFF_FIXTURE_LIBRARY_ONLY', true);
require_once __DIR__ . '/search3-messenger-handoff-test.php';

$root = sys_get_temp_dir() . '/search3-handoff-http-' . bin2hex(random_bytes(8));
mkdir($root, 0700); mkdir($root . '/sessions', 0700);
$router = $root . '/router.php';
$fixture = var_export(__DIR__ . '/search3-messenger-handoff-test.php', true);
$routerSource = <<<'PHP'
<?php
define('ANYTOUR_ANEX_PREVIEW_ENABLED', true);
define('ANYTOUR_HANDOFF_FIXTURE_LIBRARY_ONLY', true);
require __FIXTURE_PATH__;
if ($_SERVER['REQUEST_URI'] === '/fixture-seed') {
    session_name('ANYTOUR_ANEX_SEARCH3'); session_start();
    [$session, $request] = mh_fixture([], true, time() - 3); $_SESSION = $session;
    $sid = session_id(); session_write_close();
    echo json_encode(['sid' => $sid, 'request' => $request]); return;
}
if ($_SERVER['REQUEST_URI'] === '/fixture-checkpoint') {
    session_name('ANYTOUR_ANEX_SEARCH3'); session_start();
    $operation = array_key_first($_SESSION['search3_messenger_handoffs']);
    $record = $_SESSION['search3_messenger_handoffs'][$operation];
    $kp = sodium_crypto_sign_keypair(); $now = time();
    $key = ['key_id' => 'ephemeral-http', 'secret_key' => sodium_crypto_sign_secretkey($kp), 'not_before' => $now - 1, 'not_after' => $now + 100];
    $reply = AnyTourSearch3MessengerHandoff::transact(['action' => 'handoff_create', 'operation_id' => $operation, 'csrf' => $_SESSION['search3_handoff_csrf']],
        $_SESSION, $now, $key, [AnyTourSearch3MessengerHandoff::class, 'checkpoint'],
        static function () use ($record): array {
            // A second process can read the actual durable UNKNOWN reservation even during transport.
            $disk = file_get_contents(session_save_path() . '/sess_' . session_id());
            if (!str_contains($disk, 'unknown')) throw new RuntimeException('not_checkpointed');
            return ['status' => 200, 'body' => json_encode(['ok' => true, 'state' => 'ready', 'request_hash' => $record['request_hash'],
                'links' => ['telegram' => 'https://t.me/fixture?start=s3_a', 'max' => 'https://max.ru/fixture?start=s3_b']])];
        });
    session_write_close(); session_start(['read_and_close' => true]);
    echo json_encode(['reply' => $reply, 'state' => $_SESSION['search3_messenger_handoffs'][$operation]['state']]); return;
}
anytour_anex_search3_http();
PHP;
file_put_contents($router, str_replace('__FIXTURE_PATH__', $fixture, $routerSource));
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$socket) throw new RuntimeException('loopback_socket');
$address = stream_socket_get_name($socket, false); fclose($socket);
$log = $root . '/server.log';
$process = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $root . '/sessions', '-S', $address, '-t', $root, $router],
    [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes);
if (!is_resource($process)) throw new RuntimeException('loopback_server');

function mh_http(string $path, ?array $payload = null, ?string $sid = null, array $overrides = []): array
{
    global $address;
    $headers = ['Host: anytoour.ru', 'Origin: https://anytoour.ru', 'Sec-Fetch-Site: same-origin'];
    if ($sid !== null) $headers[] = 'Cookie: ANYTOUR_ANEX_SEARCH3=' . $sid;
    if ($payload !== null) $headers[] = 'Content-Type: application/json';
    foreach ($overrides as $name => $value) {
        $headers = array_values(array_filter($headers, static fn(string $h): bool => !str_starts_with(strtolower($h), strtolower($name) . ':')));
        if ($value !== null) $headers[] = $name . ': ' . $value;
    }
    $ctx = stream_context_create(['http' => ['method' => $payload === null ? 'GET' : 'POST', 'header' => implode("\r\n", $headers),
        'content' => $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR), 'ignore_errors' => true, 'timeout' => 5]]);
    $body = file_get_contents('http://' . $address . $path, false, $ctx);
    preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $m);
    return ['status' => (int)($m[1] ?? 0), 'body' => json_decode((string)$body, true), 'headers' => $http_response_header ?? []];
}
try {
    for ($i = 0; $i < 100; ++$i) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($ready) { fclose($ready); break; } usleep(20000);
    }
    $seed = mh_http('/fixture-seed');
    mh_check($seed['status'] === 200 && is_array($seed['body']), 'seed synthetic session');
    $sid = $seed['body']['sid']; $request = $seed['body']['request'];
    $path = '/_preview/search3-anex-candidate/api-anex-search3-preview.php';
    $token = mh_http($path . '?action=handoff_token', null, $sid);
    mh_check($token['status'] === 200 && $token['body']['csrf'] === $request['csrf'], 'same session token endpoint');
    $denied = mh_http($path, $request, $sid, ['Origin' => 'https://evil.test']);
    mh_check($denied['status'] === 403, 'cross origin blocked at real HTTP boundary');
    $denied = mh_http($path, $request, $sid, ['Sec-Fetch-Site' => null]);
    mh_check($denied['status'] === 403, 'missing fetch site blocked');
    $denied = mh_http($path, array_replace($request, ['csrf' => 'wrong']), $sid);
    mh_check($denied['status'] === 403, 'wrong csrf blocked');
    $denied = mh_http($path, $request);
    mh_check($denied['status'] === 403, 'no source session cannot freeze');
    $preview = mh_http($path, $request, $sid);
    mh_check($preview['status'] === 200 && ($preview['body']['data']['state'] ?? null) === 'prepared', 'real endpoint freezes before any DB or supplier dependency ' . json_encode($preview['body']));
    mh_check(str_contains(implode("\n", $preview['headers']), 'no-store') && str_contains(implode("\n", $preview['headers']), 'no-referrer'), 'private HTTP response');
    $operation = $preview['body']['data']['operation_id'];
    $same = mh_http($path, $request, $sid);
    mh_check($same['body']['data']['operation_id'] === $operation, 'second HTTP request retains original operation');
    $tx = ['action' => 'handoff_create', 'operation_id' => $operation, 'csrf' => $request['csrf']];
    $missingSigner = mh_http($path, $tx, $sid);
    mh_check($missingSigner['status'] === 503 && $missingSigner['body']['error'] === 'signer_unavailable', 'no key closes actual HTTP create with zero outbound request');
    $status = mh_http($path, array_replace($tx, ['action' => 'handoff_status']), $sid);
    mh_check($status['body']['data']['state'] === 'prepared', 'missing key did not consume the frozen intent');
    $persisted = mh_http('/fixture-checkpoint', null, $sid);
    mh_check($persisted['body']['reply']['state'] === 'ready' && $persisted['body']['state'] === 'ready', 'real PHP checkpoint persists reservation and receipt across reopen');
    $bad = mh_http($path, array_replace($request, ['generation' => 8]), $sid);
    mh_check($bad['status'] === 422 && $bad['body']['field'] === 'search.current_context', 'stale version gives exact missing field');
    $bad = mh_http($path, $request + ['observation' => ['price' => 1]], $sid);
    mh_check($bad['status'] === 400, 'browser observation cannot be signed');
    $tvRequest=array_replace($request,['provider'=>'tourvisor','offer_ref'=>'tourvisor:'.str_repeat('b',64),
        'choice_ref'=>'manager-deferred']);
    $tvHeld=mh_http($path,$tvRequest,$sid);
    mh_check($tvHeld['status']===422 && ($tvHeld['body']['field']??null)==='record.current_retained',
        'actual HTTP cannot mint a TV record from a browser selector');
    $tvInjected=mh_http($path,$tvRequest+['observation'=>['price'=>1]],$sid);
    mh_check($tvInjected['status']===400,'TV browser observation cannot become server capture');
    $tvCapture=mh_http($path,array_replace($tvRequest,['action'=>'handoff_capture']),$sid);
    mh_check($tvCapture['status']===400,'no public HTTP capture action exists');
    $quoteReceipt = mh_http($path . '?action=quote_receipt', null, $sid);
    mh_check($quoteReceipt['status'] === 200, 'existing readonly diagnostic endpoint remains available');
    echo 'Search3 producer HTTP: ' . ($GLOBALS['mh_checks'] ?? 0) . " checks PASS; loopback synthetic only, real provider/bot/SQL=0.\n";
} catch (Throwable $failure) {
    fwrite(STDERR, (string)file_get_contents($log));
    throw $failure;
} finally {
    proc_terminate($process); proc_close($process);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($root);
}

