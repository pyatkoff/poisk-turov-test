<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/andromeda-client.php';

$checks = 0;
function checkPackageBootstrap(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException('CHECK_FAILED_' . $checks);
}
function expectPackageError(callable $fn, string $message): void
{
    try {
        $fn();
    } catch (RuntimeException $e) {
        checkPackageBootstrap($e->getMessage() === $message);
        return;
    }
    throw new RuntimeException('EXPECTED_' . $message);
}

$calls = [];
$transport = static function (string $url, array $options) use (&$calls): array {
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $calls[] = $query;
    checkPackageBootstrap(($options['method'] ?? null) === 'GET');
    if (($query['action'] ?? '') === 'login') {
        return ['status' => 200, 'body' => json_encode(['sid' => 'sid_pkg_123'], JSON_THROW_ON_ERROR)];
    }
    if (($query['action'] ?? '') === 'broninit') {
        return ['status' => 200, 'body' => json_encode([
            'claimDocument' => [[
                'catalogKey' => 'catalog:opaque:1',
                'buyerMoneys' => [['currency' => 'RUB', 'money' => '123456']],
            ]],
        ], JSON_THROW_ON_ERROR)];
    }
    throw new RuntimeException('UNEXPECTED_ACTION');
};

$client = new AnyTourAndromedaClient($transport, true, true);
$client->login('user', 'secret');
$claim = $client->package('opaque:claim/ABC-123');
checkPackageBootstrap(($claim['claimDocument'][0]['catalogKey'] ?? null) === 'catalog:opaque:1');
checkPackageBootstrap(count($calls) === 2);
checkPackageBootstrap(($calls[1]['action'] ?? null) === 'broninit');
checkPackageBootstrap(($calls[1]['sid'] ?? null) === 'sid_pkg_123');
checkPackageBootstrap(($calls[1]['claiminc'] ?? null) === 'opaque:claim/ABC-123');
checkPackageBootstrap(!isset($calls[1]['bron'], $calls[1]['bron_ticket']));
expectPackageError(static fn() => $client->package('opaque:claim/ABC-123'), 'ANDROMEDA_PACKAGE_REPLAY_REFUSED');
checkPackageBootstrap(count($calls) === 2);

$disabledCalls = 0;
$disabledTransport = static function (string $url, array $options) use (&$disabledCalls): array {
    ++$disabledCalls;
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    if (($query['action'] ?? '') !== 'login') throw new RuntimeException('UNEXPECTED_ACTION');
    return ['status' => 200, 'body' => json_encode(['sid' => 'sid_disabled_1'], JSON_THROW_ON_ERROR)];
};
$disabled = new AnyTourAndromedaClient($disabledTransport, true);
$disabled->login('user', 'secret');
expectPackageError(static fn() => $disabled->package('opaque-1'), 'ANDROMEDA_PACKAGE_DISABLED');
checkPackageBootstrap($disabledCalls === 1);

$invalidCalls = 0;
$invalidTransport = static function (string $url, array $options) use (&$invalidCalls): array {
    ++$invalidCalls;
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    if (($query['action'] ?? '') !== 'login') throw new RuntimeException('UNEXPECTED_ACTION');
    return ['status' => 200, 'body' => json_encode(['sid' => 'sid_invalid_1'], JSON_THROW_ON_ERROR)];
};
$invalid = new AnyTourAndromedaClient($invalidTransport, true, true);
$invalid->login('user', 'secret');
expectPackageError(static fn() => $invalid->package("bad claim"), 'ANDROMEDA_INVALID_PACKAGE_ID');
checkPackageBootstrap($invalidCalls === 1);

$malformedCalls = 0;
$malformedTransport = static function (string $url, array $options) use (&$malformedCalls): array {
    ++$malformedCalls;
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    if (($query['action'] ?? '') === 'login') {
        return ['status' => 200, 'body' => json_encode(['sid' => 'sid_badpkg_1'], JSON_THROW_ON_ERROR)];
    }
    if (($query['action'] ?? '') === 'broninit') {
        return ['status' => 200, 'body' => json_encode(['claimDocument' => [[]]], JSON_THROW_ON_ERROR)];
    }
    throw new RuntimeException('UNEXPECTED_ACTION');
};
$malformed = new AnyTourAndromedaClient($malformedTransport, true, true);
$malformed->login('user', 'secret');
expectPackageError(static fn() => $malformed->package('opaque-2'), 'ANDROMEDA_INVALID_PACKAGE_RESPONSE');
checkPackageBootstrap($malformedCalls === 2);
expectPackageError(static fn() => $malformed->package('opaque-2'), 'ANDROMEDA_PACKAGE_REPLAY_REFUSED');
checkPackageBootstrap($malformedCalls === 2);

// Execute the actual parser and package guard on every rejected structural boundary.
// The same instance must remain sealed after the single attempted package.
$shapeCases = [
    [[], 201],
    [['claimDocument' => null], 201],
    [['claimDocument' => 'private-structural-value'], 201],
    [['claimDocument' => []], 202],
    [['claimDocument' => [1 => ['catalogKey' => 'synthetic-key']]], 202],
    [['claimDocument' => [['catalogKey' => 'synthetic-key'], ['catalogKey' => 'second-key']]], 202],
    [['claimDocument' => [null]], 203],
    [['claimDocument' => ['private-document-value']], 203],
    [['claimDocument' => [[]]], 204],
    [['claimDocument' => [['catalogKey' => null]]], 204],
    [['claimDocument' => [['catalogKey' => 123]]], 204],
    [['claimDocument' => [['catalogKey' => []]]], 204],
    [['claimDocument' => [['catalogKey' => '']]], 205],
];
foreach ($shapeCases as [$reply, $expectedCode]) {
    $shapeCalls = [];
    $shapeTransport = static function(string $url, array $options) use (&$shapeCalls, $reply): array {
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        $shapeCalls[] = $query['action'] ?? null;
        return ['status' => 200, 'body' => json_encode(
            ($query['action'] ?? null) === 'login' ? ['sid' => 'sid_package_shape'] : $reply,
            JSON_THROW_ON_ERROR)];
    };
    $shapeClient = new AnyTourAndromedaClient($shapeTransport, true, true);
    $shapeClient->login('user', 'secret');
    try {
        $shapeClient->package('synthetic-package-shape');
        throw new LogicException('INVALID_PACKAGE_ACCEPTED');
    } catch (RuntimeException $error) {
        checkPackageBootstrap(get_class($error) === RuntimeException::class);
        checkPackageBootstrap($error->getMessage() === 'ANDROMEDA_INVALID_PACKAGE_RESPONSE');
        checkPackageBootstrap($error->getCode() === $expectedCode);
    }
    checkPackageBootstrap($shapeCalls === ['login', 'broninit']);
    expectPackageError(static fn() => $shapeClient->package('synthetic-package-shape'),
        'ANDROMEDA_PACKAGE_REPLAY_REFUSED');
    checkPackageBootstrap($shapeCalls === ['login', 'broninit']);
}
// Existing nonempty-string acceptance is unchanged; do not add coercion or trimming.
foreach (['synthetic-key', '0', ' '] as $catalogKey) {
    $reply = ['claimDocument' => [['catalogKey' => $catalogKey]]];
    $validCalls = [];
    $validTransport = static function(string $url, array $options) use (&$validCalls, $reply): array {
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        $validCalls[] = $query['action'] ?? null;
        return ['status' => 200, 'body' => json_encode(
            ($query['action'] ?? null) === 'login' ? ['sid' => 'sid_package_valid'] : $reply,
            JSON_THROW_ON_ERROR)];
    };
    $validClient = new AnyTourAndromedaClient($validTransport, true, true);
    $validClient->login('user', 'secret');
    checkPackageBootstrap($validClient->package('synthetic-valid-package') === $reply);
    checkPackageBootstrap($validCalls === ['login', 'broninit']);
}

fwrite(STDOUT, "andromeda-package-bootstrap: {$checks} checks OK\n");
