<?php
declare(strict_types=1);
// Deployment-only additive schema. Never reached from an HTTP request.
error_reporting(0);
try {
    if (PHP_SAPI !== 'cli') throw new RuntimeException();
    $root = realpath((string)getenv('HOME') . '/www/anytoour.ru');
    if (!$root || basename($root) !== 'anytoour.ru') throw new RuntimeException();
    require_once (is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php');
    require_once __DIR__ . '/payload/app/integrations/anex-search-observations.php';
    $pdo = v2_data_db();
    if (!$pdo instanceof PDO || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') throw new RuntimeException();
    AnyTourAnexSearchObservations::install($pdo);
    echo "ANEX_OBSERVATION_SCHEMA_READY\n";
} catch (Throwable $error) {
    echo "ANEX_OBSERVATION_SCHEMA_FAILED\n";
    exit(1);
}
