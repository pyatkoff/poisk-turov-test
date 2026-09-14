<?php

declare(strict_types=1);

require_once __DIR__ . '/../scripts/diagnostics/hotel_match_tourvisor_legacy_probe.php';

function t_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$found = legacy_tv_find_credentials(
    ['TOURVISOR_LOGIN' => 'user', 'TOURVISOR_PASSWORD' => 'pass', 'TOURVISOR_JWT' => 'new-api-token'],
    [],
    []
);
t_assert($found['found'] === true, 'legacy pair not found');
t_assert($found['source'] === 'constant', 'wrong credential source');
t_assert($found['login_name'] === 'TOURVISOR_LOGIN', 'wrong login name');
t_assert($found['pass_name'] === 'TOURVISOR_PASSWORD', 'wrong pass name');
t_assert(in_array('TOURVISOR_JWT', $found['present_names'], true), 'presence census should retain Tourvisor names');

$absent = legacy_tv_find_credentials(
    ['TOURVISOR_JWT' => 'jwt-only'],
    ['TOURVISOR_LOGIN' => 'login-without-pass'],
    []
);
t_assert($absent['found'] === false, 'partial pair must not authorize legacy probe');
t_assert($absent['login'] === '' && $absent['pass'] === '', 'absent result must not carry secrets');

$auth = legacy_tv_classify_http(200, '{"error":"Authorization Error"}');
t_assert($auth['status'] === 'legacy_auth_rejected', 'auth error classification');

$available = legacy_tv_classify_http(200, '{"operators":{"operator":[{"id":13,"name":"ANEX"}]}}');
t_assert($available['status'] === 'legacy_list_available', 'valid JSON must be available');
$shape = legacy_tv_shape_summary($available['json']);
t_assert(in_array('operators', $shape['top_keys'], true), 'top keys missing');
t_assert($shape['max_list_items'] === 1, 'list cardinality mismatch');

$nonJson = legacy_tv_classify_http(200, '<html>old gateway</html>');
t_assert($nonJson['status'] === 'legacy_non_json_response', 'non-json classification');

$http = legacy_tv_classify_http(403, '{"error":"denied"}');
t_assert($http['status'] === 'legacy_http_error' && $http['http_status'] === 403, 'http classification');

$source = file_get_contents(__DIR__ . '/../scripts/diagnostics/hotel_match_tourvisor_legacy_probe.php');
t_assert(is_string($source), 'source read');
t_assert(strpos($source, '/xml/search.php') === false, 'viability probe must not start searches');
t_assert(strpos($source, '/xml/result.php') === false, 'viability probe must not read search results');
t_assert(strpos($source, 'api.tourvisor.ru') === false, 'must not consume new Tourvisor quota');
t_assert(strpos($source, 'INSERT ') === false && strpos($source, 'UPDATE ') === false && strpos($source, 'DELETE ') === false, 'must remain read-only');

echo "HOTEL_MATCH_LEGACY_TOURVISOR_PROBE_OK\n";
