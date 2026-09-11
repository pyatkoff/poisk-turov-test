<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/andromeda-package-retry-policy.php';

$checks = 0;
function retry_check(bool $ok): void {
    global $checks; ++$checks;
    if (!$ok) throw new RuntimeException('andromeda_retry_policy_check_' . $checks);
}

retry_check(AnyTourAndromedaPackageRetryPolicy::classify(
    new AnyTourAndromedaNetworkTransportFailure('socket timeout')) === 'network_transport');
// Current legacy client collapses several errors into this message; string matching must NOT make it retryable.
retry_check(AnyTourAndromedaPackageRetryPolicy::classify(
    new RuntimeException('ANDROMEDA_TRANSPORT_ERROR')) === 'unclassified');
retry_check(AnyTourAndromedaPackageRetryPolicy::classify(
    new RuntimeException('ANDROMEDA_HTTP_ERROR')) === 'unclassified');

$claim = hash('sha256', 'private-full-claiminc');
$context = hash('sha256', 'selected-context');
$operation = hash('sha256', 'capture-runtime-v2');
$evidence = [
    'version' => 2,
    'status' => 'unknown_transport',
    'failure_class' => 'network_transport',
    'attempt' => 1,
    'claiminc_sha256' => $claim,
    'context_sha256' => $context,
    'operation_sha256' => $operation,
];
$allowed = AnyTourAndromedaPackageRetryPolicy::decision($evidence, $claim, $context, $operation);
retry_check($allowed === [
    'retry_allowed' => true,
    'reason' => 'supplier_confirmed_network_retry',
    'next_attempt' => 2,
    'max_attempts' => 2,
]);

// Historical v1 UNKNOWN lacks failure provenance and must remain sealed.
$legacy = ['version'=>1,'status'=>'unknown','failure_class'=>'unclassified','attempt'=>1,
    'claiminc_sha256'=>$claim,'context_sha256'=>$context,'operation_sha256'=>$operation];
$decision = AnyTourAndromedaPackageRetryPolicy::decision($legacy, $claim, $context, $operation);
retry_check(!$decision['retry_allowed'] && $decision['reason'] === 'legacy_or_unclassified');

foreach (['unknown','captured','reserved','stale'] as $status) {
    $changed = array_replace($evidence, ['status'=>$status]);
    $decision = AnyTourAndromedaPackageRetryPolicy::decision($changed, $claim, $context, $operation);
    retry_check(!$decision['retry_allowed'] && $decision['reason'] === 'outcome_not_retryable');
}
foreach (['unclassified','http','supplier','invalid_response','response_too_large','local_guard'] as $failure) {
    $changed = array_replace($evidence, ['failure_class'=>$failure]);
    $decision = AnyTourAndromedaPackageRetryPolicy::decision($changed, $claim, $context, $operation);
    retry_check(!$decision['retry_allowed'] && $decision['reason'] === 'outcome_not_retryable');
}

$second = array_replace($evidence, ['attempt'=>2]);
$decision = AnyTourAndromedaPackageRetryPolicy::decision($second, $claim, $context, $operation);
retry_check(!$decision['retry_allowed'] && $decision['reason'] === 'retry_already_consumed');
foreach ([0,3,'1'] as $attempt) {
    $changed = array_replace($evidence, ['attempt'=>$attempt]);
    $decision = AnyTourAndromedaPackageRetryPolicy::decision($changed, $claim, $context, $operation);
    retry_check(!$decision['retry_allowed'] && $decision['reason'] === 'invalid_attempt');
}

foreach ([
    ['claiminc_sha256', hash('sha256','other-claiminc')],
    ['context_sha256', hash('sha256','other-context')],
    ['operation_sha256', hash('sha256','other-operation')],
] as [$field,$value]) {
    $changed = array_replace($evidence, [$field=>$value]);
    $decision = AnyTourAndromedaPackageRetryPolicy::decision($changed, $claim, $context, $operation);
    retry_check(!$decision['retry_allowed'] && $decision['reason'] === 'provenance_changed');
}
foreach (['claiminc_sha256','context_sha256','operation_sha256'] as $field) {
    $changed = array_replace($evidence, [$field=>'not-a-digest']);
    $decision = AnyTourAndromedaPackageRetryPolicy::decision($changed, $claim, $context, $operation);
    retry_check(!$decision['retry_allowed'] && $decision['reason'] === 'invalid_evidence_provenance');
}

$withRaw = $evidence + ['claiminc'=>'PRIVATE MUST NOT ENTER POLICY'];
$decision = AnyTourAndromedaPackageRetryPolicy::decision($withRaw, $claim, $context, $operation);
retry_check(!$decision['retry_allowed'] && $decision['reason'] === 'invalid_evidence_envelope');
$missing = $evidence; unset($missing['failure_class']);
$decision = AnyTourAndromedaPackageRetryPolicy::decision($missing, $claim, $context, $operation);
retry_check(!$decision['retry_allowed'] && $decision['reason'] === 'invalid_evidence_envelope');
$decision = AnyTourAndromedaPackageRetryPolicy::decision($evidence, 'bad', $context, $operation);
retry_check(!$decision['retry_allowed'] && $decision['reason'] === 'invalid_expected_provenance');

$json = json_encode($allowed, JSON_THROW_ON_ERROR);
retry_check(!str_contains($json, 'private-full-claiminc') && !str_contains($json, $claim)
    && !str_contains($json, $context) && !str_contains($json, $operation));

echo "Andromeda package retry policy: $checks checks passed; no supplier/SSH/DB/filesystem operations.\n";
