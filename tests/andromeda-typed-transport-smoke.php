<?php
declare(strict_types=1);

require __DIR__ . '/../app/integrations/andromeda-package-retry-policy.php';
require __DIR__ . '/../app/integrations/andromeda-transport.php';
require __DIR__ . '/../app/integrations/andromeda-search.php';

$checks = 0;
function typed_transport_check(bool $ok): void {
    global $checks; ++$checks;
    if (!$ok) throw new RuntimeException('typed_transport_check_' . $checks);
}
function typed_transport_error(callable $call, string $class, string $message): Throwable {
    try { $call(); }
    catch (Throwable $error) {
        typed_transport_check(get_class($error) === $class);
        typed_transport_check($error->getMessage() === $message);
        return $error;
    }
    throw new RuntimeException('typed_transport_expected_error');
}

$packageUrl = 'https://gateway.samo.ru/api/?version=1.01&action=broninit&sid=fixture&claiminc=opaque';
$execCalls = 0;
$network = new AnyTourAndromedaTransport(false, true,
    static function($handle, $writer) use (&$execCalls): bool { ++$execCalls; return false; });
$error = typed_transport_error(static fn() => $network($packageUrl),
    AnyTourAndromedaNetworkTransportFailure::class, 'ANDROMEDA_NETWORK_TRANSPORT_FAILURE');
typed_transport_check($execCalls === 1);
typed_transport_check(AnyTourAndromedaPackageRetryPolicy::classify($error) === 'network_transport');
typed_transport_check($error->curlErrorCode() === null); // Offline false execution has no actual curl errno.

$execCalls = 0;
$oversize = new AnyTourAndromedaTransport(false, true,
    static function($handle, $writer) use (&$execCalls): bool {
        ++$execCalls;
        typed_transport_check($writer($handle, str_repeat('x', 2097153)) === 0);
        return false;
    });
$error = typed_transport_error(static fn() => $oversize($packageUrl),
    RuntimeException::class, 'ANDROMEDA_RESPONSE_TOO_LARGE');
typed_transport_check($execCalls === 1);
typed_transport_check(AnyTourAndromedaPackageRetryPolicy::classify($error) === 'unclassified');

$execCalls = 0;
$generic = new AnyTourAndromedaTransport(false, true,
    static function($handle, $writer) use (&$execCalls): bool {
        ++$execCalls;
        throw new RuntimeException('fixture_executor_failure');
    });
$error = typed_transport_error(static fn() => $generic($packageUrl),
    RuntimeException::class, 'fixture_executor_failure');
typed_transport_check($execCalls === 1);
typed_transport_check(AnyTourAndromedaPackageRetryPolicy::classify($error) === 'unclassified');

$execCalls = 0;
$success = new AnyTourAndromedaTransport(false, true,
    static function($handle, $writer) use (&$execCalls): bool {
        ++$execCalls;
        typed_transport_check($writer($handle, '{"ok":true}') === 11);
        return true;
    });
$reply = $success($packageUrl);
typed_transport_check($execCalls === 1 && $reply['status'] === 0 && $reply['body'] === '{"ok":true}');

$priceUrl = 'https://gateway.samo.ru/api/?version=1.01&action=price&sid=fixture&TOWNFROMINC=1&STATEINC=3&PAGE=1';
$neverCalls = 0;
$never = static function($handle, $writer) use (&$neverCalls): bool { ++$neverCalls; return true; };
$guarded = new AnyTourAndromedaTransport(false, false, $never);
typed_transport_error(static fn() => $guarded($packageUrl), RuntimeException::class, 'ANDROMEDA_ACTION_NOT_ALLOWED');
typed_transport_error(static fn() => $guarded($priceUrl), RuntimeException::class, 'ANDROMEDA_ACTION_NOT_ALLOWED');
typed_transport_error(static fn() => $guarded('http://gateway.samo.ru/api/?version=1.01&action=login'),
    RuntimeException::class, 'ANDROMEDA_ENDPOINT_REJECTED');
typed_transport_check($neverCalls === 0);

$priceCalls = 0;
$price = new AnyTourAndromedaTransport(true, false,
    static function($handle, $writer) use (&$priceCalls): bool {
        ++$priceCalls;
        typed_transport_check($writer($handle, '{"PAGE":1,"PAGES_COUNT":0,"PRICES":[]}') === 38);
        return true;
    });
$priceReply = $price($priceUrl);
typed_transport_check($priceCalls === 1 && $priceReply['body'] === '{"PAGE":1,"PAGES_COUNT":0,"PRICES":[]}');

typed_transport_check(AnyTourAndromedaPackageRetryPolicy::classify(
    new RuntimeException('ANDROMEDA_TRANSPORT_ERROR')) === 'unclassified');
typed_transport_check(AnyTourAndromedaPackageRetryPolicy::classify(
    new RuntimeException('ANDROMEDA_HTTP_ERROR')) === 'unclassified');

// Preserve an emitted transport marker across the client boundary, without making
// the client wrapper eligible for package retry or retaining the original error.
$clientCalls = 0;
$networkClient = new AnyTourAndromedaClient(new AnyTourAndromedaTransport(true, false,
    static function($handle, $writer) use (&$clientCalls): bool { ++$clientCalls; return false; }), true);
$wrapped = typed_transport_error(static fn() => $networkClient->login('fixture-user', 'fixture-password'),
    AnyTourAndromedaClientTransportFailure::class, 'ANDROMEDA_TRANSPORT_ERROR');
typed_transport_check($clientCalls === 1);
typed_transport_check($wrapped->diagnosticFacts() === ['source' => 'andromeda_transport_error',
    'action' => 'login', 'reason_category' => 'network_transfer', 'curl_errno' => null]);
typed_transport_check($wrapped->getPrevious() === null && $wrapped->getCode() === 0);
typed_transport_check(AnyTourAndromedaPackageRetryPolicy::classify($wrapped) === 'unclassified');

// Synthetic errno fixtures establish propagation, not a real timeout/DNS/TLS failure.
foreach ([0, -1, 1000, PHP_INT_MAX] as $invalidErrno) {
    typed_transport_check(AnyTourAndromedaNetworkTransportFailure::fromCurlError($invalidErrno)->curlErrorCode() === null);
}
typed_transport_check(AnyTourAndromedaNetworkTransportFailure::fromCurlError(28)->curlErrorCode() === 28);
typed_transport_check((new AnyTourAndromedaNetworkTransportFailure('arbitrary', 28))->curlErrorCode() === null);

$canary = 'raw-secret-fixture?sid=private-session&password=private-password';
$cases = [
    [new RuntimeException($canary, 28, new LogicException($canary)), 'unclassified', null],
    [new RuntimeException('ANDROMEDA_NETWORK_TRANSPORT_FAILURE', 28), 'unclassified', null],
    [new RuntimeException('ANDROMEDA_TRANSPORT_ERROR', 28), 'unclassified', null],
    [new RuntimeException('ANDROMEDA_ENDPOINT_REJECTED'), 'endpoint_guard', null],
    [new RuntimeException('ANDROMEDA_ACTION_NOT_ALLOWED'), 'action_guard', null],
    [new RuntimeException('ANDROMEDA_REQUEST_BUDGET'), 'request_budget', null],
    [new RuntimeException('ANDROMEDA_CURL_REQUIRED'), 'curl_unavailable', null],
    [new RuntimeException('ANDROMEDA_RESPONSE_TOO_LARGE'), 'response_size_guard', null],
    [new OverflowException('monthly_quota_exhausted'), 'monthly_quota', null],
    [new RuntimeException('monthly_quota_exhausted'), 'unclassified', null],
    [AnyTourAndromedaNetworkTransportFailure::fromCurlError(28), 'network_transfer', 28],
    [new AnyTourAndromedaNetworkTransportFailure($canary, 28), 'network_transfer', null],
];
foreach ($cases as [$original, $category, $errno]) {
    $calls = 0;
    $client = new AnyTourAndromedaClient(static function($url, $options) use ($original, &$calls) {
        ++$calls;
        throw $original;
    }, true);
    $error = typed_transport_error(static fn() => $client->login('fixture-user', 'fixture-password'),
        AnyTourAndromedaClientTransportFailure::class, 'ANDROMEDA_TRANSPORT_ERROR');
    typed_transport_check($calls === 1 && $client->privateSession() === []);
    typed_transport_check($error->getPrevious() === null && $error->getCode() === 0);
    typed_transport_check($error->diagnosticFacts() === ['source' => 'andromeda_transport_error',
        'action' => 'login', 'reason_category' => $category, 'curl_errno' => $errno]);
    typed_transport_check(AnyTourAndromedaPackageRetryPolicy::classify($error) === 'unclassified');
    ob_start(); var_dump($error); $debug = ob_get_clean();
    typed_transport_check(!str_contains($debug, $canary) && !str_contains($debug, 'private-session'));
    typed_transport_error(static fn() => serialize($error), RuntimeException::class, 'ANDROMEDA_SERIALIZATION_DISABLED');
}

$untrusted = new AnyTourAndromedaClientTransportFailure($canary, $canary, 28);
typed_transport_check($untrusted->diagnosticFacts() === ['source' => 'andromeda_transport_error',
    'action' => 'unknown', 'reason_category' => 'unclassified', 'curl_errno' => null]);
foreach ([0, -1, 1000, PHP_INT_MAX] as $invalidErrno) {
    typed_transport_check((new AnyTourAndromedaClientTransportFailure('price', 'network_transfer', $invalidErrno))
        ->diagnosticFacts()['curl_errno'] === null);
}

// The existing response-size boundary must not acquire network provenance in the
// client either, even though curl execution returns false at the writer guard.
$oversizeClient = new AnyTourAndromedaClient(new AnyTourAndromedaTransport(true, false,
    static function($handle, $writer): bool { $writer($handle, str_repeat('x', 2097153)); return false; }), true);
$sizeError = typed_transport_error(static fn() => $oversizeClient->login('fixture-user', 'fixture-password'),
    AnyTourAndromedaClientTransportFailure::class, 'ANDROMEDA_TRANSPORT_ERROR');
typed_transport_check($sizeError->diagnosticFacts()['reason_category'] === 'response_size_guard'
    && $sizeError->diagnosticFacts()['curl_errno'] === null);
typed_transport_check(AnyTourAndromedaPackageRetryPolicy::classify($sizeError) === 'unclassified');

$criteria = AnyTourAndromedaClient::priceProbeParams();
$now = 1700000000;
foreach (['login', 'price'] as $stage) {
    $state = []; $saved = []; $calls = [];
    $client = new AnyTourAndromedaClient(static function($url, $options) use ($stage, &$calls, &$saved) {
        typed_transport_check(($saved[count($saved) - 1]['status'] ?? null) === 'pending');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $calls[] = $query['action'];
        if ($query['action'] === $stage) throw AnyTourAndromedaNetworkTransportFailure::fromCurlError(28);
        return ['status' => 200, 'body' => '{"sid":"private-session"}'];
    }, true);
    $handler = new AnyTourAndromedaSearch($state, static function($next) use (&$saved): bool {
        $saved[] = $next; return true;
    }, true);
    $ref = 'transport_' . $stage;
    $out = $handler->start($criteria, $ref, 1, $now, $client, 'private-user', 'private-password');
    typed_transport_check($calls === ($stage === 'login' ? ['login'] : ['login', 'price']));
    typed_transport_check(count($saved) === 2 && $saved[0]['status'] === 'pending'
        && !isset($saved[0]['transport_failure']) && $saved[1] === $state);
    typed_transport_check($state['status'] === 'unavailable' && $state['error_code'] === 'ANDROMEDA_TRANSPORT_ERROR'
        && $state['error'] === 'supplier_result_unavailable' && !isset($state['store']['snapshot']));
    typed_transport_check($state['transport_failure'] === ['source' => 'andromeda_transport_error',
        'action' => $stage, 'reason_category' => 'network_transfer', 'curl_errno' => 28]);
    $encoded = json_encode($saved, JSON_THROW_ON_ERROR);
    foreach (['private-user', 'private-password', 'private-session', 'gateway.samo.ru', 'nonce', 'previous'] as $secret) {
        typed_transport_check(!str_contains($encoded, $secret));
    }
    typed_transport_check($out === ['provider' => 'andromeda', 'search_ref' => $ref, 'generation' => 1,
        'status' => 'unavailable', 'selection_enabled' => false,
        'date_range' => ['from' => '2026-09-18', 'to' => '2026-09-18'],
        'page' => null, 'pages_count' => null, 'hotels' => [], 'offers' => [], 'error' => 'supplier_result_unavailable']);
    typed_transport_check($handler->resume($ref, 1, $now + 1) === $out && count($saved) === 2);
    $originalCalls = $calls;
    typed_transport_error(static fn() => $handler->start($criteria, $ref, 1, $now + 1, $client, 'u', 'p'),
        RuntimeException::class, 'ANDROMEDA_SEARCH_REPLAY_REFUSED');
    typed_transport_error(static fn() => $handler->start($criteria, 'new_generation', 2, $now + 1, $client, 'u', 'p'),
        RuntimeException::class, 'ANDROMEDA_PREVIOUS_RESULT_UNKNOWN');
    if ($stage === 'price') {
        typed_transport_error(static fn() => $client->price($criteria), RuntimeException::class, 'ANDROMEDA_PRICE_REPLAY_REFUSED');
    }
    typed_transport_check($calls === $originalCalls && count($saved) === 2);
    // Existing checkpoints without the new optional field still resume identically.
    unset($state['transport_failure']);
    typed_transport_check($handler->resume($ref, 1, $now + 1) === $out && $calls === $originalCalls);
    typed_transport_error(static fn() => $handler->resume($ref, 1, $state['store']['expires_at']),
        RuntimeException::class, 'EXPIRED_SEARCH');
    typed_transport_check($calls === $originalCalls);
}

// Successful and actual HTTP/supplier-error responses keep their existing paths.
foreach (['success', 'http', 'supplier'] as $kind) {
    $state = []; $saved = []; $calls = [];
    $client = new AnyTourAndromedaClient(static function($url) use ($kind, &$calls) {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $calls[] = $query['action'];
        if ($query['action'] === 'login') return ['status' => 200, 'body' => '{"sid":"private-session"}'];
        return match ($kind) {
            'http' => ['status' => 503, 'body' => '{}'],
            'supplier' => ['status' => 200, 'body' => '{"error":{"code":1108}}'],
            default => ['status' => 200, 'body' => '{"PAGE":1,"PAGES_COUNT":0,"PRICES":[]}'],
        };
    }, true);
    $handler = new AnyTourAndromedaSearch($state, static function($next) use (&$saved): bool {
        $saved[] = $next; return true;
    }, true);
    $out = $handler->start($criteria, 'transport_control_' . $kind, 1, $now, $client, 'u', 'p');
    typed_transport_check($calls === ['login', 'price'] && count($saved) === 2 && !isset($state['transport_failure']));
    typed_transport_check($out['status'] === ($kind === 'success' ? 'complete' : 'unavailable'));
    if ($kind !== 'success') {
        typed_transport_check($state['error_code'] === ($kind === 'http' ? 'ANDROMEDA_HTTP_ERROR' : 'ANDROMEDA_SUPPLIER_ERROR'));
    }
    typed_transport_check($handler->resume('transport_control_' . $kind, 1, $now + 1) === $out && $calls === ['login', 'price']);
}

echo 'Andromeda typed transport: ' . $checks . " checks passed; curl_exec disabled/not invoked, supplier/SSH/DB=0.\n";
