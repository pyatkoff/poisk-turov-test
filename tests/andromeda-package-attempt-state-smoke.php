<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/andromeda-package-attempt-state.php';

$checks = 0;
function attempt_check(bool $ok): void {
    global $checks; ++$checks;
    if (!$ok) throw new RuntimeException('andromeda_attempt_state_check_' . $checks);
}
function attempt_denied(callable $call, string $message): void {
    try { $call(); }
    catch (RuntimeException $error) {
        attempt_check($error->getMessage() === $message); return;
    }
    throw new RuntimeException('expected_' . $message);
}

$claiminc = 'operator5:form42:opaque-package-id';
$context = hash('sha256', 'selected-public-context-v1');
$operation = hash('sha256', 'package-runtime-source-v2');
$first = AnyTourAndromedaPackageAttemptState::reserveFirst($claiminc, $context, $operation);
attempt_check($first['version'] === 2 && $first['status'] === 'reserved'
    && $first['failure_class'] === 'none' && $first['attempt'] === 1);
attempt_check($first['claiminc_sha256'] === hash('sha256', $claiminc)
    && $first['context_sha256'] === $context && $first['operation_sha256'] === $operation);
attempt_check(!str_contains(json_encode($first, JSON_THROW_ON_ERROR), $claiminc));

$network = AnyTourAndromedaPackageAttemptState::failed(
    $first, new AnyTourAndromedaNetworkTransportFailure('private network detail'));
attempt_check($network['status'] === 'unknown_transport'
    && $network['failure_class'] === 'network_transport' && $network['attempt'] === 1);
attempt_check($first['status'] === 'reserved' && $first['failure_class'] === 'none');

$second = AnyTourAndromedaPackageAttemptState::reserveRetry(
    $network, $claiminc, $context, $operation);
attempt_check($second['status'] === 'reserved' && $second['failure_class'] === 'none'
    && $second['attempt'] === 2);
attempt_check($second['claiminc_sha256'] === $first['claiminc_sha256']
    && $second['context_sha256'] === $context && $second['operation_sha256'] === $operation);
$secondFailure = AnyTourAndromedaPackageAttemptState::failed(
    $second, new AnyTourAndromedaNetworkTransportFailure('second network failure'));
attempt_check($secondFailure['status'] === 'unknown_transport'
    && $secondFailure['attempt'] === 2);
attempt_denied(fn() => AnyTourAndromedaPackageAttemptState::reserveRetry(
    $secondFailure, $claiminc, $context, $operation), 'ANDROMEDA_PACKAGE_RETRY_REFUSED');

// A legacy/generic error string is never enough to produce retryable evidence.
$generic = AnyTourAndromedaPackageAttemptState::failed(
    $first, new RuntimeException('ANDROMEDA_TRANSPORT_ERROR'));
attempt_check($generic['status'] === 'unknown_unclassified'
    && $generic['failure_class'] === 'unclassified');
attempt_denied(fn() => AnyTourAndromedaPackageAttemptState::reserveRetry(
    $generic, $claiminc, $context, $operation), 'ANDROMEDA_PACKAGE_RETRY_REFUSED');
foreach (['ANDROMEDA_HTTP_ERROR','ANDROMEDA_SUPPLIER_ERROR','ANDROMEDA_INVALID_RESPONSE',
    'ANDROMEDA_RESPONSE_TOO_LARGE','ANDROMEDA_REQUEST_BUDGET'] as $message) {
    $failure = AnyTourAndromedaPackageAttemptState::failed($first, new RuntimeException($message));
    attempt_check($failure['status'] === 'unknown_unclassified'
        && $failure['failure_class'] === 'unclassified');
    attempt_denied(fn() => AnyTourAndromedaPackageAttemptState::reserveRetry(
        $failure, $claiminc, $context, $operation), 'ANDROMEDA_PACKAGE_RETRY_REFUSED');
}

attempt_denied(fn() => AnyTourAndromedaPackageAttemptState::reserveRetry(
    $network, 'different-full-claiminc', $context, $operation), 'ANDROMEDA_PACKAGE_RETRY_REFUSED');
attempt_denied(fn() => AnyTourAndromedaPackageAttemptState::reserveRetry(
    $network, $claiminc, hash('sha256','different-context'), $operation), 'ANDROMEDA_PACKAGE_RETRY_REFUSED');
attempt_denied(fn() => AnyTourAndromedaPackageAttemptState::reserveRetry(
    $network, $claiminc, $context, hash('sha256','different-operation')), 'ANDROMEDA_PACKAGE_RETRY_REFUSED');

// Historical v1 UNKNOWN shape cannot be migrated into a retry reservation implicitly.
$legacy = ['version'=>1,'status'=>'unknown','context'=>['provider'=>'andromeda']];
attempt_denied(fn() => AnyTourAndromedaPackageAttemptState::reserveRetry(
    $legacy, $claiminc, $context, $operation), 'ANDROMEDA_PACKAGE_RETRY_REFUSED');

$extra = $network; $extra['claiminc'] = 'PRIVATE RAW ID';
attempt_denied(fn() => AnyTourAndromedaPackageAttemptState::reserveRetry(
    $extra, $claiminc, $context, $operation), 'ANDROMEDA_PACKAGE_RETRY_REFUSED');
$badReserved = $first; $badReserved['claiminc'] = 'PRIVATE RAW ID';
attempt_denied(fn() => AnyTourAndromedaPackageAttemptState::failed(
    $badReserved, new AnyTourAndromedaNetworkTransportFailure('x')), 'ANDROMEDA_PACKAGE_ATTEMPT_INVALID');

foreach (['', "bad\nclaim", str_repeat('x', 4097)] as $badClaim) {
    attempt_denied(fn() => AnyTourAndromedaPackageAttemptState::reserveFirst(
        $badClaim, $context, $operation), 'ANDROMEDA_CLAIMINC_INVALID');
}
attempt_denied(fn() => AnyTourAndromedaPackageAttemptState::reserveFirst(
    $claiminc, 'bad', $operation), 'ANDROMEDA_PACKAGE_PROVENANCE_INVALID');
attempt_denied(fn() => AnyTourAndromedaPackageAttemptState::reserveFirst(
    $claiminc, $context, 'bad'), 'ANDROMEDA_PACKAGE_PROVENANCE_INVALID');

foreach ([$first,$network,$second,$secondFailure,$generic] as $state) {
    $json = json_encode($state, JSON_THROW_ON_ERROR);
    attempt_check(!str_contains($json, $claiminc)
        && !str_contains($json, 'private network detail')
        && !str_contains($json, 'second network failure'));
}

echo "Andromeda package attempt state: $checks checks passed; supplier/SSH/DB/filesystem operations=0.\n";
