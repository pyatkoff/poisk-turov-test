<?php
declare(strict_types=1);
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';

function assert_true($value, string $message): void {
    if (!$value) throw new RuntimeException($message);
}
$http502 = new RuntimeException('ANEX_HTTP_ERROR');
assert_true(anytour_anex_search3_retryable_initial_price_502($http502, [
    'action' => 'SearchTour_PRICES', 'http_status' => 502, 'curl_errno' => 0,
]), 'exact PRICES HTTP502 must be retryable once');
assert_true(!anytour_anex_search3_retryable_initial_price_502(new RuntimeException('ANEX_TRANSPORT_ERROR'), [
    'action' => 'SearchTour_PRICES', 'http_status' => 502, 'curl_errno' => 0,
]), 'transport error must not retry');
assert_true(!anytour_anex_search3_retryable_initial_price_502($http502, [
    'action' => 'SearchTour_PRICES', 'http_status' => 503, 'curl_errno' => 0,
]), 'non-502 must not retry');
assert_true(!anytour_anex_search3_retryable_initial_price_502($http502, [
    'action' => 'SearchTour_PRICES', 'http_status' => 502, 'curl_errno' => 28,
]), 'curl timeout must not retry as HTTP502');
assert_true(!anytour_anex_search3_retryable_initial_price_502($http502, [
    'action' => 'SearchTour_STATES', 'http_status' => 502, 'curl_errno' => 0,
]), 'dictionary HTTP502 must not retry the search');
echo "ANEX_PREVIEW_PRICE502_RETRY_OK\n";
