<?php
declare(strict_types=1);
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';

function cf_check(bool $ok, string $reason): void {
    if (!$ok) throw new RuntimeException($reason);
}
$http = anytour_anex_search3_continue_failure(new RuntimeException('ANEX_HTTP_ERROR'), [
    'action' => 'SearchTour_PRICES', 'http_status' => 502,
    'url' => 'private-fixture-token', 'body' => 'private-fixture-body',
]);
cf_check($http === ['X-AnyTour-Anex-Failure' => 'ANEX_HTTP_ERROR',
    'X-AnyTour-Anex-Upstream-Status' => '502'], 'http_classification');
$internal = anytour_anex_search3_continue_failure(new RuntimeException('ANEX_INVALID_PUBLIC_RESULT'), []);
cf_check($internal === ['X-AnyTour-Anex-Failure' => 'ANEX_INVALID_PUBLIC_RESULT'], 'internal_not_upstream');
$supplier = anytour_anex_search3_continue_failure(new RuntimeException('ANEX_SUPPLIER_ERROR'), [
    'action' => 'SearchTour_PRICES', 'http_status' => 200, 'supplier_code' => 3, 'curl_errno' => 0,
]);
cf_check($supplier === ['X-AnyTour-Anex-Failure' => 'ANEX_SUPPLIER_ERROR',
    'X-AnyTour-Anex-Upstream-Status' => '200', 'X-AnyTour-Anex-Supplier-Code' => '3',
    'X-AnyTour-Anex-Transport-Code' => '0'], 'supplier_classification');
foreach ([new RuntimeException("private-fixture\r\nX-Leak: yes"), new TypeError('private-fixture'),
    new PDOException('private-fixture')] as $error) {
    $safe = anytour_anex_search3_continue_failure($error, ['action' => 'SearchTour_PRICES',
        'http_status' => 'private-fixture', 'supplier_code' => ['private-fixture'], 'curl_errno' => false]);
    cf_check(count($safe) === 1 && !str_contains(json_encode($safe), 'private-fixture'), 'unsafe_metadata');
}
$other = anytour_anex_search3_continue_failure(new RuntimeException('ANEX_HTTP_ERROR'), [
    'action' => 'SearchTour_CURRENCIES', 'http_status' => 502, 'supplier_code' => 3,
]);
cf_check(count($other) === 1, 'wrong_request_diagnostics');
$continued = anytour_anex_search3_continue_failure(new RuntimeException('ANEX_HTTP_ERROR'), [
    'action' => 'SearchTour_PRICES', 'http_status' => 502, 'elapsed_ms' => 19001,
], 'continue');
cf_check($continued === $http, 'explicit_continue_keeps_original_headers');
foreach (['SearchTour_TOWNFROMS', 'SearchTour_STATES', 'SearchTour_CURRENCIES', 'SearchTour_PRICES'] as $action) {
    $initial = anytour_anex_search3_continue_failure(new RuntimeException('ANEX_TRANSPORT_ERROR'), [
        'action' => $action, 'http_status' => 0, 'curl_errno' => 28, 'elapsed_ms' => 20001,
        'url' => 'private-fixture-token', 'body' => 'private-fixture-body',
        'request_id' => 'private-fixture-request', 'response_bytes' => 123,
    ], 'search');
    cf_check($initial === ['X-AnyTour-Anex-Failure' => 'ANEX_TRANSPORT_ERROR',
        'X-AnyTour-Anex-Stage' => 'search', 'X-AnyTour-Anex-Category' => 'transport',
        'X-AnyTour-Anex-Native-Action' => $action, 'X-AnyTour-Anex-Upstream-Status' => '0',
        'X-AnyTour-Anex-Transport-Code' => '28', 'X-AnyTour-Anex-Elapsed-Ms' => '20001'],
        'initial_search_' . $action);
}
foreach (['ANEX_HTTP_ERROR' => 'http', 'ANEX_SUPPLIER_ERROR' => 'supplier',
    'ANEX_INVALID_RESPONSE' => 'response', 'ANEX_RATE_LIMIT' => 'limit'] as $code => $category) {
    $initial = anytour_anex_search3_continue_failure(new RuntimeException($code), [
        'action' => 'SearchTour_PRICES', 'http_status' => 200, 'supplier_code' => 101, 'elapsed_ms' => 0,
    ], 'search');
    cf_check($initial['X-AnyTour-Anex-Category'] === $category
        && $initial['X-AnyTour-Anex-Supplier-Code'] === '101'
        && $initial['X-AnyTour-Anex-Elapsed-Ms'] === '0', 'initial_category_' . $category);
}
foreach ([['http_status' => '502', 'supplier_code' => ['private-fixture'], 'curl_errno' => false, 'elapsed_ms' => 1.5],
    ['http_status' => -1, 'supplier_code' => 100000, 'curl_errno' => 1000, 'elapsed_ms' => 120001],
    ['http_status' => 600, 'supplier_code' => -1, 'curl_errno' => -1, 'elapsed_ms' => -1]] as $bad) {
    $initial = anytour_anex_search3_continue_failure(new RuntimeException("private-fixture\r\nX-Leak: yes"),
        ['action' => 'SearchTour_PRICES'] + $bad, 'search');
    cf_check($initial === ['X-AnyTour-Anex-Failure' => 'ANEX_INTERNAL_ERROR',
        'X-AnyTour-Anex-Stage' => 'search', 'X-AnyTour-Anex-Category' => 'application',
        'X-AnyTour-Anex-Native-Action' => 'SearchTour_PRICES'], 'initial_unsafe_numeric_metadata');
}
foreach (['SearchTour_PRICES private-fixture', "SearchTour_PRICES\r\nX-Leak: yes", 'Booking_BOOK', null, ['SearchTour_PRICES']] as $action) {
    $initial = anytour_anex_search3_continue_failure(new PDOException('private-fixture'), [
        'action' => $action, 'http_status' => 502, 'supplier_code' => 3, 'curl_errno' => 28, 'elapsed_ms' => 20001,
    ], 'search');
    cf_check($initial === ['X-AnyTour-Anex-Failure' => 'ANEX_DATABASE_ERROR',
        'X-AnyTour-Anex-Stage' => 'search', 'X-AnyTour-Anex-Category' => 'database'], 'initial_unknown_action_masked');
}
foreach (['ANEX_HTTP_ERROR', 'ANEX_SUPPLIER_ERROR', 'ANEX_TRANSPORT_ERROR', 'ANEX_INVALID_RESPONSE'] as $code) {
    $safe = anytour_anex_search3_continue_failure(new RuntimeException($code), [
        'action' => 'SearchTour_PRICES', 'http_status' => 200, 'supplier_code' => 101,
        'curl_errno' => 28, 'elapsed_ms' => 19001, 'url' => 'private-fixture-token', 'body' => 'private-fixture-body',
    ], 'expand');
    cf_check($safe['X-AnyTour-Anex-Stage'] === 'expand'
        && $safe['X-AnyTour-Anex-Elapsed-Ms'] === '19001'
        && $safe['X-AnyTour-Anex-Failure'] === $code
        && $safe['X-AnyTour-Anex-Supplier-Code'] === '101'
        && !str_contains(json_encode($safe), 'private-fixture'), 'expand_safe_' . $code);
}
$missing = anytour_anex_search3_continue_failure(new RuntimeException('ANEX_TRANSPORT_ERROR'), [], 'expand');
cf_check(!isset($missing['X-AnyTour-Anex-Elapsed-Ms'], $missing['X-AnyTour-Anex-Upstream-Status']), 'missing_is_not_zero');
$stored = anytour_anex_search3_retained_expand_failure(['failure' => [
    'X-AnyTour-Anex-Stage' => 'expand', 'X-AnyTour-Anex-Failure' => "private-fixture\r\nX-Leak: yes",
    'X-AnyTour-Anex-Phase' => "private-fixture", 'X-AnyTour-Anex-Native-Action' => 'SearchTour_PRICES',
    'X-AnyTour-Anex-Upstream-Status' => '502', 'X-AnyTour-Anex-Elapsed-Ms' => '120001', 'body' => 'private-fixture',
]]);
cf_check($stored['X-AnyTour-Anex-Failure'] === 'ANEX_INTERNAL_ERROR'
    && $stored['X-AnyTour-Anex-Upstream-Status'] === '502'
    && !isset($stored['X-AnyTour-Anex-Elapsed-Ms'])
    && !str_contains(json_encode($stored), 'private-fixture'), 'stored_expand_resanitized');
cf_check(anytour_anex_search3_retained_expand_failure(['status' => 'unknown']) === [], 'legacy_unknown_no_diagnosis');
$source = file_get_contents(__DIR__ . '/../v2/api-anex-search3-preview.php');
cf_check(str_contains($source, "if (in_array(\$action ?? null, ['search', 'continue', 'expand'], true)"), 'search_continue_expand_diagnostics');
cf_check(str_contains($source, "\$extra['diagnostics'] = \$safe;"), 'same_post_failure_body_retains_safe_diagnostics');
echo "ANEX_CONTINUE_FAILURE_CLASSIFICATION_OK supplier0/DB0/lead0\n";
