<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/seo-page-launch-readiness-v1.php';

function seo2_readiness_cli_fail(string $message, int $code = 2): never
{
    fwrite(STDERR, "SEO_LAUNCH_READINESS_REPORT_FAIL:$message\n");
    exit($code);
}

$options = getopt('', ['catalog-file:', 'catalog-function:', 'evidence-file::', 'require-ready::']);
$catalogFile = trim((string)($options['catalog-file'] ?? ''));
$catalogFunction = trim((string)($options['catalog-function'] ?? ''));
$evidenceFile = trim((string)($options['evidence-file'] ?? ''));
$requireReady = array_key_exists('require-ready', $options)
    ? max(0, (int)($options['require-ready'] === false ? 1 : $options['require-ready']))
    : 0;

if ($catalogFile === '' || $catalogFunction === '') {
    seo2_readiness_cli_fail('usage: --catalog-file=<path> --catalog-function=<function> [--evidence-file=<json>] [--require-ready=<n>]');
}

$repoRoot = dirname(__DIR__, 2);
$resolvedCatalog = realpath($repoRoot . '/' . ltrim($catalogFile, '/'));
if ($resolvedCatalog === false || !str_starts_with($resolvedCatalog, $repoRoot . DIRECTORY_SEPARATOR)) {
    seo2_readiness_cli_fail('catalog file must resolve inside the repository');
}
require_once $resolvedCatalog;
if (!function_exists($catalogFunction)) seo2_readiness_cli_fail('catalog function does not exist');

$catalog = $catalogFunction();
if (!is_array($catalog)) seo2_readiness_cli_fail('catalog function must return an array');

$evidence = [];
if ($evidenceFile !== '') {
    $resolvedEvidence = realpath($evidenceFile);
    if ($resolvedEvidence === false || !is_file($resolvedEvidence)) seo2_readiness_cli_fail('evidence file not found');
    $raw = file_get_contents($resolvedEvidence);
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) seo2_readiness_cli_fail('evidence file must contain a JSON array');
    $evidence = $decoded;
} else {
    $stdinIsTty = function_exists('stream_isatty') ? stream_isatty(STDIN) : true;
    if (!$stdinIsTty) {
        $raw = stream_get_contents(STDIN);
        if (trim((string)$raw) !== '') {
            $decoded = json_decode((string)$raw, true);
            if (!is_array($decoded)) seo2_readiness_cli_fail('stdin evidence must contain a JSON array');
            $evidence = $decoded;
        }
    }
}

$rows = v2_seo_page_launch_readiness($catalog, $evidence);
$summary = v2_seo_page_launch_readiness_summary($rows);
$ready = array_values(array_filter($rows, static fn(array $row): bool => ($row['ready_for_launch_review'] ?? false) === true));
$blocked = array_values(array_filter($rows, static fn(array $row): bool => ($row['ready_for_launch_review'] ?? false) !== true));

$output = [
    'state' => 'review_only_launch_readiness_report',
    'generated_at' => gmdate('c'),
    'summary' => $summary,
    'ready_count' => count($ready),
    'blocked_count' => count($blocked),
    'ready' => $ready,
    'blocked' => $blocked,
];

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

if ($requireReady > 0 && count($ready) < $requireReady) {
    seo2_readiness_cli_fail('ready page count is below required threshold', 3);
}
