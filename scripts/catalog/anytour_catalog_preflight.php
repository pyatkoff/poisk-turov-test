<?php
/** Read-only, environment-only inspection. Never loads site config or installs schema. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../v2/data/anytour-canonical-catalog-v1.php';

try {
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!preg_match('/^--(ids|expect-dsn-sha256|expect-database-sha256)=(.+)$/D', $arg, $m)
            || isset($options[$m[1]])) {
            throw new InvalidArgumentException('Explicit IDs and target fingerprints required');
        }
        $options[$m[1]] = $m[2];
    }
    $ids = AnyTourCanonicalCatalog::ids(explode(',', $options['ids'] ?? ''));
    foreach (['expect-dsn-sha256', 'expect-database-sha256'] as $name) {
        if (!preg_match('/^[0-9a-f]{64}$/D', $options[$name] ?? '')) {
            throw new InvalidArgumentException('Exact target fingerprint required');
        }
    }
    // No config.php, document-root fallback, environment copying or credential output.
    $dsn = (string)getenv('ANYTOUR_DATA_DSN');
    $user = (string)getenv('ANYTOUR_DATA_DB_USER');
    if (!str_starts_with($dsn, 'mysql:') || $user === ''
        || !hash_equals($options['expect-dsn-sha256'], hash('sha256', $dsn))) {
        throw new RuntimeException('Explicit MySQL target does not match');
    }
    $pdo = new PDO($dsn, $user, (string)getenv('ANYTOUR_DATA_DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database === '' || !hash_equals($options['expect-database-sha256'], hash('sha256', $database))) {
        throw new RuntimeException('Selected database does not match');
    }
    $result = (new AnyTourCanonicalCatalog($pdo))->preflight($ids);
    // Fingerprints bind the reviewed target, not an authorization to write to that target.
    $result['dsn_sha256'] = $options['expect-dsn-sha256'];
    echo AnyTourCanonicalCatalog::json($result) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ANYTOUR_PREFLIGHT_FAILED class=' . get_class($e) . " writes=0\n");
    exit(1);
}
