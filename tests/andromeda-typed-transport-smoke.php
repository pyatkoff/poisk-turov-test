<?php
declare(strict_types=1);

require __DIR__ . '/../app/integrations/andromeda-package-retry-policy.php';
require __DIR__ . '/../app/integrations/andromeda-transport.php';

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

$neverCalls = 0;
$never = static function($handle, $writer) use (&$neverCalls): bool { ++$neverCalls; return true; };
$guarded = new AnyTourAndromedaTransport(false, false, $never);
typed_transport_error(static fn() => $guarded($packageUrl), RuntimeException::class, 'ANDROMEDA_ACTION_NOT_ALLOWED');
typed_transport_error(static fn() => $guarded('http://gateway.samo.ru/api/?version=1.01&action=login'),
    RuntimeException::class, 'ANDROMEDA_ENDPOINT_REJECTED');
typed_transport_check($neverCalls === 0);

typed_transport_check(AnyTourAndromedaPackageRetryPolicy::classify(
    new RuntimeException('ANDROMEDA_TRANSPORT_ERROR')) === 'unclassified');
typed_transport_check(AnyTourAndromedaPackageRetryPolicy::classify(
    new RuntimeException('ANDROMEDA_HTTP_ERROR')) === 'unclassified');

echo 'Andromeda typed transport: ' . $checks . " checks passed; curl_exec disabled/not invoked, supplier/SSH/DB=0.\n";
