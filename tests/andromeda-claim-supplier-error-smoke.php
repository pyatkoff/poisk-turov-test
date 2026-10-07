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
    || ($facts['action'] ?? null) !== 'get_flights'
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


$stageRun = static function(string $stage) use ($claim): array {
    $actions = new AnyTourAndromedaClaimActions('SID_stage_test', static function(): void {}, static function(string $url, string $post) use ($stage): array {
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        if (($query['action'] ?? null) !== $stage) throw new RuntimeException('BAD_STAGE');
        return ['status'=>200,'body'=>json_encode(['error'=>['code'=>'STAGE_REJECTED','message'=>'private stage detail']], JSON_THROW_ON_ERROR)];
    });
    try {
        if ($stage === 'get_flights') $actions->getFlights($claim);
        elseif ($stage === 'changeservice') $actions->changeService($claim, 'NEW_UID');
        else $actions->calc($claim);
    } catch (AnyTourAndromedaSupplierException $error) { return $error->diagnosticFacts(); }
    throw new RuntimeException('stage_error_not_thrown');
};
foreach (['get_flights','changeservice','calc'] as $stage) {
    $stageFacts=$stageRun($stage);
    if (($stageFacts['action']??null)!==$stage || ($stageFacts['code']??null)!=='STAGE_REJECTED') throw new RuntimeException('stage_facts_'.$stage);
    if (str_contains(json_encode($stageFacts,JSON_THROW_ON_ERROR),'private stage detail')) throw new RuntimeException('stage_raw_leak_'.$stage);
    ++$checks;
}

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

// Exercise the real response decoder for every allowed action. No failed reply
// is retried, and syntax details or a private body never become an exception cause.
$malformedBodies = [
    'truncated' => '{"claimDocument":[{"private":"private@example.com"}',
    'non_json' => 'private supplier HTML private@example.com',
    'invalid_utf8' => "{\"private\":\"\xB1\"}",
    'depth' => str_repeat('[', 65) . '0' . str_repeat(']', 65),
    'null' => 'null',
    'number' => '42',
];
$rawReplyRun = static function(string $stage, string $body, int $status = 200) use ($claim): array {
    $reserved = 0; $requests = 0;
    $actions = new AnyTourAndromedaClaimActions('SID_parser_test',
        static function() use (&$reserved): void { ++$reserved; },
        static function(string $url, string $post) use ($stage, $body, $status, &$requests, $claim): array {
            ++$requests;
            parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
            parse_str($post, $form);
            if (($query['action'] ?? null) !== $stage
                || json_decode($form['claim'] ?? '', true, 16, JSON_THROW_ON_ERROR) !== $claim) {
                throw new RuntimeException('parser_request_shape');
            }
            return ['status' => $status, 'body' => $body];
        });
    try {
        if ($stage === 'get_flights') $actions->getFlights($claim);
        elseif ($stage === 'changeservice') $actions->changeService($claim, 'NEW_UID');
        else $actions->calc($claim);
    } catch (Throwable $error) {
        return ['error' => $error, 'reserved' => $reserved, 'requests' => $requests];
    }
    throw new RuntimeException('parser_failure_not_thrown');
};
foreach (['get_flights', 'changeservice', 'calc'] as $stage) {
    foreach ($malformedBodies as $shape => $body) {
        $failed = $rawReplyRun($stage, $body);
        $error = $failed['error'];
        if (get_class($error) !== RuntimeException::class
            || $error->getMessage() !== 'ANDROMEDA_INVALID_RESPONSE'
            || $error->getPrevious() !== null || $error->getCode() !== 0
            || $failed['reserved'] !== 1 || $failed['requests'] !== 1) {
            throw new RuntimeException('parser_boundary_' . $stage . '_' . $shape);
        }
        ++$checks;
    }
    foreach ([
        ['not JSON', 503, 'ANDROMEDA_HTTP_ERROR'],
        [str_repeat('x', 2097153), 200, 'ANDROMEDA_RESPONSE_TOO_LARGE'],
    ] as [$body, $status, $reason]) {
        $failed = $rawReplyRun($stage, $body, $status);
        if ($failed['error']->getMessage() !== $reason
            || $failed['reserved'] !== 1 || $failed['requests'] !== 1) {
            throw new RuntimeException('parser_precedence_' . $stage);
        }
        ++$checks;
    }
}

// Request JSON encoding is a separate pre-transport boundary, not supplier JSON.
$requestReserved = 0; $requestCalls = 0;
$requestActions = new AnyTourAndromedaClaimActions('SID_request_test',
    static function() use (&$requestReserved): void { ++$requestReserved; },
    static function() use (&$requestCalls): array { ++$requestCalls; return []; });
try {
    $requestActions->getFlights(['claimDocument' => [['private' => "\xB1"]]]);
    throw new RuntimeException('request_json_failure_not_thrown');
} catch (JsonException $error) {
    if ($requestReserved !== 0 || $requestCalls !== 0) throw new RuntimeException('request_json_reserved');
    ++$checks;
}

echo "andromeda supplier error facts checks={$checks}\n";
