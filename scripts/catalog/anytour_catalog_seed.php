<?php
/** Explicit saved-data seeding only. Default is a read-only plan; no schema install. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../v2/data/db-v1.php';
require_once __DIR__ . '/../../v2/data/anytour-canonical-catalog-v1.php';

try {
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!preg_match('/^--(ids|apply-source-sha256)=(.+)$/D', $arg, $m) || isset($options[$m[1]])) {
            throw new InvalidArgumentException('Usage: --ids=ID,ID [--apply-source-sha256=SHA256]. No implicit all-catalogue writes.');
        }
        $options[$m[1]] = $m[2];
    }
    if (!isset($options['ids'])) throw new InvalidArgumentException('Explicit --ids is required');
    $ids = AnyTourCanonicalCatalog::ids(explode(',', $options['ids']));
    $catalog = new AnyTourCanonicalCatalog(v2_data_db());
    $result = isset($options['apply-source-sha256'])
        ? $catalog->seed($ids, $options['apply-source-sha256']) : $catalog->plan($ids);
    echo AnyTourCanonicalCatalog::json($result) . "\n";
} catch (Throwable $e) {
    // Never print DSN, credentials or raw PDO diagnostics into public CI/receipts.
    fwrite(STDERR, "ANYTOUR_CATALOG_FAILED class=" . get_class($e) . "; inspect execution state before another apply\n");
    exit(1);
}
