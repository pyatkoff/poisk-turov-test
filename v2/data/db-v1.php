<?php
/**
 * AnyTour first-party data DB helper.
 *
 * This file is intentionally isolated from the live search path. It has no
 * side effects until explicitly required by a data endpoint/CLI job.
 */

declare(strict_types=1);

function v2_data_db_config(): array
{
    $privateConfig = dirname(__DIR__) . '/config.php';
    if (is_file($privateConfig)) require_once $privateConfig;

    $env = static function (string $name): string {
        return trim((string)getenv($name));
    };
    $constant = static function (string $name): string {
        return defined($name) ? trim((string)constant($name)) : '';
    };

    $dsn = $env('ANYTOUR_DATA_DSN');
    if ($dsn === '') $dsn = $constant('ANYTOUR_DATA_DSN');

    $user = $env('ANYTOUR_DATA_DB_USER');
    if ($user === '') $user = $constant('ANYTOUR_DATA_DB_USER');

    $password = (string)getenv('ANYTOUR_DATA_DB_PASSWORD');
    if ($password === '' && defined('ANYTOUR_DATA_DB_PASSWORD')) {
        $password = (string)constant('ANYTOUR_DATA_DB_PASSWORD');
    }

    if ($dsn === '') {
        $host = $env('ANYTOUR_DATA_DB_HOST');
        if ($host === '') $host = $constant('ANYTOUR_DATA_DB_HOST');

        $name = $env('ANYTOUR_DATA_DB_NAME');
        if ($name === '') $name = $constant('ANYTOUR_DATA_DB_NAME');

        $port = $env('ANYTOUR_DATA_DB_PORT');
        if ($port === '') $port = $constant('ANYTOUR_DATA_DB_PORT');
        if ($port === '') $port = '3306';

        if ($host !== '' && $name !== '') {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $host,
                $port,
                $name
            );
        }
    }

    return [
        'dsn' => $dsn,
        'user' => $user,
        'password' => $password,
    ];
}

function v2_data_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $config = v2_data_db_config();
    if ($config['dsn'] === '' || $config['user'] === '') {
        throw new RuntimeException('AnyTour data database is not configured');
    }

    $pdo = new PDO($config['dsn'], $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);

    return $pdo;
}

function v2_data_normalize_text(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = str_replace('ё', 'е', $value);
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return trim($value);
}

function v2_data_slug(string $value): string
{
    $value = v2_data_normalize_text($value);
    $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
        'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
        'ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e',
        'ю'=>'yu','я'=>'ya',
    ];
    $value = strtr($value, $map);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
    return trim($value, '-');
}
