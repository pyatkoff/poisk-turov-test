<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/andromeda-client.php';
$checks = 0;
function check(bool $ok): void { global $checks; ++$checks; if (!$ok) throw new RuntimeException('CHECK_FAILED_' . $checks); }
function fails(callable $call, string $message): void {
    try { $call(); } catch (RuntimeException $e) { check($e->getMessage() === $message && $e->getPrevious() === null); return; }
    throw new RuntimeException('EXPECTED_ERROR');
}
$seen = [];
$transport = function ($url, $options) use (&$seen) {
    parse_str(parse_url($url, PHP_URL_QUERY), $p);
    $seen[] = $p;
    check(strpos($url, 'https://gateway.samo.ru/api/?') === 0);
    check($p['version'] === '1.01' && $options['follow_redirects'] === false && $options['verify_peer']);
    if ($p['action'] === 'login') {
        check(!isset($p['sid']) && $p['username'] === 'fixture-account');
        $raw = base64_decode($p['nonce'], true);
        check(is_string($raw) && strlen($raw) === 32);
        check((bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $p['created']));
        // Independent hash API and binary SHA1 output; fixture password is not a credential.
        $want = base64_encode(hash('sha1', $raw . $p['created'] . hash('md5', 'fixture-password'), true));
        check(hash_equals($want, $p['password']));
        return ['status' => 200, 'body' => '{"sid":"fixture-session-123"}'];
    }
    check($p['sid'] === 'fixture-session-123');
    $out = $p['action'] === 'townfrom' ? ['TOWNFROM' => []] : ($p['action'] === 'state' ? ['STATE' => []] :
        array_fill_keys(['CHECKIN_BEG', 'TOWNTO', 'STARS', 'HOTELS', 'MEAL', 'CURRENCY', 'OPERATORS'], []));
    return ['status' => 200, 'body' => json_encode($out)];
};
$off = new AnyTourAndromedaClient($transport);
fails(fn() => $off->login('fixture-account', 'fixture-password'), 'ANDROMEDA_DISABLED');
check(count($seen) === 0);
$c = new AnyTourAndromedaClient($transport, true);
fails(fn() => $c->catalog('townfrom'), 'ANDROMEDA_LOGIN_REQUIRED');
fails(fn() => $c->catalog('price'), 'ANDROMEDA_ACTION_NOT_ALLOWED');
fails(fn() => $c->catalog('bron'), 'ANDROMEDA_ACTION_NOT_ALLOWED');
$c->login('fixture-account', 'fixture-password');
fails(fn() => $c->catalog('state', ['TOWNFROMINC' => '1']), 'ANDROMEDA_INVALID_PARAMS');
fails(fn() => $c->catalog('state', ['TOWNFROMINC' => 1, 'sid' => 'override']), 'ANDROMEDA_INVALID_PARAMS');
check($c->catalog('townfrom') === ['TOWNFROM' => []]);
check($c->catalog('state', ['TOWNFROMINC' => 1]) === ['STATE' => []]);
check(isset($c->catalog('all', ['TOWNFROMINC' => 1, 'STATEINC' => 2])['HOTELS']));
fails(fn() => $c->catalog('townfrom'), 'ANDROMEDA_REQUEST_BUDGET');
check(count($seen) === 4);
ob_start(); var_dump($c); $dump = ob_get_clean();
check(strpos($dump, 'fixture-session') === false);
fails(fn() => serialize($c), 'ANDROMEDA_SERIALIZATION_DISABLED');
$c2 = new AnyTourAndromedaClient($transport, true);
$c2->login('fixture-account', 'fixture-password');
check($seen[0]['nonce'] !== $seen[4]['nonce']);
foreach ([
    [['status' => 302, 'body' => 'secret'], 'ANDROMEDA_HTTP_ERROR'],
    [['status' => 429, 'body' => 'secret'], 'ANDROMEDA_HTTP_ERROR'],
    [['status' => 200, 'body' => '{"error":{"message":"secret"}}'], 'ANDROMEDA_SUPPLIER_ERROR'],
    [['status' => 200, 'body' => '<html>secret</html>'], 'ANDROMEDA_INVALID_RESPONSE'],
    [['status' => 200, 'body' => str_repeat('x', 2097153)], 'ANDROMEDA_RESPONSE_TOO_LARGE'],
    [['status' => 200, 'body' => '{"sid":null}'], 'ANDROMEDA_INVALID_RESPONSE'],
] as [$response, $error]) {
    $calls = 0;
    $bad = new AnyTourAndromedaClient(function () use ($response, &$calls) { ++$calls; return $response; }, true);
    fails(fn() => $bad->login('fixture-account', 'fixture-password'), $error);
    check($calls === 1); // No replay for redirects, throttling, malformed/unknown responses.
}
$bad = new AnyTourAndromedaClient(function () { throw new RuntimeException('secret URL'); }, true);
fails(fn() => $bad->login('fixture-account', 'fixture-password'), 'ANDROMEDA_TRANSPORT_ERROR');
$echo = new AnyTourAndromedaClient(function ($url) {
    parse_str(parse_url($url, PHP_URL_QUERY), $p);
    return ['status' => 200, 'body' => $p['action'] === 'login' ? '{"sid":"session-secret"}' :
        '{"TOWNFROM":[{"name":"session%252Dsecret"}]}'];
}, true);
$echo->login('fixture-account', 'fixture-password');
fails(fn() => $echo->catalog('townfrom'), 'ANDROMEDA_SECRET_ECHO');

function dictionaryClient(array $reply, int &$calls): AnyTourAndromedaClient {
    return new AnyTourAndromedaClient(function ($url) use ($reply, &$calls) {
        ++$calls;
        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        return ['status' => 200, 'body' => $params['action'] === 'login'
            ? '{"sid":"dictionary-fixture-session"}' : json_encode($reply)];
    }, true);
}
$valid = [['id' => 85, 'name' => 'Архангельск', 'selected' => 1],
    ['id' => '9007199254740993', 'name' => 'Synthetic opaque ID', 'selected' => 0]];
foreach (['townfrom' => ['TOWNFROM', []], 'state' => ['STATE', ['TOWNFROMINC' => 85]]] as $action => [$key, $params]) {
    $calls = 0;
    $ok = dictionaryClient([$key => $valid], $calls);
    $ok->login('fixture-account', 'fixture-password');
    check($ok->catalog($action, $params) === [$key => $valid]);
    check($calls === 2);
    foreach ([
        ['error' => 'not a list'],
        ['not a row'],
        [['id' => 1]],
        [['id' => 1, 'name' => '  ']],
        [['id' => false, 'name' => 'bad']],
        [['id' => 1.5, 'name' => 'bad']],
        [['id' => '1e2', 'name' => 'bad']],
        [['id' => 0, 'name' => 'bad']],
        [['id' => 1, 'name' => 'first'], ['id' => '1', 'name' => 'duplicate']],
    ] as $badRows) {
        $calls = 0;
        $bad = dictionaryClient([$key => $badRows], $calls);
        $bad->login('fixture-account', 'fixture-password');
        fails(fn() => $bad->catalog($action, $params), 'ANDROMEDA_INVALID_DICTIONARY');
        check($calls === 2);
    }
}
$all = array_fill_keys(['CHECKIN_BEG', 'TOWNTO', 'STARS', 'HOTELS', 'MEAL', 'CURRENCY', 'OPERATORS'], []);
$all['HOTELS'] = [['id' => 2791, 'name' => 'Acropol Beach Hotel', 'townKey' => 27]];
$calls = 0;
$ok = dictionaryClient($all, $calls); $ok->login('fixture-account', 'fixture-password');
check($ok->catalog('all', ['TOWNFROMINC' => 1, 'STATEINC' => 2]) === $all);
$all['HOTELS'][] = ['id' => '2791', 'name' => 'Duplicate hotel'];
$calls = 0;
$bad = dictionaryClient($all, $calls); $bad->login('fixture-account', 'fixture-password');
fails(fn() => $bad->catalog('all', ['TOWNFROMINC' => 1, 'STATEINC' => 2]), 'ANDROMEDA_INVALID_DICTIONARY');

echo 'Andromeda offline checks: ' . $checks . " passed\n";
