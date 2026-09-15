<?php
declare(strict_types=1);

require_once __DIR__.'/../app/integrations/andromeda-claim-actions.php';

$checks = 0;
$claim = ['claimDocument' => [0 => ['condition' => 'ccOffer']]];

$run = static function(array $reply, string $sid = 'SID_error_test') use ($claim): array {
    $reserved = 0;
    $actions = new AnyTourAndromedaClaimActions(
        $sid,
        static function() use (&$reserved): void { ++$reserved; },
        static function(string $url, string $post) use ($reply): array {
            parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
            if (($query['action'] ?? null) !== 'get_flights') throw new RuntimeException('BAD_ACTION');
            parse_str($post, $form);
            $decoded = json_decode($form['claim'] ?? '', true, 16, JSON_THROW_ON_ERROR);
            if (($decoded['claimDocument'][0]['condition'] ?? null) !== 'ccOffer') throw new RuntimeException('BAD_CLAIM');
            return ['status' => 200, 'body' => json_encode($reply, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
        }
    );
    try {
        return ['value' => $actions->getFlights($claim), 'reserved' => $reserved];
    } catch (Throwable $error) {
        return ['error' => $error, 'reserved' => $reserved];
    }
};

$rawMessage = 'No flights available for private passenger private@example.com';
$first = $run(['error' => ['code' => 'FLIGHT_NOT_AVAILABLE', 'message' => $rawMessage]]);
$error = $first['error'] ?? null;
if (!$error instanceof AnyTourAndromedaSupplierException) throw new RuntimeException('supplier_exception_type'); ++$checks;
if ($error->getMessage() !== 'ANDROMEDA_SUPPLIER_ERROR' || ($first['reserved'] ?? null) !== 1) throw new RuntimeException('supplier_exception_contract'); ++$checks;
$facts = $error->diagnosticFacts();
if (($facts['source'] ?? null) !== 'andromeda_claim_error'
    || ($facts['shape'] ?? null) !== 'array'
    || ($facts['reason_category'] ?? null) !== 'flight_or_freight'
    || ($facts['code'] ?? null) !== 'FLIGHT_NOT_AVAILABLE'
    || ($facts['code_field'] ?? null) !== 'code'
    || !is_string($facts['error_sha256'] ?? null)
    || preg_match('/^[a-f0-9]{64}$/D', $facts['error_sha256']) !== 1) {
    throw new RuntimeException('supplier_facts');
}
++$checks;
$encodedFacts = json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
foreach ([$rawMessage, 'private@example.com', 'private passenger'] as $secret) {
    if (str_contains($encodedFacts, $secret)) throw new RuntimeException('raw_supplier_error_leak');
}
++$checks;

$auth = $run(['error' => 'AUTH_FAILED']);
$authError = $auth['error'] ?? null;
if (!$authError instanceof AnyTourAndromedaSupplierException) throw new RuntimeException('auth_exception');
$authFacts = $authError->diagnosticFacts();
if (($authFacts['shape'] ?? null) !== 'string'
    || ($authFacts['code'] ?? null) !== 'AUTH_FAILED'
    || ($authFacts['code_field'] ?? null) !== 'error'
    || ($authFacts['reason_category'] ?? null) !== 'auth_or_session') {
    throw new RuntimeException('auth_facts');
}
++$checks;

$privateText = 'Authorization failed for private-user@example.com; password rejected';
$private = $run(['error' => $privateText]);
$privateError = $private['error'] ?? null;
if (!$privateError instanceof AnyTourAndromedaSupplierException) throw new RuntimeException('private_exception');
$privateFacts = $privateError->diagnosticFacts();
if (($privateFacts['reason_category'] ?? null) !== 'auth_or_session' || isset($privateFacts['code'])) {
    throw new RuntimeException('private_facts');
}
if (str_contains(json_encode($privateFacts, JSON_THROW_ON_ERROR), 'private-user')) throw new RuntimeException('private_error_leak');
++$checks;

$numeric = $run(['error' => 417]);
$numericError = $numeric['error'] ?? null;
if (!$numericError instanceof AnyTourAndromedaSupplierException
    || ($numericError->diagnosticFacts()['code'] ?? null) !== '417'
    || ($numericError->diagnosticFacts()['reason_category'] ?? null) !== 'unclassified') {
    throw new RuntimeException('numeric_error');
}
++$checks;

$echo = $run(['error' => ['message' => 'failure SID_secret_echo']], 'SID_secret_echo');
$echoError = $echo['error'] ?? null;
if (!$echoError instanceof RuntimeException || $echoError instanceof AnyTourAndromedaSupplierException
    || $echoError->getMessage() !== 'ANDROMEDA_SECRET_ECHO') {
    throw new RuntimeException('session_echo_precedence');
}
++$checks;

$successReply = $claim;
$success = $run($successReply);
if (($success['value']['claimDocument'][0]['condition'] ?? null) !== 'ccOffer'
    || ($success['reserved'] ?? null) !== 1) {
    throw new RuntimeException('success_regression');
}
++$checks;

echo "andromeda supplier error facts checks={$checks}\n";
