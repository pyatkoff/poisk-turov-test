<?php

declare(strict_types=1);

/**
 * MATCH-only legacy Tourvisor viability probe.
 *
 * It never prints credentials and never runs a tour search. The only permitted
 * network operation is one legacy list.php operator-catalog request after an
 * existing private credential pair has been discovered.
 */

function legacy_tv_candidate_pairs(): array
{
    return [
        ['TOURVISOR_LEGACY_LOGIN', 'TOURVISOR_LEGACY_PASSWORD'],
        ['TOURVISOR_LEGACY_LOGIN', 'TOURVISOR_LEGACY_PASS'],
        ['TOURVISOR_AUTHLOGIN', 'TOURVISOR_AUTHPASS'],
        ['TOURVISOR_LOGIN', 'TOURVISOR_PASSWORD'],
        ['TOURVISOR_LOGIN', 'TOURVISOR_PASS'],
        ['TV_LOGIN', 'TV_PASSWORD'],
        ['TV_LOGIN', 'TV_PASS'],
    ];
}

function legacy_tv_clean_scalar($value): string
{
    if (!is_string($value) && !is_numeric($value)) return '';
    return trim((string)$value);
}

function legacy_tv_find_credentials(array $constantValues, array $envValues, array $globalValues): array
{
    $sources = [
        'constant' => $constantValues,
        'env' => $envValues,
        'global' => $globalValues,
    ];
    $presentNames = [];
    foreach ($sources as $sourceValues) {
        foreach ($sourceValues as $name => $value) {
            if (!is_string($name)) continue;
            if (!preg_match('/^(?:TOURVISOR|TV)_/i', $name)) continue;
            if (legacy_tv_clean_scalar($value) !== '') $presentNames[$name] = true;
        }
    }
    ksort($presentNames, SORT_STRING);

    foreach (legacy_tv_candidate_pairs() as [$loginName, $passName]) {
        foreach ($sources as $source => $sourceValues) {
            $login = legacy_tv_clean_scalar($sourceValues[$loginName] ?? '');
            $pass = legacy_tv_clean_scalar($sourceValues[$passName] ?? '');
            if ($login !== '' && $pass !== '') {
                return [
                    'found' => true,
                    'source' => $source,
                    'login_name' => $loginName,
                    'pass_name' => $passName,
                    'login' => $login,
                    'pass' => $pass,
                    'present_names' => array_keys($presentNames),
                ];
            }
        }
    }

    return [
        'found' => false,
        'source' => null,
        'login_name' => null,
        'pass_name' => null,
        'login' => '',
        'pass' => '',
        'present_names' => array_keys($presentNames),
    ];
}

function legacy_tv_classify_http(int $httpStatus, string $body): array
{
    $trimmed = trim($body);
    if ($httpStatus < 200 || $httpStatus >= 300) {
        return ['status' => 'legacy_http_error', 'http_status' => $httpStatus, 'json' => null];
    }
    if ($trimmed === '') {
        return ['status' => 'legacy_empty_response', 'http_status' => $httpStatus, 'json' => null];
    }
    if (preg_match('/Authorization\s+Error/i', $trimmed)) {
        return ['status' => 'legacy_auth_rejected', 'http_status' => $httpStatus, 'json' => null];
    }
    $decoded = json_decode($trimmed, true);
    if (!is_array($decoded)) {
        return ['status' => 'legacy_non_json_response', 'http_status' => $httpStatus, 'json' => null];
    }
    return ['status' => 'legacy_list_available', 'http_status' => $httpStatus, 'json' => $decoded];
}

function legacy_tv_shape_summary(array $decoded): array
{
    $topKeys = array_keys($decoded);
    sort($topKeys, SORT_STRING);
    $arrays = 0;
    $scalars = 0;
    $maxList = 0;
    $walk = function ($value) use (&$walk, &$arrays, &$scalars, &$maxList): void {
        if (is_array($value)) {
            $arrays++;
            if (array_is_list($value)) $maxList = max($maxList, count($value));
            foreach ($value as $child) $walk($child);
            return;
        }
        if (is_scalar($value) || $value === null) $scalars++;
    };
    $walk($decoded);
    return [
        'top_keys' => array_slice($topKeys, 0, 40),
        'array_nodes' => $arrays,
        'scalar_nodes' => $scalars,
        'max_list_items' => $maxList,
    ];
}

function legacy_tv_main(array $argv): int
{
    $config = '';
    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--config=')) $config = substr($arg, strlen('--config='));
    }

    $result = [
        'operation' => 'legacy_tourvisor_viability_probe',
        'status' => 'legacy_credentials_absent',
        'credential_source' => null,
        'credential_names' => [],
        'present_legacy_names' => [],
        'endpoint_host' => 'tourvisor.ru',
        'endpoint_path' => '/xml/list.php',
        'legacy_tourvisor_calls' => 0,
        'new_tourvisor_calls' => 0,
        'supplier_calls' => 0,
        'database_reads' => 0,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'booking_calls' => 0,
        'lead_calls' => 0,
    ];

    if ($config === '' || !is_file($config)) {
        $result['status'] = 'private_config_missing';
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        return 0;
    }

    require $config;
    $userConstants = get_defined_constants(true)['user'] ?? [];
    $env = getenv();
    if (!is_array($env)) $env = [];
    $globals = $GLOBALS;
    foreach (['argv', 'argc'] as $drop) unset($globals[$drop]);

    $credentials = legacy_tv_find_credentials($userConstants, $env, $globals);
    $result['credential_source'] = $credentials['source'];
    $result['credential_names'] = $credentials['found'] ? [$credentials['login_name'], $credentials['pass_name']] : [];
    $result['present_legacy_names'] = $credentials['present_names'];

    if (!$credentials['found']) {
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        return 0;
    }

    if (!function_exists('curl_init')) {
        $result['status'] = 'curl_unavailable';
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        return 0;
    }

    $url = 'https://tourvisor.ru/xml/list.php?' . http_build_query([
        'authlogin' => $credentials['login'],
        'authpass' => $credentials['pass'],
        'type' => 'operator',
    ], '', '&', PHP_QUERY_RFC3986);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'AnyTour-MATCH-legacy-viability/1.0',
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $result['legacy_tourvisor_calls'] = 1;

    if ($errno !== 0 || $body === false) {
        $result['status'] = 'legacy_connection_error';
        $result['http_status'] = $http;
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        return 0;
    }

    $classified = legacy_tv_classify_http($http, (string)$body);
    $result['status'] = $classified['status'];
    $result['http_status'] = $classified['http_status'];
    if ($classified['status'] === 'legacy_list_available' && is_array($classified['json'])) {
        $result['response_shape'] = legacy_tv_shape_summary($classified['json']);
    }
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    return 0;
}

$explicitStdinExecution = getenv('MATCH_LEGACY_PROBE_EXEC') === '1';
if ($explicitStdinExecution || realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(legacy_tv_main($argv ?? []));
}
