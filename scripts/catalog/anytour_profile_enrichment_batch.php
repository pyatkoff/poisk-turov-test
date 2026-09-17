<?php
/**
 * One-shot CLI wrapper for a guarded LOCAL profile-enrichment batch.
 * The product logic lives in merged AnyTourProfileEnrichmentV1; this wrapper only
 * binds it to the existing AnyTour DB helper and exact plan/apply CLI arguments.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/anytour-profile-enrichment-v1.php';

function anytour_profile_enrichment_batch_db(): PDO
{
    $siteRoot = rtrim((string)getenv('ANYTOUR_SITE_ROOT'), "/\\");
    if ($siteRoot === '') throw new RuntimeException('ANYTOUR_PROFILE_BATCH_SITE_ROOT');
    $helper = $siteRoot . '/data/db-v1.php';
    if (!is_file($helper)) throw new RuntimeException('ANYTOUR_PROFILE_BATCH_DB_HELPER');
    require_once $helper;
    if (!function_exists('v2_data_db')) throw new RuntimeException('ANYTOUR_PROFILE_BATCH_DB_CONTRACT');
    $pdo = v2_data_db();
    if (!$pdo instanceof PDO || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        throw new RuntimeException('ANYTOUR_PROFILE_BATCH_DB');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    return $pdo;
}

function anytour_profile_enrichment_batch_args(array $argv): array
{
    $args = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!preg_match('/\A--(mode|operation|limit|demand-through|expected-plan-sha256)=(.+)\z/D', $arg, $m)
            || array_key_exists($m[1], $args)) {
            throw new InvalidArgumentException('USAGE');
        }
        $args[$m[1]] = $m[2];
    }
    if (!isset($args['mode'], $args['limit'], $args['demand-through'])
        || !in_array($args['mode'], ['plan','apply'], true)) {
        throw new InvalidArgumentException('USAGE');
    }
    if ($args['mode'] === 'apply' && (!isset($args['operation'], $args['expected-plan-sha256']) || count($args) !== 5)) {
        throw new InvalidArgumentException('USAGE');
    }
    if ($args['mode'] === 'plan' && count($args) !== 3) throw new InvalidArgumentException('USAGE');
    return $args;
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        $args = anytour_profile_enrichment_batch_args($argv);
        $engine = new AnyTourProfileEnrichmentV1(anytour_profile_enrichment_batch_db());
        $result = $args['mode'] === 'plan'
            ? $engine->plan($args['limit'], $args['demand-through'])
            : $engine->apply(
                (string)$args['operation'],
                $args['limit'],
                $args['demand-through'],
                (string)$args['expected-plan-sha256']
            );
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable $error) {
        $code = preg_replace('/[^A-Z0-9_]/', '_', strtoupper($error->getMessage()));
        fwrite(STDERR, 'ANYTOUR_PROFILE_ENRICHMENT_BATCH_FAILED code=' . $code . "\n");
        exit(1);
    }
}
