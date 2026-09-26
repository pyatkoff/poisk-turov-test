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
$source = file_get_contents(__DIR__ . '/../v2/api-anex-search3-preview.php');
cf_check(str_contains($source, "if ((\$action ?? null) === 'continue') {\n            foreach (anytour_anex_search3_continue_failure"), 'continue_only');
echo "ANEX_CONTINUE_FAILURE_CLASSIFICATION_OK supplier0/DB0/lead0\n";
