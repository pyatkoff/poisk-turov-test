<?php

declare(strict_types=1);

use AnyTour\Platform\Database;

require_once dirname(__DIR__) . '/src/Database.php';

$requiredExtensions = ['pdo', 'pdo_mysql', 'json'];
$missing = array_values(array_filter(
    $requiredExtensions,
    static fn (string $extension): bool => !extension_loaded($extension)
));

if ($missing !== []) {
    fwrite(STDERR, 'Missing PHP extensions: ' . implode(', ', $missing) . PHP_EOL);
    exit(2);
}

try {
    $pdo = Database::connectFromEnvironment();
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    fwrite(STDOUT, "DB_OK database={$database} version={$version}\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'DB_FAIL ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
