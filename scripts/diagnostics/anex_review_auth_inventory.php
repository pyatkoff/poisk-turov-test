<?php
declare(strict_types=1);
// Presence-only CLI inventory. Never include application/bootstrap/adapter code.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = realpath(getcwd());
if (!$root || basename($root) !== 'anytoour.ru') throw new RuntimeException('anytour_root_required');
$paths = [
    'bitrix_bootstrap' => 'bitrix/modules/main/include/prolog_before.php',
    'bitrix_admin_entry' => 'bitrix/admin/index.php',
    'bitrix_user_class' => 'bitrix/modules/main/classes/general/user.php',
    'site_auth_entry' => 'auth/index.php',
    'review_entry' => '_preview/search3-anex-candidate/anex-hotel-review.php'
];
$present = [];
foreach ($paths as $label => $relative) {
    $resolved = realpath($root . '/' . $relative);
    $inside = $resolved !== false && strpos($resolved, $root . '/') === 0;
    $present[$label] = ['present' => $resolved !== false, 'inside_anytour_root' => $inside,
        'readable_file' => $inside && is_file($resolved) && is_readable($resolved)];
}
$configured = getenv('ANYTOUR_ANEX_REVIEW_AUTH_FILE');
$adapter = is_string($configured) && substr($configured, 0, 1) === '/' ? realpath($configured) : false;
echo json_encode(['status' => 'ok', 'scope' => 'cli_presence_only', 'components' => $present,
    'trusted_adapter' => ['configured_in_cli' => is_string($configured) && $configured !== '',
        'outside_document_root' => $adapter !== false && $adapter !== $root && strpos($adapter, $root . '/') !== 0,
        'readable_file' => $adapter !== false && is_file($adapter) && is_readable($adapter)],
    'owner_authority' => 'not_established', 'web_session_verified' => false,
    'session_started' => false, 'application_code_executed' => false,
    'database_calls' => 0, 'supplier_requests' => 0, 'data_writes' => 0,
    'limitation' => 'CLI presence does not prove web environment, role, expiry or owner authorization.'
], JSON_THROW_ON_ERROR) . PHP_EOL;
