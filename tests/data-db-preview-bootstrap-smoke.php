<?php
declare(strict_types=1);

$root = sys_get_temp_dir() . '/anytour-data-db-preview-' . bin2hex(random_bytes(6));
if (!mkdir($root, 0700, true) && !is_dir($root)) {
    throw new RuntimeException('failed to create temporary document root');
}

$configPath = $root . '/config.php';
$configSource = <<<'PHP'
<?php
define('ANYTOUR_DATA_DSN', 'mysql:host=preview-db.example;port=3307;dbname=anytour_preview;charset=utf8mb4');
define('ANYTOUR_DATA_DB_USER', 'preview_reader');
define('ANYTOUR_DATA_DB_PASSWORD', 'fixture-only');
PHP;

try {
    if (file_put_contents($configPath, $configSource) === false) {
        throw new RuntimeException('failed to write temporary private config fixture');
    }
    $_SERVER['DOCUMENT_ROOT'] = $root;
    require_once __DIR__ . '/../v2/data/db-v1.php';

    $config = v2_data_db_config();
    if ($config['dsn'] !== 'mysql:host=preview-db.example;port=3307;dbname=anytour_preview;charset=utf8mb4') {
        throw new RuntimeException('document-root DSN fallback was not loaded');
    }
    if ($config['user'] !== 'preview_reader' || $config['password'] !== 'fixture-only') {
        throw new RuntimeException('document-root DB credentials fallback was not loaded');
    }

    echo "ANYTOUR_DATA_DB_PREVIEW_BOOTSTRAP_OK\n";
} finally {
    @unlink($configPath);
    @rmdir($root);
}
