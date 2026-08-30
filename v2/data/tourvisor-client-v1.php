<?php
/** Isolated Tourvisor client for catalog/snapshot CLI jobs. */

declare(strict_types=1);

function v2_data_tourvisor_token(): string
{
    $token = trim((string)getenv('TOURVISOR_JWT'));
    if ($token !== '') return $token;

    $privateConfig = dirname(__DIR__) . '/config.php';
    if (is_file($privateConfig)) require_once $privateConfig;
    return defined('TOURVISOR_JWT') ? trim((string)TOURVISOR_JWT) : '';
}

function v2_data_query_string(array $params): string
{
    $parts = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') continue;
        if (is_bool($value)) $value = $value ? 'true' : 'false';
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($item === null || $item === '') continue;
                $parts[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$item);
            }
            continue;
        }
        $parts[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
    }
    return implode('&', $parts);
}

function v2_data_tv_get(string $path, array $params = []): array
{
    $token = v2_data_tourvisor_token();
    if ($token === '') throw new RuntimeException('TOURVISOR_JWT is not configured');

    $url = 'https://api.tourvisor.ru/search/api/v1' . $path;
    $query = v2_data_query_string($params);
    if ($query !== '') $url .= '?' . $query;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new RuntimeException('Tourvisor connection error: ' . $error);
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('Tourvisor HTTP ' . $status);
    }

    $decoded = json_decode((string)$body, true);
    if (!is_array($decoded)) throw new RuntimeException('Invalid Tourvisor JSON response');
    return $decoded;
}
