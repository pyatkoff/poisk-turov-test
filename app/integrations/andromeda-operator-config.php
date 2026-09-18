<?php
declare(strict_types=1);

/**
 * Validate the optional private tour-operator credential pair used by broninit.
 * Values are returned only for in-memory client construction and must never be logged.
 */
function anytour_andromeda_operator_credentials_from_config(array $config): array
{
    $hasLogin = array_key_exists('operator_login', $config);
    $hasPassword = array_key_exists('operator_password', $config);
    if (!$hasLogin && !$hasPassword) return [null, null];
    if ($hasLogin !== $hasPassword) {
        throw new RuntimeException('ANDROMEDA_OPERATOR_CREDENTIALS_PAIR_REQUIRED');
    }

    $login = $config['operator_login'];
    $password = $config['operator_password'];
    if (!is_string($login) || !is_string($password)
        || $login === '' || strlen($login) > 256
        || $password === '' || strlen($password) > 4096) {
        throw new RuntimeException('ANDROMEDA_OPERATOR_CREDENTIALS_INVALID');
    }
    return [$login, $password];
}
