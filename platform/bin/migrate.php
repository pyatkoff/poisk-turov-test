<?php

declare(strict_types=1);

use AnyTour\Platform\Database;

require_once dirname(__DIR__) . '/src/Database.php';

$pdo = Database::connectFromEnvironment();
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS at_schema_migrations (
        migration VARCHAR(190) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$migrationDir = dirname(__DIR__) . '/migrations';
$files = glob($migrationDir . '/*.sql') ?: [];
sort($files, SORT_STRING);

$alreadyApplied = $pdo->query('SELECT migration FROM at_schema_migrations')
    ->fetchAll(PDO::FETCH_COLUMN);
$appliedMap = array_fill_keys($alreadyApplied, true);

$insert = $pdo->prepare('INSERT INTO at_schema_migrations (migration) VALUES (:migration)');

foreach ($files as $file) {
    $name = basename($file);
    if (isset($appliedMap[$name])) {
        fwrite(STDOUT, "SKIP {$name}\n");
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException("Migration {$name} is empty or unreadable.");
    }

    fwrite(STDOUT, "APPLY {$name}\n");
    $pdo->beginTransaction();
    try {
        $pdo->exec($sql);
        $insert->execute(['migration' => $name]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

fwrite(STDOUT, "DONE\n");
