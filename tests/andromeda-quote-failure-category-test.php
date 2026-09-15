<?php
declare(strict_types=1);

$sourcePath = __DIR__ . '/../v2/api-andromeda-quote-preview.php';
$source = file_get_contents($sourcePath);
if (!is_string($source)) throw new RuntimeException('FAILURE_SOURCE_READ');

$extractFunction = static function (string $source, string $name): string {
    $needle = 'function ' . $name;
    $start = strpos($source, $needle);
    if ($start === false) throw new RuntimeException('FAILURE_FUNCTION_MISSING_' . $name);
    $open = strpos($source, '{', $start);
    if ($open === false) throw new RuntimeException('FAILURE_FUNCTION_OPEN_' . $name);
    $depth = 0;
    $length = strlen($source);
    for ($i = $open; $i < $length; ++$i) {
        if ($source[$i] === '{') ++$depth;
        elseif ($source[$i] === '}' && --$depth === 0) return substr($source, $start, $i - $start + 1);
    }
    throw new RuntimeException('FAILURE_FUNCTION_CLOSE_' . $name);
};

eval($extractFunction($source, 'anytour_andromeda_quote_failure_category'));
eval($extractFunction($source, 'anytour_andromeda_quote_supplier_failure'));

$cases = [
    'ANDROMEDA_TRANSPORT_ERROR' => 'supplier_transport',
    'ANDROMEDA_NETWORK_TRANSPORT_FAILURE' => 'supplier_transport',
    'ANDROMEDA_CURL_REQUIRED' => 'supplier_transport',
    'ANDROMEDA_HTTP_ERROR' => 'supplier_http',
    'ANDROMEDA_SUPPLIER_ERROR' => 'supplier_rejected',
    'ANDROMEDA_INVALID_RESPONSE' => 'supplier_response',
    'ANDROMEDA_INVALID_CLAIM_RESPONSE' => 'supplier_response',
    'ANDROMEDA_INVALID_PACKAGE_RESPONSE' => 'supplier_response',
    'ANDROMEDA_RESPONSE_TOO_LARGE' => 'supplier_response',
    'ANDROMEDA_SECRET_ECHO' => 'supplier_response',
    'ANDROMEDA_LOGIN_REQUIRED' => 'supplier_auth',
    'ANDROMEDA_CREDENTIALS_REQUIRED' => 'supplier_auth',
    'ANDROMEDA_CLAIM_SESSION_INVALID' => 'supplier_auth',
    'ANDROMEDA_QUOTE_CONTEXT_MISMATCH' => 'quote_state',
    'ANDROMEDA_QUOTE_CHECKPOINT_INVALID' => 'quote_state',
    'ANDROMEDA_QUOTE_CHECKPOINT_CHANGED' => 'quote_state',
    'ANDROMEDA_QUOTE_CHECKPOINT_FAILED' => 'quote_state',
    'ANDROMEDA_QUOTE_LOCK_FAILED' => 'quote_state',
    'ANDROMEDA_QUOTE_NOT_OFFER' => 'quote_state',
    'ANDROMEDA_FLIGHT_STATE_CHANGED' => 'quote_state',
    'ANDROMEDA_FLIGHT_STATE_FAILED' => 'quote_state',
    'ANDROMEDA_FLIGHT_STATE_INVALID' => 'quote_state',
    'ANDROMEDA_FLIGHT_SELECTION_INVALID' => 'quote_state',
    'ANDROMEDA_FLIGHT_UID_INVALID' => 'quote_state',
    'ANDROMEDA_FLIGHT_OPTIONS_INVALID' => 'quote_state',
    'ANDROMEDA_FLIGHT_REF_INVALID' => 'quote_state',
    'ANDROMEDA_FLIGHT_REFS_INVALID' => 'quote_state',
    'ANDROMEDA_FLIGHT_CONTEXT_INVALID' => 'quote_state',
    'ANDROMEDA_FLIGHT_ALREADY_SELECTED' => 'quote_state',
    'ANDROMEDA_SELECTED_FLIGHTS_INVALID' => 'quote_state',
    'ANDROMEDA_FINAL_PRICE_MISSING' => 'quote_state',
    'ANDROMEDA_CLAIM_SHAPE_INVALID' => 'quote_state',
    'ANDROMEDA_CLAIM_TOO_LARGE' => 'quote_state',
    'ANDROMEDA_CLAIM_REQUEST_BUDGET' => 'quote_state',
    'ANDROMEDA_CLAIM_ACTION_NOT_ALLOWED' => 'quote_state',
    'ANDROMEDA_PACKAGE_DISABLED' => 'quote_state',
    'ANDROMEDA_PACKAGE_REPLAY_REFUSED' => 'quote_state',
    'ANDROMEDA_INVALID_PACKAGE_ID' => 'quote_state',
];

$allowed = [
    'supplier_transport', 'supplier_http', 'supplier_rejected', 'supplier_response',
    'supplier_auth', 'quote_state', 'internal',
];

foreach ($cases as $message => $expected) {
    $actual = anytour_andromeda_quote_failure_category(new RuntimeException($message));
    if ($actual !== $expected || !in_array($actual, $allowed, true)) {
        throw new RuntimeException('FAILURE_CATEGORY_' . $message);
    }
}

foreach (['', 'unexpected', 'ANDROMEDA_UNKNOWN', 'secret sid=abc url=https://gateway.samo.ru/api/'] as $message) {
    if (anytour_andromeda_quote_failure_category(new RuntimeException($message)) !== 'internal') {
        throw new RuntimeException('FAILURE_CATEGORY_FALLBACK');
    }
}

$secret = 'secret sid=abc url=https://gateway.samo.ru/api/?action=changeservice';
$payload = anytour_andromeda_quote_supplier_failure(new RuntimeException($secret));
$expectedPayload = [
    'ok' => false,
    'error' => 'supplier_unavailable',
    'failure_category' => 'internal',
];
if ($payload !== $expectedPayload) throw new RuntimeException('FAILURE_PAYLOAD_SHAPE');
$encoded = json_encode($payload, JSON_THROW_ON_ERROR);
foreach (['secret', 'sid=abc', 'gateway.samo.ru', 'RuntimeException'] as $forbidden) {
    if (str_contains($encoded, $forbidden)) throw new RuntimeException('FAILURE_PAYLOAD_LEAK');
}

if (!str_contains($source, 'anytour_anex_search3_out(anytour_andromeda_quote_supplier_failure($e),502)')) {
    throw new RuntimeException('FAILURE_HTTP_WIRING');
}

echo "andromeda quote failure category: OK\n";
