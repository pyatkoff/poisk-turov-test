<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/andromeda-client.php';

$checks = 0;
function operator_credentials_check(bool $ok, string $label): void {
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException($label . '_' . $checks);
}

$transportFor = static function (array &$requests, ?array $broninitReply = null): callable {
    return static function (string $url, array $options) use (&$requests, $broninitReply): array {
        $query = [];
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        $requests[] = ['url' => $url, 'query' => $query, 'options' => $options];
        if (($query['action'] ?? null) === 'login') {
            return ['status' => 200, 'body' => json_encode(['sid' => 'synthetic_sid_123'], JSON_THROW_ON_ERROR)];
        }
        if (($query['action'] ?? null) === 'broninit') {
            $payload = $broninitReply ?? ['claimDocument' => [['catalogKey' => 'synthetic/catalog/key']]];
            return ['status' => 200, 'body' => json_encode($payload, JSON_THROW_ON_ERROR)];
        }
        throw new RuntimeException('UNEXPECTED_SYNTHETIC_ACTION');
    };
};

// Default-absent credentials preserve the existing broninit request shape exactly.
$plainRequests = [];
$plain = new AnyTourAndromedaClient($transportFor($plainRequests), true, true);
$plain->login('gateway-user', 'gateway-password');
$plainResult = $plain->package('opaque-claim-123');
operator_credentials_check(($plainResult['claimDocument'][0]['catalogKey'] ?? null) === 'synthetic/catalog/key', 'plain_package_failed');
operator_credentials_check(count($plainRequests) === 2, 'plain_request_count');
$plainBroninit = $plainRequests[1];
operator_credentials_check(($plainBroninit['query']['action'] ?? null) === 'broninit', 'plain_wrong_action');
operator_credentials_check(($plainBroninit['query']['sid'] ?? null) === 'synthetic_sid_123', 'plain_sid_changed');
operator_credentials_check(($plainBroninit['query']['claiminc'] ?? null) === 'opaque-claim-123', 'plain_claiminc_changed');
operator_credentials_check(!array_key_exists('OPERATOR_LOGIN', $plainBroninit['query']), 'plain_operator_login_added');
operator_credentials_check(!array_key_exists('OPERATOR_PASSWORD', $plainBroninit['query']), 'plain_operator_password_added');
$expectedPlainUrl = 'https://gateway.samo.ru/api/?version=1.01&action=broninit&sid=synthetic_sid_123&claiminc=opaque-claim-123';
operator_credentials_check($plainBroninit['url'] === $expectedPlainUrl, 'plain_broninit_url_changed');

// A complete configured pair is forwarded only to broninit and is RFC3986 encoded.
$operatorLogin = 'operator+login@example.test';
$operatorPassword = 'p@ss &+%/=? synthetic';
$credentialRequests = [];
$withCredentials = new AnyTourAndromedaClient(
    $transportFor($credentialRequests),
    true,
    true,
    $operatorLogin,
    $operatorPassword
);
$withCredentials->login('gateway-user', 'gateway-password');
$withCredentials->package('opaque-claim-456');
operator_credentials_check(count($credentialRequests) === 2, 'credential_request_count');
operator_credentials_check(!array_key_exists('OPERATOR_LOGIN', $credentialRequests[0]['query']), 'operator_login_leaked_to_gateway_login');
operator_credentials_check(!array_key_exists('OPERATOR_PASSWORD', $credentialRequests[0]['query']), 'operator_password_leaked_to_gateway_login');
$credentialBroninit = $credentialRequests[1];
operator_credentials_check(($credentialBroninit['query']['OPERATOR_LOGIN'] ?? null) === $operatorLogin, 'operator_login_not_forwarded');
operator_credentials_check(($credentialBroninit['query']['OPERATOR_PASSWORD'] ?? null) === $operatorPassword, 'operator_password_not_forwarded');
operator_credentials_check(strpos($credentialBroninit['url'], rawurlencode($operatorLogin)) !== false, 'operator_login_not_rfc3986');
operator_credentials_check(strpos($credentialBroninit['url'], rawurlencode($operatorPassword)) !== false, 'operator_password_not_rfc3986');
operator_credentials_check(strpos($credentialBroninit['url'], $operatorPassword) === false, 'operator_password_left_raw');

$debug = json_encode($withCredentials->__debugInfo(), JSON_THROW_ON_ERROR);
operator_credentials_check(strpos($debug, $operatorLogin) === false && strpos($debug, $operatorPassword) === false, 'credentials_in_debug');
try {
    serialize($withCredentials);
    throw new LogicException('serialization_allowed');
} catch (RuntimeException $expected) {
    operator_credentials_check($expected->getMessage() === 'ANDROMEDA_SERIALIZATION_DISABLED', 'wrong_serialization_error');
}

// Partial or explicitly empty credentials fail before any transport call.
$invalidTransportCalls = 0;
$invalidTransport = static function () use (&$invalidTransportCalls): array {
    ++$invalidTransportCalls;
    throw new RuntimeException('INVALID_TRANSPORT_SHOULD_NOT_RUN');
};
foreach ([
    ['login-only', null, 'ANDROMEDA_OPERATOR_CREDENTIALS_PAIR_REQUIRED'],
    [null, 'password-only', 'ANDROMEDA_OPERATOR_CREDENTIALS_PAIR_REQUIRED'],
    ['', '', 'ANDROMEDA_OPERATOR_CREDENTIALS_INVALID'],
    [str_repeat('l', 257), 'password', 'ANDROMEDA_OPERATOR_CREDENTIALS_INVALID'],
    ['login', str_repeat('p', 4097), 'ANDROMEDA_OPERATOR_CREDENTIALS_INVALID'],
] as [$login, $password, $code]) {
    try {
        new AnyTourAndromedaClient($invalidTransport, true, true, $login, $password);
        throw new LogicException('invalid_operator_credentials_accepted');
    } catch (RuntimeException $expected) {
        operator_credentials_check($expected->getMessage() === $code, 'wrong_operator_credentials_error');
    }
}
operator_credentials_check($invalidTransportCalls === 0, 'invalid_credentials_reached_transport');

// Supplier diagnostics remain bounded even if supplier text contains synthetic credential values.
$errorRequests = [];
$errorReply = ['error' => ['code' => '1108', 'message' => 'rejected ' . $operatorLogin . ' ' . $operatorPassword]];
$errorClient = new AnyTourAndromedaClient(
    $transportFor($errorRequests, $errorReply),
    true,
    true,
    $operatorLogin,
    $operatorPassword
);
$errorClient->login('gateway-user', 'gateway-password');
try {
    $errorClient->package('opaque-claim-error');
    throw new LogicException('supplier_error_not_thrown');
} catch (AnyTourAndromedaPackageSupplierException $expected) {
    $facts = $expected->diagnosticFacts();
    $factsJson = json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    operator_credentials_check(($facts['code'] ?? null) === '1108', 'supplier_code_missing');
    operator_credentials_check(strpos($factsJson, $operatorLogin) === false, 'operator_login_in_diagnostics');
    operator_credentials_check(strpos($factsJson, $operatorPassword) === false, 'operator_password_in_diagnostics');
    operator_credentials_check(!array_key_exists('message', $facts), 'raw_supplier_message_in_diagnostics');
}

echo 'Andromeda operator credentials: ' . $checks . " checks passed; supplier/network/DB/booking calls=0.\n";
