<?php
declare(strict_types=1);

require __DIR__ . '/../app/integrations/andromeda-client.php';
require __DIR__ . '/../app/integrations/andromeda-operator-config.php';
require __DIR__ . '/../app/integrations/andromeda-saved-package-runtime.php';

$checks = 0;
function operator_config_check(bool $ok, string $label): void {
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException($label . '_' . $checks);
}

operator_config_check(anytour_andromeda_operator_credentials_from_config([]) === [null, null], 'absent_pair_changed');

foreach ([
    [['operator_login' => 'operator'], 'ANDROMEDA_OPERATOR_CREDENTIALS_PAIR_REQUIRED'],
    [['operator_password' => 'password'], 'ANDROMEDA_OPERATOR_CREDENTIALS_PAIR_REQUIRED'],
    [['operator_login' => null, 'operator_password' => null], 'ANDROMEDA_OPERATOR_CREDENTIALS_INVALID'],
    [['operator_login' => '', 'operator_password' => 'password'], 'ANDROMEDA_OPERATOR_CREDENTIALS_INVALID'],
    [['operator_login' => 'operator', 'operator_password' => ''], 'ANDROMEDA_OPERATOR_CREDENTIALS_INVALID'],
    [['operator_login' => str_repeat('l', 257), 'operator_password' => 'password'], 'ANDROMEDA_OPERATOR_CREDENTIALS_INVALID'],
    [['operator_login' => 'operator', 'operator_password' => str_repeat('p', 4097)], 'ANDROMEDA_OPERATOR_CREDENTIALS_INVALID'],
] as [$config, $code]) {
    try {
        anytour_andromeda_operator_credentials_from_config($config);
        throw new LogicException('invalid_operator_config_accepted');
    } catch (RuntimeException $expected) {
        operator_config_check($expected->getMessage() === $code, 'wrong_operator_config_error');
    }
}

$login = 'operator+login@example.test';
$password = 'p@ss &+%/=? synthetic';
operator_config_check(
    anytour_andromeda_operator_credentials_from_config([
        'operator_login' => $login,
        'operator_password' => $password,
        'catalog_path' => '/private/catalog.json',
    ]) === [$login, $password],
    'valid_pair_not_preserved'
);

// The saved-package bridge keeps its existing callers compatible while exposing
// one trailing private config parameter. No supplier call is made by reflection.
$saved = new ReflectionFunction('anytour_andromeda_capture_saved_package');
$params = $saved->getParameters();
$last = $params[array_key_last($params)];
operator_config_check($last->getName() === 'operatorConfig', 'saved_bridge_config_missing');
operator_config_check($last->isOptional() && $last->getDefaultValue() === [], 'saved_bridge_default_changed');

$runtime = file_get_contents(__DIR__ . '/../app/integrations/andromeda-saved-package-runtime.php');
operator_config_check(is_string($runtime), 'runtime_source_unreadable');
operator_config_check(
    substr_count($runtime, 'anytour_andromeda_operator_credentials_from_config(') === 1,
    'runtime_config_validation_count'
);
operator_config_check(
    str_contains($runtime, '$flightRequest, $config);'),
    'selected_wrapper_config_not_forwarded'
);

// End-to-end synthetic client construction with extracted values verifies that
// the pair reaches broninit only. Network remains a local closure.
$requests = [];
$transport = static function(string $url, array $options) use (&$requests): array {
    $query = [];
    parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
    $requests[] = $query;
    if (($query['action'] ?? null) === 'login') {
        return ['status' => 200, 'body' => json_encode(['sid' => 'synthetic_sid_456'], JSON_THROW_ON_ERROR)];
    }
    return ['status' => 200, 'body' => json_encode([
        'claimDocument' => [['catalogKey' => 'synthetic/catalog/key']],
    ], JSON_THROW_ON_ERROR)];
};
[$operatorLogin, $operatorPassword] = anytour_andromeda_operator_credentials_from_config([
    'operator_login' => $login,
    'operator_password' => $password,
]);
$client = new AnyTourAndromedaClient($transport, true, true, $operatorLogin, $operatorPassword);
$client->login('gateway-user', 'gateway-password');
$client->package('opaque-claim-operator-config');
operator_config_check(!isset($requests[0]['OPERATOR_LOGIN'], $requests[0]['OPERATOR_PASSWORD']), 'pair_leaked_to_login');
operator_config_check(($requests[1]['OPERATOR_LOGIN'] ?? null) === $login, 'login_not_forwarded_to_broninit');
operator_config_check(($requests[1]['OPERATOR_PASSWORD'] ?? null) === $password, 'password_not_forwarded_to_broninit');

echo 'Andromeda operator config: ' . $checks . " checks passed; supplier/network/DB/booking calls=0.\n";
