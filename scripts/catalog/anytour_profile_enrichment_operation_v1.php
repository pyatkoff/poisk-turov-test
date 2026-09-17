<?php
/**
 * LOCAL one-shot wrapper for AnyTourProfileEnrichmentV1.
 *
 * This file adds no enrichment policy of its own. It only binds the already-merged
 * component to an explicitly configured AnyTour DB for a guarded plan/apply run.
 */
declare(strict_types=1);

function anytour_enrich_cli_error(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(2);
}

$options = getopt('', [
    'mode:',
    'operation:',
    'limit:',
    'demand-through:',
    'expected-plan-sha::',
]);
if (!is_array($options)) anytour_enrich_cli_error('ANYTOUR_PROFILE_ENRICH_OPTIONS');

$mode = (string)($options['mode'] ?? '');
$operation = (string)($options['operation'] ?? '');
$limit = (string)($options['limit'] ?? '');
$demandThrough = (string)($options['demand-through'] ?? '');
$expectedPlanSha = (string)($options['expected-plan-sha'] ?? '');
if (!in_array($mode, ['plan', 'apply'], true)) anytour_enrich_cli_error('ANYTOUR_PROFILE_ENRICH_MODE');
if ($operation === '' || $limit === '' || $demandThrough === '') anytour_enrich_cli_error('ANYTOUR_PROFILE_ENRICH_REQUIRED');

$siteRoot = rtrim((string)getenv('ANYTOUR_SITE_ROOT'), "/\\");
if ($siteRoot === '') anytour_enrich_cli_error('ANYTOUR_SITE_ROOT_REQUIRED');
$dbHelper = (string)getenv('ANYTOUR_DB_HELPER');
if ($dbHelper === '') $dbHelper = $siteRoot . '/data/db-v1.php';
if (!is_file($dbHelper)) anytour_enrich_cli_error('ANYTOUR_DB_HELPER_MISSING');

$componentDir = rtrim((string)getenv('ANYTOUR_ENRICH_COMPONENT_DIR'), "/\\");
if ($componentDir === '') $componentDir = dirname(__DIR__, 2) . '/v2/data';
$component = $componentDir . '/anytour-profile-enrichment-v1.php';
if (!is_file($component)) anytour_enrich_cli_error('ANYTOUR_PROFILE_ENRICH_COMPONENT_MISSING');

require_once $dbHelper;
require_once $component;
if (!function_exists('v2_data_db')) anytour_enrich_cli_error('ANYTOUR_DB_FACTORY_MISSING');

try {
    $db = v2_data_db();
    $engine = new AnyTourProfileEnrichmentV1($db);
    if ($mode === 'plan') {
        $result = $engine->plan($limit, $demandThrough);
    } else {
        if ($expectedPlanSha === '') anytour_enrich_cli_error('ANYTOUR_PROFILE_ENRICH_PLAN_SHA_REQUIRED');
        $result = $engine->apply($operation, $limit, $demandThrough, $expectedPlanSha);
    }
    fwrite(STDOUT, AnyTourProfileEnrichmentV1::json($result) . PHP_EOL);
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error) . ':' . $error->getMessage() . PHP_EOL);
    exit(1);
}
